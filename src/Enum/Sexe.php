<?php

namespace App\Enum;

/** Sexe du salarié. */
enum Sexe: string
{
    case M = 'M';
    case F = 'F';

    public function libelle(): string
    {
        return match ($this) {
            self::M => 'Homme',
            self::F => 'Femme',
        };
    }
}
