<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte de connexion. Les droits ne sont pas portés ici : ils dépendent du
 * rôle tenu dans chaque société (voir Membre), sauf pour le super-administrateur
 * de la plateforme qui gère pays, sociétés et comptes.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $prenoms = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $superAdmin = false;

    #[ORM\Column]
    private bool $actif = true;

    /** Force le changement du mot de passe à la prochaine connexion. */
    #[ORM\Column]
    private bool $doitChangerMotDePasse = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $langue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $derniereConnexion = null;

    /** @var Collection<int, Membre> */
    #[ORM\OneToMany(targetEntity: Membre::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $membres;

    /** @var Collection<int, Succursale> */
    #[ORM\ManyToMany(targetEntity: Succursale::class, mappedBy: 'administrateurs')]
    private Collection $succursalesAdministrees;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->succursalesAdministrees = new ArrayCollection();
    }

    /** @return Collection<int, Succursale> */
    public function getSuccursalesAdministrees(): Collection
    {
        return $this->succursalesAdministrees;
    }

    /** @return Succursale[] */
    public function getSuccursalesAdministreesActives(): array
    {
        return array_values($this->succursalesAdministrees->filter(static fn (Succursale $s) => $s->isActive())->toArray());
    }

    public function isAdministrateurSuccursale(): bool
    {
        return [] !== $this->getSuccursalesAdministreesActives();
    }

    /** Vrai si l'utilisateur administre la succursale (active) de cette société. */
    public function administre(Societe $societe): bool
    {
        $succursale = $societe->getSuccursale();

        return null !== $succursale && $succursale->isActive() && $this->succursalesAdministrees->contains($succursale);
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Rôles Symfony « techniques ». Les permissions métier passent par
     * le PermissionVoter et le rôle tenu dans la société courante.
     */
    public function getRoles(): array
    {
        return $this->superAdmin ? ['ROLE_USER', 'ROLE_SUPER_ADMIN'] : ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(string $prenoms): static
    {
        $this->prenoms = $prenoms;

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim($this->prenoms.' '.$this->nom);
    }

    public function getInitiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->prenoms, 0, 1).mb_substr((string) $this->nom, 0, 1));
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

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    public function setSuperAdmin(bool $superAdmin): static
    {
        $this->superAdmin = $superAdmin;

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

    public function isDoitChangerMotDePasse(): bool
    {
        return $this->doitChangerMotDePasse;
    }

    public function setDoitChangerMotDePasse(bool $doitChangerMotDePasse): static
    {
        $this->doitChangerMotDePasse = $doitChangerMotDePasse;

        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(?string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDerniereConnexion(): ?\DateTimeImmutable
    {
        return $this->derniereConnexion;
    }

    public function setDerniereConnexion(?\DateTimeImmutable $derniereConnexion): static
    {
        $this->derniereConnexion = $derniereConnexion;

        return $this;
    }

    /** @return Collection<int, Membre> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    /** @return Membre[] adhésions actives dans des sociétés actives */
    public function getMembresActifs(): array
    {
        return array_values($this->membres->filter(
            static fn (Membre $m) => $m->isActif() && $m->getSociete()->isActive()
        )->toArray());
    }

    public function addMembre(Membre $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setUser($this);
        }

        return $this;
    }

    public function getMembrePour(Societe $societe): ?Membre
    {
        foreach ($this->membres as $membre) {
            if ($membre->getSociete() === $societe) {
                return $membre;
            }
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }
}
