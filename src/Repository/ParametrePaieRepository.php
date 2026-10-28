<?php

namespace App\Repository;

use App\Entity\BaremePart;
use App\Entity\ParametrePaie;
use App\Entity\Pays;
use App\Enum\SituationFamiliale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParametrePaie>
 */
class ParametrePaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametrePaie::class);
    }

    /** Paramètres du pays, ou paramètres par défaut (non enregistrés) s'il n'est pas encore paramétré. */
    public function pour(Pays $pays): ParametrePaie
    {
        return $this->findOneBy(['pays' => $pays]) ?? new ParametrePaie($pays);
    }

    /**
     * Grille des parts : [situation => [nombre d'enfants => parts]], triée par nombre d'enfants.
     *
     * @return array<string, array<int, string>>
     */
    public function grilleParts(Pays $pays): array
    {
        $grille = [];
        foreach ($this->getEntityManager()->getRepository(BaremePart::class)->findBy(['pays' => $pays], ['nombreEnfants' => 'ASC']) as $ligne) {
            $grille[$ligne->getSituation()->value][$ligne->getNombreEnfants()] = $ligne->getParts();
        }

        return $grille;
    }

    /** Nombre de parts selon la grille ; au-delà du dernier nombre d'enfants, la dernière valeur. */
    public static function partsDepuisGrille(array $grille, ?SituationFamiliale $situation, int $enfants): ?string
    {
        $ligne = $grille[$situation?->value ?? ''] ?? null;
        if (!$ligne) {
            return null;
        }
        $parts = null;
        foreach ($ligne as $n => $valeur) {
            if ($n <= $enfants) {
                $parts = $valeur;
            }
        }

        return $parts;
    }
}