<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tranche du barème mensuel de l'impôt sur le salaire d'un pays (calcul progressif).
 * Ex. Côte d'Ivoire (ITS) : 0 à 75 000 → 0 % ; 75 001 à 240 000 → 16 % ; ...
 */
#[ORM\Entity]
class TrancheImpot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    /** Début de la tranche (exclu : la tranche s'applique au-delà de ce montant). */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $de = '0';

    /** Fin de la tranche (incluse) ; vide = sans limite. */
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $a = null;

    /** Taux en %. */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 3)]
    private string $taux = '0';

    public function __construct(Pays $pays, string $de, ?string $a, string $taux)
    {
        $this->pays = $pays;
        $this->de = $de;
        $this->a = $a;
        $this->taux = $taux;
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function getDe(): string { return $this->de; }
    public function getA(): ?string { return $this->a; }
    public function getTaux(): string { return $this->taux; }
}
