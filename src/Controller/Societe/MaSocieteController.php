<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Controller\SignaleLegislationTrait;
use App\Form\SocieteType;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionSalaries;
use App\Service\LogoSociete;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/societe')]
class MaSocieteController extends AbstractSocieteController
{
    use SignaleLegislationTrait;

    #[Route('', name: 'app_ma_societe')]
    #[IsGranted(P::SOCIETE_VOIR)]
    public function show(): Response
    {
        return $this->render('societe/show.html.twig', ['societe' => $this->societe()]);
    }

    #[Route('/modifier', name: 'app_ma_societe_modifier')]
    #[IsGranted(P::SOCIETE_MODIFIER)]
    public function edit(Request $request, EntityManagerInterface $em, LogoSociete $logos, GestionSalaries $salaries): Response
    {
        $societe = $this->societe();
        $paysPaie = $societe->getPaysPaie();
        // Changer de pays modifierait toute la législation applicable : réservé à la plateforme.
        // La législation de paie peut aussi être choisie par l'administrateur de la succursale.
        $form = $this->createForm(SocieteType::class, $societe, [
            'modifier_pays' => false,
            'modifier_legislation' => $this->isGranted('ROLE_SUPER_ADMIN') || $societe->getSuccursale()->estAdministreePar($this->utilisateur()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logos->traiterFormulaire($form, $societe);
            $em->flush();
            $this->addFlash('success', 'Informations de la société enregistrées.');
            if ($societe->getPaysPaie() !== $paysPaie) {
                $this->signalerLegislation($societe, $salaries->adapterAuPaysPaie($societe));
            }

            return $this->redirectToRoute('app_ma_societe');
        }

        return $this->render('societe/edit.html.twig', ['form' => $form, 'societe' => $societe]);
    }
}
