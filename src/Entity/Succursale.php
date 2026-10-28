<?php

namespace App\Entity;

use App\Repository\SuccursaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Regroupement de sociétés (dans un ou plusieurs pays) confié à un ou plusieurs
 * administrateurs. Les administrateurs ont tous les droits sur les sociétés de
 * la succursale ; chaque société est ensuite opérée par ses RH, comptables, trésoriers.
 */
#[ORM\Entity(repositoryClass: SuccursaleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom'], message: 'Une succursale porte déjà ce nom.')]
class Succursale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Societe> */
    #[ORM\OneToMany(targetEntity: Societe::class, mappedBy: 'succursale')]
    #[ORM\OrderBy(['raisonSociale' => \SortDirection::Ascending])]
    private Collection $societes;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'succursalesAdministrees')]
    #[ORM\JoinTable(name: 'succursale_administrateur')]
    private Collection $administrateurs;

    public function __construct()
    {
        $this->societes = new ArrayCollection();
        $this->administrateurs = new ArrayCollection();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);

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

    /** @return Collection<int, Societe> */
    public function getSocietes(): Collection
    {
        return $this->societes;
    }

    /** @return Societe[] */
    public function getSocietesActives(): array
    {
        return array_values($this->societes->filter(static fn (Societe $s) => $s->isActive())->toArray());
    }

    public function addSociete(Societe $societe): static
    {
        if (!$this->societes->contains($societe)) {
            $this->societes->add($societe);
            $societe->setSuccursale($this);
        }

        return $this;
    }

    /** @return Pays[] pays couverts par les sociétés de la succursale */
    public function getPays(): array
    {
        $pays = [];
        foreach ($this->societes as $societe) {
            $pays[$societe->getPays()->getCodeIso()] = $societe->getPays();
        }
        ksort($pays);

        return array_values($pays);
    }

    /** @return Collection<int, User> */
    public function getAdministrateurs(): Collection
    {
        return $this->administrateurs;
    }

    /** @return User[] */
    public function getAdministrateursActifs(): array
    {
        return array_values($this->administrateurs->filter(static fn (User $u) => $u->isActif())->toArray());
    }

    public function addAdministrateur(User $user): static
    {
        if (!$this->administrateurs->contains($user)) {
            $this->administrateurs->add($user);
            $user->getSuccursalesAdministrees()->add($this);
        }

        return $this;
    }

    public function removeAdministrateur(User $user): static
    {
        if ($this->administrateurs->removeElement($user)) {
            $user->getSuccursalesAdministrees()->removeElement($this);
        }

        return $this;
    }

    public function estAdministreePar(User $user): bool
    {
        return $this->administrateurs->contains($user);
    }

    public function getInitiales(): string
    {
        $mots = preg_split('/\s+/', trim((string) $this->nom)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn ($m) => mb_substr($m, 0, 1), array_slice($mots, 0, 2))));
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
