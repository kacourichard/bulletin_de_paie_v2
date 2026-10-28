<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Societe;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des états de paie (mêmes données que les états imprimables de EtatsPaie).
 */
class ExportEtatsPaie
{
    private const FORMAT_MONTANT = '#,##0.##;-#,##0.##;""';

    public function rubriques(Societe $societe, string $periode, ?string $population, array $etat): Spreadsheet
    {
        [$classeur, $feuille] = $this->classeur('État des rubriques', $societe, 'État des rubriques — '.$periode.($population ? ' — '.$population : ''), $etat['provisoire']);
        $feuille->fromArray(['Code', 'Libellé', 'Gains', 'Retenues', 'Part patronale', 'Bulletins'], null, 'A4');
        $n = 5;
        foreach ($etat['lignes'] as $l) {
            $feuille->setCellValueExplicit('A'.$n, $l['code'], DataType::TYPE_STRING);
            $feuille->fromArray([$l['libelle'], (float) $l['gain'], (float) $l['retenue'], (float) $l['patronal'], $l['bulletins']], null, 'B'.$n);
            ++$n;
        }
        $feuille->fromArray(['', 'TOTAL', (float) $etat['totaux']['gain'], (float) $etat['totaux']['retenue'], (float) $etat['totaux']['patronal']], null, 'A'.$n);
        $feuille->fromArray(['', 'Net à payer', (float) $etat['totaux']['net']], null, 'A'.($n + 1));
        $feuille->fromArray(['', sprintf('Effectif : %d salarié(s), %d bulletin(s)', $etat['effectif'], $etat['nbBulletins'])], null, 'A'.($n + 2));
        $this->mettreEnForme($feuille, 'F', $n, 'C', 'E', [12, 42, 16, 16, 16, 10]);
        $feuille->getStyle('C'.($n + 1))->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT);
        $feuille->getStyle('B'.($n + 1).':C'.($n + 1))->getFont()->setBold(true);

