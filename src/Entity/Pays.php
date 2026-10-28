<?php

namespace App\Entity;

use App\Repository\PaysRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pays dans lequel une société exerce : porte la devise, la langue et le fuseau
 * horaire par défaut utilisés pour la paie.
 */
#[ORM\Entity(repositoryClass: PaysRepository::class)]
#[UniqueEntity(fields: ['codeIso'], message: 'Ce pays existe déjà.')]
class Pays
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Code ISO 3166-1 alpha-2 (CI, FR, SN...). */
    #[ORM\Column(length: 2, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Country]
    private ?string $codeIso = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    /** Code ISO 4217 (XOF, EUR, USD...). */
    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    private ?string $devise = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Locale]
    private string $langue = 'fr';

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Timezone]
    private string $fuseauHoraire = 'UTC';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $indicatifTelephone = null;

    /** Nom local de l'organisme de sécurité sociale (CNPS, URSSAF, CSS...). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $organismeSocial = null;

    #[ORM\Column]
    private bool $actif = true;

    /** @var Collection<int, Societe> */
    #[ORM\OneToMany(targetEntity: Societe::class, mappedBy: 'pays')]
    private Collection $societes;

    public function __construct()
    {
        $this->societes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeIso(): ?string
    {
        return $this->codeIso;
    }

    public function setCodeIso(string $codeIso): static
    {
        $this->codeIso = strtoupper($codeIso);
        if (null === $this->nom && Countries::exists($this->codeIso)) {
            $this->nom = Countries::getName($this->codeIso, 'fr');
        }

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $nom = trim((string) $nom);
        if ('' === $nom && null !== $this->codeIso && Countries::exists($this->codeIso)) {
            $nom = Countries::getName($this->codeIso, 'fr');
        }
        $this->nom = $nom;

        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = strtoupper($devise);

        return $this;
    }

    public function getLangue(): string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    public function getFuseauHoraire(): string
    {
        return $this->fuseauHoraire;
    }

    public function setFuseauHoraire(string $fuseauHoraire): static
    {
        $this->fuseauHoraire = $fuseauHoraire;

        return $this;
    }

    public function getIndicatifTelephone(): ?string
    {
        return $this->indicatifTelephone;
    }

    public function setIndicatifTelephone(?string $indicatifTelephone): static
    {
        $this->indicatifTelephone = $indicatifTelephone;

        return $this;
    }

    public function getOrganismeSocial(): ?string
    {
        return $this->organismeSocial;
    }

    public function setOrganismeSocial(?string $organismeSocial): static
    {
        $this->organismeSocial = $organismeSocial;

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

    /** @return Collection<int, Societe> */
    public function getSocietes(): Collection
    {
        return $this->societes;
    }

    /** Drapeau emoji calculé à partir du code ISO. */
    public function getDrapeau(): string
    {
        if (null === $this->codeIso) {
            return '';
        }

        return implode('', array_map(
            static fn (string $c) => mb_chr(0x1F1E6 + ord($c) - ord('A')),
            str_split($this->codeIso)
        ));
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
