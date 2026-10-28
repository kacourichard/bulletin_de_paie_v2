<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Réduction mensuelle de l'impôt sur le salaire selon le nombre de parts
 * (ex. Côte d'Ivoire : réduction pour charges de famille).
 */
#[ORM\Entity]
class ReductionImpot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    private string $parts = '1';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $montant = '0';

    public function __construct(Pays $pays, string $parts, string $montant)
    {
        $this->pays = $pays;
        $this->parts = $parts;
        $this->montant = $montant;
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function getParts(): string { return $this->parts; }
    public function getMontant(): string { return $this->montant; }
}
