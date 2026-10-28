<?php

namespace App\Repository;

use App\Entity\Affectation;
use App\Entity\Societe;
use App\Enum\TypeContrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Affectation>
 */
class AffectationRepository extends ServiceEntityRepository
{
    public const STATUT_ACTIFS = 'actifs';
    public const STATUT_SORTIS = 'sortis';
    public const STATUT_TOUS = 'tous';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Affectation::class);
    }

    /**
     * Personnel d'une société, une ligne par affectation. Sans $parPage : toutes les lignes
     * (la liste est alors paginée à l'écran par DataTables).
     *
     * @return Paginator<Affectation>
     */
    public function rechercher(Societe $societe, string $statut = self::STATUT_ACTIFS, string $recherche = '', ?TypeContrat $contrat = null, int $page = 1, ?int $parPage = null): Paginator
    {
        $qb = $this->baseSociete($societe)
            ->leftJoin('a.remunerations', 'r')->addSelect('r')
            ->leftJoin('a.avantages', 'av')->addSelect('av')
            ->leftJoin('av.rubrique', 'rub')->addSelect('rub')
            ->orderBy('s.nom')->addOrderBy('s.prenoms');

        match ($statut) {
            self::STATUT_ACTIFS => $qb->andWhere('a.dateSortie IS NULL'),
            self::STATUT_SORTIS => $qb->andWhere('a.dateSortie IS NOT NULL'),
            default => null,
        };

        if ('' !== $recherche) {
            $qb->andWhere('s.nom LIKE :q OR s.prenoms LIKE :q OR a.matricule LIKE :q OR a.poste LIKE :q OR CONCAT(s.prenoms, \' \', s.nom) LIKE :q')
                ->setParameter('q', '%'.$recherche.'%');
        }
        if ($contrat) {
            $qb->andWhere('a.typeContrat = :contrat')->setParameter('contrat', $contrat);
        }

        if (null !== $parPage) {
            $qb->setFirstResult(($page - 1) * $parPage)->setMaxResults($parPage);
        }

        return new Paginator($qb);
    }

    /** @return Affectation[] affectations en cours, avec rémunérations et avantages chargés */
    public function enCours(Societe $societe): array
    {
        return $this->baseSociete($societe)
            ->andWhere('a.dateSortie IS NULL')
            ->leftJoin('a.remunerations', 'r')->addSelect('r')
            ->leftJoin('a.avantages', 'av')->addSelect('av')
            ->getQuery()->getResult();
    }

    /**
     * Salariés présents au moins un jour sur la période (entrés ou sortis en cours de mois compris).
     *
     * @return Affectation[]
     */
    public function presentesSurPeriode(Societe $societe, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->baseSociete($societe)
            ->leftJoin('a.remunerations', 'r')->addSelect('r')
            ->leftJoin('a.avantages', 'av')->addSelect('av')
            ->andWhere('a.dateEntree <= :fin')
            ->andWhere('a.dateSortie IS NULL OR a.dateSortie >= :debut')
            ->setParameter('debut', $debut)->setParameter('fin', $fin)
            ->orderBy('s.nom')->addOrderBy('s.prenoms')
            ->getQuery()->getResult();
    }

    /** @return array{actifs: int, sortis: int} */
    public function compter(Societe $societe): array
    {
        $resultat = $this->createQueryBuilder('a')
            ->select('SUM(CASE WHEN a.dateSortie IS NULL THEN 1 ELSE 0 END) AS actifs, SUM(CASE WHEN a.dateSortie IS NOT NULL THEN 1 ELSE 0 END) AS sortis')
            ->where('a.societe = :societe')->setParameter('societe', $societe)
            ->getQuery()->getSingleResult();

        return ['actifs' => (int) $resultat['actifs'], 'sortis' => (int) $resultat['sortis']];
    }

    /**
     * Salariés en poste par société, en une seule requête.
     *
     * @return array<int, int> [id société => effectif]
     */
    public function effectifs(): array
    {
        $lignes = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.societe) AS societe, COUNT(a.id) AS effectif')
            ->where('a.dateSortie IS NULL')
            ->groupBy('a.societe')
            ->getQuery()->getArrayResult();

        return array_column(array_map(static fn ($l) => ['s' => (int) $l['societe'], 'n' => (int) $l['effectif']], $lignes), 'n', 's');
    }

    /** Propose le matricule suivant : M0001, M0002... (le premier numéro libre). */
    public function prochainMatricule(Societe $societe, string $prefixe = 'M'): string
    {
        $existants = array_flip($this->createQueryBuilder('a')
            ->select('a.matricule')
            ->where('a.societe = :societe')->setParameter('societe', $societe)
            ->getQuery()->getSingleColumnResult());

        $numero = count($existants) + 1;
        while (isset($existants[$matricule = sprintf('%s%04d', $prefixe, $numero)])) {
            ++$numero;
        }

        return $matricule;
    }

    public function matriculeDisponible(Societe $societe, string $matricule): bool
    {
        return 0 === $this->count(['societe' => $societe, 'matricule' => mb_strtoupper(trim($matricule))]);
    }

    private function baseSociete(Societe $societe): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.salarie', 's')->addSelect('s')
            ->where('a.societe = :societe')->setParameter('societe', $societe);
    }
}
