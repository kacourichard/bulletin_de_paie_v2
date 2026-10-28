<?php

namespace App\Enum;

/** Situation familiale (utile au calcul de l'impôt selon les pays). */
enum SituationFamiliale: string
{
    case CELIBATAIRE = 'CELIBATAIRE';
    case MARIE = 'MARIE';
    case UNION_LIBRE = 'UNION_LIBRE';
    case DIVORCE = 'DIVORCE';
    case VEUF = 'VEUF';

    public function libelle(): string
    {
        return match ($this) {
            self::CELIBATAIRE => 'Célibataire',
            self::MARIE => 'Marié(e)',
            self::UNION_LIBRE => 'Union libre / PACS',
            self::DIVORCE => 'Divorcé(e)',
            self::VEUF => 'Veuf / veuve',
        };
    }
}
