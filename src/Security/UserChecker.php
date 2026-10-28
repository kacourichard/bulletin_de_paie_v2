<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Votre compte est désactivé. Contactez votre administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        if ([] === $user->getMembresActifs() && !$user->isAdministrateurSuccursale()) {
            throw new CustomUserMessageAccountStatusException('Votre compte n\'est rattaché à aucune société ni succursale active.');
        }
    }
}
