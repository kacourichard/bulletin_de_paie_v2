<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Action élémentaire qu'un rôle peut effectuer (ex. « salarie.creer »).
 * La liste fait référence dans App\Security\PermissionCatalogue et est
 * synchronisée en base par la commande app:permissions:sync.
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    /** Module fonctionnel servant à regrouper les permissions à l'écran. */
    #[ORM\Column(length: 50)]
    private ?string $module = null;

    #[ORM\Column]
    private int $ordre = 0;

    public function __construct(string $code, string $libelle, string $module, int $ordre = 0)
    {
        $this->code = $code;
        $this->libelle = $libelle;
        $this->module = $module;
        $this->ordre = $ordre;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
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

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->libelle;
    }
}
