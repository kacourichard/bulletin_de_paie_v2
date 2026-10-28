<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\ImportPointage;
use App\Entity\JourFerie;
use App\HeuresSup\RegimeHoraire;
use App\Repository\AffectationRepository;
use App\Repository\JourFerieRepository;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionPaie;
use App\Service\HeuresSupplementaires;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Heures supplémentaires (module repris du projet heures-sup) : import du relevé de pointage, calcul selon
 * le régime 40 h / 42 h et le calendrier des jours fériés, report dans la paie du mois.
 */
#[Route('/heures-sup')]
#[IsGranted(P::PAIE_VOIR)]
class HeuresSupController extends AbstractSocieteController
{
    public function __construct(
        private readonly HeuresSupplementaires $heuresSup,
        private readonly GestionPaie $paie,
        private readonly EntityManagerInterface $em,
        private readonly AffectationRepository $affectations,
        private readonly JourFerieRepository $joursFeries,
    ) {
    }

    #[Route('', name: 'app_heures_sup')]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $mois = $this->mois($request->query->getString('mois'));

        return $this->render('heures_sup/index.html.twig', [
            'societe' => $societe,
            'mois' => $mois,
            'imports' => $this->em->getRepository(ImportPointage::class)->findBy(['societe' => $societe, 'mois' => $mois], ['importeLe' => 'DESC']),
            'periode' => $this->paie->trouver($societe, $mois),
            'regimes' => RegimeHoraire::cases(),
            'precedent' => $mois->modify('-1 month'),
            'suivant' => $mois->modify('+1 month'),
            'feriesDuMois' => $this->joursFeries->pourSociete($societe, $mois, $mois->modify('last day of this month')),
        ]);
    }

    #[Route('/importer', name: 'app_heures_sup_importer', methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function importer(Request $request): Response
    {
        $this->verifierCsrf($request);
        $mois = $this->mois($request->getPayload()->getString('mois'));
        $fichier = $request->files->get('fichier');
        if (!$fichier instanceof UploadedFile || !$fichier->isValid()) {
            $this->addFlash('danger', 'Choisissez le fichier de pointage (Excel ou CSV).');

            return $this->redirectToRoute('app_heures_sup', ['mois' => $mois->format('Y-m')]);
        }
        if (!in_array(strtolower($fichier->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
            $this->addFlash('danger', sprintf('« %s » n\'est pas un fichier Excel (.xlsx, .xls) ou CSV. Partez d\'un fichier d\'exemple de cette page.', $fichier->getClientOriginalName()));

            return $this->redirectToRoute('app_heures_sup', ['mois' => $mois->format('Y-m')]);
        }
        // Gros relevés : un mois de pointage peut compter des dizaines de milliers de lignes.
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');
        try {
            $import = $this->heuresSup->importer($this->societe(), $mois, $fichier->getPathname(), $fichier->getClientOriginalName(),
                RegimeHoraire::tryFrom($request->getPayload()->getString('regime')) ?? RegimeHoraire::H40, $this->utilisateur());
            $this->addFlash('success', sprintf('Pointage importé : %d ligne(s), %d salarié(s), du %s au %s.', $import->getNombreLignes(), count($import->getDonnees()), $import->getDu()->format('d/m/Y'), $import->getAu()->format('d/m/Y')));
            $inconnus = $this->heuresSup->nonRapproches($import);
            if ($inconnus) {
                $this->addFlash('warning', sprintf(
                    count($inconnus) === count($import->getDonnees())
                        ? 'Aucun salarié du fichier n\'est reconnu (%s) : vérifiez qu\'il s\'agit bien du pointage de %s. Si le logiciel utilise ses propres identifiants (badges), rattachez chaque identifiant à son salarié ci-dessous ; le lien est mémorisé.'
                        : '%s : identifiant(s) inconnu(s) dans %s, à rattacher à leur salarié ci-dessous (sinon leurs heures ne sont pas reportées).',
                    implode(', ', array_slice($inconnus, 0, 8)).(count($inconnus) > 8 ? '…' : ''),
                    $this->societe()->getRaisonSociale(),
                ));
            }

            return $this->redirectToRoute('app_heures_sup_voir', ['id' => $import->getId()]);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_heures_sup', ['mois' => $mois->format('Y-m')]);
        }
    }

    /** Fichiers d'exemple : modèle simple, ou ?format=logiciel pour l'export du logiciel de pointage. */
    #[Route('/modele', name: 'app_heures_sup_modele')]
    public function modele(Request $request): Response
    {
        return 'logiciel' === $request->query->getString('format')
            ? $this->excel($this->heuresSup->exempleLogiciel($this->societe()), 'exemple-pointage-export-logiciel.xlsx')
            : $this->excel($this->heuresSup->modele($this->societe()), 'exemple-pointage-modele-simple.xlsx');
    }

    #[Route('/{id}', name: 'app_heures_sup_voir', requirements: ['id' => '\d+'])]
    public function voir(ImportPointage $import, Request $request): Response
    {
        $this->verifierAppartenance($import->getSociete());
        if ('xlsx' === $request->query->getString('format')) {
            return $this->excel($this->heuresSup->export($import), sprintf('heures-sup-%s-%s.xlsx', (new AsciiSlugger())->slug($import->getSociete()->getRaisonSociale())->lower(), $import->getMois()->format('Y-m')));
        }
        $lignes = $this->heuresSup->calculer($import);
        $totaux = ['heures' => 0.0, 'hs15' => 0.0, 'hs50' => 0.0, 'hs75' => 0.0, 'hs100' => 0.0, 'panier' => 0, 'feries' => 0, 'nonRapproches' => 0];
        foreach ($lignes as $l) {
            $r = $l['resultat'];
            $totaux['heures'] += $r->heuresTravaillees;
            foreach (['hs15', 'hs50', 'hs75', 'hs100'] as $t) {
                $totaux[$t] += $r->$t;
            }
            $totaux['panier'] += $r->joursPanier;
            $totaux['feries'] += $r->joursFeriesTravailles;
            $totaux['nonRapproches'] += null === $l['affectation'] ? 1 : 0;
        }

        return $this->render('heures_sup/voir.html.twig', [
            'import' => $import,
            'lignes' => $lignes,
            'totaux' => $totaux,
            'libellesFeries' => $this->heuresSup->libellesFeries($import),
            'periode' => $this->paie->trouver($import->getSociete(), $import->getMois()),
            'salaries' => $this->affectations->presentesSurPeriode($import->getSociete(), $import->getDu(), $import->getAu()),
            'rubriques' => HeuresSupplementaires::RUBRIQUES + ['panier' => HeuresSupplementaires::RUBRIQUE_PANIER],
        ]);
    }

    #[Route('/{id}/lier', name: 'app_heures_sup_lier', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function lier(ImportPointage $import, Request $request): Response
    {
        $this->verifierAppartenance($import->getSociete());
        $this->verifierCsrf($request);
        $identifiant = $request->getPayload()->getString('identifiant');
        $affectation = $request->getPayload()->getInt('affectation') ? $this->affectations->find($request->getPayload()->getInt('affectation')) : null;
        try {
            $this->heuresSup->lier($import, $identifiant, $affectation);
            $this->addFlash('success', $affectation
                ? sprintf('Identifiant de pointage %s rattaché à %s (mémorisé pour les prochains imports).', $identifiant, $affectation->getSalarie()->getNomComplet())
                : sprintf('Identifiant de pointage %s détaché.', $identifiant));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_heures_sup_voir', ['id' => $import->getId()]);
    }

    #[Route('/{id}/reporter', name: 'app_heures_sup_reporter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function reporter(ImportPointage $import, Request $request): Response
    {
        $this->verifierAppartenance($import->getSociete());
        $this->verifierCsrf($request);
        $periode = $this->paie->trouver($import->getSociete(), $import->getMois());
        if (null === $periode) {
            $this->addFlash('danger', 'Ouvrez d\'abord la paie du mois pour y reporter les heures.');

            return $this->redirectToRoute('app_heures_sup_voir', ['id' => $import->getId()]);
        }
        $panier = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], $request->getPayload()->getString('panier'));
        try {
            $stats = $this->heuresSup->reporter($import, $periode, is_numeric($panier) && $panier > 0 ? number_format((float) $panier, 2, '.', '') : null, $this->utilisateur());
            $this->addFlash('success', sprintf('%d élément(s) reporté(s) pour %d salarié(s) dans la paie — %s.', $stats['elements'], $stats['salaries'], $periode->getLibelle()));
            foreach ($stats['avertissements'] as $a) {
                $this->addFlash('warning', $a);
            }
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_heures_sup_voir', ['id' => $import->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'app_heures_sup_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function supprimer(ImportPointage $import, Request $request): Response
    {
        $this->verifierAppartenance($import->getSociete());
        $this->verifierCsrf($request);
        $mois = $import->getMois()->format('Y-m');
        $this->em->remove($import);
        $this->em->flush();
        $this->addFlash('warning', 'Import supprimé. Les heures déjà reportées restent dans la paie du mois (à supprimer depuis la paie si besoin).');

        return $this->redirectToRoute('app_heures_sup', ['mois' => $mois]);
    }

    /** Jours fériés : nationaux (modifiables par la plateforme) et propres à la société. */
    #[Route('/jours-feries', name: 'app_heures_sup_feries')]
    public function feries(Request $request): Response
    {
        $societe = $this->societe();
        $annee = $request->query->getInt('annee') ?: (int) date('Y');

        return $this->render('heures_sup/feries.html.twig', [
            'societe' => $societe,
            'annee' => $annee,
            'jours' => $this->joursFeries->pourSociete($societe, new \DateTimeImmutable($annee.'-01-01'), new \DateTimeImmutable($annee.'-12-31')),
            'peutNational' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'peutSociete' => $this->peutGererFeries(),
        ]);
    }

    #[Route('/jours-feries/enregistrer/{id}', name: 'app_heures_sup_ferie_enregistrer', requirements: ['id' => '\d+'], defaults: ['id' => null], methods: ['POST'])]
    public function enregistrerFerie(?int $id, Request $request): Response
    {
        $this->verifierCsrf($request);
        $societe = $this->societe();
        $p = $request->getPayload();
        $national = $p->getBoolean('national');
        $jour = $id ? $this->joursFeries->find($id) : new JourFerie($societe->getPaysPaie(), $national ? null : $societe);
        if (null === $jour || $jour->getPays() !== $societe->getPaysPaie() || (!$jour->isNational() && $jour->getSociete() !== $societe)) {
            throw $this->createNotFoundException();
        }
        $this->verifierDroitFerie($jour);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $p->getString('date'));
        if (!$date || '' === trim($p->getString('libelle'))) {
            $this->addFlash('danger', 'Indiquez la date et le libellé du jour férié.');

            return $this->redirectToRoute('app_heures_sup_feries', ['annee' => $date ? $date->format('Y') : null]);
        }
        $jour->setDate($date)->setLibelle(mb_substr($p->getString('libelle'), 0, 120))->setChomePaye($p->getBoolean('chomePaye'))
            ->setVeille($p->getBoolean('veille'))->setLendemain($p->getBoolean('lendemain'))->setAConfirmer(false);
        $this->em->persist($jour);
        $this->em->flush();
        $this->addFlash('success', sprintf('Jour férié du %s enregistré. Les calculs d\'heures supplémentaires en tiennent compte immédiatement.', $date->format('d/m/Y')));

        return $this->redirectToRoute('app_heures_sup_feries', ['annee' => $date->format('Y')]);
    }

    #[Route('/jours-feries/{id}/supprimer', name: 'app_heures_sup_ferie_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimerFerie(#[MapEntity(id: 'id')] JourFerie $jour, Request $request): Response
    {
        $this->verifierCsrf($request);
        if ($jour->getPays() !== $this->societe()->getPaysPaie() || (!$jour->isNational() && $jour->getSociete() !== $this->societe())) {
            throw $this->createNotFoundException();
        }
        $this->verifierDroitFerie($jour);
        $annee = $jour->getDate()->format('Y');
        $this->em->remove($jour);
        $this->em->flush();
        $this->addFlash('warning', 'Jour férié supprimé.');

        return $this->redirectToRoute('app_heures_sup_feries', ['annee' => $annee]);
    }

    private function peutGererFeries(): bool
    {
        return $this->isGranted(P::PAIE_PREPARER) || $this->isGranted(P::PARAMETRE_GERER);
    }

    /** Nationaux : super-administrateur (valent pour toutes les sociétés du pays) ; propres à la société : RH / comptable. */
    private function verifierDroitFerie(JourFerie $jour): void
    {
        if ($jour->isNational() ? !$this->isGranted('ROLE_SUPER_ADMIN') : !$this->peutGererFeries()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function mois(string $code): \DateTimeImmutable
    {
        $mois = ('' !== $code ? \DateTimeImmutable::createFromFormat('!Y-m', $code) : false) ?: new \DateTimeImmutable('first day of this month');

        return $mois->modify('first day of this month')->setTime(0, 0);
    }

    private function verifierCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('heures-sup', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }

    private function excel(Spreadsheet $classeur, string $nom): StreamedResponse
    {
        $reponse = new StreamedResponse(static fn () => (new Xlsx($classeur))->save('php://output'));
        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nom));

        return $reponse;
    }
}
