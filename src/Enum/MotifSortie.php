<?php

namespace App\Enum;

/** Raison de la fin d'une affectation dans une société. */
enum MotifSortie: string
{
    case DEMISSION = 'DEMISSION';
    case LICENCIEMENT = 'LICENCIEMENT';
    case FIN_CONTRAT = 'FIN_CONTRAT';
    case RUPTURE_CONVENTIONNELLE = 'RUPTURE_CONVENTIONNELLE';
    case RETRAITE = 'RETRAITE';
    case DECES = 'DECES';
    case TRANSFERT = 'TRANSFERT';
    case AUTRE = 'AUTRE';

    public function libelle(): string
    {
        return match ($this) {
            self::DEMISSION => 'Démission',
            self::LICENCIEMENT => 'Licenciement',
            self::FIN_CONTRAT => 'Fin de contrat',
            self::RUPTURE_CONVENTIONNELLE => 'Rupture d\'un commun accord',
            self::RETRAITE => 'Départ à la retraite',
            self::DECES => 'Décès',
            self::TRANSFERT => 'Transfert vers une autre société',
            self::AUTRE => 'Autre',
        };
    }
}
