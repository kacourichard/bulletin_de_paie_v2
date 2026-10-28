<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\ElementVariable;
use App\Entity\ImportPointage;
use App\Entity\PeriodePaie;
use App\Entity\Societe;
use App\Entity\User;
use App\HeuresSup\CalculHeuresSup;
use App\HeuresSup\JourTravaille;
use App\HeuresSup\NatureJour;
use App\HeuresSup\RegimeHoraire;
use App\HeuresSup\ResultatHeuresSup;
use App\Repository\AffectationRepository;
use App\Repository\JourFerieRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\String\UnicodeString;

/**
 * Heures supplémentaires à partir d'un relevé de pointage (module repris du projet heures-sup) :
 * lecture du fichier, calcul par salarié avec le calendrier des jours fériés, report dans la paie du mois
 * (rubriques horaires 0014 / 0015 / 0016 / 0017 à 15 / 50 / 75 / 100 % et prime de panier 2073, codes de la v1).
 *
 * Deux formats de fichier :
 * - l'export du logiciel de pointage utilisé par heures-sup : en-têtes ligne 14, données dès la ligne 15,
 *   colonnes A nom, B identifiant, C date, I horaire (« …(40h) » / « …(42h) »), J entrée, K sortie, M heures du jour ;
 * - le modèle simple téléchargeable : Matricule, Nom, Date, Heures, Entrée, Sortie, Horaire (en-têtes ligne 1).
 */
class HeuresSupplementaires
{
    /** Rubriques de paie alimentées par le report (codes du référentiel du pays). */
    /** Codes de la v1 (Côte d'Ivoire : 0014 à 0017, panier 2073). */
    public const RUBRIQUES = ['hs15' => '0014', 'hs50' => '0015', 'hs75' => '0016', 'hs100' => '0017'];
    public const RUBRIQUE_PANIER = '2073';
    private const COMMENTAIRE = 'Heures sup. — pointage';

