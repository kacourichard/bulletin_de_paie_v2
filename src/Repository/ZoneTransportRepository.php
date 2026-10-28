<?php

namespace App\Repository;

use App\Entity\Pays;
use App\Entity\ZoneTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneTransport>
 */
class ZoneTransportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneTransport::class);
    }

    /** @return ZoneTransport[] */
    public function duPays(Pays $pays, bool $actives = false): array
    {
        return $this->findBy(['pays' => $pays] + ($actives ? ['actif' => true] : []), ['ordre' => 'ASC', 'libelle' => 'ASC']);
    }

    /** @return int[] identifiants des rubriques alimentées par les zones actives du pays */
    public function rubriquesDuPays(Pays $pays): array
    {
        return array_values(array_unique(array_map(static fn (ZoneTransport $z) => $z->getRubrique()->getId(), $this->duPays($pays, true))));
    }
}
