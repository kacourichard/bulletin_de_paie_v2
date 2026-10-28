<?php

namespace App\Entity;

use App\Enum\SectionBulletin;
use App\Enum\TraitementFiscal;
use App\Enum\TraitementSocial;
use App\Enum\TypeRubrique;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ligne type du bulletin de paie (indemnité de logement, prime de transport, mutuelle...).
 * Chaque pays a son référentiel, car le traitement social et fiscal dépend de la législation.
 */
#[ORM\Entity(repositoryClass: RubriqueRepository::class)]
#[ORM\UniqueConstraint(name: 'rubrique_code_unique', columns: ['pays_id', 'code'])]
#[UniqueEntity(fields: ['pays', 'code'], message: 'Ce code de rubrique existe déjà pour ce pays.', errorPath: 'code')]
class Rubrique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    #[ORM\Column(length: 10, enumType: TypeRubrique::class)]
    private TypeRubrique $type = TypeRubrique::GAIN;

    #[ORM\Column(enumType: SectionBulletin::class)]
    private SectionBulletin $section = SectionBulletin::SOUMIS;

    #[ORM\Column(length: 20, enumType: TraitementSocial::class)]
    private TraitementSocial $traitementSocial = TraitementSocial::IMPOSABLE;

    #[ORM\Column(length: 20, enumType: TraitementFiscal::class)]
    private TraitementFiscal $traitementFiscal = TraitementFiscal::IMPOSABLE;

    /** % du brut en dessous duquel la rubrique est exonérée (traitement EXONERE_PLAFOND). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $plafondExoneration = null;

    /** Peut être attribuée comme avantage / élément fixe d'un salarié. */
    #[ORM\Column]
    private bool $elementFixe = true;

    /**
     * Élément fixe versé (ou retenu) au prorata des jours travaillés. Non pour un montant dû en entier
     * quel que soit le temps de présence (ex. cotisation syndicale). Un élément variable du mois n'est jamais proratisé.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $proratisee = true;

    public function isProratisee(): bool
    {
        return $this->proratisee;
    }

    public function setProratisee(bool $proratisee): static
    {
        $this->proratisee = $proratisee;

        return $this;
    }

    /**
     * Rubrique horaire (heures supplémentaires) : coefficient de majoration (1,15 ; 1,5 ; 2...).
     * On saisit le nombre d'heures, le taux = taux horaire du salarié × coefficient.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $coefficientHoraire = null;

    public function getCoefficientHoraire(): ?string
    {
        return $this->coefficientHoraire;
    }

    public function setCoefficientHoraire(?string $coefficientHoraire): static
    {
        $this->coefficientHoraire = '' === $coefficientHoraire ? null : $coefficientHoraire;

        return $this;
    }

    public function isHoraire(): bool
    {
        return null !== $this->coefficientHoraire;
    }

    /** Peut être saisie comme élément variable dans la paie d'un mois. */
    #[ORM\Column(options: ['default' => true])]
    private bool $elementVariable = true;

    #[ORM\Column]
    private bool $actif = true;

    public function isElementVariable(): bool
    {
        return $this->elementVariable;
    }

    public function setElementVariable(bool $elementVariable): static
    {
        $this->elementVariable = $elementVariable;

        return $this;
    }

    #[Assert\Callback]
    public function valider(ExecutionContextInterface $context): void
    {
        if (TraitementFiscal::EXONERE_PLAFOND === $this->traitementFiscal && null === $this->plafondExoneration) {
            $context->buildViolation('Indiquez le plafond d\'exonération (en % du brut).')->atPath('plafondExoneration')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPays(): ?Pays
    {
        return $this->pays;
    }

    public function setPays(?Pays $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code ? mb_strtoupper(trim($code)) : null;

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

    public function getType(): TypeRubrique
    {
        return $this->type;
    }

    public function setType(TypeRubrique $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isGain(): bool
    {
        return TypeRubrique::GAIN === $this->type;
    }

    public function getSection(): SectionBulletin
    {
        return $this->section;
    }

    public function setSection(SectionBulletin $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getTraitementSocial(): TraitementSocial
    {
        return $this->traitementSocial;
    }

    public function setTraitementSocial(TraitementSocial $traitementSocial): static
    {
        $this->traitementSocial = $traitementSocial;

        return $this;
    }

    public function getTraitementFiscal(): TraitementFiscal
    {
        return $this->traitementFiscal;
    }

    public function setTraitementFiscal(TraitementFiscal $traitementFiscal): static
    {
        $this->traitementFiscal = $traitementFiscal;

        return $this;
    }

    /** Libellé du traitement fiscal, avec le plafond le cas échéant. */
    public function getTraitementFiscalDetaille(): string
    {
        if (TraitementFiscal::EXONERE_PLAFOND === $this->traitementFiscal && null !== $this->plafondExoneration) {
            return sprintf('Exonéré dans la limite de %s %% du brut', rtrim(rtrim($this->plafondExoneration, '0'), '.'));
        }

        return $this->traitementFiscal->libelle();
    }

    public function getPlafondExoneration(): ?string
    {
        return $this->plafondExoneration;
    }

    public function setPlafondExoneration(?string $plafondExoneration): static
    {
        $this->plafondExoneration = $plafondExoneration;

        return $this;
    }

    public function isElementFixe(): bool
    {
        return $this->elementFixe;
    }

    public function setElementFixe(bool $elementFixe): static
    {
        $this->elementFixe = $elementFixe;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->code, $this->libelle);
    }
}
