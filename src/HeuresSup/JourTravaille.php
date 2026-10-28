<?php

namespace App\HeuresSup;

/** Une journée pointée d'un salarié (heures du jour cumulées, repris de heures-sup : WorkDayRow). */
final class JourTravaille
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly float $heures,
        public readonly bool $dimanche,
        public readonly NatureJour $nature,
        public readonly bool $nuit,
    ) {
    }
}