    private const LIGNE_ENTETE_POINTAGE = 14;
    private const COLONNES_MODELE = [
        'id' => ['matricule', 'identifiant', 'id'],
        'nom' => ['nom', 'nom et prenoms', 'salarie'],
        'date' => ['date', 'jour'],
        'heures' => ['heures', 'heures travaillees', 'duree'],
        'entree' => ['entree', 'debut', 'heure d\'entree'],
        'sortie' => ['sortie', 'fin', 'heure de sortie'],
        'horaire' => ['horaire', 'regime'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JourFerieRepository $joursFeries,
        private readonly AffectationRepository $affectations,
        private readonly RubriqueRepository $rubriques,
        private readonly GestionPaie $paie,
        private readonly CalculHeuresSup $calcul,
    ) {
    }

    /**
     * Lit le relevé et crée l'import (heures cumulées par salarié et par jour).
     *
     * @throws \DomainException fichier illisible ou sans ligne exploitable (le message liste les lignes en erreur)
     */
    public function importer(Societe $societe, \DateTimeImmutable $mois, string $chemin, string $nomFichier, RegimeHoraire $regime, ?User $auteur): ImportPointage
    {
        try {
            $lecteur = IOFactory::createReaderForFile($chemin);
        } catch (\Throwable) {
            throw new \DomainException('Format de fichier non reconnu : envoyez un fichier Excel (.xlsx, .xls) ou CSV.');
        }
        $lecteur->setReadDataOnly(true);
        $lecteur->setReadFilter(new class implements IReadFilter {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return strlen($columnAddress) === 1 && $columnAddress <= 'U';
            }
        });
        $classeur = $lecteur->load($chemin);
        $feuille = $classeur->getActiveSheet();
        $derniere = $feuille->getHighestDataRow();

        $colonnes = $this->colonnesModele($feuille->rangeToArray('A1:U1', null, true, false)[0]);
        if (null === $colonnes) {
            // Export du logiciel de pointage : au moins 10 en-têtes en ligne 14 (contrôle de heures-sup),
            // dont l'identifiant (B), la date (C) et les heures (M).
            $entetes = $feuille->rangeToArray(sprintf('A%1$d:U%1$d', self::LIGNE_ENTETE_POINTAGE), null, true, false)[0];
            $remplies = count(array_filter($entetes, static fn ($v) => '' !== trim((string) $v)));
            if ($remplies < 10 || '' === trim((string) $entetes[1]) || '' === trim((string) $entetes[2]) || '' === trim((string) $entetes[12])) {
                $classeur->disconnectWorksheets();
                throw new \DomainException('Fichier non reconnu : ce n\'est ni le modèle simple (en-têtes Matricule, Date, Heures en ligne 1), ni l\'export du logiciel de pointage (en-têtes en ligne 14, colonnes A à U). Téléchargez un fichier d\'exemple sur cette page.');
            }
        }
        [$debut, $carte] = null !== $colonnes
            ? [2, $colonnes]
            : [self::LIGNE_ENTETE_POINTAGE + 1, ['nom' => 0, 'id' => 1, 'date' => 2, 'horaire' => 8, 'entree' => 9, 'sortie' => 10, 'heures' => 12]];

        $donnees = [];
        $erreurs = [];
        $lignes = 0;
        $min = null;
        $max = null;
        for ($n = $debut; $n <= $derniere; ++$n) {
            $v = $feuille->rangeToArray("A$n:U$n", null, true, false)[0];
            $id = mb_strtoupper(trim((string) ($v[$carte['id']] ?? '')));
            if ('' === $id) {
                continue;
            }
            $date = $this->date($v[$carte['date']] ?? null);
            $heures = $this->nombre($v[$carte['heures']] ?? null);
            if (null === $date) {
                $erreurs[] = sprintf('Ligne %d (%s) : date « %s » invalide.', $n, $id, (string) ($v[$carte['date']] ?? ''));
                continue;
            }
            $brut = $v[$carte['heures']] ?? null;
            if (null === $heures && null !== $brut && '' !== trim((string) $brut)) {
                $erreurs[] = sprintf('Ligne %d (%s) : heures « %s » invalides (nombre attendu, ex. 8,5).', $n, $id, (string) $brut);
                continue;
            }
            if (null === $heures || $heures <= 0) {
                continue;
            }
            if ($heures > 24) {
                $erreurs[] = sprintf('Ligne %d (%s) : %s heures sur une ligne, plus de 24 h.', $n, $id, $heures);
                continue;
            }
            ++$lignes;
            $cle = $date->format('Y-m-d');
            $min = null === $min || $cle < $min ? $cle : $min;
            $max = null === $max || $cle > $max ? $cle : $max;

            $horaire = isset($carte['horaire']) ? trim((string) ($v[$carte['horaire']] ?? '')) : '';
            $salarie = &$donnees[$id];
            $salarie ??= ['nom' => trim((string) ($v[$carte['nom']] ?? '')), 'horaire' => null, 'regime' => null, 'jours' => []];
            if ('' !== $horaire) {
                $salarie['horaire'] ??= $horaire;
                // Comme heures-sup : 42 h l'emporte dès qu'il apparaît sur une ligne.
                $detecte = RegimeHoraire::depuisHoraire($horaire);
                if (null !== $detecte && RegimeHoraire::H42->value !== $salarie['regime']) {
                    $salarie['regime'] = $detecte->value;
                }
            }
            $nuit = $this->nuit($v[$carte['entree']] ?? null, $v[$carte['sortie']] ?? null);
            $salarie['jours'][$cle]['heures'] = round(($salarie['jours'][$cle]['heures'] ?? 0) + $heures, 4);
            $salarie['jours'][$cle]['nuit'] = ($salarie['jours'][$cle]['nuit'] ?? false) || $nuit;
            unset($salarie);
        }
        $classeur->disconnectWorksheets();

        if ($erreurs) {
            throw new \DomainException("Fichier à corriger :\n".implode("\n", array_slice($erreurs, 0, 30)).(count($erreurs) > 30 ? sprintf("\n… et %d autre(s) ligne(s).", count($erreurs) - 30) : ''));
        }
        if (!$donnees) {
            throw new \DomainException(null === $colonnes
                ? 'Aucune ligne de pointage trouvée. Format attendu : l\'export du logiciel de pointage (en-têtes en ligne 14, colonnes A à U), ou le modèle simple à télécharger.'
                : 'Aucune ligne de pointage avec des heures.');
        }

        foreach ($donnees as $id => $s) {
            foreach ($s['jours'] as $date => $j) {
                if ($j['heures'] > 24) {
                    $erreurs[] = sprintf('%s le %s : %s heures pointées dans la journée, plus de 24 h.', $id, (new \DateTimeImmutable($date))->format('d/m/Y'), $j['heures']);
                }
            }
        }
        $finMois = $mois->modify('last day of this month')->format('Y-m-d');
        if ($max < $mois->format('Y-m-d') || $min > $finMois) {
            $erreurs[] = sprintf('Le pointage couvre du %s au %s : aucun jour en %s. Vérifiez le fichier ou le mois de paie choisi.',
                (new \DateTimeImmutable($min))->format('d/m/Y'), (new \DateTimeImmutable($max))->format('d/m/Y'), \IntlDateFormatter::formatObject($mois, 'MMMM y', 'fr'));
        }
        if ($erreurs) {
            throw new \DomainException("Fichier à corriger :\n".implode("\n", array_slice($erreurs, 0, 30)));
        }

        ksort($donnees);
        $import =(new ImportPointage($societe, $mois, $nomFichier, $regime, $auteur))
            ->definirDonnees($donnees, new \DateTimeImmutable($min), new \DateTimeImmutable($max), $lignes);
        $this->em->persist($import);
        $this->em->flush();

        return $import;
    }

