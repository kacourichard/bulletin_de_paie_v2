<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\PermissionRepository;
use App\Security\PermissionCatalogue as P;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rôles de la société courante et matrice de leurs permissions.
 */
#[Route('/roles')]
#[IsGranted(P::ROLE_GERER)]
class RoleController extends AbstractSocieteController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'app_role')]
    public function index(PermissionRepository $permissions): Response
    {
        return $this->render('role/index.html.twig', [
            'roles' => $this->societe()->getRoles(),
            'permissions' => $permissions->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_role_nouveau')]
    public function nouveau(Request $request): Response
    {
        $role = (new Role())->setSociete($this->societe());

        return $this->traiterFormulaire($request, $role, 'Rôle « %s » créé.');
    }

    #[Route('/{id}/modifier', name: 'app_role_modifier')]
    public function modifier(Role $role, Request $request): Response
    {
        $this->verifierAppartenance($role->getSociete());

        return $this->traiterFormulaire($request, $role, 'Rôle « %s » mis à jour.');
    }

    #[Route('/{id}/supprimer', name: 'app_role_supprimer', methods: ['POST'])]
    public function supprimer(Role $role, Request $request): Response
    {
        $this->verifierAppartenance($role->getSociete());
        if (!$this->isCsrfTokenValid('role-'.$role->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($role->isSysteme()) {
            $this->addFlash('danger', 'Les rôles par défaut ne peuvent pas être supprimés.');
        } elseif (!$role->getMembres()->isEmpty()) {
            $this->addFlash('danger', 'Ce rôle est attribué à des utilisateurs : changez d\'abord leur rôle.');
        } else {
            $this->societe()->getRoles()->removeElement($role);
            $this->em->remove($role);
            $this->em->flush();
            $this->addFlash('success', sprintf('Rôle « %s » supprimé.', $role->getLibelle()));
        }

        return $this->redirectToRoute('app_role');
    }

    private function traiterFormulaire(Request $request, Role $role, string $succes): Response
    {
        $form = $this->createForm(RoleType::class, $role, ['code_verrouille' => $role->isSysteme()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null === $role->getId()) {
                $this->societe()->addRole($role);
                $this->em->persist($role);
            }
            $this->em->flush();
            $this->addFlash('success', sprintf($succes, $role->getLibelle()));

            return $this->redirectToRoute('app_role');
        }

        return $this->render('role/form.html.twig', [
            'form' => $form,
            'role' => $role,
        ]);
    }
}
