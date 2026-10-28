<?php

namespace App\Command;

use App\Repository\SocieteRepository;
use App\Service\PermissionSynchroniseur;
use App\Service\SocieteInitialiseur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:permissions:sync', description: 'Synchronise les permissions du catalogue en base et complète les rôles par défaut des sociétés')]
class PermissionsSyncCommand
{
    public function __construct(
        private readonly PermissionSynchroniseur $synchroniseur,
        private readonly SocieteInitialiseur $initialiseur,
        private readonly SocieteRepository $societes,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Ajoute aussi les rôles par défaut manquants dans chaque société')] bool $roles = false): int
    {
        $stats = $this->synchroniseur->synchroniser();
        $io->success(sprintf('Permissions : %d ajoutée(s), %d mise(s) à jour, %d supprimée(s).', $stats['ajoutees'], $stats['misesAJour'], $stats['supprimees']));

        if ($roles) {
            foreach ($this->societes->findAll() as $societe) {
                $this->initialiseur->creerRolesParDefaut($societe);
            }
            $this->em->flush();
            $io->success('Rôles par défaut vérifiés pour toutes les sociétés.');
        }

        return 0;
    }
}
