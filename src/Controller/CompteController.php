<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangerMotDePasseType;
use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte')]
class CompteController extends AbstractController
{
    #[Route('', name: 'app_compte')]
    public function profil(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('compte/profil.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/mot-de-passe', name: 'app_compte_mot_de_passe')]
    public function motDePasse(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangerMotDePasseType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('nouveau')->getData()));
            $user->setDoitChangerMotDePasse(false);
            $em->flush();
            $this->addFlash('success', 'Votre mot de passe a été modifié.');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('compte/mot_de_passe.html.twig', ['form' => $form, 'force' => $user->isDoitChangerMotDePasse()]);
    }
}
