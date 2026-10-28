<?php

namespace App\Enum;

/** Soumission aux cotisations sociales. */
enum TraitementSocial: string
{
    case IMPOSABLE = 'IMPOSABLE';
    case NON_IMPOSABLE = 'NON_IMPOSABLE';
    case CHARGE_SALARIALE = 'CHARGE_SALARIALE';

    public function libelle(): string
    {
        return match ($this) {
            self::IMPOSABLE => 'Soumis',
            self::NON_IMPOSABLE => 'Non soumis',
            self::CHARGE_SALARIALE => 'Charge salariale',
        };
    }
}
