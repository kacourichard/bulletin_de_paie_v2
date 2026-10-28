<?php

namespace App\Service;

use App\Entity\Role;
use App\Entity\Societe;
use App\Repository\PermissionRepository;
use App\Security\PermissionCatalogue;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prépare une nouvelle société : crée ses rôles par défaut
 * (Administrateur, RH, Comptable, Trésorier, Lecteur) avec leurs permissions.
 */
class SocieteInitialiseur
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PermissionRepository $permissions,
    ) {
    }

    /** Crée les rôles par défaut manquants. Ne fait pas de flush. */
    public function creerRolesParDefaut(Societe $societe): void
    {
        $toutes = $this->permissions->findAll();
        $parCode = [];
        foreach ($toutes as $permission) {
            $parCode[$permission->getCode()] = $permission;
        }

        foreach (PermissionCatalogue::rolesParDefaut() as $code => $def) {
            if (null !== $societe->getRoleParCode($code)) {
                continue;
            }

            $role = (new Role())
                ->setCode($code)
                ->setLibelle($def['libelle'])
                ->setDescription($def['description'])
                ->setCouleur($def['couleur'])
                ->setSysteme(true);

            $codes = '*' === $def['permissions'] ? array_keys($parCode) : $def['permissions'];
            foreach ($codes as $permissionCode) {
                if (isset($parCode[$permissionCode])) {
                    $role->addPermission($parCode[$permissionCode]);
                }
            }

            $societe->addRole($role);
            $this->em->persist($role);
        }
    }
}
