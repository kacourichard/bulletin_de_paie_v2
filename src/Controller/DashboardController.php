<?php

namespace App\Controller;

use App\Repository\AffectationRepository;
use App\Repository\PaysRepository;
use App\Repository\SocieteRepository;
use App\Repository\SuccursaleRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractSocieteController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(PaysRepository $pays, SocieteRepository $societes, SuccursaleRepository $succursales, UserRepository $users, AffectationRepository $affectations): Response
    {
        $user = $this->utilisateur();
        $societe = $this->societeCourante->get();

        // Super-admin sans société choisie : vue plateforme.
        if (null === $societe && $user->isSuperAdmin()) {
            return $this->render('dashboard/plateforme.html.twig', [
                'nbPays' => $pays->count(['actif' => true]),
                'nbSuccursales' => $succursales->count(['active' => true]),
                'nbSocietes' => $societes->count(['active' => true]),
                'nbUtilisateurs' => $users->count(['actif' => true]),
                'dernieresSocietes' => $societes->findBy([], ['createdAt' => 'DESC'], 5),
            ]);
        }

        // Administrateur de succursale sans société choisie : vue de sa succursale.
        if (null === $societe && $user->isAdministrateurSuccursale()) {
            return $this->redirectToRoute('app_succursales');
        }

        $societe = $this->societe();
        $membresActifs = $societe->getMembres()->filter(static fn ($m) => $m->isActif());

        // Masse salariale fixe du mois : salaires + avantages en vigueur aujourd'hui.
        $enCours = $affectations->enCours($societe);
        $masse = '0';
        foreach ($enCours as $affectation) {
            $remuneration = $affectation->getRemunerationA();
            $masse = bcadd($masse, bcadd($remuneration?->getSalaireTotal() ?? '0', $affectation->getTotalAvantagesA(), 2), 2);
        }

        return $this->render('dashboard/index.html.twig', [
            'societe' => $societe,
            'nbMembres' => $membresActifs->count(),
            'nbRoles' => $societe->getRoles()->count(),
            'nbSalaries' => count($enCours),
            'masseSalariale' => $masse,
        ]);
    }
}
