<?php

namespace App\Service;

use App\Entity\Cotisation;
use App\Entity\ParametrePaie;
use App\Entity\ReductionImpot;
use App\Entity\TrancheImpot;
use App\Enum\AssietteCotisation;
use App\Enum\BaseAnciennete;
use App\Enum\CategorieCotisation;
use App\Enum\ModeExoneration;
use App\Enum\SectionBulletin;
use App\Enum\SituationFamiliale;
use App\Enum\TraitementFiscal;
use App\Repository\ParametrePaieRepository;

/**
 * Moteur de calcul de la première partie du bulletin, selon les paramètres du pays :
 * prime d'ancienneté, nombre de parts, salaire brut, brut imposable.
 *
 * Entrée (indépendante des entités, pour servir aussi au simulateur) :
 *  - salaireCategoriel, sursalaire (chaînes décimales)
 *  - dateEmbauche, dateReference (\DateTimeInterface)
 *  - situation (?SituationFamiliale), enfants (int)
 *  - gains : list<array{code: ?string, libelle: string, montant: string, section: SectionBulletin, fiscal: TraitementFiscal, plafond: ?string}>
 *    (avantages et éléments variables de gain, hors salaire catégoriel et sursalaire)
 */
class CalculPaie
{
    /**
     * @return array{
     *   anciennete: array{annees: int, taux: string, base: string, montant: string, applicable: bool, code: string},
     *   parts: ?string,
     *   brut: string, compositionBrut: list<array{libelle: string, montant: string}>,
     *   exoneration: string, nonImposable: string, brutImposable: string, detailExoneration: string
     * }
     */
    public function calculer(ParametrePaie $p, array $grilleParts, array $entree): array
    {
        $d = $p->getDecimales();
        $categoriel = $entree['salaireCategoriel'] ?? '0';
        $sursalaire = $entree['sursalaire'] ?? '0';

        // 1. Prime d'ancienneté
        $annees = $entree['dateEmbauche'] && $entree['dateReference'] >= $entree['dateEmbauche']
            ? $entree['dateEmbauche']->diff($entree['dateReference'])->y
            : 0;
        $taux = $p->tauxAnciennete($annees);
        $base = BaseAnciennete::CATEGORIEL_SURSALAIRE === $p->getAncienneteBase() ? bcadd($categoriel, $sursalaire, 2) : $categoriel;
        $montantAnciennete = $this->arrondir(bcdiv(bcmul($base, $taux, 6), '100', 6), $d);
        $anciennete = [
            'annees' => $annees,
            'taux' => $taux,
            'base' => $base,
            'montant' => $montantAnciennete,
            'applicable' => bccomp($montantAnciennete, '0', 2) > 0,
            'code' => $p->getAncienneteCode(),
        ];

        // 2. Nombre de parts
        $parts = $p->isPartsActives()
            ? ParametrePaieRepository::partsDepuisGrille($grilleParts, $entree['situation'] ?? null, (int) ($entree['enfants'] ?? 0))
            : null;

        // 3. Salaire brut
        $composition = [['libelle' => 'Salaire catégoriel', 'montant' => $categoriel]];
        if ($p->isBrutInclutSursalaire() && bccomp($sursalaire, '0', 2) > 0) {
            $composition[] = ['libelle' => 'Sursalaire', 'montant' => $sursalaire];
        }
        if ($p->isBrutInclutAnciennete() && $anciennete['applicable']) {
            $composition[] = ['libelle' => sprintf('Prime d\'ancienneté (%s %%)', $this->nombre($taux)), 'montant' => $montantAnciennete];
        }
        $gainsDansBrut = [];
        foreach ($entree['gains'] ?? [] as $gain) {
            $soumis = SectionBulletin::NON_SOUMIS !== $gain['section'];
            if (($soumis && $p->isBrutInclutGainsSoumis()) || (!$soumis && $p->isBrutInclutGainsNonSoumis())) {
                $gainsDansBrut[] = $gain;
                $composition[] = ['libelle' => $gain['libelle'], 'montant' => $gain['montant']];
            }
        }
        $brut = '0';
        foreach ($composition as $ligne) {
            $brut = bcadd($brut, $ligne['montant'], 2);
        }
        $brut = $this->arrondir($brut, $d);

        // 4. Brut imposable : brut − gains non imposables − exonération plafonnée
        $nonImposable = '0';
        $exonerables = [];
        foreach ($gainsDansBrut as $gain) {
            if (TraitementFiscal::NON_IMPOSABLE === $gain['fiscal']) {
                $nonImposable = bcadd($nonImposable, $gain['montant'], 2);
            } elseif (TraitementFiscal::EXONERE_PLAFOND === $gain['fiscal']) {
                $plafond = $gain['plafond'] ?? '0';
                $exonerables[$plafond] = bcadd($exonerables[$plafond] ?? '0', $gain['montant'], 2);
            }
        }

        $exoneration = '0';
        $details = [];
        foreach ($exonerables as $plafond => $total) {
            $limite = bcdiv(bcmul($brut, (string) $plafond, 6), '100', 6);
            $exo = ModeExoneration::FORFAITAIRE === $p->getModeExoneration() ? $limite : (bccomp($total, $limite, 6) < 0 ? $total : $limite);
            $exoneration = bcadd($exoneration, $exo, 6);
            $details[] = ModeExoneration::FORFAITAIRE === $p->getModeExoneration()
                ? sprintf('%s %% du brut (forfait)', $this->nombre($plafond))
                : sprintf('min(indemnités %s ; %s %% du brut = %s)', $this->nombre($total), $this->nombre($plafond), $this->nombre($this->arrondir($limite, $d)));
        }
        $exoneration = $this->arrondir($exoneration, $d);
        $brutImposable = $this->arrondir(bcsub(bcsub($brut, $nonImposable, 2), $exoneration, 2), $d);

        return [
            'anciennete' => $anciennete,
            'parts' => $parts,
            'brut' => $brut,
            'compositionBrut' => $composition,
            'nonImposable' => $this->arrondir($nonImposable, $d),
            'exoneration' => $exoneration,
            'detailExoneration' => implode(' + ', $details),
            'brutImposable' => bccomp($brutImposable, '0', 2) < 0 ? '0' : $brutImposable,
        ];
    }