    /**
     * Calcul des heures supplémentaires de chaque salarié du relevé, rapproché des salariés de la société
     * (identifiant de pointage, sinon matricule).
     *
     * @return list<array{identifiant: string, nom: string, regime: RegimeHoraire, regimeDetecte: bool, affectation: ?Affectation, resultat: ResultatHeuresSup, libellesFeries: array<string, string>}>
     */
    public function calculer(ImportPointage $import): array
    {
        $calendrier = $this->joursFeries->calendrier($import->getSociete(), $import->getDu(), $import->getAu());
        $salaries = $this->correspondances($import->getSociete());
        $lignes = [];
        foreach ($import->getDonnees() as $identifiant => $s) {
            $regime = RegimeHoraire::tryFrom((string) $s['regime']) ?? $import->getRegimeParDefaut();
            $jours = [];
            foreach ($s['jours'] as $date => $j) {
                $d = new \DateTimeImmutable($date);
                $jours[] = new JourTravaille($d, (float) $j['heures'], '7' === $d->format('N'), $calendrier['natures'][$date] ?? NatureJour::NORMAL, (bool) $j['nuit']);
            }
            $lignes[] = [
                'identifiant' => (string) $identifiant,
                'nom' => $s['nom'],
                'regime' => $regime,
                'regimeDetecte' => null !== $s['regime'],
                'affectation' => $salaries[(string) $identifiant] ?? null,
                'resultat' => $this->calcul->calculer($jours, $regime),
            ];
        }

        return $lignes;
    }

    /** @return array<string, string> libellés des jours fériés de la période du relevé */
    public function libellesFeries(ImportPointage $import): array
    {
        return $this->joursFeries->calendrier($import->getSociete(), $import->getDu(), $import->getAu())['libelles'];
    }

    /** Rattache un identifiant de pointage inconnu à un salarié (mémorisé pour les imports suivants). */
    public function lier(ImportPointage $import, string $identifiant, ?Affectation $affectation): void
    {
        if (!isset($import->getDonnees()[$identifiant])) {
            throw new \DomainException('Identifiant absent du relevé.');
        }
        foreach ($this->affectations->findBy(['societe' => $import->getSociete(), 'identifiantPointage' => $identifiant]) as $ancienne) {
            $ancienne->setIdentifiantPointage(null);
        }
        if ($affectation) {
            if ($affectation->getSociete() !== $import->getSociete()) {
                throw new \DomainException('Ce salarié n\'appartient pas à la société.');
            }
            $affectation->setIdentifiantPointage($identifiant);
        }
        $this->em->flush();
    }

