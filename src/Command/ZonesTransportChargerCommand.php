<?php

namespace App\Command;

use App\Entity\Pays;
use App\Entity\ZoneTransport;
use App\Referentiel\ZonesTransportParDefaut;
use App\Repository\PaysRepository;
use App\Repository\RubriqueRepository;
use App\Repository\ZoneTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:transport:charger', description: 'Charge le barème de l\'indemnité de transport par zone de résidence d\'un pays (sans doublon)')]
class ZonesTransportChargerCommand
{
    public function __construct(
        private readonly PaysRepository $pays,
        private readonly ZoneTransportRepository $zones,
        private readonly RubriqueRepository $rubriques,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Code ISO du pays (CI)')] string $code): int
    {
        $pays = $this->pays->findOneBy(['codeIso' => strtoupper($code)]);
        if (null === $pays || !isset(ZonesTransportParDefaut::parPays()[strtoupper($code)])) {
            $io->error(sprintf('Pays %s inconnu ou sans barème par défaut (disponibles : %s).', $code, implode(', ', array_keys(ZonesTransportParDefaut::parPays()))));

            return 1;
        }
        $ajoutees = self::charger($this->em, $this->zones, $this->rubriques, $pays);
        $io->success(sprintf('%s : %d zone(s) ajoutée(s).', $pays->getNom(), $ajoutees));

        return 0;
    }

    /** Ajoute les zones absentes (par libellé). Retourne le nombre d'ajouts. */
    public static function charger(EntityManagerInterface $em, ZoneTransportRepository $zones, RubriqueRepository $rubriques, Pays $pays): int
    {
        $existantes = array_flip(array_map(static fn (ZoneTransport $z) => $z->getLibelle(), $zones->duPays($pays)));
        $ajoutees = 0;
        foreach (ZonesTransportParDefaut::parPays()[$pays->getCodeIso()] ?? [] as $ordre => [$libelle, $montant, $code]) {
            $rubrique = $rubriques->findOneBy(['pays' => $pays, 'code' => $code]);
            if (isset($existantes[$libelle]) || null === $rubrique) {
                continue;
            }
            $em->persist((new ZoneTransport())->setPays($pays)->setLibelle($libelle)->setMontant($montant)->setRubrique($rubrique)->setOrdre($ordre + 1));
            ++$ajoutees;
        }
        $em->flush();

        return $ajoutees;
    }
}
