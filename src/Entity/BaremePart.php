<?php

namespace App\Entity;

use App\Enum\SituationFamiliale;
use Doctrine\ORM\Mapping as ORM;

/**
 * Grille du nombre de parts d'un pays : situation familiale × nombre d'enfants.
 * Au-delà du dernier nombre d'enfants renseigné, la dernière valeur s'applique.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'bareme_part_unique', columns: ['pays_id', 'situation', 'nombre_enfants'])]
class BaremePart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    #[ORM\Column(length: 20, enumType: SituationFamiliale::class)]
    private ?SituationFamiliale $situation = null;

    #[ORM\Column]
    private int $nombreEnfants = 0;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    private string $parts = '1';

    public function __construct(Pays $pays, SituationFamiliale $situation, int $nombreEnfants, string $parts)
    {
        $this->pays = $pays;
        $this->situation = $situation;
        $this->nombreEnfants = $nombreEnfants;
        $this->parts = $parts;
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function getSituation(): ?SituationFamiliale { return $this->situation; }
    public function getNombreEnfants(): int { return $this->nombreEnfants; }
    public function getParts(): string { return $this->parts; }
    public function setParts(string $parts): static { $this->parts = $parts; return $this; }
}
