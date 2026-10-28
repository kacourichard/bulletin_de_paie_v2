<?php

namespace App\Entity;

use App\Enum\TraitementFiscal;
use App\Repository\AvantageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Élément fixe du salaire (indemnité, prime) lié à une rubrique du bulletin,
 * versé chaque mois entre dateDebut et dateFin.
 * On ne modifie pas un montant : on arrête la ligne et on en ouvre une nouvelle (historique).
 */
#[ORM\Entity(repositoryClass: AvantageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Avantage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'avantages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    /** Rubrique du bulletin : porte le code et le traitement social / fiscal. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Choisissez la rubrique.')]
    private ?Rubrique $rubrique = null;

    /** Libellé spécifique ; vide = libellé de la rubrique. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro.')]
    private string $montant = '0';

    /** Avantage en nature (logement, véhicule...) plutôt qu'en espèces. */
    #[ORM\Column]
    private bool $enNature = false;

    /** Vide à la saisie d'une embauche : GestionSalaries retient alors la date d'entrée. */
    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

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

    #[Assert\Callback]
    public function validerDates(ExecutionContextInterface $context): void
    {
        if ($this->dateDebut && $this->dateFin && $this->dateFin < $this->dateDebut) {
            $context->buildViolation('La date de fin doit être postérieure au début.')->atPath('dateFin')->addViolation();
        }
    }

    public function estEnVigueurA(\DateTimeInterface $date): bool
    {
        return $this->dateDebut <= $date && (null === $this->dateFin || $this->dateFin >= $date);
    }

    public function isActif(): bool
    {
        return null === $this->dateFin || $this->dateFin >= new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /** Libellé affiché : celui saisi, sinon celui de la rubrique. */
    public function getLibelle(): ?string
    {
        return $this->libelle ?: $this->rubrique?->getLibelle();
    }

    /** Libellé saisi (sans repli sur la rubrique), pour les formulaires. */
    public function getLibelleSpecifique(): ?string
    {
        return $this->libelle;
    }

    public function setLibelleSpecifique(?string $libelle): static
    {
        return $this->setLibelle($libelle);
    }

    public function setLibelle(?string $libelle): static
    {
        $libelle = $libelle ? trim($libelle) : null;
        $this->libelle = $libelle ?: null;

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

    public function isEnNature(): bool
    {
        return $this->enNature;
    }

    public function setEnNature(bool $enNature): static
    {
        $this->enNature = $enNature;

        return $this;
    }

    /** Imposable d'après le traitement fiscal de la rubrique (exonération plafonnée comprise). */
    public function isImposable(): bool
    {
        return null === $this->rubrique || TraitementFiscal::NON_IMPOSABLE !== $this->rubrique->getTraitementFiscal();
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

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

    /** Copie à partir d'une date (révision du montant, transfert...). */
    public function dupliquer(\DateTimeImmutable $dateDebut): self
    {
        return (new self())
            ->setRubrique($this->rubrique)
            ->setLibelle($this->libelle)
            ->setMontant($this->montant)
            ->setEnNature($this->enNature)
            ->setDateDebut($dateDebut)
            ->setCommentaire($this->commentaire);
    }
}