        return $classeur;
    }

    public function livre(Societe $societe, string $periode, array $livre): Spreadsheet
    {
        [$classeur, $feuille] = $this->classeur('Livre de paie', $societe, 'Livre de paie — '.$periode, $livre['provisoire']);
        $entetes = ['Mois', 'Matricule', 'Nom et prénoms'];
        foreach ($livre['colonnes'] as $c) {
            $entetes[] = $c['libelle'].($c['code'] ? ' ('.$c['code'].')' : '');
        }
        $feuille->fromArray($entetes, null, 'A4');
        $derniere = Coordinate::stringFromColumnIndex(count($entetes));
        $n = 5;
        foreach ($livre['mois'] as $mois) {
            foreach ($mois['lignes'] as $ligne) {
                $feuille->setCellValue('A'.$n, $mois['periode']->getLibelle());
                $feuille->setCellValueExplicit('B'.$n, $ligne['affectation']->getMatricule(), DataType::TYPE_STRING);
                $feuille->setCellValue('C'.$n, $ligne['affectation']->getSalarie()->getNomComplet());
                $feuille->fromArray(array_map(static fn (array $c) => (float) ($ligne['valeurs'][$c['cle']] ?? 0), $livre['colonnes']), null, 'D'.$n);
                ++$n;
            }
            if (count($livre['mois']) > 1) {
                $feuille->setCellValue('C'.$n, 'Total '.$mois['periode']->getLibelle());
                $feuille->fromArray(array_map(static fn (array $c) => $c['somme'] ? (float) ($mois['total'][$c['cle']] ?? 0) : null, $livre['colonnes']), null, 'D'.$n);
                $feuille->getStyle('A'.$n.':'.$derniere.$n)->getFont()->setItalic(true)->setBold(true);
                ++$n;
            }
        }
        $feuille->setCellValue('C'.$n, sprintf('TOTAL GÉNÉRAL (%d salarié(s))', $livre['effectif']));
        $feuille->fromArray(array_map(static fn (array $c) => $c['somme'] ? (float) ($livre['total'][$c['cle']] ?? 0) : null, $livre['colonnes']), null, 'D'.$n);
        $this->mettreEnForme($feuille, $derniere, $n, 'D', $derniere, [14, 12, 30]);
        $feuille->getStyle('A4:'.$derniere.'4')->getAlignment()->setWrapText(true)->setVertical('top');
        for ($i = 4; $i <= count($entetes); ++$i) {
            $feuille->getColumnDimensionByColumn($i)->setWidth(14);
        }
        $feuille->freezePane('D5');
        $this->paysage($feuille);

        return $classeur;
    }

    public function fiche(Affectation $affectation, int $annee, array $fiche): Spreadsheet
    {
        $salarie = $affectation->getSalarie();
        [$classeur, $feuille] = $this->classeur('Fiche individuelle', $affectation->getSociete(), sprintf('Fiche individuelle %d — %s (%s)', $annee, $salarie->getNomComplet(), $affectation->getMatricule()), [] !== $fiche['provisoires']);
        $entetes = ['Code', 'Libellé'];
        foreach ($fiche['mois'] as $mois) {
            $entetes[] = ucfirst(\IntlDateFormatter::formatObject($mois, 'MMM', 'fr'));
        }
        $entetes[] = 'Total';
        $feuille->fromArray($entetes, null, 'A4');
        $n = 5;
        $ligneMontants = static fn (array $mois, ?string $total) => [...array_map(static fn (int $m) => isset($mois[$m]) ? (float) $mois[$m] : null, range(1, 12)), null === $total ? null : (float) $total];
        foreach ($fiche['sections'] as $section) {
            $feuille->setCellValue('B'.$n, mb_strtoupper($section['titre']));
            $feuille->getStyle('B'.$n)->getFont()->setBold(true);
            ++$n;
            foreach ($section['lignes'] as $l) {
                $feuille->setCellValueExplicit('A'.$n, $l['code'], DataType::TYPE_STRING);
                $feuille->setCellValue('B'.$n, $l['libelle']);
                $feuille->fromArray($ligneMontants($l['mois'], $l['total']), null, 'C'.$n, true);
                ++$n;
            }
            $feuille->setCellValue('B'.$n, 'Total '.mb_strtolower($section['titre']));
            $feuille->fromArray($ligneMontants($section['total']['mois'], $section['total']['total']), null, 'C'.$n, true);
            $feuille->getStyle('A'.$n.':O'.$n)->getFont()->setBold(true);
            $n += 2;
        }
        $feuille->setCellValue('B'.$n, 'SYNTHÈSE');
        $feuille->getStyle('B'.$n)->getFont()->setBold(true);
        ++$n;
        foreach ($fiche['synthese'] as $l) {
            $feuille->setCellValue('B'.$n, $l['libelle']);
            $feuille->fromArray($ligneMontants($l['mois'], $l['total']), null, 'C'.$n, true);
            if ($l['important']) {
                $feuille->getStyle('A'.$n.':O'.$n)->getFont()->setBold(true);
            }
            ++$n;
        }
        $this->mettreEnForme($feuille, 'O', null, 'C', 'O', [10, 36, ...array_fill(0, 13, 13)]);
        $feuille->getStyle('C5:O'.$n)->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT);
        $feuille->freezePane('C5');
        $this->paysage($feuille);

        return $classeur;
    }

    public function bordereau(Societe $societe, string $periode, array $bordereau): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);
        foreach ($bordereau['groupes'] as $groupe) {
            $feuille = $classeur->createSheet()->setTitle(mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], ' ', $groupe['nom']), 0, 31));
            $this->entete($feuille, $societe, 'Bordereau des salaires — '.$periode.' — '.$groupe['nom'], $bordereau['provisoire']);
            $feuille->fromArray(['N°', 'Matricule', 'Nom et prénoms', 'Mode de paiement', 'Banque', 'N° de compte', 'Net à payer'], null, 'A4');
            $n = 5;
            foreach ($groupe['lignes'] as $i => $l) {
                $s = $l['affectation']->getSalarie();
                $feuille->fromArray([$i + 1, null, $s->getNomComplet(), $s->getModePaiement()->libelle(), $s->getBanque() ?? '', null, (float) $l['net']], null, 'A'.$n);
                $feuille->setCellValueExplicit('B'.$n, $l['affectation']->getMatricule(), DataType::TYPE_STRING);
                $feuille->setCellValueExplicit('F'.$n, (string) ('MOBILE_MONEY' === $s->getModePaiement()->value ? $s->getTelephone() : $s->getNumeroCompte()), DataType::TYPE_STRING);
                ++$n;
            }
            $feuille->setCellValue('F'.$n, 'Total salaires nets');
            $feuille->setCellValue('G'.$n, (float) $groupe['total']);
            $feuille->setCellValue('A'.($n + 2), 'Arrêté le présent bordereau à la somme de : '.mb_strtoupper($groupe['enLettres']));
            $feuille->setCellValue('E'.($n + 5), 'LE DIRECTEUR');
            $this->mettreEnForme($feuille, 'G', $n, 'G', 'G', [6, 12, 32, 18, 24, 28, 16]);
        }
        if (0 === $classeur->getSheetCount()) {
            $classeur->createSheet()->setTitle('Bordereau')->setCellValue('A1', 'Aucun salaire sur la période.');
        }
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /** @return array{0: Spreadsheet, 1: Worksheet} */
    private function classeur(string $nomFeuille, Societe $societe, string $titre, bool $provisoire): array
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle($nomFeuille);
        $this->entete($feuille, $societe, $titre, $provisoire);

        return [$classeur, $feuille];
    }

    private function entete(Worksheet $feuille, Societe $societe, string $titre, bool $provisoire): void
    {
        $feuille->setCellValue('A1', $societe->getRaisonSociale().' — '.$titre);
        $feuille->setCellValue('A2', sprintf('Édité le %s · montants en %s%s', (new \DateTimeImmutable())->format('d/m/Y'), $societe->getDeviseEffective(), $provisoire ? ' · contient des mois non validés (provisoire)' : ''));
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        if ($provisoire) {
            $feuille->getStyle('A2')->getFont()->getColor()->setRGB('B45309');
        }
    }

    /** Impression A4 paysage, ajustée à la largeur d'une page, en-tête du tableau répété sur chaque page. */
    private function paysage(Worksheet $feuille): void
    {
        $feuille->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(4, 4);
        $feuille->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);
    }

    /** En-tête de tableau en ligne 4, ligne de total en gras, format des montants et largeurs de colonnes. */
    private function mettreEnForme(Worksheet $feuille, string $derniere, ?int $ligneTotal, string $premiereMontant, string $derniereMontant, array $largeurs): void
    {
        $feuille->getStyle('A4:'.$derniere.'4')->getFont()->setBold(true);
        $feuille->getStyle('A4:'.$derniere.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        if ($ligneTotal) {
            $feuille->getStyle('A'.$ligneTotal.':'.$derniere.$ligneTotal)->getFont()->setBold(true);
            $feuille->getStyle($premiereMontant.'5:'.$derniereMontant.$ligneTotal)->getNumberFormat()->setFormatCode(self::FORMAT_MONTANT);
        }
        foreach ($largeurs as $i => $largeur) {
            $feuille->getColumnDimensionByColumn($i + 1)->setWidth($largeur);
        }
    }
}