    /**
     * Reporte les heures dans la paie du mois : remplace les éléments issus d'un précédent report
     * (les heures saisies à la main ne sont pas touchées).
     *
     * @return array{salaries: int, elements: int, avertissements: list<string>}
     */
    public function reporter(ImportPointage $import, PeriodePaie $periode, ?string $montantPanier, User $auteur): array
    {
        if ($periode->isCloturee()) {
            throw new \DomainException(sprintf('La paie — %s est validée : rouvrez-la pour y reporter les heures.', $periode->getLibelle()));
        }
        $societe = $import->getSociete();
        $rubriques = [];
        $manquantes = [];
        foreach ([...self::RUBRIQUES, 'panier' => self::RUBRIQUE_PANIER] as $cle => $code) {
            $rubrique = $this->rubriques->findOneBy(['pays' => $societe->getPaysPaie(), 'code' => $code, 'actif' => true]);
            if (null === $rubrique || $societe->isRubriqueMasquee($rubrique) || !$rubrique->isElementVariable()) {
                $manquantes[] = $code;
                continue;
            }
            $rubriques[$cle] = $rubrique;
        }
        $avertissements = $manquantes ? [sprintf('Rubrique(s) %s absente(s) ou masquée(s) dans « Rubriques & barèmes » : non reportée(s).', implode(', ', $manquantes))] : [];
        $panier = null !== $montantPanier && bccomp($montantPanier, '0', 2) > 0 ? $montantPanier : null;

        $stats = ['salaries' => 0, 'elements' => 0];
        $this->em->wrapInTransaction(function () use ($import, $periode, $rubriques, $panier, $auteur, &$stats, &$avertissements) {
            foreach ($this->calculer($import) as $ligne) {
                $affectation = $ligne['affectation'];
                if (null === $affectation) {
                    continue;
                }
                $r = $ligne['resultat'];
                $valeurs = ['hs15' => $r->hs15, 'hs50' => $r->hs50, 'hs75' => $r->hs75, 'hs100' => $r->hs100, 'panier' => null !== $panier ? $r->joursPanier : 0];
                try {
                    foreach ($periode->getElementsDe($affectation) as $existant) {
                        if (self::COMMENTAIRE === $existant->getCommentaire()) {
                            $this->paie->supprimerElement($existant);
                        }
                    }
                    $ajoutes = 0;
                    foreach ($valeurs as $cle => $quantite) {
                        if ($quantite <= 0 || !isset($rubriques[$cle])) {
                            continue;
                        }
                        $element = (new ElementVariable())->setRubrique($rubriques[$cle])->setCommentaire(self::COMMENTAIRE)
                            ->setBase(number_format($quantite, 2, '.', ''));
                        if ('panier' === $cle) {
                            // Base = jours de panier, taux = montant par jour ; montant fixé ici (pas de prorata).
                            $element->setTaux($panier)->setMontant(bcmul((string) $quantite, $panier, 2));
                        }
                        $this->paie->ajouterElement($periode, $affectation, $element, $auteur);
                        ++$ajoutes;
                    }
                    $stats['elements'] += $ajoutes;
                    $stats['salaries'] += $ajoutes > 0 ? 1 : 0;
                } catch (\DomainException $e) {
                    $avertissements[] = sprintf('%s : %s', $affectation->getSalarie()->getNomComplet(), $e->getMessage());
                }
            }
            $import->marquerReporte($auteur, $panier);
            $this->em->flush();
        });

        return $stats + ['avertissements' => $avertissements];
    }

