<?php

namespace App\Repository;

use App\Entity\Pays;
use App\Entity\Rubrique;
use App\Entity\Societe;
use App\Entity\ZoneTransport;
use App\Enum\TypeRubrique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rubrique>
 */
class RubriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rubrique::class);
    }

    /**
     * @return Rubrique[] rubriques actives attribuables comme élément fixe d'un salarié : gains (avantages) puis retenues
     *                    fixes (cotisation syndicale…), hors rubriques masquées par la société et hors rubrique du transport
     *                    quand le pays a un barème par zone de résidence
     */
    public function avantagesDisponibles(Pays $pays, ?Societe $societe = null): array
    {
        $transport = $this->getEntityManager()->getRepository(ZoneTransport::class)->rubriquesDuPays($pays);

        return array_values(array_filter(self::sansMasquees($this->findBy(
            ['pays' => $pays, 'elementFixe' => true, 'actif' => true],
            ['type' => 'ASC', 'libelle' => 'ASC']
        ), $societe), static fn (Rubrique $r) => !in_array($r->getId(), $transport, true)));
    }

    /** @return Rubrique[] rubriques actives saisissables comme élément variable du mois (gains puis retenues), hors masquées */
    public function variablesDisponibles(Pays $pays, ?Societe $societe = null): array
    {
        return self::sansMasquees($this->findBy(['pays' => $pays, 'elementVariable' => true, 'actif' => true], ['type' => 'ASC', 'libelle' => 'ASC']), $societe);
    }

    /**
     * @param Rubrique[] $rubriques
     *
     * @return Rubrique[]
     */
    private static function sansMasquees(array $rubriques, ?Societe $societe): array
    {
        return null === $societe ? $rubriques : array_values(array_filter($rubriques, static fn (Rubrique $r) => !$societe->isRubriqueMasquee($r)));
    }

    /** @return Rubrique[] */
    public function duPays(Pays $pays): array
    {
        return $this->findBy(['pays' => $pays], ['type' => 'ASC', 'section' => 'ASC', 'code' => 'ASC']);
    }
}
