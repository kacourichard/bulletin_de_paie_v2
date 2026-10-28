<?php

namespace App\Controller\Succursale;

use App\Entity\Societe;
use App\Entity\Succursale;
use App\Entity\User;
use App\Form\AdministrateurType;
use App\Form\SocieteType;
use App\Form\SuccursaleType;
use App\Repository\AffectationRepository;
use App\Security\SocieteCourante;
use App\Service\GestionSuccursales;
use App\Service\LogoSociete;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace d'administration d'une succursale : ses sociétés et ses administrateurs.
 * Accessible à ses administrateurs et au super-admin.
 */
#[Route('/succursales')]
class SuccursaleController extends AbstractController
{
    public function __construct(
        private readonly GestionSuccursales $gestion,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Liste des succursales administrées (s'il y en a plusieurs). */
    #[Route('', name: 'app_succursales')]
    public function index(): Response
    {
        $succursales = $this->utilisateur()->getSuccursalesAdministreesActives();
        if (1 === count($succursales)) {
            return $this->redirectToRoute('app_succursale_voir', ['id' => $succursales[0]->getId()]);
        }

        return $this->render('succursale/index.html.twig', ['succursales' => $succursales]);
    }

    #[Route('/{id}', name: 'app_succursale_voir', requirements: ['id' => '\d+'])]
    public function voir(Succursale $succursale, AffectationRepository $affectations): Response
    {
        $this->verifierAcces($succursale);

        return $this->render('succursale/voir.html.twig', [
            'succursale' => $succursale,
            'effectifs' => $affectations->effectifs(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_succursale_modifier')]
    public function modifier(Succursale $succursale, Request $request): Response
    {
        $this->verifierAcces($succursale);
        $form = $this->createForm(SuccursaleType::class, $succursale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Succursale mise à jour.');

            return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
        }

        return $this->render('succursale/modifier.html.twig', ['form' => $form, 'succursale' => $succursale]);
    }

    #[Route('/{id}/societes/nouvelle', name: 'app_succursale_societe_nouvelle')]
    public function nouvelleSociete(Succursale $succursale, Request $request, LogoSociete $logos): Response
    {
        $this->verifierAcces($succursale);
        // Rattachée dès maintenant : la validation exige une succursale.
        $societe = (new Societe())->setSuccursale($succursale);
        $form = $this->createForm(SocieteType::class, $societe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logos->traiterFormulaire($form, $societe);
            $this->gestion->creerSociete($succursale, $societe);
            $this->addFlash('success', sprintf('Société « %s » créée avec les rôles RH, Comptable, Trésorier et Consultant. Ajoutez maintenant son équipe.', $societe->getRaisonSociale()));

            return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
        }

        return $this->render('succursale/nouvelle_societe.html.twig', ['form' => $form, 'succursale' => $succursale]);
    }

    /** Ouvre une société de la succursale (pour gérer son équipe, ses salariés...). */
    #[Route('/{id}/societes/{societe}/ouvrir', name: 'app_succursale_societe_ouvrir', methods: ['POST'])]
    public function ouvrirSociete(Succursale $succursale, #[MapEntity(id: 'societe')] Societe $societe, Request $request, SocieteCourante $societeCourante): Response
    {
        $this->verifierAcces($succursale);
        if ($societe->getSuccursale() !== $succursale || !$this->isCsrfTokenValid('ouvrir-'.$societe->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$societe->isActive()) {
            $this->addFlash('danger', 'Cette société est désactivée.');

            return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
        }

        $societeCourante->definir($societe);
        $cible = $request->getPayload()->getString('vers');

        return $this->redirectToRoute(in_array($cible, ['app_equipe', 'app_salarie'], true) ? $cible : 'app_dashboard');
    }

    #[Route('/{id}/administrateurs/ajouter', name: 'app_succursale_admin_ajouter')]
    public function ajouterAdministrateur(Succursale $succursale, Request $request): Response
    {
        $this->verifierAcces($succursale);
        $form = $this->createForm(AdministrateurType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            try {
                $motDePasse = $this->gestion->ajouterAdministrateur($succursale, $d['email'], $d['prenoms'], $d['nom']);
                if ($motDePasse) {
                    $this->addFlash('mot_de_passe', ['email' => mb_strtolower(trim($d['email'])), 'motDePasse' => $motDePasse]);
                }
                $this->addFlash('success', 'Administrateur ajouté à la succursale.');

                return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('succursale/administrateur.html.twig', ['form' => $form, 'succursale' => $succursale]);
    }

    #[Route('/{id}/administrateurs/{user}/retirer', name: 'app_succursale_admin_retirer', methods: ['POST'])]
    public function retirerAdministrateur(Succursale $succursale, #[MapEntity(id: 'user')] User $user, Request $request): Response
    {
        $this->verifierAcces($succursale);
        if (!$this->isCsrfTokenValid('admin-'.$succursale->getId().'-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->gestion->retirerAdministrateur($succursale, $user);
            $this->addFlash('success', sprintf('%s n\'administre plus la succursale.', $user->getNomComplet()));

            // Si l'utilisateur s'est retiré lui-même, il n'a plus accès à cette page.
            if ($user === $this->getUser() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            }
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_succursale_voir', ['id' => $succursale->getId()]);
    }

    private function verifierAcces(Succursale $succursale): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }
        if (!$succursale->isActive() || !$succursale->estAdministreePar($this->utilisateur())) {
            throw $this->createAccessDeniedException('Vous n\'administrez pas cette succursale.');
        }
    }

    private function utilisateur(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
