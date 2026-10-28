<?php

namespace App\Enum;

/**
 * Traitement des indemnités « exonérées dans la limite de X % du brut » pour le brut imposable.
 */
enum ModeExoneration: string
{
    /** Exonération = min(total de ces indemnités, X % du brut). */
    case PLAFONNEE = 'PLAFONNEE';
    /** Abattement forfaitaire de X % du brut dès qu'une telle indemnité existe (calcul de la v1). */
    case FORFAITAIRE = 'FORFAITAIRE';

    public function libelle(): string
    {
        return match ($this) {
            self::PLAFONNEE => 'Plafonnée : montant des indemnités, dans la limite de X % du brut',
            self::FORFAITAIRE => 'Forfaitaire : X % du brut dès qu\'une indemnité exonérable existe (calcul v1)',
        };
    }
}
