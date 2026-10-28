<?php

namespace App\Repository;

use App\Entity\Pays;
use App\Entity\SecteurActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecteurActivite>
 */
class SecteurActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecteurActivite::class);
    }

    /** @return SecteurActivite[] */
    public function duPays(Pays $pays): array
    {
        return $this->findBy(['pays' => $pays], ['libelle' => 'ASC']);
    }
}
