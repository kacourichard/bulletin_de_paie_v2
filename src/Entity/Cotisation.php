<?php

namespace App\Entity;

use App\Enum\AssietteCotisation;
use App\Enum\CategorieCotisation;
use App\Repository\CotisationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Cotisation d'un pays (CNPS retraite, CMU, FDFP...) : part salariale et/ou patronale,
 * calculée sur une assiette éventuellement plafonnée. Paramétrée par le super-administrateur.
 */
#[ORM\Entity(repositoryClass: CotisationRepository::class)]
class Cotisation
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

    /** Code de la ligne « part salariale » sur le bulletin (ex. 2604). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeSalarial = null;

    /** Code de la ligne « part patronale » sur le bulletin (ex. 3900). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codePatronal = null;

    #[ORM\Column(length: 30, enumType: AssietteCotisation::class)]
    private AssietteCotisation $assiette = AssietteCotisation::BRUT;

    /** Montant par personne couverte (assiette FORFAIT_PERSONNES). */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $forfaitUnitaire = null;

    /** Plafond de l'assiette (vide = pas de plafond). */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $plafond = null;

    /** Taux salarial en % (vide = pas de part salariale). */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tauxSalarial = null;

    /** Taux patronal en % (vide = pas de part patronale). */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tauxPatronal = null;

    /** Taux patronal appliqué aux salariés d'une autre nationalité que le pays (ex. impôt employeur CI : 12 % au lieu de 1,2 %). */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tauxPatronalNonNational = null;

    /** Le taux patronal est celui de la société s'il est renseigné (taux de risque accident du travail selon le secteur). */
    #[ORM\Column(options: ['default' => false])]
    private bool $tauxPatronalSociete = false;

    #[ORM\Column(length: 20, enumType: CategorieCotisation::class)]
    private CategorieCotisation $categorie = CategorieCotisation::SOCIALE;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private bool $actif = true;

    #[Assert\Callback]
    public function valider(ExecutionContextInterface $context): void
    {
        if (null === $this->tauxSalarial && null === $this->tauxPatronal) {
            $context->buildViolation('Renseignez au moins un taux (salarial ou patronal).')->atPath('tauxSalarial')->addViolation();
        }
        if (AssietteCotisation::FORFAIT_PERSONNES === $this->assiette && null === $this->forfaitUnitaire) {
            $context->buildViolation('Indiquez le montant par personne.')->atPath('forfaitUnitaire')->addViolation();
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function setPays(?Pays $v): static { $this->pays = $v; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $v): static { $this->libelle = $v; return $this; }
    public function getCodeSalarial(): ?string { return $this->codeSalarial; }
    public function setCodeSalarial(?string $v): static { $this->codeSalarial = $v ?: null; return $this; }
    public function getCodePatronal(): ?string { return $this->codePatronal; }
    public function setCodePatronal(?string $v): static { $this->codePatronal = $v ?: null; return $this; }
    public function getAssiette(): AssietteCotisation { return $this->assiette; }
    public function setAssiette(AssietteCotisation $v): static { $this->assiette = $v; return $this; }
    public function getForfaitUnitaire(): ?string { return $this->forfaitUnitaire; }
    public function setForfaitUnitaire(?string $v): static { $this->forfaitUnitaire = '' === $v ? null : $v; return $this; }
    public function getPlafond(): ?string { return $this->plafond; }
    public function setPlafond(?string $v): static { $this->plafond = '' === $v ? null : $v; return $this; }
    public function getTauxSalarial(): ?string { return $this->tauxSalarial; }
    public function setTauxSalarial(?string $v): static { $this->tauxSalarial = '' === $v ? null : $v; return $this; }
    public function getTauxPatronal(): ?string { return $this->tauxPatronal; }
    public function setTauxPatronal(?string $v): static { $this->tauxPatronal = '' === $v ? null : $v; return $this; }
    public function getTauxPatronalNonNational(): ?string { return $this->tauxPatronalNonNational; }
    public function setTauxPatronalNonNational(?string $v): static { $this->tauxPatronalNonNational = '' === $v ? null : $v; return $this; }
    public function isTauxPatronalSociete(): bool { return $this->tauxPatronalSociete; }
    public function setTauxPatronalSociete(bool $v): static { $this->tauxPatronalSociete = $v; return $this; }

    /** Taux patronal effectif pour un salarié (null = pas de part patronale). */
    public function tauxPatronalPour(bool $national, ?string $tauxSociete): ?string
    {
        if (null === $this->tauxPatronal) {
            return null;
        }
        if ($this->tauxPatronalSociete && null !== $tauxSociete) {
            return $tauxSociete;
        }

        return !$national && null !== $this->tauxPatronalNonNational ? $this->tauxPatronalNonNational : $this->tauxPatronal;
    }
    public function getCategorie(): CategorieCotisation { return $this->categorie; }
    public function setCategorie(CategorieCotisation $v): static { $this->categorie = $v; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(?int $v): static { $this->ordre = (int) $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }
}
