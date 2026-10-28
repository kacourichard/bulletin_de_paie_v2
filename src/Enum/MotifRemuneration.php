<?php

namespace App\Enum;

/** Raison d'un changement de rémunération. */
enum MotifRemuneration: string
{
    case EMBAUCHE = 'EMBAUCHE';
    case REVALORISATION = 'REVALORISATION';
    case PROMOTION = 'PROMOTION';
    case AJUSTEMENT = 'AJUSTEMENT';
    case TRANSFERT = 'TRANSFERT';
    case CHANGEMENT_POSTE = 'CHANGEMENT_POSTE';

    public function libelle(): string
    {
        return match ($this) {
            self::EMBAUCHE => 'Embauche',
            self::REVALORISATION => 'Revalorisation',
            self::PROMOTION => 'Promotion',
            self::AJUSTEMENT => 'Ajustement conventionnel',
            self::TRANSFERT => 'Transfert',
            self::CHANGEMENT_POSTE => 'Changement de poste',
        };
    }
}
