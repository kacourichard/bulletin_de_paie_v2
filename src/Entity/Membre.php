<?php

namespace App\Entity;

use App\Repository\MembreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Appartenance d'un utilisateur à une société, avec le rôle qu'il y tient.
 * Un utilisateur a exactement un rôle par société.
 */
#[ORM\Entity(repositoryClass: MembreRepository::class)]
#[ORM\UniqueConstraint(name: 'membre_unique', columns: ['user_id', 'societe_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['user', 'societe'], message: 'Cet utilisateur est déjà membre de la société.', errorPath: 'user')]
class Membre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Societe $societe = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez un rôle.')]
    private ?Role $role = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(?User $user = null, ?Societe $societe = null, ?Role $role = null)
    {
        $this->user = $user;
        $this->societe = $societe;
        $this->role = $role;
    }

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validerRole(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->role && $this->societe && $this->role->getSociete() !== $this->societe) {
            $context->buildViolation('Ce rôle n\'appartient pas à la société.')->atPath('role')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function peut(string $permission): bool
    {
        return $this->actif && $this->role?->aPermission($permission);
    }
}
