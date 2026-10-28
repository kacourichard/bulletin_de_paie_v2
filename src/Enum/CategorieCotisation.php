<?php

namespace App\Enum;

/** Regroupement des charges sur le bulletin. */
enum CategorieCotisation: string
{
    case SOCIALE = 'SOCIALE';
    case FISCALE = 'FISCALE';

    public function libelle(): string
    {
        return match ($this) {
            self::SOCIALE => 'Charge sociale',
            self::FISCALE => 'Charge fiscale',
        };
    }
}
