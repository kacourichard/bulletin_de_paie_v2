<?php

namespace App\Entity;

use App\HeuresSup\RegimeHoraire;
use Doctrine\ORM\Mapping as ORM;

/**
 * Relevé de pointage importé pour le calcul des heures supplémentaires d'un mois de paie.
 * Les heures sont conservées agrégées par salarié et par jour (donnees) : le calcul est refait à chaque
 * consultation, il suit donc les corrections du calendrier des jours fériés.
 */
#[ORM\Entity]
#[ORM\Index(name: 'import_pointage_mois_idx', columns: ['societe_id', 'mois'])]
class ImportPointage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Societe $societe = null;

    /** Mois de paie auquel les heures sont rattachées (premier jour du mois). */
    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $mois = null;

    #[ORM\Column(length: 255)]
    private ?string $nomFichier = null;

    /** Régime appliqué quand l'horaire du pointage n'indique ni 40 h ni 42 h. */
    #[ORM\Column(length: 3, enumType: RegimeHoraire::class)]
    private RegimeHoraire $regimeParDefaut = RegimeHoraire::H40;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $du = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $au = null;

    #[ORM\Column]
    private int $nombreLignes = 0;

    /**
     * Pointage agrégé : [identifiant => [nom, horaire, regime (40H|42H|null), jours => [Y-m-d => [heures, nuit]]]].
     *
     * @var array<string, array{nom: string, horaire: ?string, regime: ?string, jours: array<string, array{heures: float, nuit: bool}>}>
     */
    #[ORM\Column(type: 'json')]
    private array $donnees = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $importePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $importeLe = null;

    /** Dernier report des heures dans la paie du mois. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reporteLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reportePar = null;

    /** Montant de la prime de panier par jour retenu au dernier report. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $montantPanier = null;

    public function __construct(Societe $societe, \DateTimeImmutable $mois, string $nomFichier, RegimeHoraire $regimeParDefaut, ?User $importePar)
    {
        $this->societe = $societe;
        $this->mois = $mois->modify('first day of this month')->setTime(0, 0);
        $this->nomFichier = mb_substr($nomFichier, 0, 255);
        $this->regimeParDefaut = $regimeParDefaut;
        $this->importePar = $importePar;
        $this->importeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSociete(): ?Societe { return $this->societe; }
    public function getMois(): ?\DateTimeImmutable { return $this->mois; }
    public function getNomFichier(): ?string { return $this->nomFichier; }
    public function getRegimeParDefaut(): RegimeHoraire { return $this->regimeParDefaut; }
    public function getDu(): ?\DateTimeImmutable { return $this->du; }
    public function getAu(): ?\DateTimeImmutable { return $this->au; }
    public function getNombreLignes(): int { return $this->nombreLignes; }
    public function getDonnees(): array { return $this->donnees; }
    public function getImportePar(): ?User { return $this->importePar; }
    public function getImporteLe(): ?\DateTimeImmutable { return $this->importeLe; }
    public function getReporteLe(): ?\DateTimeImmutable { return $this->reporteLe; }
    public function getReportePar(): ?User { return $this->reportePar; }
    public function getMontantPanier(): ?string { return $this->montantPanier; }

    public function definirDonnees(array $donnees, \DateTimeImmutable $du, \DateTimeImmutable $au, int $nombreLignes): static
    {
        $this->donnees = $donnees;
        $this->du = $du;
        $this->au = $au;
        $this->nombreLignes = $nombreLignes;

        return $this;
    }

    public function marquerReporte(?User $par, ?string $montantPanier): static
    {
        $this->reporteLe = new \DateTimeImmutable();
        $this->reportePar = $par;
        $this->montantPanier = $montantPanier;

        return $this;
    }
}
