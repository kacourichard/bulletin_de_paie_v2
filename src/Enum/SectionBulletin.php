<?php

namespace App\Enum;

/**
 * Emplacement de la rubrique sur le bulletin (la « position » de la v1) :
 * 1 = éléments soumis avant cotisations, 2 = cotisations salariales, 3 = éléments après le net imposable.
 */
enum SectionBulletin: int
{
    case SOUMIS = 1;
    case COTISATIONS = 2;
    case NON_SOUMIS = 3;

    public function libelle(): string
    {
        return match ($this) {
            self::SOUMIS => '1 — Éléments soumis (brut imposable)',
            self::COTISATIONS => '2 — Cotisations salariales',
            self::NON_SOUMIS => '3 — Éléments non soumis',
        };
    }
}
