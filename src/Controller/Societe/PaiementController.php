<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Affectation;
use App\Entity\Paiement;
use App\Entity\PeriodePaie;
use App\Enum\ModePaiement;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionPaie;
use App\Service\GestionPaiements;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Trésorerie : paiement des salaires des paies validées (un salarié, tous, ou par moyen de paiement),
 * annulation, ordre de virement Excel et état d'émargement imprimable.
 */
#[Route('/paiements')]
#[IsGranted(P::PAIEMENT_VOIR)]
class PaiementController extends AbstractSocieteController
{
    public function __construct(
        private readonly GestionPaie $paie,
        private readonly GestionPaiements $paiements,
    ) {
    }

    #[Route('', name: 'app_paiements')]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $code = $request->query->getString('mois');
        $mois = ('' !== $code ? \DateTimeImmutable::createFromFormat('!Y-m', $code) : false) ?: new \DateTimeImmutable('first day of this month');
        $mois = $mois->modify('first day of this month')->setTime(0, 0);
        $periode = $this->paie->trouver($societe, $mois);

        return $this->render('paiement/index.html.twig', [
            'societe' => $societe,
            'mois' => $mois,
            'periode' => $periode,
            'etat' => $periode ? $this->paiements->etat($periode) : null,
            'modes' => ModePaiement::cases(),
            'precedent' => $mois->modify('-1 month'),
            'suivant' => $mois->modify('+1 month'),
        ]);
    }

    #[Route('/{mois}/payer/{id}', name: 'app_paiement_payer', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIEMENT_EXECUTER)]
    public function payer(string $mois, Affectation $affectation, Request $request): Response
    {
        $periode = $this->periode($mois, $request);
        $this->verifierAppartenance($affectation->getSociete());
        try {
            $paiement = $this->paiements->payer($periode, $affectation, $this->mode($request) ?? $affectation->getSalarie()->getModePaiement(), $this->date($request), $this->reference($request), $this->utilisateur());
            $this->addFlash('success', sprintf('Paiement de %s enregistré (%s).', $affectation->getSalarie()->getNomComplet(), $paiement->getMode()->libelle()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paiements', ['mois' => $mois]);
    }

    /** Paie tous les salariés restant à payer, éventuellement pour un seul moyen de paiement. */
    #[Route('/{mois}/payer-tous', name: 'app_paiement_payer_tous', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIEMENT_EXECUTER)]
    public function payerTous(string $mois, Request $request): Response
    {
        $periode = $this->periode($mois, $request);
        $mode = $this->mode($request);
        try {
            $nombre = $this->paiements->payerTous($periode, $mode, $this->date($request), $this->reference($request), $this->utilisateur());
            $this->addFlash($nombre ? 'success' : 'warning', $nombre
                ? sprintf('%d paiement(s) enregistré(s)%s.', $nombre, $mode ? ' ('.mb_strtolower($mode->libelle()).')' : '')
                : 'Aucun salarié restant à payer'.($mode ? ' par '.mb_strtolower($mode->libelle()) : '').'.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paiements', ['mois' => $mois]);
    }

    #[Route('/{mois}/annuler/{paiement}', name: 'app_paiement_annuler', requirements: ['mois' => '\d{4}-\d{2}', 'paiement' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIEMENT_EXECUTER)]
    public function annuler(string $mois, #[MapEntity(id: 'paiement')] Paiement $paiement, Request $request): Response
    {
        $periode = $this->periode($mois, $request);
        if ($paiement->getPeriode() !== $periode) {
            throw $this->createNotFoundException();
        }
        try {
            $this->paiements->annuler($paiement, $request->getPayload()->getString('motif'), $this->utilisateur());
            $this->addFlash('warning', sprintf('Paiement de %s annulé : il apparaît de nouveau à payer.', $paiement->getAffectation()->getSalarie()->getNomComplet()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paiements', ['mois' => $mois]);
    }

    /** Ordre de virement (Excel) : salariés restant à payer, ou tous avec ?tous=1. */
    #[Route('/{mois}/ordre-virement', name: 'app_paiement_ordre', requirements: ['mois' => '\d{4}-\d{2}'])]
    public function ordre(string $mois, Request $request): Response
    {
        $periode = $this->periode($mois);
        $classeur = $this->paiements->ordreVirement($periode, $request->query->getBoolean('tous'));
        $nom = sprintf('paiement-salaires-%s-%s.xlsx', (new AsciiSlugger())->slug($periode->getSociete()->getRaisonSociale())->lower(), $periode->getCode());

        $reponse = new StreamedResponse(static fn () => (new Xlsx($classeur))->save('php://output'));
        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nom));

        return $reponse;
    }

    /** État de paiement imprimable avec colonne d'émargement (chèques, espèces). */
    #[Route('/{mois}/etat', name: 'app_paiement_etat', requirements: ['mois' => '\d{4}-\d{2}'])]
    public function etat(string $mois): Response
    {
        $periode = $this->periode($mois);

        return $this->render('paiement/etat.html.twig', [
            'periode' => $periode,
            'etat' => $this->paiements->etat($periode),
            'devise' => $periode->getSociete()->getDeviseEffective(),
        ]);
    }

    private function periode(string $mois, ?Request $csrf = null): PeriodePaie
    {
        if ($csrf && !$this->isCsrfTokenValid('paiements-'.$mois, $csrf->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $mois) ?: throw $this->createNotFoundException();

        return $this->paie->trouver($this->societe(), $date) ?? throw $this->createNotFoundException('La paie de ce mois n\'est pas ouverte.');
    }

    private function mode(Request $request): ?ModePaiement
    {
        return ModePaiement::tryFrom($request->getPayload()->getString('mode'));
    }

    private function date(Request $request): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->getPayload()->getString('date'));

        return $date ?: new \DateTimeImmutable('today');
    }

    private function reference(Request $request): ?string
    {
        return mb_substr(trim($request->getPayload()->getString('reference')), 0, 80) ?: null;
    }
}