    /** Modèle simple à remplir (une ligne par salarié et par jour, ou par passage). */
    public function modele(Societe $societe): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('Pointage');
        $feuille->fromArray(['Matricule', 'Nom', 'Date', 'Heures', 'Entrée', 'Sortie', 'Horaire'], null, 'A1');
        $n = 2;
        foreach (array_slice($this->affectations->enCours($societe), 0, 3) as $a) {
            foreach (['08:00' => '17:00', '07:00' => '19:00'] as $entree => $sortie) {
                $feuille->setCellValueExplicit('A'.$n, (string) ($a->getIdentifiantPointage() ?? $a->getMatricule()), DataType::TYPE_STRING);
                $feuille->fromArray([$a->getSalarie()->getNomComplet(), (new \DateTimeImmutable('first day of this month'))->modify('+'.($n - 2).' days')->format('d/m/Y'), '07:00' === $entree ? 12 : 9, $entree, $sortie, '40h'], null, 'B'.$n);
                ++$n;
            }
        }
        $feuille->getStyle('A1:G1')->getFont()->setBold(true);
        $feuille->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        foreach ([14, 30, 12, 10, 10, 10, 10] as $i => $largeur) {
            $feuille->getColumnDimensionByColumn($i + 1)->setWidth($largeur);
        }
        $aide = $classeur->createSheet()->setTitle('Mode d\'emploi');
        $aide->fromArray([
            ['Une ligne par salarié et par jour (plusieurs lignes le même jour sont additionnées).'],
            ['Matricule : matricule du salarié dans l\'application, ou son identifiant de pointage.'],
            ['Heures : heures travaillées du jour, pause déduite (ex. 8,5).'],
            ['Entrée / Sortie (facultatives) : servent à repérer le travail de nuit (régime 42 h) : entrée à 18 h ou après, sortie à 6 h ou avant, ou passage de minuit.'],
            ['Horaire (facultatif) : 40h ou 42h ; à défaut, le régime choisi à l\'import s\'applique.'],
        ]);
        $aide->getColumnDimension('A')->setWidth(120);
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /** En-têtes de l'export du logiciel de pointage (colonnes A à U, ligne 14), d'après heures-sup. */
    public const ENTETES_LOGICIEL = ['Worker', 'Employee ID', 'Shift Date', 'Record ID', 'Job Profile', 'Location', 'Location Host', 'Pay Group',
        'Work Shift', 'In Time', 'Out Time', 'Rostered Hours', 'Reported In Hours (Lunch Break)', 'Worked Hours Day', 'Worked Hours (Lunch Break)',
        'Lunch Break', 'Worked Hours Night', 'Worked Hours', 'Non Scheduled Rest Day', 'Rest Day Off', 'Rest Day Worked'];

