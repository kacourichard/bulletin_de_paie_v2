<?php

namespace App\Entity;

use App\Repository\PeriodePaieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mois de paie d'une société. Ouverte : on saisit les éléments variables.
 * Clôturée : plus aucune modification.
 */
#[ORM\Entity(repositoryClass: PeriodePaieRepository::class)]
#[ORM\UniqueConstraint(name: 'periode_unique', columns: ['societe_id', 'mois'])]
#[ORM\HasLifecycleCallbacks]
class PeriodePaie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Societe $societe = null;

    /** Premier jour du mois. */
    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $mois = null;

    #[ORM\Column]
    private bool $cloturee = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $clotureeLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $clotureePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $ouvertePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, ElementVariable> */
    #[ORM\OneToMany(targetEntity: ElementVariable::class, mappedBy: 'periode', orphanRemoval: true)]
    private Collection $elements;

    public function __construct(Societe $societe, \DateTimeInterface $mois, ?User $ouvertePar = null)
    {
        $this->societe = $societe;
        $this->mois = \DateTimeImmutable::createFromInterface($mois)->modify('first day of this month')->setTime(0, 0);
        $this->ouvertePar = $ouvertePar;
        $this->elements = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getDebut(): \DateTimeImmutable
    {
        return $this->mois;
    }

    public function getFin(): \DateTimeImmutable
    {
        return $this->mois->modify('last day of this month');
    }

    /** Ex. « Octobre 2026 ». */
    public function getLibelle(): string
    {
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return ucfirst($mois[(int) $this->mois->format('n') - 1]).' '.$this->mois->format('Y');
    }

    /** Ex. « 2026-10 », utilisé dans les URL. */
    public function getCode(): string
    {
        return $this->mois->format('Y-m');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function getMois(): ?\DateTimeImmutable
    {
        return $this->mois;
    }

    public function isCloturee(): bool
    {
        return $this->cloturee;
    }

    public function cloturer(User $par): static
    {
        $this->cloturee = true;
        $this->clotureeLe = new \DateTimeImmutable();
        $this->clotureePar = $par;

        return $this;
    }

    public function rouvrir(): static
    {
        $this->cloturee = false;
        $this->clotureeLe = null;
        $this->clotureePar = null;

        return $this;
    }

    public function getClotureeLe(): ?\DateTimeImmutable
    {
        return $this->clotureeLe;
    }

    public function getClotureePar(): ?User
    {
        return $this->clotureePar;
    }

    public function getOuvertePar(): ?User
    {
        return $this->ouvertePar;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ElementVariable> */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    /** @return ElementVariable[] éléments variables d'un salarié pour ce mois */
    public function getElementsDe(Affectation $affectation): array
    {
        return array_values($this->elements->filter(static fn (ElementVariable $e) => $e->getAffectation() === $affectation)->toArray());
    }
}