    /**
     * 2e partie du bulletin : impôt sur le salaire, cotisations salariales et patronales, net.
     *
     * @param Cotisation[]     $cotisations
     * @param TrancheImpot[]   $tranches
     * @param ReductionImpot[] $reductions
     * @param string           $gainsHorsBrut gains versés en plus du brut (ex. transport non soumis)
     * @param string           $retenues      retenues des rubriques (acompte, avance...)
     * @param ?string          $tauxSociete   taux accident du travail de la société (cotisations « taux de la société »)
     * @param bool             $national      salarié de la nationalité du pays (sinon taux patronal non-nationaux)
     */
    public function calculerCharges(ParametrePaie $p, array $cotisations, array $tranches, array $reductions, string $brut, string $brutImposable, ?string $parts, int $personnes, string $gainsHorsBrut, string $retenues, ?string $tauxSociete = null, bool $national = true): array
    {
        $d = $p->getDecimales();
        $assiette = fn (AssietteCotisation $a, ?string $plafond, ?string $forfait) => match ($a) {
            AssietteCotisation::BRUT => null !== $plafond && bccomp($brut, $plafond, 2) > 0 ? $plafond : $brut,
            AssietteCotisation::BRUT_IMPOSABLE => null !== $plafond && bccomp($brutImposable, $plafond, 2) > 0 ? $plafond : $brutImposable,
            AssietteCotisation::FORFAIT_PERSONNES => bcmul($forfait ?? '0', (string) $personnes, 2),
        };

        // Impôt sur le salaire : barème progressif mensuel, moins la réduction liée aux parts.
        $impot = null;
        if ($p->isImpotActif()) {
            $base = $assiette($p->getImpotAssiette(), null, null);
            $brutImpot = '0';
            foreach ($tranches as $tranche) {
                $haut = null === $tranche->getA() || bccomp($base, $tranche->getA(), 2) < 0 ? $base : $tranche->getA();
                $portion = bcsub($haut, $tranche->getDe(), 2);
                if (bccomp($portion, '0', 2) > 0) {
                    $brutImpot = bcadd($brutImpot, bcdiv(bcmul($portion, $tranche->getTaux(), 6), '100', 6), 6);
                }
            }
            $reduction = '0';
            foreach ($reductions as $r) {
                if (null !== $parts && bccomp($r->getParts(), $parts, 2) <= 0) {
                    $reduction = $r->getMontant();
                }
            }
            $montant = bcsub($this->arrondir($brutImpot, $d), $reduction, 2);
            $impot = ['code' => $p->getImpotCode(), 'libelle' => $p->getImpotLibelle(), 'base' => $base, 'brut' => $this->arrondir($brutImpot, $d), 'reduction' => $reduction, 'montant' => bccomp($montant, '0', 2) < 0 ? '0.00' : $montant];
        }

        $salariales = [];
        $patronales = [];
        $total = ['salarial' => $impot['montant'] ?? '0', 'fiscal' => '0', 'social' => '0'];
        foreach ($cotisations as $c) {
            $base = $assiette($c->getAssiette(), $c->getPlafond(), $c->getForfaitUnitaire());
            if (null !== $c->getTauxSalarial()) {
                $montant = $this->arrondir(bcdiv(bcmul($base, $c->getTauxSalarial(), 6), '100', 6), $d);
                $salariales[] = ['code' => $c->getCodeSalarial(), 'libelle' => $c->getLibelle(), 'assiette' => $c->getAssiette(), 'base' => $base, 'taux' => $c->getTauxSalarial(), 'montant' => $montant];
                $total['salarial'] = bcadd($total['salarial'], $montant, 2);
            }
            $tauxPatronal = $c->tauxPatronalPour($national, $tauxSociete);
            if (null !== $tauxPatronal) {
                $montant = $this->arrondir(bcdiv(bcmul($base, $tauxPatronal, 6), '100', 6), $d);
                $fiscale = CategorieCotisation::FISCALE === $c->getCategorie();
                $patronales[$fiscale ? 'fiscales' : 'sociales'][] = ['code' => $c->getCodePatronal(), 'libelle' => $c->getLibelle(), 'base' => $base, 'taux' => $tauxPatronal, 'montant' => $montant];
                $total[$fiscale ? 'fiscal' : 'social'] = bcadd($total[$fiscale ? 'fiscal' : 'social'], $montant, 2);
            }
        }

        $salaireNet = bcsub($brut, $total['salarial'], 2);
        // Net à payer arrondi selon le pays ; arrondi > 0 = retenue, < 0 = gain (comme en v1).
        $netExact = bcsub(bcadd($salaireNet, $gainsHorsBrut, 2), $retenues, 2);
        $netAPayer = $p->getArrondiNet()->appliquer($netExact);

        return [
            'impot' => $impot,
            'salariales' => $salariales,
            'patronalesFiscales' => $patronales['fiscales'] ?? [],
            'patronalesSociales' => $patronales['sociales'] ?? [],
            'totalSalarial' => $total['salarial'],
            'totalPatronalFiscal' => $total['fiscal'],
            'totalPatronalSocial' => $total['social'],
            'totalPatronal' => bcadd($total['fiscal'], $total['social'], 2),
            'salaireNet' => $salaireNet,
            'gainsHorsBrut' => $gainsHorsBrut,
            'retenues' => $retenues,
            'arrondi' => bcsub($netExact, $netAPayer, 2),
            'netAPayer' => $netAPayer,
            'personnes' => $personnes,
        ];
    }

    private function arrondir(string $valeur, int $decimales): string
    {
        return self::arrondi($valeur, $decimales);
    }

    /** Arrondi au plus proche (demi vers le haut) au nombre de décimales demandé, rendu sur 2 décimales. */
    public static function arrondi(string $valeur, int $decimales): string
    {
        $demi = '0.'.str_repeat('0', $decimales).'5';
        $resultat = bccomp($valeur, '0', 6) >= 0 ? bcadd($valeur, $demi, $decimales) : bcsub($valeur, $demi, $decimales);

        return bcadd($resultat, '0', 2);
    }

    private function nombre(string $valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',');
    }
}
