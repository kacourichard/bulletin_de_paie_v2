<?php

namespace App\Entity;

use App\Enum\ModePaiement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paiement du net à payer d'un salarié pour une paie validée (trésorerie).
 * Montant, moyen et coordonnées bancaires sont figés au moment du paiement.
 * Un paiement erroné est annulé (avec motif), jamais supprimé.
 */
#[ORM\Entity]
#[ORM\Index(name: 'paiement_idx', columns: ['periode_id', 'affectation_id'])]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PeriodePaie $periode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $montant = '0';

    #[ORM\Column(length: 20, enumType: ModePaiement::class)]
    private ModePaiement $mode = ModePaiement::VIREMENT;

    /** Banque, ou opérateur pour le mobile money. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $banque = null;

    /** N° de compte, ou n° de téléphone pour le mobile money. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $compte = null;

    /** N° de chèque, référence du virement ou de la transaction. */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $payeLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $enregistrePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $enregistreLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $annuleLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $annulePar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifAnnulation = null;

    public function __construct(PeriodePaie $periode, Affectation $affectation, string $montant, ModePaiement $mode, \DateTimeImmutable $payeLe, ?string $reference, ?User $par)
    {
        $salarie = $affectation->getSalarie();
        $this->periode = $periode;
        $this->affectation = $affectation;
        $this->montant = $montant;
        $this->mode = $mode;
        $this->payeLe = $payeLe;
        $this->reference = $reference ?: null;
        $this->enregistrePar = $par;
        $this->enregistreLe = new \DateTimeImmutable();
        [$this->banque, $this->compte] = match ($mode) {
            ModePaiement::VIREMENT => [$salarie->getBanque(), $salarie->getNumeroCompte()],
            ModePaiement::MOBILE_MONEY => [$salarie->getBanque(), $salarie->getTelephone()],
            default => [null, null],
        };
    }

    public function annuler(string $motif, ?User $par): static
    {
        $this->annuleLe = new \DateTimeImmutable();
        $this->annulePar = $par;
        $this->motifAnnulation = mb_substr($motif, 0, 255);

        return $this;
    }

    public function isAnnule(): bool { return null !== $this->annuleLe; }
    public function getId(): ?int { return $this->id; }
    public function getPeriode(): ?PeriodePaie { return $this->periode; }
    public function getAffectation(): ?Affectation { return $this->affectation; }
    public function getMontant(): string { return $this->montant; }
    public function getMode(): ModePaiement { return $this->mode; }
    public function getBanque(): ?string { return $this->banque; }
    public function getCompte(): ?string { return $this->compte; }
    public function getReference(): ?string { return $this->reference; }
    public function getPayeLe(): ?\DateTimeImmutable { return $this->payeLe; }
    public function getEnregistrePar(): ?User { return $this->enregistrePar; }
    public function getEnregistreLe(): ?\DateTimeImmutable { return $this->enregistreLe; }
    public function getAnnuleLe(): ?\DateTimeImmutable { return $this->annuleLe; }
    public function getAnnulePar(): ?User { return $this->annulePar; }
    public function getMotifAnnulation(): ?string { return $this->motifAnnulation; }
}
