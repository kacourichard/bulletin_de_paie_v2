<?php

namespace App\Controller\Plateforme;

use App\Entity\Pays;
use App\Entity\SecteurActivite;
use App\Form\SecteurActiviteType;
use App\Repository\SecteurActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Secteurs d'activité d'un pays et leur taux de risque accident du travail. */
#[Route('/plateforme/pays/{pays}/secteurs')]
class SecteurActiviteController extends AbstractController
{
    #[Route('', name: 'app_plateforme_secteurs')]
    public function index(#[MapEntity(id: 'pays')] Pays $pays, SecteurActiviteRepository $secteurs): Response
    {
        return $this->render('plateforme/secteur/index.html.twig', ['pays' => $pays, 'secteurs' => $secteurs->duPays($pays)]);
    }

    #[Route('/nouveau', name: 'app_plateforme_secteur_nouveau')]
    public function nouveau(#[MapEntity(id: 'pays')] Pays $pays, Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, $pays, (new SecteurActivite())->setPays($pays));
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_secteur_modifier')]
    public function modifier(#[MapEntity(id: 'pays')] Pays $pays, SecteurActivite $secteur, Request $request, EntityManagerInterface $em): Response
    {
        if ($secteur->getPays() !== $pays) {
            throw $this->createNotFoundException();
        }

        return $this->formulaire($request, $em, $pays, $secteur);
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Pays $pays, SecteurActivite $secteur): Response
    {
        $form = $this->createForm(SecteurActiviteType::class, $secteur);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($secteur);
            $em->flush();
            $this->addFlash('success', sprintf('Secteur « %s » enregistré.', $secteur->getLibelle()));

            return $this->redirectToRoute('app_plateforme_secteurs', ['pays' => $pays->getId()]);
        }

        return $this->render('plateforme/secteur/form.html.twig', ['form' => $form, 'pays' => $pays, 'secteur' => $secteur]);
    }
}
