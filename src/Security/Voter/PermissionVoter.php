<?php

namespace App\Security\Voter;

use App\Entity\Societe;
use App\Entity\User;
use App\Security\PermissionCatalogue;
use App\Security\SocieteCourante;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Décide si l'utilisateur peut effectuer une action du catalogue
 * (ex. is_granted('salarie.creer')) dans une société donnée
 * ou, par défaut, dans la société courante.
 *
 * @extends Voter<string, Societe|null>
 */
class PermissionVoter extends Voter
{
    public function __construct(private readonly SocieteCourante $societeCourante)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(PermissionCatalogue::permissions()[$attribute])
            && (null === $subject || $subject instanceof Societe);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActif()) {
            return false;
        }

        $societe = $subject ?? $this->societeCourante->get();
        if (null === $societe || !$societe->isActive()) {
            return false;
        }

        // Super-admin : partout. Administrateur de succursale : toutes les sociétés de sa succursale.
        if ($user->isSuperAdmin() || $user->administre($societe)) {
            return true;
        }

        $membre = $user->getMembrePour($societe);
        if (null === $membre || !$membre->peut($attribute)) {
            $vote?->addReason(sprintf('Votre rôle ne permet pas l\'action « %s ».', $attribute));

            return false;
        }

        return true;
    }
}
