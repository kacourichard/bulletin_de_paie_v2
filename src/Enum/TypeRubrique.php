<?php

namespace App\Enum;

/** Une rubrique ajoute au salaire (gain) ou en retire (retenue). */
enum TypeRubrique: string
{
    case GAIN = 'GAIN';
    case RETENUE = 'RETENUE';

    public function libelle(): string
    {
        return match ($this) {
            self::GAIN => 'Gain',
            self::RETENUE => 'Retenue',
        };
    }
}
