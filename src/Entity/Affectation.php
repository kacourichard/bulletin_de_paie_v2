<?php

namespace App\Entity;

use App\Enum\MotifSortie;
use App\Enum\TypeContrat;
use App\Repository\AffectationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Passage d'un salarié dans une société : matricule, poste, contrat,
 * dates d'entrée et de sortie, avec l'historique de sa rémunération et de ses avantages.
 */
#[ORM\Entity(repositoryClass: AffectationRepository::class)]
#[ORM\UniqueConstraint(name: 'affectation_matricule_unique', columns: ['societe_id', 'matricule'])]
#[ORM\Index(name: 'affectation_en_cours_idx', columns: ['societe_id', 'date_sortie'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['societe', 'matricule'], message: 'Ce matricule est déjà utilisé dans la société.', errorPath: 'matricule')]
class Affectation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'affectations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Salarie $salarie = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    /** Attribué automatiquement par GestionSalaries s'il est laissé vide à l'embauche. */
    #[ORM\Column(length: 30)]
    private ?string $matricule = null;

    /** Identifiant du salarié dans le logiciel de pointage, s'il diffère du matricule (heures supplémentaires). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $identifiantPointage = null;

    /** Fonction occupée (ex. Chef comptable). */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $poste = null;

    /** Emploi / métier (ex. Comptabilité). */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $metier = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    /** Catégorie professionnelle de la convention collective. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $echelon = null;

    /** Établissement / site de travail. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 20, enumType: TypeContrat::class)]
    private TypeContrat $typeContrat = TypeContrat::CDI;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Indiquez la date d\'entrée.')]
    private ?\DateTimeImmutable $dateEntree = null;

    /** Fin prévue d'un CDD ou d'un stage. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFinContrat = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateSortie = null;

    #[ORM\Column(length: 30, nullable: true, enumType: MotifSortie::class)]
    private ?MotifSortie $motifSortie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaireSortie = null;

    /** Affectation précédente lorsque le salarié a été transféré depuis une autre société. */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Affectation $provenance = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $creePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Remuneration> */
    #[ORM\OneToMany(targetEntity: Remuneration::class, mappedBy: 'affectation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateEffet' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $remunerations;

    /** @var Collection<int, Avantage> */
    #[ORM\OneToMany(targetEntity: Avantage::class, mappedBy: 'affectation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateDebut' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $avantages;

    /** @var Collection<int, HistoriquePoste> */
    #[ORM\OneToMany(targetEntity: HistoriquePoste::class, mappedBy: 'affectation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $historiquePostes;

    public function __construct()
    {
        $this->remunerations = new ArrayCollection();
        $this->avantages = new ArrayCollection();
        $this->historiquePostes = new ArrayCollection();
    }

    /** @return HistoriquePoste[] du plus récent au plus ancien */
    public function getHistoriquePostes(): array
    {
        $liste = $this->historiquePostes->toArray();
        usort($liste, static fn (HistoriquePoste $a, HistoriquePoste $b) => [$b->getDateEffet(), $b->getId() ?? PHP_INT_MAX] <=> [$a->getDateEffet(), $a->getId() ?? PHP_INT_MAX]);

        return $liste;
    }

    public function addHistoriquePoste(HistoriquePoste $historique): static
    {
        if (!$this->historiquePostes->contains($historique)) {
            $this->historiquePostes->add($historique);
        }

        return $this;
    }

    public function getMetier(): ?string
    {
        return $this->metier;
    }

    public function setMetier(?string $metier): static
    {
        $this->metier = $metier;

        return $this;
    }

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validerDates(ExecutionContextInterface $context): void
    {
        if ($this->dateEntree && $this->dateFinContrat && $this->dateFinContrat < $this->dateEntree) {
            $context->buildViolation('La fin de contrat doit être postérieure à l\'entrée.')->atPath('dateFinContrat')->addViolation();
        }
    }

    public function isEnCours(): bool
    {
        return null === $this->dateSortie;
    }

    /**
     * Historique des salaires, du plus récent au plus ancien.
     * Trié ici car #[OrderBy] ne s'applique qu'au chargement depuis la base.
     *
     * @return Remuneration[]
     */
    public function getHistoriqueRemunerations(): array
    {
        $liste = $this->remunerations->toArray();
        usort($liste, static fn (Remuneration $a, Remuneration $b) => [$b->getDateEffet(), $b->getId() ?? PHP_INT_MAX] <=> [$a->getDateEffet(), $a->getId() ?? PHP_INT_MAX]);

        return $liste;
    }

    /** Rémunération applicable à une date (par défaut aujourd'hui). */
    public function getRemunerationA(?\DateTimeInterface $date = null): ?Remuneration
    {
        $date ??= new \DateTimeImmutable('today');
        foreach ($this->getHistoriqueRemunerations() as $remuneration) {
            if ($remuneration->getDateEffet() <= $date) {
                return $remuneration;
            }
        }

        return null;
    }

    /** Dernière rémunération enregistrée (peut être future). */
    public function getDerniereRemuneration(): ?Remuneration
    {
        return $this->getHistoriqueRemunerations()[0] ?? null;
    }

    /** Première rémunération (embauche ou transfert). */
    public function getPremiereRemuneration(): ?Remuneration
    {
        $historique = $this->getHistoriqueRemunerations();

        return end($historique) ?: null;
    }

    /** @return Avantage[] avantages en vigueur à une date (par défaut aujourd'hui) */
    public function getAvantagesA(?\DateTimeInterface $date = null): array
    {
        $date ??= new \DateTimeImmutable('today');

        return array_values($this->avantages->filter(static fn (Avantage $a) => $a->estEnVigueurA($date))->toArray());
    }

    /** Total des avantages en espèces en vigueur. */
    public function getTotalAvantagesA(?\DateTimeInterface $date = null): string
    {
        $total = '0';
        // Les retenues fixes (cotisation syndicale…) viennent en déduction.
        foreach ($this->getAvantagesA($date) as $avantage) {
            $total = false === $avantage->getRubrique()?->isGain()
                ? bcsub($total, $avantage->getMontant(), 2)
                : bcadd($total, $avantage->getMontant(), 2);
        }

        return $total;
    }

    public function getAnciennete(?\DateTimeInterface $date = null): ?\DateInterval
    {
        $fin = $this->dateSortie ?? $date ?? new \DateTimeImmutable('today');

        return $this->dateEntree?->diff($fin);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalarie(): ?Salarie
    {
        return $this->salarie;
    }

    public function setSalarie(?Salarie $salarie): static
    {
        $this->salarie = $salarie;

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

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule ? mb_strtoupper(trim($matricule)) : null;

        return $this;
    }

    public function getIdentifiantPointage(): ?string
    {
        return $this->identifiantPointage;
    }

    public function setIdentifiantPointage(?string $identifiant): static
    {
        $this->identifiantPointage = $identifiant ? mb_strtoupper(trim($identifiant)) : null;

        return $this;
    }

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(?string $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getEchelon(): ?string
    {
        return $this->echelon;
    }

    public function setEchelon(?string $echelon): static
    {
        $this->echelon = $echelon;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getTypeContrat(): TypeContrat
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(TypeContrat $typeContrat): static
    {
        $this->typeContrat = $typeContrat;

        return $this;
    }

    public function getDateEntree(): ?\DateTimeImmutable
    {
        return $this->dateEntree;
    }

    public function setDateEntree(?\DateTimeImmutable $dateEntree): static
    {
        $this->dateEntree = $dateEntree;

        return $this;
    }

    public function getDateFinContrat(): ?\DateTimeImmutable
    {
        return $this->dateFinContrat;
    }

    public function setDateFinContrat(?\DateTimeImmutable $dateFinContrat): static
    {
        $this->dateFinContrat = $dateFinContrat;

        return $this;
    }

    public function getDateSortie(): ?\DateTimeImmutable
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTimeImmutable $dateSortie): static
    {
        $this->dateSortie = $dateSortie;

        return $this;
    }

    public function getMotifSortie(): ?MotifSortie
    {
        return $this->motifSortie;
    }

    public function setMotifSortie(?MotifSortie $motifSortie): static
    {
        $this->motifSortie = $motifSortie;

        return $this;
    }

    /** Sortie de la société pendant la période (hors transfert dans le groupe) : son bulletin est un solde de tout compte. */
    public function isSoldeToutCompte(\DateTimeInterface $debut, \DateTimeInterface $fin): bool
    {
        return null !== $this->dateSortie && MotifSortie::TRANSFERT !== $this->motifSortie
            && $this->dateSortie >= $debut && $this->dateSortie <= $fin;
    }

    public function getCommentaireSortie(): ?string
    {
        return $this->commentaireSortie;
    }

    public function setCommentaireSortie(?string $commentaireSortie): static
    {
        $this->commentaireSortie = $commentaireSortie;

        return $this;
    }

    public function getProvenance(): ?Affectation
    {
        return $this->provenance;
    }

    public function setProvenance(?Affectation $provenance): static
    {
        $this->provenance = $provenance;

        return $this;
    }

    public function getCreePar(): ?User
    {
        return $this->creePar;
    }

    public function setCreePar(?User $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Remuneration> de la plus récente à la plus ancienne */
    public function getRemunerations(): Collection
    {
        return $this->remunerations;
    }

    public function addRemuneration(Remuneration $remuneration): static
    {
        if (!$this->remunerations->contains($remuneration)) {
            $this->remunerations->add($remuneration);
            $remuneration->setAffectation($this);
        }

        return $this;
    }

    /** @return Collection<int, Avantage> */
    public function getAvantages(): Collection
    {
        return $this->avantages;
    }

    public function addAvantage(Avantage $avantage): static
    {
        if (!$this->avantages->contains($avantage)) {
            $this->avantages->add($avantage);
            $avantage->setAffectation($this);
        }

        return $this;
    }

    public function removeAvantage(Avantage $avantage): static
    {
        $this->avantages->removeElement($avantage);

        return $this;
    }
}
