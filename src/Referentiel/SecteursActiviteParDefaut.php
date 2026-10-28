<?php

namespace App\Referentiel;

/**
 * Secteurs d'activité et taux de risque accident du travail (%), par pays (code ISO).
 *
 * - CI : reprise de la table secteur_activite de la v1 (taux CNPS par secteur).
 *
 * Format : [libellé, taux de risque %]
 */
final class SecteursActiviteParDefaut
{
    /** @return array<string, list<array{0: string, 1: string}>> */
    public static function parPays(): array
    {
        return [
            'CI' => [
                ['MÉCANIQUE GÉNÉRALE', '3'],
                ['INDUSTRIES EXTRACTIVES ET PROSPECTION', '4'],
                ['MINIÈRE', '5'],
                ['INDUSTRIES ALIMENTAIRES', '4'],
                ['INDUSTRIES DES CORPS GRAS', '4'],
                ['INDUSTRIES CHIMIQUES', '4'],
                ['AUTRES INDUSTRIES', '3'],
                ['TRANSPORT', '3'],
                ['BOIS', '3'],
                ['TEXTILE', '3'],
                ['TRANSFORMATION DU THON', '2'],
                ['POLYGRAPHIQUE', '3'],
                ['HÔTELLERIE ET TOURISME', '3'],
                ['PRODUCTION AGRICOLE', '3'],
                ['SUCRE', '3'],
                ['AUXILIAIRES DE TRANSPORT', '5'],
                ['BÂTIMENT - TRAVAUX PUBLICS ET ACTIVITÉS CONNEXES', '5'],
                ['COMMERCE - NÉGOCE - PROFESSIONS LIBÉRALES', '2'],
                ['AGRICOLE - ÉLEVAGE - FORESTIER', '2'],
                ['BANQUES', '2'],
                ['ASSURANCES', '5'],
                ['ENTREPRISES PÉTROLIÈRES', '2'],
                ['INSTITUTS DE RECHERCHE', '2'],
                ['TRANSPORT DE FONDS ET VALEURS', '2'],
                ['SÉCURITÉ PRIVÉE', '2'],
                ['DOCKERS', '2'],
                ['GENS DE MAISON', '2'],
                ['ENTREPRISES PÉTROLIÈRES (DISTRIBUTION)', '3'],
                ['IMPRIMERIE ET SERVICES', '2'],
            ],
        ];
    }
}
