<?php

namespace App\Repository;

use App\Entity\Cotisation;
use App\Entity\Pays;
use App\Entity\ReductionImpot;
use App\Entity\TrancheImpot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cotisation>
 */
class CotisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cotisation::class);
    }

    /** @return Cotisation[] */
    public function duPays(Pays $pays, bool $actives = false): array
    {
        return $this->findBy($actives ? ['pays' => $pays, 'actif' => true] : ['pays' => $pays], ['ordre' => 'ASC', 'id' => 'ASC']);
    }

    /** @return TrancheImpot[] triées par borne */
    public function tranches(Pays $pays): array
    {
        $liste = $this->getEntityManager()->getRepository(TrancheImpot::class)->findBy(['pays' => $pays]);
        usort($liste, static fn (TrancheImpot $a, TrancheImpot $b) => bccomp($a->getDe(), $b->getDe(), 2));

        return $liste;
    }

    /** @return ReductionImpot[] triées par nombre de parts */
    public function reductions(Pays $pays): array
    {
        $liste = $this->getEntityManager()->getRepository(ReductionImpot::class)->findBy(['pays' => $pays]);
        usort($liste, static fn (ReductionImpot $a, ReductionImpot $b) => bccomp($a->getParts(), $b->getParts(), 2));

        return $liste;
    }
}