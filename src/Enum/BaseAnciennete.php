<?php

namespace App\Enum;

/** Assiette de la prime d'ancienneté. */
enum BaseAnciennete: string
{
    case CATEGORIEL = 'CATEGORIEL';
    case CATEGORIEL_SURSALAIRE = 'CATEGORIEL_SURSALAIRE';

    public function libelle(): string
    {
        return match ($this) {
            self::CATEGORIEL => 'Salaire catégoriel',
            self::CATEGORIEL_SURSALAIRE => 'Salaire catégoriel + sursalaire',
        };
    }
}