    /** Exemple au format de l'export du logiciel de pointage, avec les salariés de la société. */
    public function exempleLogiciel(Societe $societe): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('Time Report');
        $feuille->fromArray([
            ['Time Report — '.$societe->getRaisonSociale()],
            ['Exemple : les lignes 1 à 13 (titre, filtres du logiciel) sont ignorées ; les en-têtes sont en ligne 14, les données à partir de la ligne 15.'],
            ['Colonnes utilisées : B identifiant du salarié, C date, I horaire (« (40h) » ou « (42h) »), J heure d\'entrée, K heure de sortie, M heures du jour (pause déduite).'],
        ], null, 'A1');
        $feuille->fromArray(self::ENTETES_LOGICIEL, null, 'A'.self::LIGNE_ENTETE_POINTAGE);
        $n = self::LIGNE_ENTETE_POINTAGE + 1;
        $jour = new \DateTimeImmutable('first monday of this month');
        foreach (array_slice($this->affectations->enCours($societe), 0, 3) as $i => $a) {
            $nuit = 2 === $i;
            for ($k = 0; $k < 3; ++$k) {
                $date = $jour->modify("+$k days");
                $entree = $date->setTime($nuit ? 19 : 8, 0);
                $sortie = $nuit ? $date->modify('+1 day')->setTime(7, 0) : $date->setTime(17, 30);
                $feuille->fromArray([
                    $a->getSalarie()->getNomComplet(), null, ExcelDate::PHPToExcel($date), 'R'.$n, $a->getPoste() ?? '', $societe->getVille() ?? '', '', '',
                    $nuit ? 'Night 12 Hours(42h)' : 'Day 8 Hours(40h)', ExcelDate::PHPToExcel($entree), ExcelDate::PHPToExcel($sortie), $nuit ? 12 : 8,
                    $nuit ? 11 : 8.5, $nuit ? 0 : 8.5, $nuit ? 11 : 8.5, 1, $nuit ? 11 : 0, $nuit ? 11 : 8.5, '', '', '',
                ], null, 'A'.$n);
                $feuille->setCellValueExplicit('B'.$n, (string) ($a->getIdentifiantPointage() ?? $a->getMatricule()), DataType::TYPE_STRING);
                ++$n;
            }
        }
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $feuille->getStyle('A2:A3')->getFont()->setItalic(true);
        $ligne = self::LIGNE_ENTETE_POINTAGE;
        $feuille->getStyle("A$ligne:U$ligne")->getFont()->setBold(true);
        $feuille->getStyle("A$ligne:U$ligne")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        foreach (['B', 'C', 'I', 'J', 'K', 'M'] as $col) {
            $feuille->getStyle("{$col}{$ligne}")->getFill()->getStartColor()->setRGB('FDE68A');
        }
        $feuille->getStyle('C15:C'.$n)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $feuille->getStyle('J15:K'.$n)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        foreach (range('A', 'U') as $col) {
            $feuille->getColumnDimension($col)->setWidth(in_array($col, ['A', 'I', 'J', 'K', 'M'], true) ? 22 : 13);
        }

