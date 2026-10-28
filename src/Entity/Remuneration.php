<?php

namespace App\Entity;

use App\Enum\MotifRemuneration;
use App\Repository\RemunerationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une ligne de l'historique des salaires : montants applicables
 * à partir de la date d'effet, jusqu'à la ligne suivante.
 */
#[ORM\Entity(repositoryClass: RemunerationRepository::class)]
#[ORM\UniqueConstraint(name: 'remuneration_date_unique', columns: ['affectation_id', 'date_effet'])]
#[ORM\HasLifecycleCallbacks]
class Remuneration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'remunerations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Indiquez la date d\'effet.')]
    private ?\DateTimeImmutable $dateEffet = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $salaireBase = '0';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $sursalaire = '0';

    #[ORM\Column(length: 20, enumType: MotifRemuneration::class)]
    private MotifRemuneration $motif = MotifRemuneration::REVALORISATION;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $creePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Renseignés lorsqu'une erreur de saisie a été corrigée. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $corrigePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $corrigeLe = null;

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getSalaireTotal(): string
    {
        return bcadd($this->salaireBase, $this->sursalaire, 2);
    }

    /** Ligne précédente dans l'historique de la même affectation. */
    public function getPrecedente(): ?Remuneration
    {
        $trouve = false;
        foreach ($this->affectation?->getHistoriqueRemunerations() ?? [] as $remuneration) {
            if ($trouve) {
                return $remuneration;
            }
            $trouve = $remuneration === $this;
        }

        return null;
    }

    /** Variation en % du salaire total par rapport à la ligne précédente. */
    public function getVariation(): ?float
    {
        $precedente = $this->getPrecedente();
        if (null === $precedente || 0 === bccomp($precedente->getSalaireTotal(), '0', 2)) {
            return null;
        }

        return round(((float) $this->getSalaireTotal() / (float) $precedente->getSalaireTotal() - 1) * 100, 2);
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

    public function getDateEffet(): ?\DateTimeImmutable
    {
        return $this->dateEffet;
    }

    public function setDateEffet(?\DateTimeImmutable $dateEffet): static
    {
        $this->dateEffet = $dateEffet;

        return $this;
    }

    public function getSalaireBase(): string
    {
        return $this->salaireBase;
    }

    public function setSalaireBase(?string $salaireBase): static
    {
        $this->salaireBase = $salaireBase ?? '0';

        return $this;
    }

    public function getSursalaire(): string
    {
        return $this->sursalaire;
    }

    public function setSursalaire(?string $sursalaire): static
    {
        $this->sursalaire = $sursalaire ?? '0';

        return $this;
    }

    public function getMotif(): MotifRemuneration
    {
        return $this->motif;
    }

    public function setMotif(MotifRemuneration $motif): static
    {
        $this->motif = $motif;

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

    public function getCorrigePar(): ?User
    {
        return $this->corrigePar;
    }

    public function getCorrigeLe(): ?\DateTimeImmutable
    {
        return $this->corrigeLe;
    }

    public function marquerCorrigee(User $par): static
    {
        $this->corrigePar = $par;
        $this->corrigeLe = new \DateTimeImmutable();

        return $this;
    }
}
