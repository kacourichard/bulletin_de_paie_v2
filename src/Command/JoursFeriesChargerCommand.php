<?php

namespace App\Command;

use App\Entity\JourFerie;
use App\Entity\Pays;
use App\Referentiel\JoursFeriesParDefaut;
use App\Repository\JourFerieRepository;
use App\Repository\PaysRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:jours-feries:charger', description: 'Charge les jours fériés nationaux par défaut d\'un pays pour une année (sans doublon)')]
class JoursFeriesChargerCommand
{
    public function __construct(
        private readonly PaysRepository $pays,
        private readonly JourFerieRepository $jours,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Code ISO du pays (CI)')] string $code, #[Argument('Année')] int $annee): int
    {
        $pays = $this->pays->findOneBy(['codeIso' => strtoupper($code)]);
        if (null === $pays || [] === JoursFeriesParDefaut::pour($code, $annee)) {
            $io->error(sprintf('Pays %s inconnu ou sans calendrier par défaut (disponibles : %s).', $code, implode(', ', JoursFeriesParDefaut::pays())));

            return 1;
        }
        $ajoutes = self::charger($this->em, $this->jours, $pays, $annee);
        $io->success(sprintf('%s %d : %d jour(s) férié(s) ajouté(s). Vérifiez les dates « à confirmer » (fêtes musulmanes).', $pays->getNom(), $annee, $ajoutes));

        return 0;
    }

    /** Ajoute les jours fériés nationaux absents (par date). Retourne le nombre d'ajouts. */
    public static function charger(EntityManagerInterface $em, JourFerieRepository $jours, Pays $pays, int $annee): int
    {
        $existants = [];
        foreach ($jours->findBy(['pays' => $pays, 'societe' => null]) as $j) {
            $existants[$j->getDate()->format('Y-m-d')] = true;
        }
        $ajoutes = 0;
        foreach (JoursFeriesParDefaut::pour((string) $pays->getCodeIso(), $annee) as $l) {
            if (isset($existants[$l['date']])) {
                continue;
            }
            $em->persist((new JourFerie($pays))->setDate(new \DateTimeImmutable($l['date']))->setLibelle($l['libelle'])
                ->setChomePaye($l['chomePaye'])->setVeille($l['veille'])->setLendemain($l['lendemain'])->setAConfirmer($l['aConfirmer']));
            ++$ajoutes;
        }
        $em->flush();

        return $ajoutes;
    }
}
