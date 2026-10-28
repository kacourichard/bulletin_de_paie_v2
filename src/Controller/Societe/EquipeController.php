<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Membre;
use App\Entity\Role;
use App\Form\NouveauMembreType;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionMembres;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Utilisateurs de la société courante et rôle de chacun.
 */
#[Route('/equipe')]
class EquipeController extends AbstractSocieteController
{
    public function __construct(private readonly GestionMembres $gestion)
    {
    }

    #[Route('', name: 'app_equipe')]
    #[IsGranted(P::UTILISATEUR_VOIR)]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $filtreRole = $request->query->get('role');
        $recherche = mb_strtolower(trim($request->query->getString('q')));

        $membres = array_filter($societe->getMembres()->toArray(), static function (Membre $m) use ($filtreRole, $recherche) {
            if ($filtreRole && $m->getRole()->getCode() !== $filtreRole) {
                return false;
            }
            if ('' !== $recherche) {
                $texte = mb_strtolower($m->getUser()->getNomComplet().' '.$m->getUser()->getEmail());

                return str_contains($texte, $recherche);
            }

            return true;
        });
        usort($membres, static fn (Membre $a, Membre $b) => strcasecmp($a->getUser()->getNom(), $b->getUser()->getNom()));

        return $this->render('equipe/index.html.twig', [
            'societe' => $societe,
            'membres' => $membres,
            'filtreRole' => $filtreRole,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/ajouter', name: 'app_equipe_ajouter')]
    #[IsGranted(P::UTILISATEUR_GERER)]
    public function ajouter(Request $request): Response
    {
        $societe = $this->societe();
        $form = $this->createForm(NouveauMembreType::class, null, ['societe' => $societe]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $resultat = $this->gestion->ajouter($societe, $data['email'], $data['role'], $data['prenoms'], $data['nom'], $data['telephone']);
                $user = $resultat['membre']->getUser();
                $this->addFlash('success', sprintf('%s a été ajouté(e) comme %s.', $user->getNomComplet(), $data['role']->getLibelle()));
                if ($resultat['motDePasse']) {
                    $this->addFlash('mot_de_passe', ['email' => $user->getEmail(), 'motDePasse' => $resultat['motDePasse']]);
                }

                return $this->redirectToRoute('app_equipe');
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('equipe/ajouter.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/role', name: 'app_equipe_role', methods: ['POST'])]
    #[IsGranted(P::UTILISATEUR_GERER)]
    public function changerRole(Membre $membre, Request $request): Response
    {
        $this->verifierAppartenance($membre->getSociete());
        $this->verifierCsrf($request, 'membre-'.$membre->getId());

        $role = $this->societe()->getRoles()->findFirst(static fn ($k, Role $r) => $r->getId() === $request->getPayload()->getInt('role'));
        if (null === $role) {
            throw $this->createNotFoundException();
        }

        return $this->executer(fn () => $this->gestion->changerRole($membre, $role), sprintf('%s est maintenant %s.', $membre->getUser()->getNomComplet(), $role->getLibelle()));
    }

    #[Route('/{id}/statut', name: 'app_equipe_statut', methods: ['POST'])]
    #[IsGranted(P::UTILISATEUR_GERER)]
    public function basculerStatut(Membre $membre, Request $request): Response
    {
        $this->verifierAppartenance($membre->getSociete());
        $this->verifierCsrf($request, 'membre-'.$membre->getId());
        $message = $membre->isActif() ? 'Accès suspendu pour %s.' : 'Accès réactivé pour %s.';

        return $this->executer(fn () => $this->gestion->basculerStatut($membre, $this->utilisateur()), sprintf($message, $membre->getUser()->getNomComplet()));
    }

    #[Route('/{id}/retirer', name: 'app_equipe_retirer', methods: ['POST'])]
    #[IsGranted(P::UTILISATEUR_GERER)]
    public function retirer(Membre $membre, Request $request): Response
    {
        $this->verifierAppartenance($membre->getSociete());
        $this->verifierCsrf($request, 'membre-'.$membre->getId());
        $nom = $membre->getUser()->getNomComplet();

        return $this->executer(fn () => $this->gestion->retirer($membre, $this->utilisateur()), sprintf('%s a été retiré(e) de la société.', $nom));
    }

    #[Route('/{id}/reinitialiser', name: 'app_equipe_reinitialiser', methods: ['POST'])]
    #[IsGranted(P::UTILISATEUR_GERER)]
    public function reinitialiser(Membre $membre, Request $request): Response
    {
        $this->verifierAppartenance($membre->getSociete());
        $this->verifierCsrf($request, 'membre-'.$membre->getId());
        $user = $membre->getUser();

        // Un compte partagé entre plusieurs sociétés ou super-admin ne peut être réinitialisé que par la plateforme.
        if ($user->isSuperAdmin() || $user->getMembres()->count() > 1) {
            $this->addFlash('danger', 'Ce compte est utilisé dans plusieurs sociétés : seul le support de la plateforme peut réinitialiser son mot de passe.');

            return $this->redirectToRoute('app_equipe');
        }

        $motDePasse = $this->gestion->reinitialiserMotDePasse($user);
        $this->addFlash('mot_de_passe', ['email' => $user->getEmail(), 'motDePasse' => $motDePasse]);

        return $this->redirectToRoute('app_equipe');
    }

    private function verifierCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }

    private function executer(callable $action, string $succes): Response
    {
        try {
            $action();
            $this->addFlash('success', $succes);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_equipe');
    }
}
