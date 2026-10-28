<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rôle propre à une société (Administrateur, Comptable, Trésorier...).
 * Chaque société reçoit les rôles par défaut à sa création et peut
 * en ajuster les permissions ou créer ses propres rôles.
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\UniqueConstraint(name: 'role_unique', columns: ['societe_id', 'code'])]
#[UniqueEntity(fields: ['societe', 'code'], message: 'Ce code de rôle existe déjà dans la société.', errorPath: 'code')]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'roles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Societe $societe = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z][A-Z0-9_]*$/', message: 'Majuscules, chiffres et _ uniquement (ex. CHEF_PAIE).')]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** Couleur du badge (nom de couleur Bootstrap : primary, success...). */
    #[ORM\Column(length: 20)]
    private string $couleur = 'secondary';

    /** Rôle livré par défaut : il ne peut pas être supprimé. */
    #[ORM\Column]
    private bool $systeme = false;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\OrderBy(['module' => \SortDirection::Ascending, 'ordre' => \SortDirection::Ascending])]
    private Collection $permissions;

    /** @var Collection<int, Membre> */
    #[ORM\OneToMany(targetEntity: Membre::class, mappedBy: 'role')]
    private Collection $membres;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->membres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCouleur(): string
    {
        return $this->couleur;
    }

    public function setCouleur(string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function isSysteme(): bool
    {
        return $this->systeme;
    }

    public function setSysteme(bool $systeme): static
    {
        $this->systeme = $systeme;

        return $this;
    }

    /** @return Collection<int, Permission> */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    public function aPermission(string $code): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Membre> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function __toString(): string
    {
        return (string) $this->libelle;
    }
}
