<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Poste occupé par le salarié dans la société à partir d'une date
 * (embauche, promotion, mutation...). Le poste courant est recopié sur l'Affectation.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class HistoriquePoste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historiquePostes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Affectation $affectation = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateEffet = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $metier = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $echelon = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $creePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Photographie du poste actuel de l'affectation. */
    public static function depuis(Affectation $affectation, \DateTimeImmutable $dateEffet, ?string $commentaire, ?User $auteur): self
    {
        $h = new self();
        $h->affectation = $affectation;
        $h->dateEffet = $dateEffet;
        $h->poste = $affectation->getPoste();
        $h->metier = $affectation->getMetier();
        $h->service = $affectation->getService();
        $h->categorie = $affectation->getCategorie();
        $h->echelon = $affectation->getEchelon();
        $h->commentaire = $commentaire;
        $h->creePar = $auteur;

        return $h;
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

    public function getAffectation(): ?Affectation
    {
        return $this->affectation;
    }

    public function getDateEffet(): ?\DateTimeImmutable
    {
        return $this->dateEffet;
    }

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function getMetier(): ?string
    {
        return $this->metier;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function getEchelon(): ?string
    {
        return $this->echelon;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function getCreePar(): ?User
    {
        return $this->creePar;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
