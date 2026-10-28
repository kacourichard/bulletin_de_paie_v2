<?php

namespace App\Service;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Security\PermissionCatalogue;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aligne la table des permissions sur PermissionCatalogue
 * (ajout des nouvelles, mise à jour des libellés, retrait des obsolètes).
 */
class PermissionSynchroniseur
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PermissionRepository $permissions,
    ) {
    }

    /**
     * @return array{ajoutees: int, misesAJour: int, supprimees: int}
     */
    public function synchroniser(): array
    {
        $existantes = [];
        foreach ($this->permissions->findAll() as $permission) {
            $existantes[$permission->getCode()] = $permission;
        }

        $stats = ['ajoutees' => 0, 'misesAJour' => 0, 'supprimees' => 0];
        $nouvelles = [];
        $ordre = 0;
        foreach (PermissionCatalogue::permissions() as $code => $def) {
            ++$ordre;
            if (!isset($existantes[$code])) {
                $this->em->persist($nouvelles[$code] = new Permission($code, $def['libelle'], $def['module'], $ordre));
                ++$stats['ajoutees'];
                continue;
            }

            $permission = $existantes[$code];
            if ($permission->getLibelle() !== $def['libelle'] || $permission->getModule() !== $def['module'] || $permission->getOrdre() !== $ordre) {
                $permission->setLibelle($def['libelle'])->setModule($def['module'])->setOrdre($ordre);
                ++$stats['misesAJour'];
            }
            unset($existantes[$code]);
        }

        foreach ($existantes as $obsolete) {
            $this->em->remove($obsolete);
            ++$stats['supprimees'];
        }

        $this->accorderAuxRolesParDefaut($nouvelles);
        $this->em->flush();

        return $stats;
    }

    /**
     * Les rôles par défaut déjà créés dans les sociétés reçoivent les nouvelles
     * permissions que le catalogue leur attribue (les rôles personnalisés ne changent pas).
     *
     * @param array<string, Permission> $nouvelles
     */
    private function accorderAuxRolesParDefaut(array $nouvelles): void
    {
        if ([] === $nouvelles) {
            return;
        }

        $defauts = PermissionCatalogue::rolesParDefaut();
        foreach ($this->em->getRepository(Role::class)->findBy(['systeme' => true]) as $role) {
            $attribuees = $defauts[$role->getCode()]['permissions'] ?? [];
            foreach ($nouvelles as $code => $permission) {
                if ('*' === $attribuees || in_array($code, $attribuees, true)) {
                    $role->addPermission($permission);
                }
            }
        }
    }
}
