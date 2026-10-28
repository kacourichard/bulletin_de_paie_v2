<?php

namespace App\Command;

use App\Entity\Rubrique;
use App\Referentiel\RubriquesParDefaut;
use App\Repository\PaysRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:rubriques:charger', description: 'Charge les rubriques de paie par défaut d\'un pays (sans écraser les rubriques existantes)')]
class RubriquesChargerCommand
{
    public function __construct(
        private readonly PaysRepository $pays,
        private readonly RubriqueRepository $rubriques,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Code ISO du pays (CI, SN, MA, FR...)')] string $code): int
    {
        $code = strtoupper($code);
        $pays = $this->pays->findOneBy(['codeIso' => $code]);
        $referentiel = RubriquesParDefaut::parPays()[$code] ?? null;
        if (null === $pays || null === $referentiel) {
            $io->error(sprintf('Pays %s inconnu ou sans référentiel par défaut (disponibles : %s).', $code, implode(', ', array_keys(RubriquesParDefaut::parPays()))));

            return 1;
        }

        $ajoutees = self::charger($this->em, $this->rubriques, $pays, $referentiel);
        $io->success(sprintf('%s : %d rubrique(s) ajoutée(s), %d déjà présente(s).', $pays->getNom(), $ajoutees, count($referentiel) - $ajoutees));

        return 0;
    }

    /** Ajoute les rubriques absentes (par code). Retourne le nombre d'ajouts. */
    public static function charger(EntityManagerInterface $em, RubriqueRepository $rubriques, \App\Entity\Pays $pays, array $referentiel): int
    {
        $existants = array_flip(array_map(static fn (Rubrique $r) => $r->getCode(), $rubriques->findBy(['pays' => $pays])));
        $ajoutees = 0;
        foreach ($referentiel as $ligne) {
            [$code, $libelle, $type, $section, $social, $fiscal, $plafond, $elementFixe] = $ligne;
            if (isset($existants[$code])) {
                continue;
            }
            $em->persist((new Rubrique())
                ->setPays($pays)->setCode($code)->setLibelle($libelle)->setType($type)->setSection($section)
                ->setTraitementSocial($social)->setTraitementFiscal($fiscal)->setPlafondExoneration($plafond)
                ->setElementFixe($elementFixe)->setElementVariable($ligne[8] ?? true)->setCoefficientHoraire($ligne[9] ?? null)->setProratisee($ligne[10] ?? true));
            ++$ajoutees;
        }
        $em->flush();

        return $ajoutees;
    }
}
