<?php

namespace App\DataFixtures;

use App\Entity\Affectation;
use App\Entity\Avantage;
use App\Entity\Pays;
use App\Entity\Remuneration;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Entity\User;
use App\Enum\ModePaiement;
use App\Enum\MotifRemuneration;
use App\Enum\MotifSortie;
use App\Command\JoursFeriesChargerCommand;
use App\Command\RubriquesChargerCommand;
use App\Command\ZonesTransportChargerCommand;
use App\Entity\JourFerie;
use App\Entity\ZoneTransport;
use App\Referentiel\RubriquesParDefaut;
use App\Referentiel\SecteursActiviteParDefaut;
use App\Entity\SecteurActivite;
use App\Repository\RubriqueRepository;
use App\Enum\SituationFamiliale;
use App\Enum\Sexe;
use App\Enum\TypeContrat;
use App\Service\GestionMembres;
use App\Entity\Succursale;
use App\Repository\UserRepository;
use App\Service\GestionSalaries;
use App\Service\GestionSuccursales;
use App\Service\GestionPaie;
use App\Entity\ElementVariable;
use App\Entity\BaremePart;
use App\Entity\Cotisation;
use App\Entity\ReductionImpot;
use App\Entity\TrancheImpot;
use App\Enum\ArrondiNet;
use App\Enum\AssietteCotisation;
use App\Enum\CategorieCotisation;
use App\Enum\ModeExoneration;
use App\Entity\ParametrePaie;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PermissionSynchroniseur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Données de démarrage : pays courants, super-administrateur et société de démonstration.
 * php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture
{
    public const MOT_DE_PASSE_DEMO = 'Demo@2026!';

    /** [code ISO, devise, langue, fuseau, indicatif, organisme social] */
    private const PAYS = [
        ['CI', 'XOF', 'fr', 'Africa/Abidjan', '+225', 'CNPS'],
        ['SN', 'XOF', 'fr', 'Africa/Dakar', '+221', 'CSS / IPRES'],
        ['BF', 'XOF', 'fr', 'Africa/Ouagadougou', '+226', 'CNSS'],
        ['ML', 'XOF', 'fr', 'Africa/Bamako', '+223', 'INPS'],
        ['BJ', 'XOF', 'fr', 'Africa/Porto-Novo', '+229', 'CNSS'],
        ['TG', 'XOF', 'fr', 'Africa/Lome', '+228', 'CNSS'],
        ['NE', 'XOF', 'fr', 'Africa/Niamey', '+227', 'CNSS'],
        ['CM', 'XAF', 'fr', 'Africa/Douala', '+237', 'CNPS'],
        ['GA', 'XAF', 'fr', 'Africa/Libreville', '+241', 'CNSS'],
        ['CG', 'XAF', 'fr', 'Africa/Brazzaville', '+242', 'CNSS'],
        ['GN', 'GNF', 'fr', 'Africa/Conakry', '+224', 'CNSS'],
        ['MA', 'MAD', 'fr', 'Africa/Casablanca', '+212', 'CNSS'],
        ['GH', 'GHS', 'en', 'Africa/Accra', '+233', 'SSNIT'],
        ['NG', 'NGN', 'en', 'Africa/Lagos', '+234', 'PenCom / NSITF'],
        ['FR', 'EUR', 'fr', 'Europe/Paris', '+33', 'URSSAF'],
        ['BE', 'EUR', 'fr', 'Europe/Brussels', '+32', 'ONSS'],
        ['CA', 'CAD', 'fr', 'America/Toronto', '+1', 'ARC'],
        ['US', 'USD', 'en', 'America/New_York', '+1', 'SSA'],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PermissionSynchroniseur $permissions,
        private readonly GestionMembres $gestion,
        private readonly GestionSalaries $salaries,
        private readonly GestionSuccursales $succursales,
        private readonly UserRepository $users,
        private readonly RubriqueRepository $rubriques,
        private readonly GestionPaie $paie,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->permissions->synchroniser();

        $pays = [];
        foreach (self::PAYS as [$code, $devise, $langue, $fuseau, $indicatif, $organisme]) {
            $p = (new Pays())
                ->setCodeIso($code)
                ->setDevise($devise)
                ->setLangue($langue)
                ->setFuseauHoraire($fuseau)
                ->setIndicatifTelephone($indicatif)
                ->setOrganismeSocial($organisme);
            $manager->persist($p);
            $pays[$code] = $p;
        }

        $manager->flush();
        foreach (RubriquesParDefaut::parPays() as $code => $referentiel) {
            RubriquesChargerCommand::charger($manager, $this->rubriques, $pays[$code], $referentiel);
        }
        foreach ([(int) date('Y'), (int) date('Y') + 1] as $annee) {
            JoursFeriesChargerCommand::charger($manager, $manager->getRepository(JourFerie::class), $pays['CI'], $annee);
        }
        ZonesTransportChargerCommand::charger($manager, $manager->getRepository(ZoneTransport::class), $this->rubriques, $pays['CI']);
        $this->chargerParametresPaie($manager, $pays);

        $superAdmin = (new User())
            ->setEmail('superadmin@bulletin-paie.local')
            ->setPrenoms('Super')
            ->setNom('Administrateur')
            ->setSuperAdmin(true);
        $superAdmin->setPassword($this->hasher->hashPassword($superAdmin, self::MOT_DE_PASSE_DEMO));
        $manager->persist($superAdmin);

        // Succursale 1 : Groupe EPSA (Côte d'Ivoire + Sénégal)
        $epsa = (new Succursale())->setNom('Groupe EPSA')->setDescription('Afrique de l\'Ouest');
        $this->succursales->creer($epsa, 'admin@epsa-demo.local', 'Awa', 'Koné');
        $awa = $this->compteDemo('admin@epsa-demo.local');

        // Secteurs d'activité et taux de risque accident du travail (v1).
        $secteurs = [];
        foreach (SecteursActiviteParDefaut::parPays() as $code => $liste) {
            foreach ($liste as [$libelle, $taux]) {
                $manager->persist($secteurs[$libelle] = (new SecteurActivite())->setPays($pays[$code])->setLibelle($libelle)->setTauxRisque($taux));
            }
        }

        $ci = (new Societe())
            ->setRaisonSociale('EPSA Côte d\'Ivoire')
            ->setSecteurActivite($secteurs['COMMERCE - NÉGOCE - PROFESSIONS LIBÉRALES'])
            ->setPays($pays['CI'])
            ->setVille('Abidjan')
            ->setAdresse('Plateau, Avenue Chardy')
            ->setTelephone('+225 27 20 00 00 00')
            ->setEmail('contact@epsa-demo.local')
            ->setNumeroEmployeur('CNPS-000000')
            ->setNumeroFiscal('CC-0000000A');
        $this->succursales->creerSociete($epsa, $ci);
        $sn = (new Societe())->setRaisonSociale('EPSA Sénégal')->setPays($pays['SN'])->setVille('Dakar');
        $this->succursales->creerSociete($epsa, $sn);

        $this->equipe($ci, [
            ['rh@epsa-demo.local', 'Koffi', 'Brou', 'RH'],
            ['comptable@epsa-demo.local', 'Yao', 'Kouassi', 'COMPTABLE'],
            ['tresorier@epsa-demo.local', 'Mariam', 'Traoré', 'TRESORIER'],
        ]);
        $this->equipe($sn, [
            ['rh.sn@epsa-demo.local', 'Aminata', 'Sow', 'RH'],
            ['comptable.sn@epsa-demo.local', 'Fatou', 'Ndiaye', 'COMPTABLE'],
            ['tresorier.sn@epsa-demo.local', 'Ousmane', 'Fall', 'TRESORIER'],
        ]);

        // Succursale 2 : Atlas Group (Maroc + France), cloisonnée de la première
        $atlas = (new Succursale())->setNom('Atlas Group')->setDescription('Maghreb & Europe');
        $this->succursales->creer($atlas, 'admin@atlas-demo.local', 'Karim', 'Benali');
        $karim = $this->compteDemo('admin@atlas-demo.local');
        $ma = (new Societe())->setRaisonSociale('Atlas Maroc')->setPays($pays['MA'])->setVille('Casablanca');
        $this->succursales->creerSociete($atlas, $ma);
        $fr = (new Societe())->setRaisonSociale('Atlas France')->setPays($pays['FR'])->setVille('Lyon');
        $this->succursales->creerSociete($atlas, $fr);
        $this->equipe($ma, [['comptable@atlas-demo.local', 'Salma', 'El Idrissi', 'COMPTABLE']]);

        $manager->flush();
        $this->chargerSalaries($ci, $sn, $awa);

        // Personnel de chaque société, montants dans la devise du pays.
        // Avantages : [code rubrique, montant, libellé spécifique?]
        $this->personnel($ci, $awa, [
            ['KONE', 'Adama', Sexe::M, '1983-11-02', 'Korhogo', SituationFamiliale::MARIE, 4, 'Directeur administratif et financier', 'Gestion', 'Direction', TypeContrat::CDI, '2018-01-08', '850000', '150000', [['085', '150000'], ['645', '50000'], ['067', '100000']]],
            ['YAO', 'Akissi Esther', Sexe::F, '1994-03-17', 'Abidjan', SituationFamiliale::CELIBATAIRE, 1, 'Commerciale', 'Commerce', 'Ventes', TypeContrat::CDI, '2023-04-03', '280000', '20000', []],
            ['OUATTARA', 'Seydou', Sexe::M, '1999-07-22', 'Bouaké', SituationFamiliale::CELIBATAIRE, 0, 'Stagiaire comptable', 'Comptabilité', 'Finance', TypeContrat::STAGE, '2026-09-01', '120000', '0', []],
        ]);
        $this->personnel($sn, $awa, [
            ['NDIAYE', 'Mamadou', Sexe::M, '1987-05-14', 'Dakar', SituationFamiliale::MARIE, 2, 'Chef d\'agence', 'Gestion', 'Direction', TypeContrat::CDI, '2020-02-03', '650000', '100000', [['085', '120000'], ['645', '40000']]],
            ['DIOP', 'Awa', Sexe::F, '1993-12-01', 'Saint-Louis', SituationFamiliale::MARIE, 1, 'Assistante administrative', 'Administration', 'Administration', TypeContrat::CDI, '2022-09-01', '260000', '15000', [['645', '26000']]],
            ['SECK', 'Ibrahima', Sexe::M, '1996-08-09', 'Thiès', SituationFamiliale::CELIBATAIRE, 0, 'Technicien réseau', 'Informatique', 'Opérations', TypeContrat::CDD, '2025-06-02', '300000', '0', [['645', '26000'], ['P10', '20000']]],
        ]);
        $this->personnel($ma, $karim, [
            ['BENNANI', 'Youssef', Sexe::M, '1982-02-25', 'Casablanca', SituationFamiliale::MARIE, 3, 'Directeur général', 'Gestion', 'Direction', TypeContrat::CDI, '2017-05-02', '28000', '4000', [['085', '5000'], ['067', '3000']]],
            ['ALAOUI', 'Khadija', Sexe::F, '1990-10-11', 'Rabat', SituationFamiliale::MARIE, 2, 'Responsable comptable', 'Comptabilité', 'Finance', TypeContrat::CDI, '2019-11-04', '14500', '1500', [['645', '500']]],
            ['TAZI', 'Omar', Sexe::M, '1995-04-30', 'Fès', SituationFamiliale::CELIBATAIRE, 0, 'Développeur', 'Informatique', 'Informatique', TypeContrat::CDI, '2024-01-15', '12000', '0', [['645', '500'], ['P10', '650']]],
            ['EL AMRANI', 'Sara', Sexe::F, '1998-06-19', 'Marrakech', SituationFamiliale::CELIBATAIRE, 0, 'Chargée de clientèle', 'Commerce', 'Ventes', TypeContrat::CDD, '2025-09-01', '7800', '0', [['645', '500']]],
        ]);
        $this->personnel($fr, $karim, [
            ['MARTIN', 'Julien', Sexe::M, '1984-09-03', 'Lyon', SituationFamiliale::MARIE, 2, 'Directeur d\'agence', 'Gestion', 'Direction', TypeContrat::CDI, '2018-03-01', '4850.00', '350.00', [['AN10', '320.00']]],
            ['DUBOIS', 'Camille', Sexe::F, '1991-01-27', 'Grenoble', SituationFamiliale::UNION_LIBRE, 1, 'Gestionnaire de paie', 'Ressources humaines', 'Ressources humaines', TypeContrat::CDI, '2021-06-14', '2780.50', '0', [['TR10', '120.00']]],
            ['LEROY', 'Thomas', Sexe::M, '1997-11-12', 'Villeurbanne', SituationFamiliale::CELIBATAIRE, 0, 'Technicien support', 'Informatique', 'Informatique', TypeContrat::CDI, '2023-10-02', '2250.00', '0', [['TP10', '44.25']]],
            ['GARCIA', 'Inès', Sexe::F, '2002-05-08', 'Lyon', SituationFamiliale::CELIBATAIRE, 0, 'Apprentie assistante RH', 'Ressources humaines', 'Ressources humaines', TypeContrat::APPRENTISSAGE, '2025-09-01', '1112.00', '0', []],
        ]);

        // Indemnité de transport selon la ville de résidence (barème CI) ; KONE garde son avantage
        // (montant particulier de 50 000) et est signalé dans les contrôles de la paie.
        $zones = [];
        foreach ($manager->getRepository(ZoneTransport::class)->findBy(['pays' => $pays['CI']]) as $z) {
            $zones[$z->getLibelle()] = $z;
        }
        foreach ($manager->getRepository(Affectation::class)->findBy(['societe' => $ci]) as $a) {
            $zone = ['KOUAME' => 'Abidjan', 'YAO' => 'Abidjan', 'BAMBA' => 'Abidjan', 'OUATTARA' => 'Autres villes'][$a->getSalarie()->getNom()] ?? null;
            if ($zone) {
                $a->getSalarie()->setZoneTransport($zones[$zone]);
            }
        }
        $manager->flush();

        $this->chargerPaie($ci, $awa);
    }

    /**
     * Paramètres de paie : Côte d'Ivoire repris de la v1 (SocieteFicheController, table nombre_part).
     * Sénégal, Maroc, France : seulement l'arrondi, le reste est à paramétrer par la plateforme.
     */
    private function chargerParametresPaie(ObjectManager $manager, array $pays): void
    {
        $ci = (new ParametrePaie($pays['CI']))
            ->setAncienneteActive(true)->setAncienneteAnneesMin(2)->setAncienneteTauxInitial('2')->setAncienneteTauxParAn('1')->setAncienneteTauxMax('25')
            ->setAncienneteCode('0013')->setPartsActives(true)->setDecimales(0)
            // Comme la v1 : abattement forfaitaire de 10 % du brut, net arrondi au 5 ou à la dizaine.
            ->setModeExoneration(ModeExoneration::FORFAITAIRE)->setArrondiNet(ArrondiNet::CINQ_DIX)->setJoursCongeParMois('2.57')
            ->setImpotActif(true)->setImpotCode('600')->setImpotLibelle('Retenue ITS');
        $manager->persist($ci);

        // ITS (Côte d'Ivoire) : barème mensuel 2024 et réduction pour charges de famille — À VALIDER.
        foreach ([['0', '75000', '0'], ['75000', '240000', '16'], ['240000', '800000', '21'], ['800000', '2400000', '24'], ['2400000', '8000000', '28'], ['8000000', null, '32']] as [$de, $a, $taux]) {
            $manager->persist(new TrancheImpot($pays['CI'], $de, $a, $taux));
        }
        foreach (['1.5' => '5500', '2' => '11000', '2.5' => '16500', '3' => '22000', '3.5' => '27500', '4' => '33000', '4.5' => '38500', '5' => '44000'] as $parts => $reduction) {
            $manager->persist(new ReductionImpot($pays['CI'], (string) $parts, $reduction));
        }

        // Cotisations du modèle de bulletin ivoirien (taux, plafonds et codes du bulletin fourni).
        $cotisations = [
            // libellé, catégorie, assiette, plafond, forfait/pers., taux salarial, code sal., taux patronal, code pat.
            ['C.N.P.S / Caisse de Retraite', 'SOCIALE', 'BRUT', '3375000', null, '6.3', '2604', '7.7', '3900'],
            ['Assurance Maladie Universelle C.M.U', 'SOCIALE', 'FORFAIT_PERSONNES', null, '1000', '50', '2645', '50', '3907'],
            ['C.N.P.S / Prestation Familiale', 'SOCIALE', 'BRUT', '75000', null, null, null, '5.75', '3910'],
            ['C.N.P.S / Accident de travail', 'SOCIALE', 'BRUT', '75000', null, null, null, '2', '3920'],
            ['Impôt Employeur', 'FISCALE', 'BRUT_IMPOSABLE', null, null, null, null, '1.2', '3930'],
            ['F.D.F.P / TA', 'FISCALE', 'BRUT_IMPOSABLE', null, null, null, null, '0.4', '3953'],
            ['F.D.F.P / F.P.C', 'FISCALE', 'BRUT_IMPOSABLE', null, null, null, null, '0.6', '3954'],
        ];
        foreach ($cotisations as $i => [$libelle, $categorie, $assiette, $plafond, $forfait, $tauxS, $codeS, $tauxP, $codeP]) {
            $manager->persist((new Cotisation())->setPays($pays['CI'])->setLibelle($libelle)
                ->setCategorie(CategorieCotisation::from($categorie))->setAssiette(AssietteCotisation::from($assiette))
                ->setPlafond($plafond)->setForfaitUnitaire($forfait)
                ->setTauxSalarial($tauxS)->setCodeSalarial($codeS)->setTauxPatronal($tauxP)->setCodePatronal($codeP)
                // AT : taux de risque de la société ; impôt employeur : 12 % pour les non-Ivoiriens.
                ->setTauxPatronalSociete('3920' === $codeP)->setTauxPatronalNonNational('3930' === $codeP ? '12' : null)
                ->setOrdre($i + 1));
        }

        // Grille v1 : [enfants => parts] ; l'union libre suit le célibataire.
        $grille = [
            'CELIBATAIRE' => [1, 2, 2.5, 3, 3.5, 4, 4.5, 5],
            'UNION_LIBRE' => [1, 2, 2.5, 3, 3.5, 4, 4.5, 5],
            'MARIE' => [2, 2.5, 3, 3.5, 4, 4.5, 5],
            'DIVORCE' => [1, 2, 2.5, 3, 3.5, 4, 4.5, 5],
            'VEUF' => [2, 2.5, 3, 3.5, 4, 4.5, 5],
        ];
        foreach ($grille as $situation => $valeurs) {
            foreach ($valeurs as $enfants => $parts) {
                $manager->persist(new BaremePart($pays['CI'], SituationFamiliale::from($situation), $enfants, number_format($parts, 2, '.', '')));
            }
        }

        foreach (['SN' => 0, 'MA' => 2, 'FR' => 2] as $code => $decimales) {
            $manager->persist((new ParametrePaie($pays[$code]))->setDecimales($decimales));
        }
        $manager->flush();
    }

    /** Paie de septembre 2026 (clôturée) et d'octobre 2026 (ouverte) avec quelques éléments variables. */
    private function chargerPaie(Societe $ci, User $auteur): void
    {
        $salarie = fn (string $nom) => $this->affectationDe($ci, $nom);
        $rubrique = fn (string $code) => $this->rubriques->findOneBy(['pays' => $ci->getPays(), 'code' => $code]);

        $septembre = $this->paie->ouvrir($ci, new \DateTimeImmutable('2026-09-01'), $auteur);
        $this->paie->ajouterElement($septembre, $salarie('KOUAME'), (new ElementVariable())->setRubrique($rubrique('0063'))->setMontant('100000'), $auteur);
        $this->paie->cloturer($septembre, $auteur);

        $octobre = $this->paie->ouvrir($ci, new \DateTimeImmutable('2026-10-01'), $auteur);
        // Heures supplémentaires : 10 h, taux = (catégoriel + sursalaire) / 173,33 × 1,5
        $this->paie->ajouterElement($octobre, $salarie('KOUAME'), (new ElementVariable())->setRubrique($rubrique('0015'))->setBase('10'), $auteur);
        // 24 jours travaillés sur 30 (comme le modèle de bulletin ivoirien) : salaire et transport au prorata (taux 24).
        $this->paie->definirJours($octobre, $salarie('BAMBA'), '24');
        // Retenue ponctuelle : acompte versé en cours de mois
        $this->paie->ajouterElement($octobre, $salarie('YAO'), (new ElementVariable())->setRubrique($rubrique('823'))->setMontant('50000')->setCommentaire('Acompte du 15/10'), $auteur);
    }

    private function affectationDe(Societe $societe, string $nom): Affectation
    {
        return $this->em->getRepository(Affectation::class)->createQueryBuilder('a')
            ->join('a.salarie', 's')->where('a.societe = :s')->andWhere('s.nom = :n')->andWhere('a.dateSortie IS NULL')
            ->setParameter('s', $societe)->setParameter('n', $nom)->getQuery()->getSingleResult();
    }

    /** Avantage de démonstration rattaché à la rubrique de code donné dans le pays de la société. */
    private function avantage(Societe $societe, string $code, string $montant, ?string $debut = null, ?string $libelle = null): Avantage
    {
        $rubrique = $this->rubriques->findOneBy(['pays' => $societe->getPaysPaie(), 'code' => $code])
            ?? throw new \LogicException(sprintf('Rubrique %s absente pour %s.', $code, $societe->getPaysPaie()->getCodeIso()));

        return (new Avantage())->setRubrique($rubrique)->setMontant($montant)->setLibelle($libelle)
            ->setDateDebut($debut ? new \DateTimeImmutable($debut) : null);
    }

    /**
     * Embauche une liste de salariés dans une société.
     *
     * @param list<array> $liste [nom, prénoms, sexe, naissance, lieu, situation, enfants, fonction, métier, service, contrat, entrée, base, sursalaire, avantages[[code, montant]]]
     */
    private function personnel(Societe $societe, User $auteur, array $liste): void
    {
        foreach ($liste as [$nom, $prenoms, $sexe, $naissance, $lieu, $situation, $enfants, $poste, $metier, $service, $contrat, $entree, $base, $sursalaire, $avantages]) {
            $affectation = (new Affectation())
                ->setPoste($poste)->setMetier($metier)->setService($service)->setTypeContrat($contrat)
                ->setSite($societe->getVille())
                ->setDateEntree(new \DateTimeImmutable($entree));
            if (TypeContrat::CDD === $contrat || TypeContrat::STAGE === $contrat || TypeContrat::APPRENTISSAGE === $contrat) {
                $affectation->setDateFinContrat((new \DateTimeImmutable($entree))->modify(TypeContrat::STAGE === $contrat ? '+6 months -1 day' : '+2 years -1 day'));
            }

            $this->salaries->embaucher(
                $societe,
                $this->salarie($nom, $prenoms, $sexe, $naissance, $lieu, $situation, $enfants, '', $societe->getPays()->getCodeIso()),
                $affectation,
                $base,
                $sursalaire,
                array_map(fn (array $a) => $this->avantage($societe, $a[0], $a[1], null, $a[2] ?? null), $avantages),
                $auteur,
            );
        }
    }

    /** @param list<array{string, string, string, string}> $membres [email, prénoms, nom, code rôle] */
    private function equipe(Societe $societe, array $membres): void
    {
        foreach ($membres as [$email, $prenoms, $nom, $role]) {
            $this->gestion->ajouter($societe, $email, $societe->getRoleParCode($role), $prenoms, $nom);
            $this->compteDemo($email);
        }
    }

    /** Compte de démonstration : mot de passe commun, pas de changement forcé. */
    private function compteDemo(string $email): User
    {
        $user = $this->users->findOneBy(['email' => $email]);
        $user->setPassword($this->hasher->hashPassword($user, self::MOT_DE_PASSE_DEMO));
        $user->setDoitChangerMotDePasse(false);

        return $user;
    }

    private function chargerSalaries(Societe $ci, Societe $sn, User $auteur): void
    {
        $d = static fn (string $date) => new \DateTimeImmutable($date);

        // 1. Carrière : revalorisation, avantages ajoutés / arrêtés, puis changement de poste
        $a = $this->salaries->embaucher($ci,
            $this->salarie('KOUAME', 'Jean-Marc', Sexe::M, '1988-04-12', 'Bouaké', SituationFamiliale::MARIE, 2, '+225 07 07 11 22 33'),
            (new Affectation())->setPoste('Comptable principal')->setMetier('Comptabilité')->setService('Finance')->setCategorie('Cadre')->setEchelon('B2')->setSite('Abidjan')->setDateEntree($d('2021-03-01')),
            '350000', '50000',
            [],
            $auteur);
        $this->salaries->revaloriser($a, (new Remuneration())->setDateEffet($d('2023-01-01'))->setSalaireBase('400000')->setSursalaire('60000')->setMotif(MotifRemuneration::REVALORISATION)->setCommentaire('Revalorisation annuelle'), $auteur);
        $this->salaries->ajouterAvantage($a, $this->avantage($ci, '085', '75000', '2023-01-01'), $auteur);
        $this->salaries->ajouterAvantage($a, $this->avantage($ci, '078', '15000', '2023-06-01', 'Forfait téléphone'), $auteur);
        $this->salaries->arreterAvantage($a->getAvantages()->filter(fn (Avantage $x) => '078' === $x->getRubrique()->getCode())->first(), $d('2024-12-31'));
        $logement = $a->getAvantages()->filter(fn (Avantage $x) => '085' === $x->getRubrique()->getCode())->first();
        $this->salaries->changerPoste(
            $a, $d('2025-07-01'),
            ['poste' => 'Chef comptable', 'categorie' => 'Cadre', 'echelon' => 'C1'],
            '480000', '70000',
            [$logement->getId() => '100000'],
            [$this->avantage($ci, '067', '50000')],
            'Promotion au poste de chef comptable',
            $auteur,
        );

        // 2. Embauche récente en CDD
        $this->salaries->embaucher($ci,
            $this->salarie('BAMBA', 'Fatou', Sexe::F, '1997-09-30', 'Abidjan', SituationFamiliale::CELIBATAIRE, 0, '+225 05 44 55 66 77'),
            (new Affectation())->setPoste('Assistante RH')->setMetier('Ressources humaines')->setService('Ressources humaines')->setTypeContrat(TypeContrat::CDD)->setSite('Abidjan')->setDateEntree($d('2025-02-01'))->setDateFinContrat($d('2026-12-31')),
            '250000', '0',
            [],
            $auteur);

        // 3. Transfert Côte d'Ivoire -> Sénégal (l'indemnité de logement 085 existe aussi au Sénégal : reprise)
        $a = $this->salaries->embaucher($ci,
            $this->salarie('DIALLO', 'Moussa', Sexe::M, '1985-01-20', 'Thiès', SituationFamiliale::MARIE, 3, '+221 77 123 45 67', 'SN'),
            (new Affectation())->setPoste('Responsable logistique')->setMetier('Logistique')->setService('Opérations')->setCategorie('Agent de maîtrise')->setSite('Abidjan')->setDateEntree($d('2019-09-01')),
            '300000', '40000',
            [$this->avantage($ci, '085', '60000')],
            $auteur);
        $this->salaries->revaloriser($a, (new Remuneration())->setDateEffet($d('2022-01-01'))->setSalaireBase('340000')->setSursalaire('40000')->setMotif(MotifRemuneration::REVALORISATION), $auteur);
        $this->salaries->transferer($a, $sn, $d('2025-01-01'), null, 'Directeur des opérations', TypeContrat::CDI, '420000', '80000', true, $auteur);

        // 4. Départ (démission)
        $a = $this->salaries->embaucher($ci,
            $this->salarie('N\'GUESSAN', 'Paul', Sexe::M, '1992-06-05', 'Yamoussoukro', SituationFamiliale::UNION_LIBRE, 1, '+225 01 02 03 04 05'),
            (new Affectation())->setPoste('Commercial')->setMetier('Commerce')->setService('Ventes')->setSite('Abidjan')->setDateEntree($d('2022-05-02')),
            '220000', '30000',
            [$this->avantage($ci, '645', '25000')],
            $auteur);
        $this->salaries->sortir($a, $d('2025-06-30'), MotifSortie::DEMISSION, 'Départ pour un autre employeur');
    }

    private function salarie(string $nom, string $prenoms, Sexe $sexe, string $naissance, string $lieu, SituationFamiliale $situation, int $enfants, string $telephone, string $nationalite = 'CI'): Salarie
    {
        return (new Salarie())
            ->setNom($nom)->setPrenoms($prenoms)->setSexe($sexe)
            ->setDateNaissance(new \DateTimeImmutable($naissance))->setLieuNaissance($lieu)
            ->setNationalite($nationalite)->setSituationFamiliale($situation)->setNombreEnfants($enfants)
            ->setTelephone($telephone ?: null)
            ->setEmail((new AsciiSlugger())->slug(strtok($prenoms, ' ').'.'.$nom, '.')->lower().'@salaries-demo.local')
            ->setModePaiement(ModePaiement::VIREMENT)->setBanque('Banque de démonstration')->setNumeroCompte($nationalite.'00 0000 0000 0000 0000 00');
    }
}
