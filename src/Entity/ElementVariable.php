<?php

namespace App\Entity;

use App\Repository\ElementVariableRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Élément variable de la paie d'un mois :
 * - ajustement d'un avantage fixe (son montant pour ce mois seulement, 0 = suspendu) ;
 * - ou rubrique ponctuelle (prime, heures supplémentaires, retenue, acompte...).
 * Le montant peut être calculé comme base × taux (ex. 10 heures × 2 500).
 */
#[ORM\Entity(repositoryClass: ElementVariableRepository::class)]
#[ORM\UniqueConstraint(name: 'ajustement_unique', columns: ['periode_id', 'avantage_id'])]
#[ORM\HasLifecycleCallbacks]
class ElementVariable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PeriodePaie $periode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Rubrique $rubrique = null;

    /** Avantage fixe dont le montant est remplacé pour ce mois (null = élément ponctuel). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Avantage $avantage = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $libelle = null;

    /** Base de calcul (nombre d'heures, de jours, montant de référence...). */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 4, nullable: true)]
    private ?string $base = null;

    /** Taux appliqué à la base (taux horaire, pourcentage exprimé en décimal...). */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 4, nullable: true)]
    private ?string $taux = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $montant = '0';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $creePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function isAjustement(): bool
    {
        return null !== $this->avantage;
    }

    public function getLibelleAffiche(): string
    {
        return $this->libelle ?: ($this->avantage?->getLibelle() ?? $this->rubrique?->getLibelle() ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriode(): ?PeriodePaie
    {
        return $this->periode;
    }

    public function setPeriode(?PeriodePaie $periode): static
    {
        $this->periode = $periode;

        return $this;
    }

    public function getAffectation(): ?Affectation
    {
        return $this->affectation;
    }

    public function setAffectation(?Affectation $affectation): static
    {
        $this->affectation = $affectation;

        return $this;
    }

    public function getRubrique(): ?Rubrique
    {
        return $this->rubrique;
    }

    public function setRubrique(?Rubrique $rubrique): static
    {
        $this->rubrique = $rubrique;

        return $this;
    }

    public function getAvantage(): ?Avantage
    {
        return $this->avantage;
    }

    public function setAvantage(?Avantage $avantage): static
    {
        $this->avantage = $avantage;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle ? trim($libelle) : null;

        return $this;
    }

    public function getBase(): ?string
    {
        return $this->base;
    }

    public function setBase(?string $base): static
    {
        $this->base = '' === $base ? null : $base;

        return $this;
    }

    public function getTaux(): ?string
    {
        return $this->taux;
    }

    public function setTaux(?string $taux): static
    {
        $this->taux = '' === $taux ? null : $taux;

        return $this;
    }

    public function getMontant(): string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): static
    {
        $this->montant = $montant ?? '0';

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getCreePar(): ?User
    {
        return $this->creePar;
    }

    public function setCreePar(?User $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