        return $classeur;
    }

    /** @return list<string> identifiants du relevé qui ne correspondent à aucun salarié de la société */
    public function nonRapproches(ImportPointage $import): array
    {
        $carte = $this->correspondances($import->getSociete());

        return array_values(array_filter(array_map('strval', array_keys($import->getDonnees())), static fn (string $id) => !isset($carte[$id])));
    }

    /** Export Excel du calcul. */
    public function export(ImportPointage $import): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('Heures supplémentaires');
        $feuille->setCellValue('A1', sprintf('%s — heures supplémentaires — pointage du %s au %s', $import->getSociete()->getRaisonSociale(), $import->getDu()->format('d/m/Y'), $import->getAu()->format('d/m/Y')));
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $feuille->fromArray(['Identifiant pointage', 'Matricule', 'Nom', 'Régime', 'Jours pointés', 'Heures travaillées', 'HS 15 %', 'HS 50 %', 'HS 75 %', 'HS 100 %', 'Total HS', 'Jours panier (≥ 11 h)', 'Fériés chômés travaillés'], null, 'A3');
        $n = 4;
        foreach ($this->calculer($import) as $l) {
            $r = $l['resultat'];
            $feuille->setCellValueExplicit('A'.$n, $l['identifiant'], DataType::TYPE_STRING);
            $feuille->setCellValueExplicit('B'.$n, (string) $l['affectation']?->getMatricule(), DataType::TYPE_STRING);
            $feuille->fromArray([$l['affectation']?->getSalarie()->getNomComplet() ?? $l['nom'], $l['regime']->libelle(), count($r->jours), $r->heuresTravaillees, $r->hs15, $r->hs50, $r->hs75, $r->hs100, $r->totalHeuresSup(), $r->joursPanier, $r->joursFeriesTravailles], null, 'C'.$n);
            ++$n;
        }
        $feuille->getStyle('A3:M3')->getFont()->setBold(true);
        $feuille->getStyle('A3:M3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        foreach ([18, 12, 30, 14, 10, 12, 10, 10, 10, 10, 10, 14, 14] as $i => $largeur) {
            $feuille->getColumnDimensionByColumn($i + 1)->setWidth($largeur);
        }

        return $classeur;
    }

    /** @return array<string, Affectation> identifiant de pointage (ou matricule) => affectation */
    private function correspondances(Societe $societe): array
    {
        $carte = [];
        foreach ($this->affectations->findBy(['societe' => $societe], ['dateEntree' => 'ASC']) as $a) {
            $carte[(string) $a->getMatricule()] = $a;
        }
        // L'identifiant de pointage enregistré l'emporte sur un matricule identique.
        foreach ($this->affectations->findBy(['societe' => $societe], ['dateEntree' => 'ASC']) as $a) {
            if ($a->getIdentifiantPointage()) {
                $carte[$a->getIdentifiantPointage()] = $a;
            }
        }

        return $carte;
    }

    /** @return ?array<string, int> colonnes du modèle simple si la ligne 1 en a les en-têtes */
    private function colonnesModele(array $entetes): ?array
    {
        // « Entrée » → « entree » (iconv de Windows donnerait « entr'ee »).
        $normaliser = static fn ($v) => (new UnicodeString((string) $v))->ascii()->lower()->trim()->toString();
        $carte = [];
        foreach ($entetes as $i => $entete) {
            $e = $normaliser($entete);
            foreach (self::COLONNES_MODELE as $cle => $noms) {
                if (!isset($carte[$cle]) && in_array($e, array_map($normaliser, $noms), true)) {
                    $carte[$cle] = $i;
                }
            }
        }
        if (!isset($carte['id'], $carte['date'], $carte['heures'])) {
            return null;
        }

        return $carte + ['nom' => $carte['id'], 'entree' => -1, 'sortie' => -1];
    }

    private function date(mixed $v): ?\DateTimeImmutable
    {
        if ($v instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($v)->setTime(0, 0);
        }
        if (is_numeric($v) && $v > 1) {
            return \DateTimeImmutable::createFromMutable(ExcelDate::excelToDateTimeObject((float) $v))->setTime(0, 0);
        }
        $s = trim((string) $v);
        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d', '!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y', '!d-m-Y'] as $format) {
            // Strict : « 32/10/2026 » ne doit pas devenir le 1er novembre.
            if ('' !== $s && ($d = \DateTimeImmutable::createFromFormat($format, $s)) && !(\DateTimeImmutable::getLastErrors()['warning_count'] ?? 0)) {
                return $d->setTime(0, 0);
            }
        }

        return null;
    }

    /** Heure ou date-heure → [jour (numéro Excel, 0 si heure seule), minutes depuis minuit] ; null si vide. */
    private function horodatage(mixed $v): ?array
    {
        if (null === $v || '' === $v) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return [(int) $v->format('Ymd'), (int) $v->format('G') * 60 + (int) $v->format('i')];
        }
        if (is_numeric($v)) {
            $jour = (int) floor((float) $v);

            return [$jour, (int) round(((float) $v - $jour) * 1440) % 1440];
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{1,2})[:h](\d{2})/', $s, $m)) {
            return [0, (int) $m[1] * 60 + (int) $m[2]];
        }
        if ($d = $this->date($s)) {
            $t = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s) ?: \DateTimeImmutable::createFromFormat('d/m/Y H:i', $s);

            return $t ? [(int) $t->format('Ymd'), (int) $t->format('G') * 60 + (int) $t->format('i')] : null;
        }

        return null;
    }

    /** Heuristique de heures-sup : passage de minuit, entrée à 18 h ou après, ou sortie à 6 h ou avant. */
    private function nuit(mixed $entree, mixed $sortie): bool
    {
        $e = $this->horodatage($entree);
        $s = $this->horodatage($sortie);
        if (null === $e || null === $s) {
            return false;
        }
        $passeMinuit = ($e[0] && $s[0]) ? $s[0] !== $e[0] : $s[1] < $e[1];

        return $passeMinuit || $e[1] >= 18 * 60 || $s[1] <= 6 * 60;
    }

    private function nombre(mixed $v): ?float
    {
        if (null === $v || '' === $v) {
            return null;
        }
        $v = is_string($v) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($v)) : $v;

        return is_numeric($v) ? (float) $v : null;
    }
}
