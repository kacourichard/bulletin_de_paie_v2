<?php

namespace App\Repository;

use App\Entity\Affectation;
use App\Entity\FichePaie;
use App\Entity\PeriodePaie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FichePaie>
 */
class FichePaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FichePaie::class);
    }

    public function obtenir(PeriodePaie $periode, Affectation $affectation): FichePaie
    {
        return $this->findOneBy(['periode' => $periode, 'affectation' => $affectation]) ?? new FichePaie($periode, $affectation);
    }
}