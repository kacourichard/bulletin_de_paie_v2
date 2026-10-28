<?php

namespace App\Service;

use App\Entity\Rubrique;
use App\Entity\Societe;
use App\Enum\SituationFamiliale;
use App\Repository\CotisationRepository;
use App\Repository\ParametrePaieRepository;

/**
 * Simulateur de bulletin pour une société : mois complet, mêmes règles que la paie du mois
 * (paramètres du pays de paie, taux accident du travail du secteur, taux non-nationaux).
 */
class SimulateurPaie
{
    public function __construct(
        private readonly CalculPaie $calcul,
        private readonly ParametrePaieRepository $parametres,
        private readonly CotisationRepository $cotisations,
    ) {
    }

    /**
     * @param array{
     *   salaireCategoriel: string, sursalaire: string, dateEmbauche: \DateTimeImmutable,
     *   situation: SituationFamiliale, enfants: int, national: bool,
     *   lignes: list<array{rubrique: Rubrique, montant: string}>
     * } $entree
     *
     * @return array{calcul: array, bulletin: array, gains: list<array{code: ?string, libelle: string, montant: string, dansBrut: bool}>, retenues: list<array{code: ?string, libelle: string, montant: string}>, totalGains: string, coutEmployeur: string}
     */
    public function simuler(Societe $societe, array $entree): array
    {
        $pays = $societe->getPaysPaie();
        $parametre = $this->parametres->pour($pays);
        $gainsRubriques = [];
        $retenues = '0';
        $lignesRetenues = [];
        foreach ($entree['lignes'] as $l) {
            $r = $l['rubrique'];
            if ($r->isGain()) {
                $gainsRubriques[] = ['code' => $r->getCode(), 'libelle' => $r->getLibelle(), 'montant' => $l['montant'], 'section' => $r->getSection(), 'fiscal' => $r->getTraitementFiscal(), 'plafond' => $r->getPlafondExoneration()];
            } else {
                $retenues = bcadd($retenues, $l['montant'], 2);
                $lignesRetenues[] = ['code' => $r->getCode(), 'libelle' => $r->getLibelle(), 'montant' => $l['montant']];
            }
        }

        $calcul = $this->calcul->calculer($parametre, $this->parametres->grilleParts($pays), [
            'salaireCategoriel' => $entree['salaireCategoriel'],
            'sursalaire' => $entree['sursalaire'],
            'dateEmbauche' => $entree['dateEmbauche'],
            'dateReference' => new \DateTimeImmutable('last day of this month'),
            'situation' => $entree['situation'],
            'enfants' => $entree['enfants'],
            'gains' => $gainsRubriques,
        ]);

        // Gains : salaire, sursalaire, ancienneté, rubriques (les gains hors brut sont versés en plus du net).
        $gains = [['code' => $parametre->getCodeSalaireBase(), 'libelle' => 'Salaire catégoriel', 'montant' => $entree['salaireCategoriel'], 'dansBrut' => true]];
        if (bccomp($entree['sursalaire'], '0', 2) > 0) {
            $gains[] = ['code' => null, 'libelle' => 'Sursalaire', 'montant' => $entree['sursalaire'], 'dansBrut' => $parametre->isBrutInclutSursalaire()];
        }
        if ($calcul['anciennete']['applicable']) {
            $gains[] = ['code' => $calcul['anciennete']['code'], 'libelle' => sprintf('Prime d\'ancienneté (%d ans, %s %%)', $calcul['anciennete']['annees'], $calcul['anciennete']['taux'] + 0), 'montant' => $calcul['anciennete']['montant'], 'dansBrut' => $parametre->isBrutInclutAnciennete()];
        }
        $dansBrut = array_column($calcul['compositionBrut'], 'libelle');
        foreach ($gainsRubriques as $g) {
            $gains[] = ['code' => $g['code'], 'libelle' => $g['libelle'], 'montant' => $g['montant'], 'dansBrut' => in_array($g['libelle'], $dansBrut, true)];
        }
        $totalGains = '0';
        foreach ($gains as $g) {
            $totalGains = bcadd($totalGains, $g['montant'], 2);
        }

        $personnes = 1 + $entree['enfants'] + (SituationFamiliale::MARIE === $entree['situation'] ? 1 : 0);
        $horsBrut = bcsub($totalGains, $calcul['brut'], 2);
        $bulletin = $this->calcul->calculerCharges(
            $parametre,
            $this->cotisations->duPays($pays, true),
            $this->cotisations->tranches($pays),
            $this->cotisations->reductions($pays),
            $calcul['brut'],
            $calcul['brutImposable'],
            $calcul['parts'],
            $personnes,
            bccomp($horsBrut, '0', 2) > 0 ? $horsBrut : '0',
            $retenues,
            $societe->getTauxAccidentTravail(),
            $entree['national'],
        );

        return [
            'calcul' => $calcul,
            'bulletin' => $bulletin,
            'gains' => $gains,
            'retenues' => $lignesRetenues,
            'totalGains' => $totalGains,
            'coutEmployeur' => bcadd($totalGains, $bulletin['totalPatronal'], 2),
        ];
    }
}
