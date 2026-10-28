<?php

namespace App\HeuresSup;

/** Résultat du calcul pour un salarié sur la période (repris de heures-sup : OvertimeResult). */
final class ResultatHeuresSup
{
    /** Jours d'au moins 11 h travaillées (prime de panier). */
    public int $joursPanier = 0;
    /** Jours fériés chômés et payés travaillés au moins 11 h. */
    public int $joursFeriesTravailles = 0;

    public float $heuresTravaillees = 0.0;
    /** Heures comptées dans le quota mensuel (hors fériés chômés payés). */
    public float $heuresQuota = 0.0;

    public float $hs15 = 0.0;
    public float $hs50 = 0.0;
    public float $hs75 = 0.0;
    public float $hs100 = 0.0;

    /**
     * Détail par jour, pour contrôle : heures supplémentaires attribuées à chaque journée.
     *
     * @var list<array{date: \DateTimeImmutable, heures: float, nature: NatureJour, dimanche: bool, nuit: bool, cumulQuota: ?float, hs15: float, hs50: float, hs75: float, hs100: float, panier: bool}>
     */
    public array $jours = [];

    public function totalHeuresSup(): float
    {
        return round($this->hs15 + $this->hs50 + $this->hs75 + $this->hs100, 2);
    }
}
