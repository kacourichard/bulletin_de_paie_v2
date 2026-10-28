<?php

namespace App\Entity;

use App\Enum\ModePaiement;
use App\Enum\SituationFamiliale;
use App\Enum\Sexe;
use App\Repository\SalarieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * La personne. Elle conserve son identité quand elle change de société :
 * chaque passage dans une société est une Affectation.
 */
#[ORM\Entity(repositoryClass: SalarieRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Salarie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $prenoms = null;

    #[ORM\Column(length: 1, nullable: true, enumType: Sexe::class)]
    private ?Sexe $sexe = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\LessThan('today', message: 'La date de naissance doit être passée.')]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lieuNaissance = null;

    /** Code pays ISO de la nationalité. */
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    private ?string $nationalite = null;

    #[ORM\Column(length: 20, nullable: true, enumType: SituationFamiliale::class)]
    private ?SituationFamiliale $situationFamiliale = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $nombreEnfants = 0;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    /** Ville / zone de résidence : fixe l'indemnité de transport (Côte d'Ivoire : Abidjan, Bouaké, autres villes, hors zone). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ZoneTransport $zoneTransport = null;

    /** Pièce d'identité (CNI, passeport...). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroPiece = null;

    /** N° de sécurité sociale (CNPS, NIR...). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroSecuriteSociale = null;

    /** N° d'assurance maladie (CMU, mutuelle...). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroAssuranceMaladie = null;

    #[ORM\Column(length: 20, enumType: ModePaiement::class)]
    private ModePaiement $modePaiement = ModePaiement::VIREMENT;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $banque = null;

    /** RIB, IBAN ou numéro mobile money selon le mode de paiement. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroCompte = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Affectation> */
    #[ORM\OneToMany(targetEntity: Affectation::class, mappedBy: 'salarie', cascade: ['persist'])]
    #[ORM\OrderBy(['dateEntree' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $affectations;

    public function __construct()
    {
        $this->affectations = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    /** Affectation en cours (sans date de sortie), toutes sociétés confondues. */
    public function getAffectationEnCours(): ?Affectation
    {
        foreach ($this->affectations as $affectation) {
            if ($affectation->isEnCours()) {
                return $affectation;
            }
        }

        return null;
    }

    /** Dernière affectation dans une société donnée (en cours ou terminée). */
    public function getAffectationDans(Societe $societe): ?Affectation
    {
        foreach ($this->getParcours() as $affectation) {
            if ($affectation->getSociete() === $societe) {
                return $affectation;
            }
        }

        return null;
    }

    /**
     * Affectations de la plus récente à la plus ancienne
     * (trié ici car #[OrderBy] ne s'applique qu'au chargement).
     *
     * @return Affectation[]
     */
    public function getParcours(): array
    {
        $liste = $this->affectations->toArray();
        usort($liste, static fn (Affectation $a, Affectation $b) => [$b->getDateEntree(), $b->getId() ?? PHP_INT_MAX] <=> [$a->getDateEntree(), $a->getId() ?? PHP_INT_MAX]);

        return $liste;
    }

    public function getNomComplet(): string
    {
        return trim(mb_strtoupper((string) $this->nom).' '.$this->prenoms);
    }

    public function getInitiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->prenoms, 0, 1).mb_substr((string) $this->nom, 0, 1));
    }

    public function getAge(): ?int
    {
        return $this->dateNaissance?->diff(new \DateTimeImmutable('today'))->y;
    }

    public function getNationaliteNom(): ?string
    {
        return $this->nationalite && Countries::exists($this->nationalite) ? Countries::getName($this->nationalite, 'fr') : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);

        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(string $prenoms): static
    {
        $this->prenoms = trim($prenoms);

        return $this;
    }

    public function getSexe(): ?Sexe
    {
        return $this->sexe;
    }

    public function setSexe(?Sexe $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function setLieuNaissance(?string $lieuNaissance): static
    {
        $this->lieuNaissance = $lieuNaissance;

        return $this;
    }

    public function getNationalite(): ?string
    {
        return $this->nationalite;
    }

    public function setNationalite(?string $nationalite): static
    {
        $this->nationalite = $nationalite;

        return $this;
    }

    public function getSituationFamiliale(): ?SituationFamiliale
    {
        return $this->situationFamiliale;
    }

    public function setSituationFamiliale(?SituationFamiliale $situationFamiliale): static
    {
        $this->situationFamiliale = $situationFamiliale;

        return $this;
    }

    public function getNombreEnfants(): int
    {
        return $this->nombreEnfants;
    }

    public function setNombreEnfants(?int $nombreEnfants): static
    {
        $this->nombreEnfants = (int) $nombreEnfants;

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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getZoneTransport(): ?ZoneTransport
    {
        return $this->zoneTransport;
    }

    public function setZoneTransport(?ZoneTransport $zoneTransport): static
    {
        $this->zoneTransport = $zoneTransport;

        return $this;
    }

    public function getNumeroPiece(): ?string
    {
        return $this->numeroPiece;
    }

    public function setNumeroPiece(?string $numeroPiece): static
    {
        $this->numeroPiece = $numeroPiece;

        return $this;
    }

    public function getNumeroSecuriteSociale(): ?string
    {
        return $this->numeroSecuriteSociale;
    }

    public function setNumeroSecuriteSociale(?string $numeroSecuriteSociale): static
    {
        $this->numeroSecuriteSociale = $numeroSecuriteSociale;

        return $this;
    }

    public function getNumeroAssuranceMaladie(): ?string
    {
        return $this->numeroAssuranceMaladie;
    }

    public function setNumeroAssuranceMaladie(?string $numeroAssuranceMaladie): static
    {
        $this->numeroAssuranceMaladie = $numeroAssuranceMaladie;

        return $this;
    }

    public function getModePaiement(): ModePaiement
    {
        return $this->modePaiement;
    }

    public function setModePaiement(ModePaiement $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

        return $this;
    }

    public function getBanque(): ?string
    {
        return $this->banque;
    }

    public function setBanque(?string $banque): static
    {
        $this->banque = $banque;

        return $this;
    }

    public function getNumeroCompte(): ?string
    {
        return $this->numeroCompte;
    }

    public function setNumeroCompte(?string $numeroCompte): static
    {
        $this->numeroCompte = $numeroCompte;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Affectation> du plus récent au plus ancien */
    public function getAffectations(): Collection
    {
        return $this->affectations;
    }

    public function addAffectation(Affectation $affectation): static
    {
        if (!$this->affectations->contains($affectation)) {
            $this->affectations->add($affectation);
            $affectation->setSalarie($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }
}
