<?php

namespace App\Service;

use App\Entity\ElementVariable;
use App\Entity\PeriodePaie;
use App\Entity\Rubrique;
use App\Entity\User;
use App\Repository\AffectationRepository;
use App\Repository\ParametrePaieRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rapport de travail Excel : saisie en masse des jours travaillés et des éléments variables
 * d'un mois (heures supplémentaires, primes, retenues...), une ligne par salarié.
 *
 * Colonnes reconnues (ligne 1) : « Matricule », « Jours », puis le code d'une rubrique variable
 * du pays (ex. « 0015 », « 0064 », éventuellement suivi d'un libellé : « 0015 — Heures supplémentaires à 50 % »).
 * Rubriques horaires : la cellule contient un nombre d'heures ; autres rubriques : un montant.
 */
class RapportTravailImport
{
    public function __construct(
        private readonly AffectationRepository $affectations,
        private readonly RubriqueRepository $rubriques,
        private readonly GestionPaie $paie,
        private readonly EntityManagerInterface $em,
        private readonly ParametrePaieRepository $parametres,
    ) {
    }

    /** Classeur modèle pré-rempli avec les salariés présents sur le mois. */
    public function modele(PeriodePaie $periode): Spreadsheet
    {
        $societe = $periode->getSociete();
        $rubriques = $this->rubriquesImportables($periode);

        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('Rapport de travail');
        $entetes = ['Matricule', 'Nom et prénoms', 'Jours'];
        foreach ($rubriques as $rubrique) {
            $entetes[] = sprintf('%s — %s (%s)', $rubrique->getCode(), $rubrique->getLibelle(), $rubrique->isHoraire() ? 'heures' : 'montant');
        }
        $feuille->fromArray($entetes, null, 'A1');

        $ligne = 2;
        foreach ($this->affectations->presentesSurPeriode($societe, $periode->getDebut(), $periode->getFin()) as $affectation) {
            $feuille->setCellValueExplicit('A'.$ligne, $affectation->getMatricule(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $feuille->setCellValue('B'.$ligne, $affectation->getSalarie()->getNomComplet());
            ++$ligne;
        }

        $derniere = Coordinate::stringFromColumnIndex(count($entetes));
        $feuille->getStyle('A1:'.$derniere.'1')->getFont()->setBold(true);
        $feuille->getStyle('A1:'.$derniere.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        $feuille->getStyle('A1:B'.max(2, $ligne - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        $feuille->freezePane('C2');
        for ($c = 1; $c <= count($entetes); ++$c) {
            $feuille->getColumnDimensionByColumn($c)->setWidth($c <= 2 ? 26 : 18);
        }
        $feuille->getRowDimension(1)->setRowHeight(45);
        $feuille->getStyle('A1:'.$derniere.'1')->getAlignment()->setWrapText(true)->setVertical('center');

        $aide = $classeur->createSheet()->setTitle('Aide');
        $aide->fromArray([
            [sprintf('Rapport de travail — %s — %s', $societe->getRaisonSociale(), $periode->getLibelle())],
            [''],
            ['Une ligne par salarié, identifié par son matricule (colonne A). La colonne B est indicative.'],
            [sprintf('Jours : jours travaillés du mois (%d = mois complet). Laisser vide pour le calcul automatique.', $this->parametres->pour($societe->getPaysPaie())->getJoursMois())],
            ['Colonnes « (heures) » : nombre d\'heures ; le taux horaire majoré est calculé par l\'application.'],
            ['Colonnes « (montant) » : montant du mois dans la devise de la société ('.$societe->getDeviseEffective().').'],
            ['Cellule vide ou 0 : rien n\'est importé pour cette rubrique. Vous pouvez supprimer les colonnes inutiles.'],
            ['Réimporter le fichier remplace les éléments importés précédemment pour les mêmes rubriques.'],
        ], null, 'A1');
        $aide->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $aide->getColumnDimension('A')->setWidth(110);
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /**
     * Lit le fichier et contrôle chaque ligne, sans rien enregistrer.
     *
     * @return array{lignes: list<array{affectation: int, matricule: string, nom: string, jours: ?string, elements: array<string, string>}>, erreurs: list<string>, avertissements: list<string>, colonnes: list<string>}
     */
    public function analyser(PeriodePaie $periode, string $chemin): array
    {
        try {
            $feuille = IOFactory::load($chemin)->getSheet(0);
        } catch (\Throwable) {
            return ['lignes' => [], 'erreurs' => ['Fichier illisible : envoyez un classeur Excel (.xlsx, .xls) ou un fichier CSV.'], 'avertissements' => [], 'colonnes' => []];
        }
        $donnees = $feuille->toArray(null, true, false, false);
        $entetes = array_shift($donnees) ?? [];

        $rubriques = [];
        foreach ($this->rubriquesImportables($periode) as $rubrique) {
            $rubriques[$rubrique->getCode()] = $rubrique;
        }
        $colMatricule = $colJours = null;
        $colRubriques = [];
        $avertissements = [];
        foreach ($entetes as $i => $entete) {
            $entete = trim((string) $entete);
            $code = mb_strtoupper(trim(preg_split('/\s+[—–-]\s+|\s+\(|\s+/u', $entete)[0] ?? ''));
            if ('' === $entete) {
                continue;
            }
            if ('MATRICULE' === $code) {
                $colMatricule = $i;
            } elseif ('JOURS' === $code) {
                $colJours = $i;
            } elseif (isset($rubriques[$code])) {
                $colRubriques[$i] = $rubriques[$code];
            } elseif (!in_array($code, ['NOM', 'NOMS', 'PRÉNOMS', 'PRENOMS'], true)) {
                $avertissements[] = sprintf('Colonne « %s » ignorée : aucune rubrique variable de ce code pour %s.', $entete, $periode->getSociete()->getPaysPaie()->getNom());
            }
        }
        if (null === $colMatricule) {
            return ['lignes' => [], 'erreurs' => ['Colonne « Matricule » introuvable en ligne 1. Utilisez le modèle à télécharger.'], 'avertissements' => $avertissements, 'colonnes' => []];
        }

        $presents = [];
        foreach ($this->affectations->presentesSurPeriode($periode->getSociete(), $periode->getDebut(), $periode->getFin()) as $affectation) {
            $presents[mb_strtoupper($affectation->getMatricule())] = $affectation;
        }

        $lignes = [];
        $erreurs = [];
        $vus = [];
        foreach ($donnees as $n => $ligne) {
            $numero = $n + 2;
            $matricule = mb_strtoupper(trim((string) ($ligne[$colMatricule] ?? '')));
            if ('' === $matricule) {
                continue;
            }
            $affectation = $presents[$matricule] ?? null;
            if (null === $affectation) {
                $erreurs[] = sprintf('Ligne %d : matricule %s inconnu ou absent de la société en %s.', $numero, $matricule, $periode->getLibelle());
                continue;
            }
            if (isset($vus[$matricule])) {
                $erreurs[] = sprintf('Ligne %d : matricule %s déjà présent ligne %d.', $numero, $matricule, $vus[$matricule]);
                continue;
            }
            $vus[$matricule] = $numero;

            $jours = null;
            if (null !== $colJours && '' !== trim((string) ($ligne[$colJours] ?? ''))) {
                $jours = $this->nombre($ligne[$colJours]);
                if (null === $jours || $jours < 0 || $jours > 31) {
                    $erreurs[] = sprintf('Ligne %d (%s) : jours « %s » invalides (0 à 31).', $numero, $matricule, $ligne[$colJours]);
                    continue;
                }
            }

            $elements = [];
            $ligneValide = true;
            foreach ($colRubriques as $i => $rubrique) {
                $brut = trim((string) ($ligne[$i] ?? ''));
                if ('' === $brut) {
                    continue;
                }
                $valeur = $this->nombre($brut);
                if (null === $valeur || $valeur < 0) {
                    $erreurs[] = sprintf('Ligne %d (%s) : valeur « %s » invalide pour %s.', $numero, $matricule, $brut, $rubrique->getCode());
                    $ligneValide = false;
                    continue;
                }
                if (bccomp($valeur, '0', 4) > 0) {
                    $elements[$rubrique->getCode()] = $valeur;
                }
            }
            if ($ligneValide && (null !== $jours || $elements)) {
                $lignes[] = ['affectation' => $affectation->getId(), 'matricule' => $affectation->getMatricule(), 'nom' => $affectation->getSalarie()->getNomComplet(), 'jours' => $jours, 'elements' => $elements];
            }
        }

        return ['lignes' => $lignes, 'erreurs' => $erreurs, 'avertissements' => $avertissements, 'colonnes' => array_values(array_map(static fn (Rubrique $r) => $r->getCode(), $colRubriques))];
    }

    /**
     * Enregistre une analyse validée. Les éléments ponctuels déjà saisis pour les mêmes
     * rubriques sont remplacés (un réimport ne crée pas de doublons).
     *
     * @return array{salaries: int, elements: int, jours: int}
     *
     * @throws \DomainException
     */
    public function appliquer(PeriodePaie $periode, array $analyse, User $auteur): array
    {
        if ($periode->isCloturee()) {
            throw new \DomainException('La paie de ce mois est clôturée.');
        }
        $rubriques = [];
        foreach ($this->rubriquesImportables($periode) as $rubrique) {
            $rubriques[$rubrique->getCode()] = $rubrique;
        }

        $stats = ['salaries' => 0, 'elements' => 0, 'jours' => 0];
        $this->em->wrapInTransaction(function () use ($periode, $analyse, $auteur, $rubriques, &$stats) {
            foreach ($analyse['lignes'] as $ligne) {
                $affectation = $this->affectations->find($ligne['affectation']);
                // Solde de tout compte arrêté : paie verrouillée, la ligne du rapport est ignorée.
                if (null === $affectation || $affectation->getSociete() !== $periode->getSociete() || $this->paie->soldeArrete($periode, $affectation)) {
                    continue;
                }
                ++$stats['salaries'];
                if (null !== $ligne['jours']) {
                    $this->paie->definirJours($periode, $affectation, $ligne['jours']);
                    ++$stats['jours'];
                }
                foreach ($periode->getElementsDe($affectation) as $existant) {
                    if (!$existant->isAjustement() && isset($ligne['elements'][$existant->getRubrique()->getCode()])) {
                        $this->paie->supprimerElement($existant);
                    }
                }
                foreach ($ligne['elements'] as $code => $valeur) {
                    $rubrique = $rubriques[$code] ?? null;
                    if (null === $rubrique) {
                        continue;
                    }
                    $element = (new ElementVariable())->setRubrique($rubrique)->setCommentaire('Import rapport de travail');
                    $rubrique->isHoraire() ? $element->setBase($valeur) : $element->setMontant($valeur);
                    $this->paie->ajouterElement($periode, $affectation, $element, $auteur);
                    ++$stats['elements'];
                }
            }
        });

        return $stats;
    }

    /** @return Rubrique[] rubriques variables : horaires d'abord, puis gains, puis retenues */
    private function rubriquesImportables(PeriodePaie $periode): array
    {
        $liste = $this->rubriques->variablesDisponibles($periode->getSociete()->getPaysPaie(), $periode->getSociete());
        usort($liste, static fn (Rubrique $a, Rubrique $b) => [$b->isHoraire(), $b->isGain(), $a->getCode()] <=> [$a->isHoraire(), $a->isGain(), $b->getCode()]);

        return $liste;
    }

    /** Nombre saisi en français ou en anglais (« 1 250,50 », « 1250.5 ») ; null si invalide. */
    private function nombre(mixed $valeur): ?string
    {
        if (is_int($valeur) || is_float($valeur)) {
            return number_format((float) $valeur, 4, '.', '');
        }
        $texte = str_replace(["\u{00A0}", "\u{202F}", ' '], '', (string) $valeur);
        $texte = str_contains($texte, ',') && !str_contains($texte, '.') ? str_replace(',', '.', $texte) : str_replace(',', '', $texte);

        return is_numeric($texte) ? number_format((float) $texte, 4, '.', '') : null;
    }
}
