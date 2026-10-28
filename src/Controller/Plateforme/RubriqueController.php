<?php

namespace App\Controller\Plateforme;

use App\Entity\Pays;
use App\Entity\Rubrique;
use App\Form\RubriqueType;
use App\Repository\AvantageRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Référentiel des rubriques de paie d'un pays (codes, traitement social et fiscal).
 */
#[Route('/plateforme/pays/{pays}/rubriques')]
class RubriqueController extends AbstractController
{
    #[Route('', name: 'app_plateforme_rubriques')]
    public function index(#[MapEntity(id: 'pays')] Pays $pays, RubriqueRepository $rubriques, AvantageRepository $avantages): Response
    {
        $liste = $rubriques->duPays($pays);
        $utilisations = [];
        foreach ([] === $liste ? [] : $avantages->createQueryBuilder('a')->select('IDENTITY(a.rubrique) AS r, COUNT(a.id) AS n')->where('a.rubrique IN (:liste)')->setParameter('liste', $liste)->groupBy('a.rubrique')->getQuery()->getArrayResult() as $ligne) {
            $utilisations[(int) $ligne['r']] = (int) $ligne['n'];
        }

        return $this->render('plateforme/rubrique/index.html.twig', ['pays' => $pays, 'rubriques' => $liste, 'utilisations' => $utilisations]);
    }

    #[Route('/nouvelle', name: 'app_plateforme_rubrique_nouvelle')]
    public function nouvelle(#[MapEntity(id: 'pays')] Pays $pays, Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, $pays, (new Rubrique())->setPays($pays), 'Rubrique %s créée.');
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_rubrique_modifier')]
    public function modifier(#[MapEntity(id: 'pays')] Pays $pays, Rubrique $rubrique, Request $request, EntityManagerInterface $em): Response
    {
        if ($rubrique->getPays() !== $pays) {
            throw $this->createNotFoundException();
        }

        return $this->formulaire($request, $em, $pays, $rubrique, 'Rubrique %s mise à jour.');
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Pays $pays, Rubrique $rubrique, string $succes): Response
    {
        $form = $this->createForm(RubriqueType::class, $rubrique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rubrique);
            $em->flush();
            $this->addFlash('success', sprintf($succes, $rubrique->getCode()));

            return $this->redirectToRoute('app_plateforme_rubriques', ['pays' => $pays->getId()]);
        }

        return $this->render('plateforme/rubrique/form.html.twig', ['form' => $form, 'pays' => $pays, 'rubrique' => $rubrique]);
    }
}
