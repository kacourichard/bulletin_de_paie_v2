<?php

namespace App\Service;

use App\Entity\Societe;
use App\Entity\Succursale;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Succursales, leurs administrateurs et la création de leurs sociétés.
 * Une succursale garde toujours au moins un administrateur actif.
 */
class GestionSuccursales
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GestionMembres $membres,
        private readonly SocieteInitialiseur $initialiseur,
    ) {
    }

    /**
     * Crée la succursale avec son premier administrateur.
     *
     * @return ?string mot de passe provisoire si le compte administrateur a été créé
     *
     * @throws \DomainException
     */
    public function creer(Succursale $succursale, string $email, ?string $prenoms, ?string $nom): ?string
    {
        ['user' => $admin, 'motDePasse' => $motDePasse] = $this->membres->obtenirUtilisateur($email, $prenoms, $nom);
        $succursale->addAdministrateur($admin);
        $this->em->persist($succursale);
        $this->em->flush();

        return $motDePasse;
    }

    /**
     * @return ?string mot de passe provisoire si le compte a été créé
     *
     * @throws \DomainException
     */
    public function ajouterAdministrateur(Succursale $succursale, string $email, ?string $prenoms, ?string $nom): ?string
    {
        ['user' => $user, 'motDePasse' => $motDePasse] = $this->membres->obtenirUtilisateur($email, $prenoms, $nom);
        if ($succursale->estAdministreePar($user)) {
            throw new \DomainException(sprintf('%s administre déjà cette succursale.', $user->getNomComplet()));
        }

        $succursale->addAdministrateur($user);
        $this->em->flush();

        return $motDePasse;
    }

    /** @throws \DomainException */
    public function retirerAdministrateur(Succursale $succursale, User $user): void
    {
        $autresActifs = array_filter($succursale->getAdministrateursActifs(), static fn (User $u) => $u !== $user);
        if ([] === $autresActifs) {
            throw new \DomainException('La succursale doit garder au moins un administrateur actif.');
        }

        $succursale->removeAdministrateur($user);
        $this->em->flush();
    }

    /** Crée une société dans la succursale, avec ses rôles RH / Comptable / Trésorier / Consultant. */
    public function creerSociete(Succursale $succursale, Societe $societe): void
    {
        $this->em->wrapInTransaction(function () use ($succursale, $societe) {
            $succursale->addSociete($societe);
            // Paie appliquée toujours explicite : par défaut celle du pays de la société.
            $societe->setLegislationPaie($societe->getLegislationPaie() ?? $societe->getPays());
            $this->em->persist($societe);
            $this->initialiseur->creerRolesParDefaut($societe);
            $this->em->flush();
        });
    }
}
