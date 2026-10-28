<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Avantage;
use App\Entity\ElementVariable;
use App\Entity\Rubrique;
use App\Enum\SituationFamiliale;
use App\Repository\CotisationRepository;
use App\Repository\ParametrePaieRepository;
use App\Repository\RubriqueRepository;
use App\Security\PermissionCatalogue as P;
use App\Service\SimulateurPaie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rubriques & barèmes de la société : la législation du pays de paie telle qu'elle s'applique à la société
 * (rubriques, paramètres, barème de l'impôt, cotisations aux taux de la société) et un simulateur de bulletin.
 * Le référentiel du pays est géré par la plateforme ; la société choisit seulement les rubriques qu'elle propose.
 */
#[Route('/rubriques-baremes')]
class RubriqueBaremeController extends AbstractSocieteController
{
    /** Lignes de rubriques proposées dans le simulateur. */
    private const LIGNES_SIMULATEUR = 5;

    public function __construct(
        private readonly RubriqueRepository $rubriques,
        private readonly ParametrePaieRepository $parametres,
        private readonly CotisationRepository $cotisations,
        private readonly SimulateurPaie $simulateur,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_rubriques_baremes')]
    public function index(Request $request): Response
    {
        // Consultation : toute personne qui voit la paie ; choix des rubriques : parametre.gerer.
        if (!$this->isGranted(P::PAIE_VOIR) && !$this->isGranted(P::PARAMETRE_GERER)) {
            throw $this->createAccessDeniedException();
        }
        $societe = $this->societe();
        $pays = $societe->getPaysPaie();
        $parametre = $this->parametres->pour($pays);
        $tauxAt = $societe->getTauxAccidentTravail();

        $cotisations = array_map(static fn ($c) => [
            'cotisation' => $c,
            'national' => $c->tauxPatronalPour(true, $tauxAt),
            'nonNational' => $c->tauxPatronalPour(false, $tauxAt),
        ], $this->cotisations->duPays($pays, true));

        $anciennete = [];
        if ($parametre->isAncienneteActive()) {
            foreach ([0, 1, 2, 3, 5, 10, 15, 20, 25, 30] as $annees) {
                $anciennete[$annees] = $parametre->tauxAnciennete($annees);
            }
        }

        return $this->render('rubrique_bareme/index.html.twig', [
            'societe' => $societe,
            'pays' => $pays,
            'parametre' => $parametre->getId() ? $parametre : null,
            'rubriques' => $this->rubriques->duPays($pays),
            'utilisation' => $this->utilisation(),
            'grille' => $this->parametres->grilleParts($pays),
            'situations' => SituationFamiliale::cases(),
            'tranches' => $this->cotisations->tranches($pays),
            'reductions' => $this->cotisations->reductions($pays),
            'cotisations' => $cotisations,
            'anciennete' => $anciennete,
            'simulation' => $this->simulation($request),
            'disponibles' => array_merge(
                $this->rubriques->avantagesDisponibles($pays, $societe),
                array_filter($this->rubriques->variablesDisponibles($pays, $societe), static fn (Rubrique $r) => !$r->isElementFixe()),
            ),
            'lignesSimulateur' => self::LIGNES_SIMULATEUR,
        ]);
    }

    /** Proposer ou non une rubrique du pays dans la société (avantages, éléments variables, import). */
    #[Route('/rubriques/{id}/proposer', name: 'app_rubrique_bareme_proposer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PARAMETRE_GERER)]
    public function proposer(Rubrique $rubrique, Request $request): Response
    {
        $societe = $this->societe();
        if (!$this->isCsrfTokenValid('rubriques-baremes', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        if ($rubrique->getPays() !== $societe->getPaysPaie()) {
            throw $this->createNotFoundException();
        }
        $proposer = $request->getPayload()->getBoolean('proposer');
        $societe->masquerRubrique($rubrique, !$proposer);
        $this->em->flush();
        $this->addFlash($proposer ? 'success' : 'warning', sprintf(
            $proposer ? 'La rubrique %s — %s est de nouveau proposée.' : 'La rubrique %s — %s n\'est plus proposée pour les nouveaux avantages et éléments variables (l\'existant est conservé).',
            $rubrique->getCode(),
            $rubrique->getLibelle(),
        ));

        return $this->redirectToRoute('app_rubriques_baremes', ['_fragment' => 'rubriques']);
    }

    /**
     * Utilisation des rubriques dans la société : salariés ayant l'avantage en cours, éléments variables saisis.
     *
     * @return array<int, array{avantages: int, variables: int, dernierMois: ?string}>
     */
    private function utilisation(): array
    {
        $societe = $this->societe();
        $resultat = [];
        $avantages = $this->em->getRepository(Avantage::class)->createQueryBuilder('a')
            ->select('IDENTITY(a.rubrique) AS r, COUNT(DISTINCT af.id) AS n')
            ->join('a.affectation', 'af')
            ->where('af.societe = :societe')->andWhere('af.dateSortie IS NULL')
            ->andWhere('a.dateFin IS NULL OR a.dateFin >= :aujourdhui')
            ->setParameter('societe', $societe)->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->groupBy('a.rubrique')->getQuery()->getArrayResult();
        foreach ($avantages as $ligne) {
            $resultat[(int) $ligne['r']]['avantages'] = (int) $ligne['n'];
        }
        $variables = $this->em->getRepository(ElementVariable::class)->createQueryBuilder('e')
            ->select('IDENTITY(e.rubrique) AS r, COUNT(e.id) AS n, MAX(p.mois) AS dernier')
            ->join('e.periode', 'p')
            ->where('p.societe = :societe')->andWhere('e.avantage IS NULL')
            ->setParameter('societe', $societe)
            ->groupBy('e.rubrique')->getQuery()->getArrayResult();
        foreach ($variables as $ligne) {
            $resultat[(int) $ligne['r']]['variables'] = (int) $ligne['n'];
            $resultat[(int) $ligne['r']]['dernierMois'] = $ligne['dernier'];
        }

        return $resultat;
    }

    /** Simulateur (?sim=1&categoriel=…) : bulletin d'un mois complet aux règles de la société. */
    private function simulation(Request $request): ?array
    {
        $q = $request->query;
        $saisie = [
            'categoriel' => $this->montant($q->get('categoriel'), '250000'),
            'sursalaire' => $this->montant($q->get('sursalaire'), '0'),
            'embauche' => \DateTimeImmutable::createFromFormat('!Y-m-d', $q->getString('embauche')) ?: new \DateTimeImmutable('-5 years'),
            'situation' => SituationFamiliale::tryFrom($q->getString('situation')) ?? SituationFamiliale::MARIE,
            'enfants' => max(0, min(20, $q->getInt('enfants', 2))),
            'national' => '0' !== $q->getString('national', '1'),
            'lignes' => [],
        ];
        $lignes = [];
        foreach (array_slice((array) $q->all('lignes'), 0, self::LIGNES_SIMULATEUR) as $l) {
            $rubrique = isset($l['rubrique']) && ctype_digit((string) $l['rubrique']) ? $this->rubriques->find((int) $l['rubrique']) : null;
            $montant = $this->montant($l['montant'] ?? null, '0');
            $saisie['lignes'][] = ['rubrique' => $rubrique?->getId(), 'montant' => $montant];
            if ($rubrique && $rubrique->getPays() === $this->societe()->getPaysPaie() && bccomp($montant, '0', 2) > 0) {
                $lignes[] = ['rubrique' => $rubrique, 'montant' => $montant];
            }
        }

        return [
            'saisie' => $saisie,
            'resultat' => $q->has('sim') ? $this->simulateur->simuler($this->societe(), [
                'salaireCategoriel' => $saisie['categoriel'],
                'sursalaire' => $saisie['sursalaire'],
                'dateEmbauche' => $saisie['embauche'],
                'situation' => $saisie['situation'],
                'enfants' => $saisie['enfants'],
                'national' => $saisie['national'],
                'lignes' => $lignes,
            ]) : null,
        ];
    }

    private function montant(mixed $valeur, string $defaut): string
    {
        $valeur = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], (string) $valeur);

        return is_numeric($valeur) && $valeur >= 0 ? number_format((float) $valeur, 2, '.', '') : $defaut;
    }
}
