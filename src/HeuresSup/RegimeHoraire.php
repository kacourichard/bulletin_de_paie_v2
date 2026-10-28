<?php

namespace App\HeuresSup;

/**
 * Régime horaire hebdomadaire (repris de heures-sup : CycleType).
 */
enum RegimeHoraire: string
{
    case H40 = '40H';
    case H42 = '42H';

    public function libelle(): string
    {
        return match ($this) {
            self::H40 => '40 h / semaine',
            self::H42 => '42 h / semaine',
        };
    }

    /** Heures normales du mois avant heures supplémentaires. */
    public function quotaMensuel(): float
    {
        return match ($this) {
            self::H40 => 173.0,
            self::H42 => 182.0,
        };
    }

    /** Heures normales d'une journée (jour férié chômé et payé). */
    public function heuresNormalesJour(): float
    {
        return match ($this) {
            self::H40 => 8.0,
            self::H42 => 8.5,
        };
    }

    /** Le travail de nuit n'est majoré différemment qu'en régime 42 h. */
    public function gereNuit(): bool
    {
        return self::H42 === $this;
    }

    /** Régime lu dans le libellé de l'horaire du pointage, ex. « … 12 Hours(42h) … » ; null si absent. */
    public static function depuisHoraire(?string $horaire): ?self
    {
        $s = trim((string) $horaire);
        if ('' === $s) {
            return null;
        }
        if (preg_match('/\(([^)]*?\d+\s*h[^)]*?)\)/i', $s, $m)) {
            $dedans = strtolower(str_replace(["\u{00A0}", ' '], '', $m[1]));
            if (str_contains($dedans, '42h')) {
                return self::H42;
            }
            if (str_contains($dedans, '40h')) {
                return self::H40;
            }
        }
        $s = strtolower(str_replace(["\u{00A0}", ' '], '', $s));

        return match (true) {
            str_contains($s, '42h') => self::H42,
            str_contains($s, '40h') => self::H40,
            default => null,
        };
    }
}
