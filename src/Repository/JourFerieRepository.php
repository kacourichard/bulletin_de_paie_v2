<?php

namespace App\Repository;

use App\Entity\JourFerie;
use App\Entity\Societe;
use App\HeuresSup\NatureJour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JourFerie>
 */
class JourFerieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourFerie::class);
    }

    /**
     * Jours fériés applicables à la société (nationaux du pays de paie + propres à la société) entre deux dates.
     *
     * @return JourFerie[]
     */
    public function pourSociete(Societe $societe, \DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.pays = :pays')->andWhere('j.societe IS NULL OR j.societe = :societe')
            ->andWhere('j.date BETWEEN :du AND :au')
            ->setParameter('pays', $societe->getPaysPaie())->setParameter('societe', $societe)
            ->setParameter('du', $du->setTime(0, 0))->setParameter('au', $au->setTime(0, 0))
            ->orderBy('j.date')
            ->getQuery()->getResult();
    }

    /**
     * Calendrier de calcul : nature de chaque date (veille et lendemain propagés), comme
     * HolidayCalendarFactory de heures-sup. Un férié chômé et payé l'emporte sur un férié simple.
     *
     * @return array{natures: array<string, NatureJour>, libelles: array<string, string>}
     */
    public function calendrier(Societe $societe, \DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        $natures = [];
        $libelles = [];
        $poser = static function (\DateTimeImmutable $date, NatureJour $nature, string $libelle) use (&$natures, &$libelles): void {
            $cle = $date->format('Y-m-d');
            if (NatureJour::FERIE_CHOME_PAYE !== ($natures[$cle] ?? null)) {
                $natures[$cle] = $nature;
            }
            $libelles[$cle] = isset($libelles[$cle]) && !str_contains($libelles[$cle], $libelle) ? $libelles[$cle].' / '.$libelle : $libelle;
        };

        // Élargi d'un jour de chaque côté pour la veille / le lendemain d'un férié hors période.
        foreach ($this->pourSociete($societe, $du->modify('-1 day'), $au->modify('+1 day')) as $jour) {
            $nature = $jour->isChomePaye() ? NatureJour::FERIE_CHOME_PAYE : NatureJour::FERIE_SIMPLE;
            $poser($jour->getDate(), $nature, $jour->getLibelle());
            if ($jour->isVeille()) {
                $poser($jour->getDate()->modify('-1 day'), $nature, 'veille de '.$jour->getLibelle());
            }
            if ($jour->isLendemain()) {
                $poser($jour->getDate()->modify('+1 day'), $nature, 'lendemain de '.$jour->getLibelle());
            }
        }

        return ['natures' => $natures, 'libelles' => $libelles];
    }
}
