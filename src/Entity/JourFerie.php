<?php

namespace App\Entity;

use App\Repository\JourFerieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Jour férié d'un pays (géré par la plateforme) ou propre à une société (journée offerte, fête locale).
 * Sert au calcul des heures supplémentaires : un férié chômé et payé est hors quota ; la veille et le lendemain
 * peuvent suivre la même règle (équipes en horaires décalés), comme dans le projet heures-sup.
 */
#[ORM\Entity(repositoryClass: JourFerieRepository::class)]
#[ORM\Index(name: 'jour_ferie_date_idx', columns: ['pays_id', 'date'])]
class JourFerie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pays $pays = null;

    /** null = jour férié national ; sinon jour propre à la société. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Societe $societe = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Indiquez la date.')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Indiquez le libellé.')]
    private ?string $libelle = null;

    #[ORM\Column]
    private bool $chomePaye = true;

    #[ORM\Column]
    private bool $veille = false;

    #[ORM\Column]
    private bool $lendemain = false;

    /** Date mobile (fêtes religieuses) à confirmer chaque année. */
    #[ORM\Column]
    private bool $aConfirmer = false;

    public function __construct(Pays $pays, ?Societe $societe = null)
    {
        $this->pays = $pays;
        $this->societe = $societe;
    }

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function getSociete(): ?Societe { return $this->societe; }
    public function isNational(): bool { return null === $this->societe; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(?\DateTimeImmutable $date): static { $this->date = $date; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $libelle): static { $this->libelle = $libelle ? trim($libelle) : null; return $this; }
    public function isChomePaye(): bool { return $this->chomePaye; }
    public function setChomePaye(bool $v): static { $this->chomePaye = $v; return $this; }
    public function isVeille(): bool { return $this->veille; }
    public function setVeille(bool $v): static { $this->veille = $v; return $this; }
    public function isLendemain(): bool { return $this->lendemain; }
    public function setLendemain(bool $v): static { $this->lendemain = $v; return $this; }
    public function isAConfirmer(): bool { return $this->aConfirmer; }
    public function setAConfirmer(bool $v): static { $this->aConfirmer = $v; return $this; }
}
