<?php

namespace App\HeuresSup;

/**
 * Moteur de calcul des heures supplémentaires, repris à l'identique du projet heures-sup
 * (src/Domain/Overtime/OvertimeCalculator.php) ; seul le détail par jour est ajouté.
 *
 * Par salarié, sur la période pointée, jours triés par date :
 * - jour férié chômé et payé : hors quota ; heures au-delà de la journée normale (8 h / 8 h 30) à 75 %,
 *   100 % la nuit en régime 42 h ; « jour férié travaillé » si au moins 11 h ;
 * - autres jours : cumul dans le quota mensuel (173 h / 182 h) ; au-delà, les 6 premières heures à 15 %,
 *   puis 50 % (75 % la nuit en 42 h), ou 75 % le dimanche et les fériés simples (100 % la nuit en 42 h) ;
 * - prime de panier : chaque jour d'au moins 11 h.
 */
final class CalculHeuresSup
{
    private const TRANCHE_15 = 6.0;

    public function __construct(
        private readonly float $seuilPanier = 11.0,
    ) {
    }

    /** @param JourTravaille[] $jours */
    public function calculer(array $jours, RegimeHoraire $regime): ResultatHeuresSup
    {
        usort($jours, static fn (JourTravaille $a, JourTravaille $b) => $a->date <=> $b->date);

        $res = new ResultatHeuresSup();
        $quota = $regime->quotaMensuel();
        $normalJour = $regime->heuresNormalesJour();

        $cumulQuota = 0.0;      // heures cumulées hors fériés chômés payés
        $cumulDepassement = 0.0; // heures déjà réparties au-delà du quota
        $reste15 = self::TRANCHE_15;

        foreach ($jours as $j) {
            $heures = max(0.0, $j->heures);
            $res->heuresTravaillees += $heures;
            $detail = ['date' => $j->date, 'heures' => $heures, 'nature' => $j->nature, 'dimanche' => $j->dimanche, 'nuit' => $j->nuit,
                'cumulQuota' => null, 'hs15' => 0.0, 'hs50' => 0.0, 'hs75' => 0.0, 'hs100' => 0.0, 'panier' => $heures >= $this->seuilPanier];

            if ($heures >= $this->seuilPanier) {
                ++$res->joursPanier;
            }

            // 1) Férié chômé et payé : excédent sur la journée normale à 75 / 100 %, hors quota.
            if (NatureJour::FERIE_CHOME_PAYE === $j->nature) {
                if ($heures >= $this->seuilPanier) {
                    ++$res->joursFeriesTravailles;
                }
                $excedent = max(0.0, $heures - $normalJour);
                if ($excedent > 0.0) {
                    $taux = RegimeHoraire::H42 === $regime && $j->nuit ? 'hs100' : 'hs75';
                    $res->$taux += $excedent;
                    $detail[$taux] += $excedent;
                }
                $res->jours[] = $detail;
                continue;
            }

            // 2) Férié simple : la majoration dès la journée normale reste inactive, comme dans heures-sup
            //    (règle non validée par le métier).

            // 3) Quota mensuel
            $cumulQuota += $heures;
            $res->heuresQuota = $cumulQuota;
            $detail['cumulQuota'] = $cumulQuota;

            $depassementDuJour = max(0.0, $cumulQuota - $quota) - $cumulDepassement;
            if ($cumulQuota <= $quota || $depassementDuJour <= 0.0) {
                $res->jours[] = $detail;
                continue;
            }

            // 3-a) Les 6 premières heures au-delà du quota : 15 %.
            $a15 = min($depassementDuJour, $reste15);
            if ($a15 > 0.0) {
                $res->hs15 += $a15;
                $detail['hs15'] += $a15;
                $reste15 -= $a15;
                $depassementDuJour -= $a15;
                $cumulDepassement += $a15;
            }

            // 3-b) Au-delà : 50 / 75 / 100 % selon le jour et la nuit.
            if ($depassementDuJour > 0.0) {
                $cumulDepassement += $depassementDuJour;
                $special = $j->dimanche || NatureJour::FERIE_SIMPLE === $j->nature;
                $taux = RegimeHoraire::H40 === $regime
                    ? ($special ? 'hs75' : 'hs50')
                    : ($special ? ($j->nuit ? 'hs100' : 'hs75') : ($j->nuit ? 'hs75' : 'hs50'));
                $res->$taux += $depassementDuJour;
                $detail[$taux] += $depassementDuJour;
            }
            $res->jours[] = $detail;
        }

        $res->heuresTravaillees = round($res->heuresTravaillees, 2);
        $res->heuresQuota = round($res->heuresQuota, 2);
        foreach (['hs15', 'hs50', 'hs75', 'hs100'] as $taux) {
            $res->$taux = round($res->$taux, 2);
        }

        return $res;
    }
}
