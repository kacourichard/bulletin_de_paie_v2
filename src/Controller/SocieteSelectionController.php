<?php

namespace App\Controller;

use App\Entity\Societe;
use App\Security\SocieteCourante;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SocieteSelectionController extends AbstractController
{
    public function __construct(private readonly SocieteCourante $societeCourante)
    {
    }

    #[Route('/societes/choisir', name: 'app_societe_choisir', methods: ['GET'])]
    public function choisir(): Response
    {
        return $this->render('societe/choisir.html.twig', [
            'societes' => $this->societeCourante->disponibles(),
        ]);
    }

    #[Route('/societes/{id}/basculer', name: 'app_societe_basculer', methods: ['POST'])]
    public function basculer(Societe $societe, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('basculer-societe', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->societeCourante->definir($societe);
        } catch (\LogicException) {
            throw $this->createAccessDeniedException();
        }

        $this->addFlash('success', sprintf('Vous travaillez maintenant sur « %s ».', $societe->getRaisonSociale()));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/societes/quitter', name: 'app_societe_quitter', methods: ['POST'])]
    public function quitter(Request $request): Response
    {
        if ($this->isCsrfTokenValid('basculer-societe', $request->getPayload()->getString('_token'))) {
            $this->societeCourante->oublier();
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
