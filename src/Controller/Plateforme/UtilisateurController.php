<?php

namespace App\Controller\Plateforme;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\GestionMembres;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tous les comptes de la plateforme, toutes sociétés confondues.
 */
#[Route('/plateforme/utilisateurs')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'app_plateforme_utilisateurs')]
    public function index(Request $request, UserRepository $users): Response
    {
        $q = trim($request->query->getString('q'));
        $profil = $request->query->getString('profil');
        $qb = $users->createQueryBuilder('u')
            ->leftJoin('u.membres', 'm')->addSelect('m')
            ->leftJoin('m.societe', 's')->addSelect('s')
            ->leftJoin('m.role', 'r')->addSelect('r')
            ->leftJoin('u.succursalesAdministrees', 'sa')->addSelect('sa')
            ->orderBy('u.nom')->addOrderBy('u.prenoms');
        if ('' !== $q) {
            $qb->andWhere('u.nom LIKE :q OR u.prenoms LIKE :q OR u.email LIKE :q')->setParameter('q', '%'.$q.'%');
        }
        match ($profil) {
            'administrateurs' => $qb->andWhere('sa.id IS NOT NULL'),
            'super' => $qb->andWhere('u.superAdmin = true'),
            'operationnels' => $qb->andWhere('m.id IS NOT NULL'),
            default => null,
        };

        return $this->render('plateforme/utilisateur/index.html.twig', [
            'utilisateurs' => $qb->getQuery()->getResult(),
            'q' => $q,
            'profil' => $profil,
        ]);
    }

    #[Route('/{id}/statut', name: 'app_plateforme_utilisateur_statut', methods: ['POST'])]
    public function statut(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifier($request, $user);
        $user->setActif(!$user->isActif());
        $em->flush();
        $this->addFlash('success', sprintf('Compte de %s %s.', $user->getNomComplet(), $user->isActif() ? 'réactivé' : 'désactivé'));

        return $this->redirectToRoute('app_plateforme_utilisateurs');
    }

    #[Route('/{id}/super-admin', name: 'app_plateforme_utilisateur_super_admin', methods: ['POST'])]
    public function superAdmin(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifier($request, $user);
        $user->setSuperAdmin(!$user->isSuperAdmin());
        $em->flush();
        $this->addFlash('success', sprintf('%s %s super-administrateur.', $user->getNomComplet(), $user->isSuperAdmin() ? 'est maintenant' : 'n\'est plus'));

        return $this->redirectToRoute('app_plateforme_utilisateurs');
    }

    #[Route('/{id}/reinitialiser', name: 'app_plateforme_utilisateur_reinitialiser', methods: ['POST'])]
    public function reinitialiser(User $user, Request $request, GestionMembres $gestion): Response
    {
        $this->verifier($request, $user);
        $this->addFlash('mot_de_passe', ['email' => $user->getEmail(), 'motDePasse' => $gestion->reinitialiserMotDePasse($user)]);

        return $this->redirectToRoute('app_plateforme_utilisateurs');
    }

    private function verifier(Request $request, User $user): void
    {
        if (!$this->isCsrfTokenValid('user-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($user === $this->getUser()) {
            throw $this->createAccessDeniedException('Action impossible sur votre propre compte.');
        }
    }
}
