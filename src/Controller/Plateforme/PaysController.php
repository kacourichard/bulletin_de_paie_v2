<?php

namespace App\Controller\Plateforme;

use App\Entity\Pays;
use App\Form\PaysType;
use App\Repository\PaysRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plateforme/pays')]
class PaysController extends AbstractController
{
    #[Route('', name: 'app_plateforme_pays')]
    public function index(PaysRepository $pays): Response
    {
        return $this->render('plateforme/pays/index.html.twig', [
            'liste' => $pays->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_plateforme_pays_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, new Pays(), 'Pays « %s » ajouté.');
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_pays_modifier')]
    public function modifier(Pays $pays, Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, $pays, 'Pays « %s » mis à jour.');
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Pays $pays, string $succes): Response
    {
        $form = $this->createForm(PaysType::class, $pays);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($pays);
            $em->flush();
            $this->addFlash('success', sprintf($succes, $pays->getNom()));

            return $this->redirectToRoute('app_plateforme_pays');
        }

        return $this->render('plateforme/pays/form.html.twig', ['form' => $form, 'pays' => $pays]);
    }
}
