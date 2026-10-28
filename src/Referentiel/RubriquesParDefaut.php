<?php

namespace App\Referentiel;

use App\Enum\SectionBulletin as S;
use App\Enum\TraitementFiscal as F;
use App\Enum\TraitementSocial as So;
use App\Enum\TypeRubrique as T;

/**
 * Rubriques de paie proposées par défaut, par pays (code ISO).
 *
 * - CI : reprise de la table rubrique_salarie de la v1 (Côte d'Ivoire Paie).
 *   Codes en double ou absents dans la v1, complétés ici et À VÉRIFIER :
 *   086B (Prime informatique, 086 déjà pris), 090B (Indemnité de communication, 090 déjà pris),
 *   V21 à V28 (rubriques sans code dans la v1).
 * - SN, MA, FR : jeux ILLUSTRATIFS pour la démonstration, à faire valider
 *   par un spécialiste de la paie du pays avant toute utilisation réelle.
 *
 * Format : [code, libellé, type, section, traitement social, traitement fiscal, plafond % (exonération),
 *           attribuable comme avantage fixe, saisissable comme élément variable (par défaut : oui),
 *           coefficient horaire des heures supplémentaires (facultatif, coefficients de la v1),
 *           élément fixe au prorata des jours travaillés (par défaut : oui)]
 */
final class RubriquesParDefaut
{
    /** @return array<string, list<array{0: string, 1: string, 2: T, 3: S, 4: So, 5: F, 6: ?string, 7: bool}>> */
    public static function parPays(): array
    {
        return [
            'CI' => [
                ['648', 'Allocations familiales complémentaires', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['090', 'Domesticité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', true],
                ['091', 'Frais de carburant', T::GAIN, S::NON_SOUMIS, So::IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['078', 'Frais de communication', T::GAIN, S::NON_SOUMIS, So::IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['086', 'Indemnité d\'expatriation', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['085', 'Indemnité de logement', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', true],
                ['076', 'Indemnité de représentation', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', true],
                ['065', 'Indemnité kilométrique', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['067', 'Indemnité de responsabilité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', true],
                ['092', 'Indemnité de transport imposable', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['645', 'Indemnité de transport non imposable', T::GAIN, S::NON_SOUMIS, So::IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['746', 'Mutuelle sociale entreprise', T::RETENUE, S::COTISATIONS, So::CHARGE_SALARIALE, F::CHARGE_SALARIALE, null, false],
                ['841', 'Retenue prêt scolaire', T::RETENUE, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['077', 'Prime de caisse', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['087', 'Prime de sujétion', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['086B', 'Prime informatique', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['737', 'Véhicule réformé', T::RETENUE, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                // Retenue fixe mensuelle due en entier, sans prorata des jours (code à valider : absent de la v1).
                ['750', 'Cotisation syndicale', T::RETENUE, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true, true, null, false],
                // Le sursalaire a son propre champ dans la rémunération : pas proposé comme avantage.
                ['0012', 'Sursalaire', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false, false],
                ['090B', 'Indemnité de communication', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['5999', 'Retenue mutuelle interne', T::RETENUE, S::NON_SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['V21', 'Prime de logement', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::IMPOSABLE, null, true],
                ['V22', 'Indemnité compensatrice', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['0090', 'Prime de valorisation', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['V24', 'Indemnité complémentaire', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['V25', 'Prime risque pays', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['V26', 'Prime internationale', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['2071', 'Prime de salissure', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['2072', 'Prime de tenue', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                // Rubriques ponctuelles (éléments variables du mois) : codes de l'état des rubriques de la v1
                // (00014 → 0014…), qui fixent leur place sur le bulletin. Traitements À VÉRIFIER.
                ['0014', 'Heures supplémentaires à 15 %', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false, true, '1.15'],
                ['0015', 'Heures supplémentaires à 50 %', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false, true, '1.5'],
                ['0016', 'Heures supplémentaires à 75 %', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false, true, '1.75'],
                ['0017', 'Heures supplémentaires à 100 %', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false, true, '2'],
                ['0028', 'Indemnité de licenciement imposable', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0030', 'Prime de précarité imposable', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0031', 'Indemnité de dommages et intérêts', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['0032', 'Indemnité de départ à la retraite imposable', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0037', 'Prime de fidélité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0050', 'Rappel de salaire', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0051', 'Rappel de prime d\'ancienneté', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0063', 'Prime de fin d\'année', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0064', 'Bonus', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0066', 'Indemnité de préavis', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0068', 'Indemnité compensatrice de congés', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0071', 'Indemnité de décès imposable', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0072', 'Indemnité de décès non imposable', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['0074', 'Rappel d\'indemnité de responsabilité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', false],
                ['0083', 'Congés payés', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0088', 'Régularisation d\'heures supplémentaires', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0093', 'Rappel de domesticité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', false],
                ['0100', 'Rappel d\'indemnité de logement', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::EXONERE_PLAFOND, '10', false],
                ['0101', 'Rappel de prime de caisse', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['0632', 'Rappel d\'indemnité de transport non imposable', T::GAIN, S::NON_SOUMIS, So::IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['2029', 'Indemnité de licenciement non imposable', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['2031', 'Prime de précarité non imposable', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['2033', 'Indemnité de départ à la retraite non imposable', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['2067', 'Préavis non exécuté', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['2073', 'Prime de panier', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, false],
                ['823', 'Acompte sur salaire', T::RETENUE, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
                ['2070', 'Avance sur salaire', T::RETENUE, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, false],
            ],
            'SN' => [
                ['085', 'Indemnité de logement', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['645', 'Indemnité de transport', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['067', 'Prime de responsabilité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['076', 'Indemnité de représentation', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['P10', 'Prime de panier', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
            ],
            'MA' => [
                ['085', 'Indemnité de logement', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['645', 'Indemnité de transport', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['067', 'Prime de responsabilité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['P10', 'Indemnité de panier', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['P20', 'Prime d\'ancienneté', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
            ],
            'FR' => [
                ['AN10', 'Véhicule de fonction (avantage en nature)', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['TR10', 'Titres-restaurant (part patronale)', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['TP10', 'Prise en charge transport (50 %)', T::GAIN, S::NON_SOUMIS, So::NON_IMPOSABLE, F::NON_IMPOSABLE, null, true],
                ['067', 'Prime de responsabilité', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
                ['P20', 'Prime d\'ancienneté', T::GAIN, S::SOUMIS, So::IMPOSABLE, F::IMPOSABLE, null, true],
            ],
        ];
    }
}
