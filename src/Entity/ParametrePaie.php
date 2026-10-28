<?php

namespace App\Entity;

use App\Enum\ArrondiNet;
use App\Enum\AssietteCotisation;
use App\Enum\BaseAnciennete;
use App\Enum\ModeExoneration;
use App\Repository\ParametrePaieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Règles de calcul de la paie d'un pays, définies par le super-administrateur.
 * Première partie du bulletin : prime d'ancienneté, nombre de parts, salaire brut, brut imposable.
 */
#[ORM\Entity(repositoryClass: ParametrePaieRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ParametrePaie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    // --- Prime d'ancienneté ---------------------------------------------------

    #[ORM\Column]
    private bool $ancienneteActive = false;

    /** Ancienneté (en années révolues) à partir de laquelle la prime est due. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $ancienneteAnneesMin = 2;

    /** Taux (%) atteint à l'ancienneté minimale. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100)]
    private string $ancienneteTauxInitial = '2';

    /** Taux (%) ajouté par année supplémentaire. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100)]
    private string $ancienneteTauxParAn = '1';

    /** Taux (%) maximum. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100)]
    private string $ancienneteTauxMax = '25';

    #[ORM\Column(length: 30, enumType: BaseAnciennete::class)]
    private BaseAnciennete $ancienneteBase = BaseAnciennete::CATEGORIEL;

    /** Compter l'ancienneté depuis la première société du groupe (transferts). */
    #[ORM\Column]
    private bool $ancienneteDepuisGroupe = true;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private string $ancienneteCode = 'ANC';

    // --- Temps de travail -----------------------------------------------------

    /** Jours de référence d'un mois complet (prorata : jours travaillés / jours de base). */
    #[ORM\Column(options: ['default' => 30])]
    #[Assert\Range(min: 1, max: 31)]
    private int $joursMois = 30;

    /** Proratiser salaire, sursalaire et avantages fixes selon les jours travaillés. */
    #[ORM\Column(options: ['default' => true])]
    private bool $proratiserFixes = true;

    /** Heures mensuelles légales (taux horaire = (catégoriel + sursalaire) / heures). */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, options: ['default' => '173.33'])]
    #[Assert\Positive]
    private string $heuresMois = '173.33';

    /** Jours de congé acquis par mois de travail (cumul « Congé acquis » du bulletin ; v1 Côte d'Ivoire : 2,57). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '2.50'])]
    #[Assert\PositiveOrZero]
    private string $joursCongeParMois = '2.50';

    public function getJoursCongeParMois(): string { return $this->joursCongeParMois; }
    public function setJoursCongeParMois(?string $v): static { $this->joursCongeParMois = '' === (string) $v ? '0' : $v; return $this; }

    public function getJoursMois(): int
    {
        return $this->joursMois;
    }

    public function setJoursMois(?int $joursMois): static
    {
        $this->joursMois = max(1, (int) $joursMois);

        return $this;
    }

    public function isProratiserFixes(): bool
    {
        return $this->proratiserFixes;
    }

    public function setProratiserFixes(bool $proratiserFixes): static
    {
        $this->proratiserFixes = $proratiserFixes;

        return $this;
    }

    public function getHeuresMois(): string
    {
        return $this->heuresMois;
    }

    public function setHeuresMois(?string $heuresMois): static
    {
        $this->heuresMois = $heuresMois ?: '173.33';

        return $this;
    }

    // --- Nombre de parts ------------------------------------------------------

    /** Le pays utilise un nombre de parts (quotient familial) pour l'impôt. */
    #[ORM\Column]
    private bool $partsActives = false;

    // --- Salaire brut ---------------------------------------------------------

    #[ORM\Column]
    private bool $brutInclutSursalaire = true;

    #[ORM\Column]
    private bool $brutInclutAnciennete = true;

    /** Gains de la section 1 (éléments soumis). */
    #[ORM\Column]
    private bool $brutInclutGainsSoumis = true;

    /** Gains de la section 3 (éléments non soumis). */
    #[ORM\Column]
    private bool $brutInclutGainsNonSoumis = false;

    // --- Brut imposable -------------------------------------------------------

    #[ORM\Column(length: 20, enumType: ModeExoneration::class)]
    private ModeExoneration $modeExoneration = ModeExoneration::PLAFONNEE;

    /** Nombre de décimales des montants calculés (0 pour le F CFA, 2 pour l'euro). */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 2)]
    private int $decimales = 0;

    /** Arrondi du net à payer (Côte d'Ivoire : au 5 ou à la dizaine). */
    #[ORM\Column(length: 20, enumType: ArrondiNet::class, options: ['default' => 'AUCUN'])]
    private ArrondiNet $arrondiNet = ArrondiNet::AUCUN;

    // --- Impôt sur le salaire (2e partie) ------------------------------------

    /** Impôt retenu selon un barème progressif par tranches, moins une réduction selon les parts. */
    #[ORM\Column(options: ['default' => false])]
    private bool $impotActif = false;

    #[ORM\Column(length: 20, options: ['default' => '600'])]
    private string $impotCode = '600';

    #[ORM\Column(length: 100, options: ['default' => 'Impôt sur le salaire'])]
    private string $impotLibelle = 'Impôt sur le salaire';

    #[ORM\Column(length: 30, enumType: AssietteCotisation::class, options: ['default' => 'BRUT_IMPOSABLE'])]
    private AssietteCotisation $impotAssiette = AssietteCotisation::BRUT_IMPOSABLE;

    // --- Codes des lignes de synthèse du bulletin ----------------------------

    #[ORM\Column(length: 20, options: ['default' => '0010'])]
    private string $codeSalaireBase = '0010';

    #[ORM\Column(length: 20, options: ['default' => '2000'])]
    private string $codeBrut = '2000';

    #[ORM\Column(length: 20, options: ['default' => '2001'])]
    private string $codeBrutImposable = '2001';

    #[ORM\Column(length: 20, options: ['default' => '4000'])]
    private string $codeSalaireNet = '4000';

    /** Ligne d'arrondi du net (bulletin ivoirien : 0954). */
    #[ORM\Column(length: 20, options: ['default' => '0954'])]
    private string $codeArrondi = '0954';

    public function isImpotActif(): bool { return $this->impotActif; }
    public function setImpotActif(bool $v): static { $this->impotActif = $v; return $this; }
    public function getImpotCode(): string { return $this->impotCode; }
    public function setImpotCode(?string $v): static { $this->impotCode = trim((string) $v) ?: '600'; return $this; }
    public function getImpotLibelle(): string { return $this->impotLibelle; }
    public function setImpotLibelle(?string $v): static { $this->impotLibelle = trim((string) $v) ?: 'Impôt sur le salaire'; return $this; }
    public function getImpotAssiette(): AssietteCotisation { return $this->impotAssiette; }
    public function setImpotAssiette(AssietteCotisation $v): static { $this->impotAssiette = $v; return $this; }
    public function getCodeSalaireBase(): string { return $this->codeSalaireBase; }
    public function setCodeSalaireBase(?string $v): static { $this->codeSalaireBase = trim((string) $v) ?: '0010'; return $this; }
    public function getCodeBrut(): string { return $this->codeBrut; }
    public function setCodeBrut(?string $v): static { $this->codeBrut = trim((string) $v) ?: '2000'; return $this; }
    public function getCodeBrutImposable(): string { return $this->codeBrutImposable; }
    public function setCodeBrutImposable(?string $v): static { $this->codeBrutImposable = trim((string) $v) ?: '2001'; return $this; }
    public function getCodeSalaireNet(): string { return $this->codeSalaireNet; }
    public function setCodeSalaireNet(?string $v): static { $this->codeSalaireNet = trim((string) $v) ?: '4000'; return $this; }
    public function getCodeArrondi(): string { return $this->codeArrondi; }
    public function setCodeArrondi(?string $v): static { $this->codeArrondi = trim((string) $v) ?: '0954'; return $this; }

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct(Pays $pays)
    {
        $this->pays = $pays;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function majDate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function valider(ExecutionContextInterface $context): void
    {
        if ($this->ancienneteActive && bccomp($this->ancienneteTauxMax, $this->ancienneteTauxInitial, 2) < 0) {
            $context->buildViolation('Le taux maximum doit être supérieur ou égal au taux initial.')->atPath('ancienneteTauxMax')->addViolation();
        }
    }

    /** Taux d'ancienneté (%) pour un nombre d'années révolues ; 0 si non applicable. */
    public function tauxAnciennete(int $annees): string
    {
        if (!$this->ancienneteActive || $annees < $this->ancienneteAnneesMin) {
            return '0';
        }
        $taux = bcadd($this->ancienneteTauxInitial, bcmul((string) ($annees - $this->ancienneteAnneesMin), $this->ancienneteTauxParAn, 2), 2);

        return bccomp($taux, $this->ancienneteTauxMax, 2) > 0 ? $this->ancienneteTauxMax : $taux;
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }

    public function isAncienneteActive(): bool { return $this->ancienneteActive; }
    public function setAncienneteActive(bool $v): static { $this->ancienneteActive = $v; return $this; }
    public function getAncienneteAnneesMin(): int { return $this->ancienneteAnneesMin; }
    public function setAncienneteAnneesMin(?int $v): static { $this->ancienneteAnneesMin = (int) $v; return $this; }
    public function getAncienneteTauxInitial(): string { return $this->ancienneteTauxInitial; }
    public function setAncienneteTauxInitial(?string $v): static { $this->ancienneteTauxInitial = $v ?? '0'; return $this; }
    public function getAncienneteTauxParAn(): string { return $this->ancienneteTauxParAn; }
    public function setAncienneteTauxParAn(?string $v): static { $this->ancienneteTauxParAn = $v ?? '0'; return $this; }
    public function getAncienneteTauxMax(): string { return $this->ancienneteTauxMax; }
    public function setAncienneteTauxMax(?string $v): static { $this->ancienneteTauxMax = $v ?? '0'; return $this; }
    public function getAncienneteBase(): BaseAnciennete { return $this->ancienneteBase; }
    public function setAncienneteBase(BaseAnciennete $v): static { $this->ancienneteBase = $v; return $this; }
    public function isAncienneteDepuisGroupe(): bool { return $this->ancienneteDepuisGroupe; }
    public function setAncienneteDepuisGroupe(bool $v): static { $this->ancienneteDepuisGroupe = $v; return $this; }
    public function getAncienneteCode(): string { return $this->ancienneteCode; }
    public function setAncienneteCode(?string $v): static { $this->ancienneteCode = mb_strtoupper(trim((string) $v)) ?: 'ANC'; return $this; }

    public function isPartsActives(): bool { return $this->partsActives; }
    public function setPartsActives(bool $v): static { $this->partsActives = $v; return $this; }

    public function isBrutInclutSursalaire(): bool { return $this->brutInclutSursalaire; }
    public function setBrutInclutSursalaire(bool $v): static { $this->brutInclutSursalaire = $v; return $this; }
    public function isBrutInclutAnciennete(): bool { return $this->brutInclutAnciennete; }
    public function setBrutInclutAnciennete(bool $v): static { $this->brutInclutAnciennete = $v; return $this; }
    public function isBrutInclutGainsSoumis(): bool { return $this->brutInclutGainsSoumis; }
    public function setBrutInclutGainsSoumis(bool $v): static { $this->brutInclutGainsSoumis = $v; return $this; }
    public function isBrutInclutGainsNonSoumis(): bool { return $this->brutInclutGainsNonSoumis; }
    public function setBrutInclutGainsNonSoumis(bool $v): static { $this->brutInclutGainsNonSoumis = $v; return $this; }

    public function getModeExoneration(): ModeExoneration { return $this->modeExoneration; }
    public function setModeExoneration(ModeExoneration $v): static { $this->modeExoneration = $v; return $this; }
    public function getDecimales(): int { return $this->decimales; }
    public function setDecimales(?int $v): static { $this->decimales = (int) $v; return $this; }
    public function getArrondiNet(): ArrondiNet { return $this->arrondiNet; }
    public function setArrondiNet(ArrondiNet $v): static { $this->arrondiNet = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }
    public function setUpdatedBy(?User $u): static { $this->updatedBy = $u; return $this; }
}
