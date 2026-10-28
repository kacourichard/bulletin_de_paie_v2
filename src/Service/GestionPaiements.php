<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Paiement;
use App\Entity\PeriodePaie;
use App\Entity\User;
use App\Enum\ModePaiement;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Trésorerie : paiement du net à payer des paies validées, annulation, ordre de virement.
 */
class GestionPaiements
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BulletinPaie $bulletins,
    ) {
    }

    /**
     * Une ligne par salarié du mois : net à payer et paiement en cours (non annulé).
     *
     * @return array{lignes: list<array{affectation: Affectation, net: string, mode: ModePaiement, paiement: ?Paiement, annules: list<Paiement>}>, totaux: array<string, string|int>}
     */
    public function etat(PeriodePaie $periode): array
    {
        $paiements = [];
        foreach ($this->em->getRepository(Paiement::class)->findBy(['periode' => $periode], ['enregistreLe' => 'ASC']) as $p) {
            $paiements[$p->getAffectation()->getId()][] = $p;
        }

        $lignes = [];
        $totaux = ['net' => '0', 'paye' => '0', 'reste' => '0', 'nbPayes' => 0, 'nbAPayer' => 0];
        foreach ($this->bulletins->donnees($periode) as $b) {
            $affectation = $b['affectation'];
            $liste = $paiements[$affectation->getId()] ?? [];
            $actif = array_values(array_filter($liste, fn (Paiement $p) => !$p->isAnnule()))[0] ?? null;
            $net = $b['recap']['net'];
            $lignes[] = [
                'affectation' => $affectation,
                'net' => $net,
                'mode' => $actif?->getMode() ?? $affectation->getSalarie()->getModePaiement(),
                'paiement' => $actif,
                'annules' => array_values(array_filter($liste, fn (Paiement $p) => $p->isAnnule())),
            ];
            $totaux['net'] = bcadd($totaux['net'], $net, 2);
            if ($actif) {
                $totaux['paye'] = bcadd($totaux['paye'], $actif->getMontant(), 2);
                ++$totaux['nbPayes'];
            } elseif (bccomp($net, '0', 2) > 0) {
                $totaux['reste'] = bcadd($totaux['reste'], $net, 2);
                ++$totaux['nbAPayer'];
            }
        }

        return ['lignes' => $lignes, 'totaux' => $totaux];
    }

    /**
     * Enregistre le paiement du net à payer d'un salarié (montant calculé, non saisi).
     *
     * @throws \DomainException
     */
    public function payer(PeriodePaie $periode, Affectation $affectation, ModePaiement $mode, \DateTimeImmutable $date, ?string $reference, ?User $par): Paiement
    {
        if (!$periode->isCloturee()) {
            throw new \DomainException('La paie doit être validée avant de payer les salaires.');
        }
        foreach ($this->etat($periode)['lignes'] as $ligne) {
            if ($ligne['affectation'] !== $affectation) {
                continue;
            }
            $nom = $affectation->getSalarie()->getNomComplet();
            if ($ligne['paiement']) {
                throw new \DomainException(sprintf('%s est déjà payé(e) (le %s).', $nom, $ligne['paiement']->getPayeLe()->format('d/m/Y')));
            }
            if (bccomp($ligne['net'], '0', 2) <= 0) {
                throw new \DomainException(sprintf('Rien à payer pour %s (net à payer nul ou négatif).', $nom));
            }
            $paiement = new Paiement($periode, $affectation, $ligne['net'], $mode, $date, $reference, $par);
            $this->em->persist($paiement);
            $this->em->flush();

            return $paiement;
        }

        throw new \DomainException('Ce salarié ne fait pas partie de la paie du mois.');
    }

    /**
     * Paie tous les salariés restant à payer, éventuellement pour un seul moyen de paiement (ex. tous les virements).
     * Chaque salarié est payé selon le moyen de paiement de sa fiche.
     *
     * @return int nombre de paiements enregistrés
     *
     * @throws \DomainException
     */
    public function payerTous(PeriodePaie $periode, ?ModePaiement $seulement, \DateTimeImmutable $date, ?string $reference, ?User $par): int
    {
        if (!$periode->isCloturee()) {
            throw new \DomainException('La paie doit être validée avant de payer les salaires.');
        }
        $nombre = 0;
        foreach ($this->etat($periode)['lignes'] as $ligne) {
            if ($ligne['paiement'] || bccomp($ligne['net'], '0', 2) <= 0 || ($seulement && $ligne['mode'] !== $seulement)) {
                continue;
            }
            $this->em->persist(new Paiement($periode, $ligne['affectation'], $ligne['net'], $ligne['mode'], $date, $reference, $par));
            ++$nombre;
        }
        $this->em->flush();

        return $nombre;
    }

    /** @throws \DomainException */
    public function annuler(Paiement $paiement, string $motif, ?User $par): void
    {
        if ($paiement->isAnnule()) {
            throw new \DomainException('Ce paiement est déjà annulé.');
        }
        if ('' === trim($motif)) {
            throw new \DomainException('Indiquez le motif de l\'annulation.');
        }
        $paiement->annuler(trim($motif), $par);
        $this->em->flush();
    }

    /** Nombre de paiements non annulés d'une période (une paie payée ne peut plus être rouverte). */
    public function nombrePaiements(PeriodePaie $periode): int
    {
        return (int) $this->em->getRepository(Paiement::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')->where('p.periode = :periode')->andWhere('p.annuleLe IS NULL')
            ->setParameter('periode', $periode)->getQuery()->getSingleScalarResult();
    }

    /**
     * Ordre de virement à remettre à la banque : une feuille par moyen de paiement, salariés restant à payer
     * (ou tous si $tous), groupés par banque avec sous-totaux.
     */
    public function ordreVirement(PeriodePaie $periode, bool $tous = false): Spreadsheet
    {
        $societe = $periode->getSociete();
        $devise = $societe->getDeviseEffective();
        $parMode = [];
        foreach ($this->etat($periode)['lignes'] as $ligne) {
            if (bccomp($ligne['net'], '0', 2) <= 0 || (!$tous && $ligne['paiement'])) {
                continue;
            }
            $parMode[$ligne['mode']->value][] = $ligne;
        }

        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);
        foreach ([ModePaiement::VIREMENT, ModePaiement::MOBILE_MONEY, ModePaiement::CHEQUE, ModePaiement::ESPECES] as $mode) {
            $lignes = $parMode[$mode->value] ?? [];
            if (!$lignes) {
                continue;
            }
            $bancaire = in_array($mode, [ModePaiement::VIREMENT, ModePaiement::MOBILE_MONEY], true);
            usort($lignes, fn ($a, $b) => [$a['affectation']->getSalarie()->getBanque() ?? '', $a['affectation']->getSalarie()->getNomComplet()]
                <=> [$b['affectation']->getSalarie()->getBanque() ?? '', $b['affectation']->getSalarie()->getNomComplet()]);

            $feuille = $classeur->createSheet()->setTitle(mb_substr($mode->libelle(), 0, 31));
            $feuille->fromArray([
                [sprintf('%s — %s de %s', $societe->getRaisonSociale(), $mode === ModePaiement::VIREMENT ? 'Ordre de virement des salaires' : 'Paiement des salaires ('.mb_strtolower($mode->libelle()).')', $periode->getLibelle())],
                [sprintf('Édité le %s · montants en %s', (new \DateTimeImmutable())->format('d/m/Y'), $devise)],
                [],
                $bancaire
                    ? ['Matricule', 'Nom et prénoms', ModePaiement::MOBILE_MONEY === $mode ? 'Opérateur' : 'Banque', ModePaiement::MOBILE_MONEY === $mode ? 'Téléphone' : 'N° de compte', 'Montant', 'Statut']
                    : ['Matricule', 'Nom et prénoms', 'Montant', 'Statut', 'Émargement'],
            ]);
            $n = 5;
            $total = '0';
            $sousTotal = '0';
            $banqueCourante = null;
            $colMontant = $bancaire ? 'E' : 'C';
            foreach ($lignes as $i => $ligne) {
                $salarie = $ligne['affectation']->getSalarie();
                $statut = $ligne['paiement'] ? 'Payé le '.$ligne['paiement']->getPayeLe()->format('d/m/Y') : 'À payer';
                $valeurs = $bancaire
                    ? [$ligne['affectation']->getMatricule(), $salarie->getNomComplet(), $salarie->getBanque() ?? '', ModePaiement::MOBILE_MONEY === $mode ? $salarie->getTelephone() : $salarie->getNumeroCompte(), (float) $ligne['net'], $statut]
                    : [$ligne['affectation']->getMatricule(), $salarie->getNomComplet(), (float) $ligne['net'], $statut, ''];
                $feuille->fromArray($valeurs, null, 'A'.$n, true);
                $feuille->setCellValueExplicit('A'.$n, $ligne['affectation']->getMatricule(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                if ($bancaire) {
                    $feuille->setCellValueExplicit('D'.$n, (string) $valeurs[3], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                if (!$bancaire && !$ligne['paiement']) {
                    $feuille->getRowDimension($n)->setRowHeight(28);
                }
                ++$n;
                $total = bcadd($total, $ligne['net'], 2);
                $sousTotal = bcadd($sousTotal, $ligne['net'], 2);
                $banqueCourante = $salarie->getBanque() ?? '';
                $suivante = $lignes[$i + 1] ?? null;
                if (ModePaiement::VIREMENT === $mode && (!$suivante || ($suivante['affectation']->getSalarie()->getBanque() ?? '') !== $banqueCourante)) {
                    $feuille->setCellValue('D'.$n, 'Sous-total '.($banqueCourante ?: 'banque non renseignée'));
                    $feuille->setCellValue('E'.$n, (float) $sousTotal);
                    $feuille->getStyle('A'.$n.':F'.$n)->getFont()->setItalic(true);
                    $sousTotal = '0';
                    ++$n;
                }
            }
            $feuille->setCellValue(($bancaire ? 'D' : 'B').$n, sprintf('TOTAL (%d salarié(s))', count($lignes)));
            $feuille->setCellValue($colMontant.$n, (float) $total);
            $derniere = $bancaire ? 'F' : 'E';
            $feuille->getStyle('A'.$n.':'.$derniere.$n)->getFont()->setBold(true);
            $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $feuille->getStyle('A4:'.$derniere.'4')->getFont()->setBold(true);
            $feuille->getStyle('A4:'.$derniere.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
            $feuille->getStyle($colMontant.'5:'.$colMontant.$n)->getNumberFormat()->setFormatCode('#,##0');
            foreach (($bancaire ? [12, 32, 24, 26, 16, 18] : [12, 32, 16, 18, 30]) as $c => $largeur) {
                $feuille->getColumnDimensionByColumn($c + 1)->setWidth($largeur);
            }
            $n += 3;
            $feuille->setCellValue('B'.$n, 'Visa du trésorier');
            $feuille->setCellValue(($bancaire ? 'E' : 'D').$n, 'Visa de la direction');
        }

        if (0 === $classeur->getSheetCount()) {
            $classeur->createSheet()->setTitle('Paiements')->setCellValue('A1', 'Aucun salarié à payer pour '.$periode->getLibelle().'.');
        }
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }
}
