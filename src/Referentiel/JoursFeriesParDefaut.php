<?php

namespace App\Referentiel;

/**
 * Jours fériés par défaut, par pays et par année : dates fixes, fêtes calculées depuis Pâques,
 * et fêtes musulmanes (dates estimées, marquées « à confirmer » chaque année selon l'annonce officielle).
 *
 * Côte d'Ivoire : tous chômés et payés ; Fête nationale avec veille et lendemain (règle reprise de heures-sup).
 * À faire valider par le métier.
 */
final class JoursFeriesParDefaut
{
    /** Fêtes musulmanes estimées (calendrier lunaire). */
    private const FETES_MUSULMANES_CI = [
        2026 => ['2026-03-17' => 'Lendemain de la Nuit du Destin', '2026-03-20' => 'Aïd el-Fitr (fin du Ramadan)', '2026-05-27' => 'Tabaski (Aïd el-Kébir)', '2026-08-26' => 'Lendemain de l\'anniversaire de la naissance du Prophète (Maouloud)'],
        2027 => ['2027-03-06' => 'Lendemain de la Nuit du Destin', '2027-03-10' => 'Aïd el-Fitr (fin du Ramadan)', '2027-05-17' => 'Tabaski (Aïd el-Kébir)', '2027-08-15' => 'Lendemain de l\'anniversaire de la naissance du Prophète (Maouloud)'],
    ];

    /** @return list<string> pays disponibles */
    public static function pays(): array
    {
        return ['CI'];
    }

    /**
     * @return list<array{date: string, libelle: string, chomePaye: bool, veille: bool, lendemain: bool, aConfirmer: bool}>
     */
    public static function pour(string $pays, int $annee): array
    {
        if ('CI' !== strtoupper($pays)) {
            return [];
        }
        $paques = self::paques($annee);
        $jour = static fn (string $date, string $libelle, bool $veille = false, bool $lendemain = false, bool $aConfirmer = false) => compact('date', 'libelle', 'veille', 'lendemain', 'aConfirmer') + ['chomePaye' => true];

        $liste = [
            $jour("$annee-01-01", 'Jour de l\'An'),
            $jour($paques->modify('+1 day')->format('Y-m-d'), 'Lundi de Pâques'),
            $jour("$annee-05-01", 'Fête du Travail'),
            $jour($paques->modify('+39 days')->format('Y-m-d'), 'Ascension'),
            $jour($paques->modify('+50 days')->format('Y-m-d'), 'Lundi de Pentecôte'),
            $jour("$annee-08-07", 'Fête nationale', true, true),
            $jour("$annee-08-15", 'Assomption'),
            $jour("$annee-11-01", 'Toussaint'),
            $jour("$annee-11-15", 'Journée nationale de la Paix'),
            $jour("$annee-12-25", 'Noël'),
        ];
        foreach (self::FETES_MUSULMANES_CI[$annee] ?? [] as $date => $libelle) {
            $liste[] = $jour($date, $libelle, aConfirmer: true);
        }
        usort($liste, static fn ($a, $b) => $a['date'] <=> $b['date']);

        return $liste;
    }

    /** Dimanche de Pâques (calendrier grégorien, algorithme de Meeus / Butcher). */
    public static function paques(int $annee): \DateTimeImmutable
    {
        $a = $annee % 19;
        $b = intdiv($annee, 100);
        $c = $annee % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%d-%02d-%02d', $annee, $mois, $jour));
    }
}
