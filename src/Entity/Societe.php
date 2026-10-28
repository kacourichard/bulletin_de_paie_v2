<?php

namespace App\Entity;

use App\Repository\SocieteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SocieteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Societe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $raisonSociale = null;

    #[ORM\ManyToOne(inversedBy: 'societes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez le pays de la société.')]
    private ?Pays $pays = null;

    /** Pays dont la législation de paie s'applique, s'il diffère du pays de la société (ex. paie ivoirienne au Burkina). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Pays $legislationPaie = null;

    #[ORM\ManyToOne(inversedBy: 'societes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez la succursale.')]
    private ?Succursale $succursale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    /** Numéro d'immatriculation employeur à la sécurité sociale (CNPS, URSSAF...). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroEmployeur = null;

    /** Identifiant fiscal (compte contribuable, SIREN, NIF...). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroFiscal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $registreCommerce = null;

    /** Secteur d'activité : fixe le taux de risque accident du travail (vide = taux de la cotisation du pays). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?SecteurActivite $secteurActivite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    /** Devise de paie ; si vide, celle du pays s'applique. */
    #[ORM\Column(length: 3, nullable: true)]
    #[Assert\Currency]
    private ?string $devise = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Membre> */
    #[ORM\OneToMany(targetEntity: Membre::class, mappedBy: 'societe', orphanRemoval: true)]
    private Collection $membres;

    /** @var Collection<int, Role> */
    #[ORM\OneToMany(targetEntity: Role::class, mappedBy: 'societe', orphanRemoval: true)]
    #[ORM\OrderBy(['libelle' => \SortDirection::Ascending])]
    private Collection $roles;

    /**
     * Rubriques du pays de paie que la société ne propose pas (avantages, éléments variables) :
     * le référentiel reste celui du pays, chaque société n'en garde que ce qui la concerne.
     *
     * @var Collection<int, Rubrique>
     */
    #[ORM\ManyToMany(targetEntity: Rubrique::class)]
    #[ORM\JoinTable(name: 'societe_rubrique_masquee')]
    private Collection $rubriquesMasquees;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->rubriquesMasquees = new ArrayCollection();
    }

    /** @return Collection<int, Rubrique> */
    public function getRubriquesMasquees(): Collection
    {
        return $this->rubriquesMasquees;
    }

    public function isRubriqueMasquee(Rubrique $rubrique): bool
    {
        return $this->rubriquesMasquees->contains($rubrique);
    }

    public function masquerRubrique(Rubrique $rubrique, bool $masquer = true): static
    {
        if ($masquer && !$this->rubriquesMasquees->contains($rubrique)) {
            $this->rubriquesMasquees->add($rubrique);
        } elseif (!$masquer) {
            $this->rubriquesMasquees->removeElement($rubrique);
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
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

    public function getLegislationPaie(): ?Pays
    {
        return $this->legislationPaie;
    }

    public function setLegislationPaie(?Pays $legislationPaie): static
    {
        $this->legislationPaie = $legislationPaie;

        return $this;
    }

    /** Pays dont les règles de paie s'appliquent (paramètres, cotisations, rubriques, secteurs). */
    public function getPaysPaie(): ?Pays
    {
        return $this->legislationPaie ?? $this->pays;
    }

    public function hasLegislationEtrangere(): bool
    {
        return null !== $this->legislationPaie && $this->legislationPaie !== $this->pays;
    }

    public function getSuccursale(): ?Succursale
    {
        return $this->succursale;
    }

    public function setSuccursale(?Succursale $succursale): static
    {
        $this->succursale = $succursale;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNumeroEmployeur(): ?string
    {
        return $this->numeroEmployeur;
    }

    public function setNumeroEmployeur(?string $numeroEmployeur): static
    {
        $this->numeroEmployeur = $numeroEmployeur;

        return $this;
    }

    public function getNumeroFiscal(): ?string
    {
        return $this->numeroFiscal;
    }

    public function setNumeroFiscal(?string $numeroFiscal): static
    {
        $this->numeroFiscal = $numeroFiscal;

        return $this;
    }

    public function getRegistreCommerce(): ?string
    {
        return $this->registreCommerce;
    }

    public function setRegistreCommerce(?string $registreCommerce): static
    {
        $this->registreCommerce = $registreCommerce;

        return $this;
    }

    public function getSecteurActivite(): ?SecteurActivite
    {
        return $this->secteurActivite;
    }

    public function setSecteurActivite(?SecteurActivite $secteurActivite): static
    {
        $this->secteurActivite = $secteurActivite;

        return $this;
    }

    /** Taux de risque accident du travail du secteur (null = taux de la cotisation du pays). */
    public function getTauxAccidentTravail(): ?string
    {
        return $this->secteurActivite?->getTauxRisque();
    }

    #[Assert\Callback]
    public function validerSecteur(ExecutionContextInterface $context): void
    {
        if ($this->secteurActivite && $this->secteurActivite->getPays() !== $this->getPaysPaie()) {
            $context->buildViolation('Ce secteur d\'activité n\'appartient pas au pays de la législation de paie de la société.')->atPath('secteurActivite')->addViolation();
        }
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(?string $devise): static
    {
        $this->devise = $devise ? strtoupper($devise) : null;

        return $this;
    }

    /** Devise réellement appliquée : celle de la société, sinon celle du pays. */
    public function getDeviseEffective(): ?string
    {
        return $this->devise ?? $this->pays?->getDevise();
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Membre> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Membre $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setSociete($this);
        }

        return $this;
    }

    /** @return Collection<int, Role> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->setSociete($this);
        }

        return $this;
    }

    public function getRoleParCode(string $code): ?Role
    {
        foreach ($this->roles as $role) {
            if ($role->getCode() === $code) {
                return $role;
            }
        }

        return null;
    }

    public function getInitiales(): string
    {
        $mots = preg_split('/\s+/', trim((string) $this->raisonSociale)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn ($m) => mb_substr($m, 0, 1), array_slice($mots, 0, 2))));
    }

    public function __toString(): string
    {
        return (string) $this->raisonSociale;
    }
}
