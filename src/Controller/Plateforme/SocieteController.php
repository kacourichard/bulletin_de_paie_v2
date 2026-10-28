<?php

namespace App\Controller\Plateforme;

use App\Controller\SignaleLegislationTrait;
use App\Entity\Societe;
use App\Form\SocieteType;
use App\Repository\AffectationRepository;
use App\Repository\PaysRepository;
use App\Repository\SocieteRepository;
use App\Repository\SuccursaleRepository;
use App\Security\SocieteCourante;
use App\Service\GestionSalaries;
use App\Service\GestionSuccursales;
use App\Service\LogoSociete;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plateforme/societes')]
class SocieteController extends AbstractController
{
    use SignaleLegislationTrait;

    #[Route('', name: 'app_plateforme_societes')]
    public function index(Request $request, SocieteRepository $societes, PaysRepository $pays, SuccursaleRepository $succursales, AffectationRepository $affectations): Response
    {
        $filtres = array_filter([
            'pays' => $request->query->getInt('pays') ?: null,
            'succursale' => $request->query->getInt('succursale') ?: null,
        ]);

        // Regroupement par succursale (dans l'ordre alphabétique) pour retrouver vite une société.
        $groupes = [];
        foreach ($societes->findBy($filtres, ['raisonSociale' => 'ASC']) as $societe) {
            $groupes[$societe->getSuccursale()->getId()] ??= ['succursale' => $societe->getSuccursale(), 'societes' => []];
            $groupes[$societe->getSuccursale()->getId()]['societes'][] = $societe;
        }
        uasort($groupes, static fn (array $a, array $b) => strcasecmp($a['succursale']->getNom(), $b['succursale']->getNom()));

        return $this->render('plateforme/societe/index.html.twig', [
            'groupes' => $groupes,
            'nbSocietes' => array_sum(array_map(static fn (array $g) => count($g['societes']), $groupes)),
            'listePays' => $pays->findBy([], ['nom' => 'ASC']),
            'listeSuccursales' => $succursales->findBy([], ['nom' => 'ASC']),
            'effectifs' => $affectations->effectifs(),
            'paysFiltre' => $filtres['pays'] ?? null,
            'succursaleFiltre' => $filtres['succursale'] ?? null,
        ]);
    }

    #[Route('/nouvelle', name: 'app_plateforme_societe_nouvelle')]
    public function nouvelle(Request $request, GestionSuccursales $gestion, SuccursaleRepository $succursales, LogoSociete $logos): Response
    {
        $societe = new Societe();
        if ($id = $request->query->getInt('succursale')) {
            $societe->setSuccursale($succursales->find($id));
        }
        $form = $this->createForm(SocieteType::class, $societe, ['succursales' => $succursales->findBy(['active' => true], ['nom' => 'ASC'])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logos->traiterFormulaire($form, $societe);
            $gestion->creerSociete($societe->getSuccursale(), $societe);
            $this->addFlash('success', sprintf('Société « %s » créée dans « %s ».', $societe->getRaisonSociale(), $societe->getSuccursale()->getNom()));

            return $this->redirectToRoute('app_plateforme_societe_voir', ['id' => $societe->getId()]);
        }

        return $this->render('plateforme/societe/nouvelle.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_plateforme_societe_voir', requirements: ['id' => '\d+'])]
    public function voir(Societe $societe): Response
    {
        return $this->render('plateforme/societe/voir.html.twig', ['societe' => $societe]);
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_societe_modifier')]
    public function modifier(Societe $societe, Request $request, EntityManagerInterface $em, SuccursaleRepository $succursales, LogoSociete $logos, GestionSalaries $salaries): Response
    {
        $paysPaie = $societe->getPaysPaie();
        $form = $this->createForm(SocieteType::class, $societe, [
            'avec_statut' => true,
            'succursales' => $succursales->findBy([], ['nom' => 'ASC']),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logos->traiterFormulaire($form, $societe);
            $em->flush();
            $this->addFlash('success', 'Société mise à jour.');
            if ($societe->getPaysPaie() !== $paysPaie) {
                $this->signalerLegislation($societe, $salaries->adapterAuPaysPaie($societe));
            }

            return $this->redirectToRoute('app_plateforme_societe_voir', ['id' => $societe->getId()]);
        }

        return $this->render('plateforme/societe/modifier.html.twig', ['form' => $form, 'societe' => $societe]);
    }

    /** Ouvre l'espace de la société pour l'administrer (support). */
    #[Route('/{id}/ouvrir', name: 'app_plateforme_societe_ouvrir', methods: ['POST'])]
    public function ouvrir(Societe $societe, Request $request, SocieteCourante $societeCourante): Response
    {
        if (!$this->isCsrfTokenValid('ouvrir-'.$societe->getId(), $request->getPayload()->getString('_token')) || !$societe->isActive()) {
            throw $this->createAccessDeniedException();
        }
        $societeCourante->definir($societe);

        return $this->redirectToRoute('app_dashboard');
    }
}
