<?php

namespace App\Entity;

use App\Repository\SecteurActiviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Secteur d'activité d'un pays et son taux de risque accident du travail
 * (Côte d'Ivoire : 2 à 5 % selon le secteur, repris de la table v1 secteur_activite).
 */
#[ORM\Entity(repositoryClass: SecteurActiviteRepository::class)]
class SecteurActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    /** Taux de risque accident du travail (%), part patronale. */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 4)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tauxRisque = null;

    #[ORM\Column]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function setPays(?Pays $v): static { $this->pays = $v; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $v): static { $this->libelle = $v; return $this; }
    public function getTauxRisque(): ?string { return $this->tauxRisque; }
    public function setTauxRisque(?string $v): static { $this->tauxRisque = '' === $v ? null : $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    public function __toString(): string
    {
        return sprintf('%s (%s %%)', $this->libelle, rtrim(rtrim((string) $this->tauxRisque, '0'), '.'));
    }
}
