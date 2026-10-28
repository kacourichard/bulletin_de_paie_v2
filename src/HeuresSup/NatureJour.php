<?php

namespace App\HeuresSup;

/** Nature d'une journée pour le calcul (repris de heures-sup : HolidayKind). */
enum NatureJour: string
{
    case NORMAL = 'NORMAL';
    /** Férié non chômé : compte dans le quota, majoré comme un dimanche au-delà du quota. */
    case FERIE_SIMPLE = 'FERIE_SIMPLE';
    /** Férié chômé et payé : hors quota, heures au-delà de la journée normale majorées à 75 % (100 % la nuit en 42 h). */
    case FERIE_CHOME_PAYE = 'FERIE_CHOME_PAYE';

    public function libelle(): string
    {
        return match ($this) {
            self::NORMAL => '',
            self::FERIE_SIMPLE => 'Férié',
            self::FERIE_CHOME_PAYE => 'Férié chômé payé',
        };
    }
}
