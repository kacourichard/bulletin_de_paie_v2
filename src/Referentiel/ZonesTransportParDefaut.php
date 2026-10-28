<?php

namespace App\Referentiel;

/**
 * Indemnité de transport par zone de résidence (Côte d'Ivoire : table v1 prime_transport, base coted420_cipaie).
 * [libellé, montant mensuel, code de la rubrique].
 */
final class ZonesTransportParDefaut
{
    /** @return array<string, list<array{0: string, 1: string, 2: string}>> */
    public static function parPays(): array
    {
        return [
            'CI' => [
                ['Abidjan', '30000', '645'],
                ['Bouaké', '24000', '645'],
                ['Autres villes', '20000', '645'],
                ['Hors zone', '0', '645'],
            ],
        ];
    }
}
