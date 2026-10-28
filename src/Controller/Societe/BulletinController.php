<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Affectation;
use App\Entity\PeriodePaie;
use App\Repository\AffectationRepository;
use App\Security\PermissionCatalogue as P;
use App\Service\BulletinPaie;
use App\Service\GestionPaie;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Bulletins de paie du mois : consultation, PDF (un salarié ou tous pour impression), envoi par e-mail.
 */
#[Route('/bulletins')]
#[IsGranted(P::BULLETIN_IMPRIMER)]
class BulletinController extends AbstractSocieteController
{
    public function __construct(
        private readonly GestionPaie $paie,
        private readonly BulletinPaie $bulletins,
        private readonly AffectationRepository $affectations,
    ) {
    }

    #[Route('', name: 'app_bulletins')]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $code = $request->query->getString('mois');
        $mois = ('' !== $code ? \DateTimeImmutable::createFromFormat('!Y-m', $code) : false) ?: new \DateTimeImmutable('first day of this month');
        $mois = $mois->modify('first day of this month')->setTime(0, 0);
        $periode = $this->paie->trouver($societe, $mois);

        return $this->render('bulletin/index.html.twig', [
            'societe' => $societe,
            'mois' => $mois,
            'periode' => $periode,
            'bulletins' => $periode ? $this->bulletins->donnees($periode) : [],
            'envois' => $periode ? $this->bulletins->derniersEnvois($periode) : [],
            'precedent' => $mois->modify('-1 month'),
            'suivant' => $mois->modify('+1 month'),
        ]);
    }

    /** Bulletin(s) à l'écran, imprimables depuis le navigateur. */
    #[Route('/{mois}/voir/{id}', name: 'app_bulletin_voir', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], defaults: ['id' => null])]
    public function voir(string $mois, ?int $id): Response
    {
        [$periode, $affectation] = $this->contexte($mois, $id);

        return new Response($this->bulletins->html($periode, $this->bulletins->donnees($periode, $affectation), true, $this->generateUrl('app_bulletin_pdf', ['mois' => $mois, 'id' => $id])));
    }

    /** PDF d'un salarié, ou de tous les salariés du mois (un bulletin par page) pour impression. */
    #[Route('/{mois}/pdf/{id}', name: 'app_bulletin_pdf', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], defaults: ['id' => null])]
    public function pdf(string $mois, ?int $id, Request $request): Response
    {
        [$periode, $affectation] = $this->contexte($mois, $id);
        $donnees = $this->bulletins->donnees($periode, $affectation);
        if (!$donnees) {
            $this->addFlash('warning', 'Aucun salarié sur ce mois.');

            return $this->redirectToRoute('app_bulletins', ['mois' => $mois]);
        }

        return new Response($this->bulletins->pdf($periode, $donnees), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $request->query->getBoolean('telecharger', true) ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $this->bulletins->nomFichier($periode, $affectation),
            ),
        ]);
    }

    #[Route('/{mois}/envoyer/{id}', name: 'app_bulletin_envoyer', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::BULLETIN_ENVOYER)]
    public function envoyer(string $mois, int $id, Request $request): Response
    {
        [$periode, $affectation] = $this->contexte($mois, $id);
        $this->verifierCsrf($request, 'bulletins-'.$mois);
        try {
            $this->bulletins->envoyer($periode, $affectation, $this->utilisateur());
            $this->addFlash('success', sprintf('Bulletin envoyé à %s (%s).', $affectation->getSalarie()->getNomComplet(), $affectation->getSalarie()->getEmail()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_bulletins', ['mois' => $mois]);
    }

    /** Envoi à tous les salariés ayant une adresse e-mail. */
    #[Route('/{mois}/envoyer-tous', name: 'app_bulletin_envoyer_tous', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::BULLETIN_ENVOYER)]
    public function envoyerTous(string $mois, Request $request): Response
    {
        [$periode] = $this->contexte($mois, null);
        $this->verifierCsrf($request, 'bulletins-'.$mois);
        if (!$periode->isCloturee()) {
            $this->addFlash('danger', 'Validez la paie avant d\'envoyer les bulletins.');

            return $this->redirectToRoute('app_bulletins', ['mois' => $mois]);
        }

        $envoyes = 0;
        $sansEmail = [];
        $echecs = [];
        foreach ($this->bulletins->donnees($periode) as $b) {
            $affectation = $b['affectation'];
            if (!$affectation->getSalarie()->getEmail()) {
                $sansEmail[] = $affectation->getSalarie()->getNomComplet();
                continue;
            }
            try {
                $this->bulletins->envoyer($periode, $affectation, $this->utilisateur());
                ++$envoyes;
            } catch (\DomainException $e) {
                $echecs[] = $e->getMessage();
            }
        }

        $this->addFlash($envoyes ? 'success' : 'warning', sprintf('%d bulletin(s) envoyé(s) par e-mail.', $envoyes));
        if ($sansEmail) {
            $this->addFlash('warning', sprintf('Sans adresse e-mail (bulletin non envoyé) : %s. Renseignez l\'e-mail dans la fiche du salarié.', implode(', ', $sansEmail)));
        }
        foreach ($echecs as $echec) {
            $this->addFlash('danger', $echec);
        }

        return $this->redirectToRoute('app_bulletins', ['mois' => $mois]);
    }

    /** @return array{0: PeriodePaie, 1: ?Affectation} */
    private function contexte(string $mois, ?int $id): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $mois) ?: throw $this->createNotFoundException();
        $periode = $this->paie->trouver($this->societe(), $date) ?? throw $this->createNotFoundException('La paie de ce mois n\'est pas ouverte.');
        $affectation = null;
        if (null !== $id) {
            $affectation = $this->affectations->find($id) ?? throw $this->createNotFoundException();
            $this->verifierAppartenance($affectation->getSociete());
        }

        return [$periode, $affectation];
    }

    private function verifierCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }
}
