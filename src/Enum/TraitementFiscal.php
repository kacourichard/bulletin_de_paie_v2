<?php

namespace App\Enum;

/** Traitement pour l'impôt sur le salaire. */
enum TraitementFiscal: string
{
    case IMPOSABLE = 'IMPOSABLE';
    case NON_IMPOSABLE = 'NON_IMPOSABLE';
    /** Exonéré dans la limite d'un pourcentage du brut (ex. 10 % en Côte d'Ivoire). */
    case EXONERE_PLAFOND = 'EXONERE_PLAFOND';
    case CHARGE_SALARIALE = 'CHARGE_SALARIALE';

    public function libelle(): string
    {
        return match ($this) {
            self::IMPOSABLE => 'Imposable',
            self::NON_IMPOSABLE => 'Non imposable',
            self::EXONERE_PLAFOND => 'Exonéré dans une limite du brut',
            self::CHARGE_SALARIALE => 'Charge salariale',
        };
    }
}
