<?php

namespace App\Entity;

use App\Repository\ZoneTransportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Zone de résidence et indemnité de transport mensuelle correspondante
 * (Côte d'Ivoire : Abidjan 30 000, Bouaké 24 000, autres villes 20 000, hors zone 0 — table v1 prime_transport).
 * L'indemnité est versée sur la rubrique indiquée, au prorata des jours travaillés.
 */
#[ORM\Entity(repositoryClass: ZoneTransportRepository::class)]
class ZoneTransport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez la ville ou la zone.')]
    private ?string $libelle = null;

    /** Indemnité mensuelle (mois complet). */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $montant = '0';

    /** Rubrique du bulletin (ex. 645 Indemnité de transport non imposable). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez la rubrique du bulletin.')]
    private ?Rubrique $rubrique = null;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function setPays(?Pays $v): static { $this->pays = $v; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $v): static { $this->libelle = $v ? trim($v) : null; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(?string $v): static { $this->montant = $v ?? '0'; return $this; }
    public function getRubrique(): ?Rubrique { return $this->rubrique; }
    public function setRubrique(?Rubrique $v): static { $this->rubrique = $v; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(?int $v): static { $this->ordre = (int) $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    public function __toString(): string
    {
        return (string) $this->libelle;
    }
}
