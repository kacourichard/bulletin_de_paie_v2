<?php

namespace App\Enum;

/** Base de calcul d'une cotisation. */
enum AssietteCotisation: string
{
    case BRUT = 'BRUT';
    case BRUT_IMPOSABLE = 'BRUT_IMPOSABLE';
    /** Montant unitaire × personnes couvertes (salarié, conjoint, enfants) — ex. CMU. */
    case FORFAIT_PERSONNES = 'FORFAIT_PERSONNES';

    public function libelle(): string
    {
        return match ($this) {
            self::BRUT => 'Salaire brut',
            self::BRUT_IMPOSABLE => 'Brut imposable',
            self::FORFAIT_PERSONNES => 'Forfait par personne couverte (salarié, conjoint, enfants)',
        };
    }
}
