<?php

namespace App\Entity;

use App\Repository\FichePaieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données d'un salarié pour un mois de paie : jours travaillés (prorata des éléments fixes).
 * Porte aussi l'arrêté du solde de tout compte d'un salarié sorti dans le mois.
 */
#[ORM\Entity(repositoryClass: FichePaieRepository::class)]
#[ORM\UniqueConstraint(name: 'fiche_paie_unique', columns: ['periode_id', 'affectation_id'])]
class FichePaie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PeriodePaie $periode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    /** Jours travaillés saisis ; vide = calcul automatique (mois complet ou entrée / sortie en cours de mois). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 31)]
    private ?string $jours = null;

    /** Solde de tout compte arrêté (salarié sorti dans le mois) : bulletin définitif sans attendre la validation du mois. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $soldeArreteLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $soldeArretePar = null;

    public function __construct(PeriodePaie $periode, Affectation $affectation)
    {
        $this->periode = $periode;
        $this->affectation = $affectation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriode(): ?PeriodePaie
    {
        return $this->periode;
    }

    public function getAffectation(): ?Affectation
    {
        return $this->affectation;
    }

    public function getJours(): ?string
    {
        return $this->jours;
    }

    public function setJours(?string $jours): static
    {
        $this->jours = null === $jours || '' === $jours ? null : $jours;

        return $this;
    }

    public function isSoldeArrete(): bool
    {
        return null !== $this->soldeArreteLe;
    }

    public function getSoldeArreteLe(): ?\DateTimeImmutable
    {
        return $this->soldeArreteLe;
    }

    public function getSoldeArretePar(): ?User
    {
        return $this->soldeArretePar;
    }

    public function arreterSolde(?User $par): static
    {
        $this->soldeArreteLe = new \DateTimeImmutable();
        $this->soldeArretePar = $par;

        return $this;
    }

    public function annulerSolde(): static
    {
        $this->soldeArreteLe = null;
        $this->soldeArretePar = null;

        return $this;
    }
}
