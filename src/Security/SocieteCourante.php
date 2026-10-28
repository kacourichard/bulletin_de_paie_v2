<?php

namespace App\Security;

use App\Entity\Membre;
use App\Entity\Societe;
use App\Entity\User;
use App\Repository\SocieteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Société sur laquelle l'utilisateur connecté travaille, conservée en session.
 */
class SocieteCourante
{
    private const CLE_SESSION = 'societe_courante_id';

    private ?Societe $cache = null;
    private bool $resolue = false;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly SocieteRepository $societes,
    ) {
    }

    public function get(): ?Societe
    {
        if ($this->resolue) {
            return $this->cache;
        }
        $this->resolue = true;

        $user = $this->user();
        if (null === $user) {
            return null;
        }

        $id = $this->requestStack->getSession()->get(self::CLE_SESSION);
        if (null !== $id) {
            $societe = $this->societes->find($id);
            if (null !== $societe && $this->peutAcceder($user, $societe)) {
                return $this->cache = $societe;
            }
        }

        // Sélection automatique quand il n'y a qu'un seul choix possible.
        $disponibles = $this->disponibles();
        if (1 === count($disponibles)) {
            $this->definir($disponibles[0]);

            return $this->cache;
        }

        return null;
    }

    public function definir(Societe $societe): void
    {
        $user = $this->user();
        if (null === $user || !$this->peutAcceder($user, $societe)) {
            throw new \LogicException('Accès refusé à cette société.');
        }

        $this->requestStack->getSession()->set(self::CLE_SESSION, $societe->getId());
        $this->cache = $societe;
        $this->resolue = true;
    }

    public function oublier(): void
    {
        $this->requestStack->getSession()->remove(self::CLE_SESSION);
        $this->cache = null;
        $this->resolue = false;
    }

    /** Adhésion de l'utilisateur connecté dans la société courante. */
    public function getMembre(): ?Membre
    {
        $societe = $this->get();
        $user = $this->user();

        return $societe && $user ? $user->getMembrePour($societe) : null;
    }

    /** @return Societe[] */
    public function disponibles(): array
    {
        $user = $this->user();
        if (null === $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return $this->societes->findBy(['active' => true], ['raisonSociale' => 'ASC']);
        }

        $societes = [];
        foreach ($user->getSuccursalesAdministreesActives() as $succursale) {
            foreach ($succursale->getSocietesActives() as $societe) {
                $societes[$societe->getId()] = $societe;
            }
        }
        foreach ($user->getMembresActifs() as $membre) {
            $societes[$membre->getSociete()->getId()] = $membre->getSociete();
        }
        $societes = array_values($societes);
        usort($societes, static fn (Societe $a, Societe $b) => strcasecmp($a->getRaisonSociale(), $b->getRaisonSociale()));

        return $societes;
    }

    public function peutAcceder(User $user, Societe $societe): bool
    {
        if (!$societe->isActive()) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->administre($societe)) {
            return true;
        }

        $membre = $user->getMembrePour($societe);

        return null !== $membre && $membre->isActif();
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
