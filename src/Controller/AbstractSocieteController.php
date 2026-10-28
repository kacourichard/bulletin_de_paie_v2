<?php

namespace App\Controller;

use App\Entity\Societe;
use App\Entity\User;
use App\Security\SocieteCourante;
use App\Security\SocieteRequiseException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Base des contrôleurs de l'espace société : donne accès à la société courante.
 */
abstract class AbstractSocieteController extends AbstractController
{
    protected SocieteCourante $societeCourante;

    #[Required]
    public function setSocieteCourante(SocieteCourante $societeCourante): void
    {
        $this->societeCourante = $societeCourante;
    }

    protected function societe(): Societe
    {
        return $this->societeCourante->get() ?? throw new SocieteRequiseException();
    }

    protected function utilisateur(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /** Refuse l'accès à une ressource d'une autre société que la société courante. */
    protected function verifierAppartenance(?Societe $societe): void
    {
        if ($societe !== $this->societe()) {
            throw $this->createNotFoundException();
        }
    }
}
