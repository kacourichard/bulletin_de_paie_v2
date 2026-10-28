<?php

namespace App\Enum;

/** Moyen de paiement du salaire. */
enum ModePaiement: string
{
    case VIREMENT = 'VIREMENT';
    case MOBILE_MONEY = 'MOBILE_MONEY';
    case CHEQUE = 'CHEQUE';
    case ESPECES = 'ESPECES';

    public function libelle(): string
    {
        return match ($this) {
            self::VIREMENT => 'Virement bancaire',
            self::MOBILE_MONEY => 'Mobile money',
            self::CHEQUE => 'Chèque',
            self::ESPECES => 'Espèces',
        };
    }
}
