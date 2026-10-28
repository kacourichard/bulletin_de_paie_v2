<?php

namespace App\Enum;

/**
 * Arrondi du net à payer ; l'écart est porté sur le bulletin (ligne « Arrondi »).
 */
enum ArrondiNet: string
{
    case AUCUN = 'AUCUN';
    /** Unités 0 et 5 conservées, sinon dizaine la plus proche : 1-4 vers le bas, 6-9 vers le haut (calcul v1). */
    case CINQ_DIX = 'CINQ_DIX';

    public function libelle(): string
    {
        return match ($this) {
            self::AUCUN => 'Aucun : net à payer exact',
            self::CINQ_DIX => 'Au 5 ou à la dizaine : unités 0 et 5 conservées, 1-4 vers le bas, 6-9 vers le haut (calcul v1)',
        };
    }

    /** Net arrondi (montant entier, en chaîne décimale). */
    public function appliquer(string $net): string
    {
        if (self::AUCUN === $this) {
            return $net;
        }
        // Partie entière (comme intval en v1), arrondie en valeur absolue puis resignée.
        $negatif = bccomp($net, '0', 2) < 0;
        $absolu = ltrim(bcadd($net, '0', 0), '-');
        $unite = (int) substr($absolu, -1);
        $arrondi = match (true) {
            0 === $unite || 5 === $unite => $absolu,
            $unite < 5 => bcsub($absolu, (string) $unite, 0),
            default => bcadd($absolu, (string) (10 - $unite), 0),
        };

        return bcadd($negatif ? '-'.$arrondi : $arrondi, '0', 2);
    }
}
