<?php

namespace App\Enum;

/** Nature du contrat de travail. */
enum TypeContrat: string
{
    case CDI = 'CDI';
    case CDD = 'CDD';
    case STAGE = 'STAGE';
    case APPRENTISSAGE = 'APPRENTISSAGE';
    case JOURNALIER = 'JOURNALIER';
    case CONSULTANT = 'CONSULTANT';

    public function libelle(): string
    {
        return match ($this) {
            self::CDI => 'CDI — durée indéterminée',
            self::CDD => 'CDD — durée déterminée',
            self::STAGE => 'Stage',
            self::APPRENTISSAGE => 'Apprentissage',
            self::JOURNALIER => 'Journalier / temporaire',
            self::CONSULTANT => 'Consultant / prestataire',
        };
    }
}
