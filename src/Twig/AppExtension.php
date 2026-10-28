<?php

namespace App\Twig;

use App\Entity\Membre;
use App\Entity\Societe;
use App\Entity\Succursale;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use App\Security\PermissionCatalogue;
use App\Security\SocieteCourante;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    public function __construct(
        private readonly SocieteCourante $societeCourante,
        private readonly Security $security,
    ) {
    }

    #[AsTwigFunction('societe_courante')]
    public function societeCourante(): ?Societe
    {
        return $this->societeCourante->get();
    }

    #[AsTwigFunction('membre_courant')]
    public function membreCourant(): ?Membre
    {
        return $this->societeCourante->getMembre();
    }

    /** @return Societe[] */
    #[AsTwigFunction('societes_disponibles')]
    public function societesDisponibles(): array
    {
        return $this->societeCourante->disponibles();
    }

    /** @return Succursale[] succursales actives administrées par l'utilisateur connecté */
    #[AsTwigFunction('succursales_administrees')]
    public function succursalesAdministrees(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getSuccursalesAdministreesActives() : [];
    }

    #[AsTwigFunction('modules_permissions')]
    public function modulesPermissions(): array
    {
        return PermissionCatalogue::MODULES;
    }
}
