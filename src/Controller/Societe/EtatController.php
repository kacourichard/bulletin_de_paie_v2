<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Affectation;
use App\Repository\AffectationRepository;
use App\Security\PermissionCatalogue as P;
use App\Service\EtatsPaie;
use App\Service\ExportEtatsPaie;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * États de paie : livre de paie, état des rubriques, bordereau des salaires et fiche individuelle.
 * Chaque état est imprimable (navigateur → PDF) et exportable en Excel (?format=xlsx).
 */
#[Route('/etats')]
#[IsGranted(P::ETAT_VOIR)]
class EtatController extends AbstractSocieteController
{
    /** Plage maximale d'un état, pour garder des temps de calcul raisonnables. */
    private const MOIS_MAX = 24;

    public function __construct(
        private readonly EtatsPaie $etats,
        private readonly ExportEtatsPaie $export,
        private readonly AffectationRepository $affectations,
    ) {
    }

    #[Route('', name: 'app_etats')]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        [$du, $au] = $this->plage($request);
        $annee = $request->query->getInt('annee') ?: (int) date('Y');
        $salaries = $this->affectations->presentesSurPeriode($societe, new \DateTimeImmutable($annee.'-01-01'), new \DateTimeImmutable($annee.'-12-31'));

        return $this->render('etat/index.html.twig', [
            'societe' => $societe,
            'du' => $du,
            'au' => $au,
            'annee' => $annee,
            'salaries' => $salaries,
            'banques' => EtatsPaie::groupesPaiement($salaries),
            'national' => $societe->getPaysPaie(),
        ]);
    }

    #[Route('/livre-de-paie', name: 'app_etat_livre')]
    public function livre(Request $request): Response
    {
        $societe = $this->societe();
        [$du, $au] = $this->plage($request);
        $livre = $this->etats->livre($this->etats->bulletins($societe, $du, $au));
        if ($this->enExcel($request)) {
            return $this->excel($this->export->livre($societe, $this->libellePlage($du, $au), $livre), 'livre-de-paie', $du, $au);
        }

        return $this->render('etat/livre.html.twig', $this->contexte($request, $du, $au) + ['livre' => $livre]);
    }

    #[Route('/rubriques', name: 'app_etat_rubriques')]
    public function rubriques(Request $request): Response
    {
        $societe = $this->societe();
        [$du, $au] = $this->plage($request);
        $nationalite = $this->nationalite($request);
        $etat = $this->etats->etatRubriques($this->etats->bulletins($societe, $du, $au, $nationalite));
        if ($this->enExcel($request)) {
            return $this->excel($this->export->rubriques($societe, $this->libellePlage($du, $au), $this->population($nationalite), $etat), 'etat-des-rubriques', $du, $au);
        }

        return $this->render('etat/rubriques.html.twig', $this->contexte($request, $du, $au) + ['etat' => $etat, 'population' => $this->population($nationalite)]);
    }

    #[Route('/bordereau-des-salaires', name: 'app_etat_bordereau')]
    public function bordereau(Request $request): Response
    {
        $societe = $this->societe();
        [$du, $au] = $this->plage($request);
        $bordereau = $this->etats->bordereau($this->etats->bulletins($societe, $du, $au), $request->query->getString('banque'), $societe->getDeviseEffective());
        if ($this->enExcel($request)) {
            return $this->excel($this->export->bordereau($societe, $this->libellePlage($du, $au), $bordereau), 'bordereau-des-salaires', $du, $au);
        }

        return $this->render('etat/bordereau.html.twig', $this->contexte($request, $du, $au) + ['bordereau' => $bordereau]);
    }

    /** Formulaire de l'écran des états (?salarie=…&annee=…) → fiche individuelle. */
    #[Route('/fiche-individuelle', name: 'app_etat_fiche_choisir')]
    public function ficheChoisir(Request $request): Response
    {
        $id = $request->query->getInt('salarie');
        if (!$id) {
            $this->addFlash('warning', 'Choisissez le salarié.');

            return $this->redirectToRoute('app_etats', ['annee' => $request->query->getInt('annee') ?: null]);
        }

        return $this->redirectToRoute('app_etat_fiche', ['id' => $id, 'annee' => $request->query->getInt('annee') ?: null, 'format' => $request->query->getString('format') ?: null]);
    }

    #[Route('/fiche-individuelle/{id}', name: 'app_etat_fiche', requirements: ['id' => '\d+'])]
    public function fiche(Affectation $affectation, Request $request): Response
    {
        $this->verifierAppartenance($affectation->getSociete());
        $annee = $request->query->getInt('annee') ?: (int) ($affectation->getDateSortie() ?? new \DateTimeImmutable())->format('Y');
        $fiche = $this->etats->ficheIndividuelle($affectation, $annee);
        if ($this->enExcel($request)) {
            $slug = (new AsciiSlugger())->slug($affectation->getSalarie()->getNomComplet())->lower();

            return $this->excel($this->export->fiche($affectation, $annee, $fiche), sprintf('fiche-individuelle-%s-%s', $affectation->getMatricule(), $slug), null, null, (string) $annee);
        }

        return $this->render('etat/fiche.html.twig', [
            'affectation' => $affectation,
            'annee' => $annee,
            'fiche' => $fiche,
            'devise' => $affectation->getSociete()->getDeviseEffective(),
        ]);
    }

    /**
     * Plage de mois ?du=AAAA-MM&au=AAAA-MM (mois courant par défaut), remise dans l'ordre et bornée.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function plage(Request $request): array
    {
        $lire = static function (string $valeur): ?\DateTimeImmutable {
            $date = '' !== $valeur ? \DateTimeImmutable::createFromFormat('!Y-m', $valeur) : false;

            return $date ?: null;
        };
        $du = $lire($request->query->getString('du')) ?? new \DateTimeImmutable('first day of this month midnight');
        $au = $lire($request->query->getString('au')) ?? $du;
        if ($au < $du) {
            [$du, $au] = [$au, $du];
        }
        if ($au > $du->modify(sprintf('+%d months', self::MOIS_MAX - 1))) {
            $au = $du->modify(sprintf('+%d months', self::MOIS_MAX - 1));
            $this->addFlash('warning', sprintf('Un état couvre au plus %d mois : la plage a été réduite.', self::MOIS_MAX));
        }

        return [$du, $au];
    }

    private function contexte(Request $request, \DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        return [
            'societe' => $this->societe(),
            'du' => $du,
            'au' => $au,
            'libellePlage' => $this->libellePlage($du, $au),
            'devise' => $this->societe()->getDeviseEffective(),
            'parametres' => $request->query->all(),
        ];
    }

    private function libellePlage(\DateTimeImmutable $du, \DateTimeImmutable $au): string
    {
        $mois = static fn (\DateTimeImmutable $d) => ucfirst(\IntlDateFormatter::formatObject($d, 'MMMM y', 'fr'));

        return $du == $au ? $mois($du) : sprintf('%s à %s', $mois($du), $mois($au));
    }

    private function nationalite(Request $request): ?string
    {
        $valeur = $request->query->getString('nationalite');

        return in_array($valeur, [EtatsPaie::NATIONAUX, EtatsPaie::NON_NATIONAUX], true) ? $valeur : null;
    }

    private function population(?string $nationalite): ?string
    {
        return match ($nationalite) {
            EtatsPaie::NATIONAUX => 'salariés nationaux',
            EtatsPaie::NON_NATIONAUX => 'salariés non nationaux (expatriés)',
            default => null,
        };
    }

    private function enExcel(Request $request): bool
    {
        if ('xlsx' !== $request->query->getString('format')) {
            return false;
        }
        $this->denyAccessUnlessGranted(P::ETAT_EXPORTER);

        return true;
    }

    private function excel(Spreadsheet $classeur, string $prefixe, ?\DateTimeImmutable $du, ?\DateTimeImmutable $au, ?string $suffixe = null): StreamedResponse
    {
        $suffixe ??= $du == $au ? $du->format('Y-m') : $du->format('Y-m').'-'.$au->format('Y-m');
        $nom = sprintf('%s-%s-%s.xlsx', $prefixe, (new AsciiSlugger())->slug($this->societe()->getRaisonSociale())->lower(), $suffixe);

        $reponse = new StreamedResponse(static fn () => (new Xlsx($classeur))->save('php://output'));
        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nom));

        return $reponse;
    }
}
