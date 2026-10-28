<?php

namespace App\Repository;

use App\Entity\PeriodePaie;
use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeriodePaie>
 */
class PeriodePaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PeriodePaie::class);
    }

    /**
     * Périodes de paie ouvertes de la société entre deux mois inclus, dans l'ordre chronologique.
     *
     * @return PeriodePaie[]
     */
    public function entre(Societe $societe, \DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.societe = :societe')->setParameter('societe', $societe)
            ->andWhere('p.mois BETWEEN :du AND :au')
            ->setParameter('du', $du->modify('first day of this month')->setTime(0, 0))
            ->setParameter('au', $au->modify('first day of this month')->setTime(0, 0))
            ->orderBy('p.mois')
            ->getQuery()->getResult();
    }
}
