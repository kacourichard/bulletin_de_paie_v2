<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Historique des envois de bulletins par e-mail. */
#[ORM\Entity]
#[ORM\Index(name: 'envoi_bulletin_idx', columns: ['periode_id', 'affectation_id'])]
class EnvoiBulletin
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

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private bool $succes = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $erreur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $envoyePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $envoyeLe = null;

    public function __construct(PeriodePaie $periode, Affectation $affectation, string $email, ?User $envoyePar, bool $succes = true, ?string $erreur = null)
    {
        $this->periode = $periode;
        $this->affectation = $affectation;
        $this->email = $email;
        $this->envoyePar = $envoyePar;
        $this->succes = $succes;
        $this->erreur = $erreur ? mb_substr($erreur, 0, 255) : null;
        $this->envoyeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPeriode(): ?PeriodePaie { return $this->periode; }
    public function getAffectation(): ?Affectation { return $this->affectation; }
    public function getEmail(): ?string { return $this->email; }
    public function isSucces(): bool { return $this->succes; }
    public function getErreur(): ?string { return $this->erreur; }
    public function getEnvoyePar(): ?User { return $this->envoyePar; }
    public function getEnvoyeLe(): ?\DateTimeImmutable { return $this->envoyeLe; }
}
