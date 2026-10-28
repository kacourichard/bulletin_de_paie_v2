<?php

namespace App\Controller\Plateforme;

use App\Entity\Succursale;
use App\Form\AdministrateurType;
use App\Form\SuccursaleType;
use App\Repository\AffectationRepository;
use App\Repository\SuccursaleRepository;
use App\Service\GestionSuccursales;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Super-admin : toutes les succursales et leurs administrateurs.
 * Le détail d'une succursale est commun avec ses administrateurs (App\Controller\Succursale).
 */
#[Route('/plateforme/succursales')]
class SuccursaleController extends AbstractController
{
    #[Route('', name: 'app_plateforme_succursales')]
    public function index(SuccursaleRepository $succursales, AffectationRepository $affectations): Response
    {
        return $this->render('plateforme/succursale/index.html.twig', [
            'succursales' => $succursales->findBy([], ['nom' => 'ASC']),
            'effectifs' => $affectations->effectifs(),
        ]);
    }

    #[Route('/nouvelle', name: 'app_plateforme_succursale_nouvelle')]
    public function nouvelle(Request $request, GestionSuccursales $gestion): Response
    {
        $succursale = new Succursale();
        $form = $this->createForm(SuccursaleType::class, $succursale);
        $form->add('administrateur', AdministrateurType::class, ['mapped' => false, 'label' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $form->get('administrateur')->getData();
            try {
                $motDePasse = $gestion->creer($succursale, $admin['email'], $admin['prenoms'], $admin['nom']);
                if ($motDePasse) {
                    $this->addFlash('mot_de_passe', ['email' => mb_strtolower(trim($admin['email'])), 'motDePasse' => $motDePasse]);
                }
                $this->addFlash('success', sprintf('Succursale « %s » créée. Son administrateur peut maintenant créer ses sociétés.', $succursale->getNom()));

                return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
            } catch (\DomainException $e) {
                $form->get('administrateur')->get('nom')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('plateforme/succursale/nouvelle.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/statut', name: 'app_plateforme_succursale_statut', methods: ['POST'])]
    public function statut(Succursale $succursale, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('succursale-'.$succursale->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $succursale->setActive(!$succursale->isActive());
        $em->flush();
        $this->addFlash('success', sprintf('Succursale « %s » %s.', $succursale->getNom(), $succursale->isActive() ? 'réactivée' : 'désactivée'));

        return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
    }
}
