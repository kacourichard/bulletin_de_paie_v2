<?php

namespace App\Controller\Plateforme;

use App\Entity\Cotisation;
use App\Entity\Pays;
use App\Form\CotisationType;
use App\Repository\CotisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Cotisations sociales et fiscales d'un pays (2e partie du bulletin). */
#[Route('/plateforme/pays/{pays}/cotisations')]
class CotisationController extends AbstractController
{
    #[Route('', name: 'app_plateforme_cotisations')]
    public function index(#[MapEntity(id: 'pays')] Pays $pays, CotisationRepository $cotisations): Response
    {
        return $this->render('plateforme/cotisation/index.html.twig', ['pays' => $pays, 'cotisations' => $cotisations->duPays($pays)]);
    }

    #[Route('/nouvelle', name: 'app_plateforme_cotisation_nouvelle')]
    public function nouvelle(#[MapEntity(id: 'pays')] Pays $pays, Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, $pays, (new Cotisation())->setPays($pays));
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_cotisation_modifier')]
    public function modifier(#[MapEntity(id: 'pays')] Pays $pays, Cotisation $cotisation, Request $request, EntityManagerInterface $em): Response
    {
        if ($cotisation->getPays() !== $pays) {
            throw $this->createNotFoundException();
        }

        return $this->formulaire($request, $em, $pays, $cotisation);
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Pays $pays, Cotisation $cotisation): Response
    {
        $form = $this->createForm(CotisationType::class, $cotisation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cotisation);
            $em->flush();
            $this->addFlash('success', sprintf('Cotisation « %s » enregistrée.', $cotisation->getLibelle()));

            return $this->redirectToRoute('app_plateforme_cotisations', ['pays' => $pays->getId()]);
        }

        return $this->render('plateforme/cotisation/form.html.twig', ['form' => $form, 'pays' => $pays, 'cotisation' => $cotisation]);
    }
}
