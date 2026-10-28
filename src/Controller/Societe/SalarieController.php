<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Affectation;
use App\Entity\Avantage;
use App\Entity\Remuneration;
use App\Entity\Salarie;
use App\Enum\MotifSortie;
use App\Enum\TypeContrat;
use App\Form\AffectationType;
use App\Form\AvantageType;
use App\Form\NouveauSalarieType;
use App\Form\RemunerationType;
use App\Form\SalarieType;
use App\Form\SortieType;
use App\Form\TransfertType;
use App\Form\Type\MontantType;
use App\Repository\AffectationRepository;
use App\Repository\RubriqueRepository;
use App\Repository\ZoneTransportRepository;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionSalaries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Personnel de la société courante. L'identifiant dans l'URL est celui de
 * l'affectation (le passage du salarié dans cette société).
 */
#[Route('/salaries')]
class SalarieController extends AbstractSocieteController
{
    public function __construct(
        private readonly GestionSalaries $gestion,
        private readonly AffectationRepository $affectations,
        private readonly RubriqueRepository $rubriques,
        private readonly EntityManagerInterface $em,
        private readonly ZoneTransportRepository $zonesTransport,
    ) {
    }

    #[Route('', name: 'app_salarie')]
    #[IsGranted(P::SALARIE_VOIR)]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $statut = $request->query->getString('statut', AffectationRepository::STATUT_ACTIFS);
        $contrat = TypeContrat::tryFrom($request->query->getString('contrat'));
        // Recherche, tri et pagination à l'écran (DataTables) ; ?q= pré-remplit la recherche.
        $resultats = $this->affectations->rechercher($societe, $statut, '', $contrat);

        return $this->render('salarie/index.html.twig', [
            'societe' => $societe,
            'affectations' => $resultats,
            'statut' => $statut,
            'recherche' => trim($request->query->getString('q')),
            'contrat' => $contrat,
            'compteurs' => $this->affectations->compter($societe),
            'typesContrat' => TypeContrat::cases(),
        ]);
    }

    #[Route('/nouveau', name: 'app_salarie_nouveau')]
    #[IsGranted(P::SALARIE_CREER)]
    public function nouveau(Request $request): Response
    {
        $societe = $this->societe();
        $data = [
            'salarie' => (new Salarie())->setNationalite($societe->getPays()->getCodeIso()),
            'affectation' => (new Affectation())->setDateEntree(new \DateTimeImmutable('today')),
            'avantages' => [],
        ];
        $form = $this->createForm(NouveauSalarieType::class, $data, [
            'devise' => $societe->getDeviseEffective(),
            'pays_prefere' => $societe->getPays()->getCodeIso(),
            'organisme_social' => $societe->getPays()->getOrganismeSocial(),
            'matricule_propose' => $this->affectations->prochainMatricule($societe),
            'rubriques' => $this->rubriques->avantagesDisponibles($societe->getPaysPaie(), $societe),
            'zones_transport' => $this->zonesTransport->duPays($societe->getPaysPaie(), true),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $affectation = $this->gestion->embaucher(
                    $societe,
                    $data['salarie'],
                    $data['affectation'],
                    $data['salaireBase'],
                    $data['sursalaire'] ?? '0',
                    $data['avantages'] ?? [],
                    $this->utilisateur(),
                );
                $this->addFlash('success', sprintf('%s a été enregistré(e) avec le matricule %s.', $data['salarie']->getNomComplet(), $affectation->getMatricule()));

                return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId()]);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('salarie/nouveau.html.twig', ['form' => $form, 'societe' => $societe]);
    }

    #[Route('/{id}', name: 'app_salarie_voir', requirements: ['id' => '\d+'])]
    #[IsGranted(P::SALARIE_VOIR)]
    public function voir(Affectation $affectation): Response
    {
        $this->verifierAppartenance($affectation->getSociete());

        return $this->render('salarie/voir.html.twig', [
            'affectation' => $affectation,
            'salarie' => $affectation->getSalarie(),
            'societe' => $affectation->getSociete(),
            'aujourdhui' => new \DateTimeImmutable('today'),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_salarie_modifier')]
    #[IsGranted(P::SALARIE_MODIFIER)]
    public function modifier(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        $societe = $affectation->getSociete();

        $form = $this->createFormBuilder(['salarie' => $affectation->getSalarie(), 'affectation' => $affectation])
            ->add('salarie', SalarieType::class, ['label' => false, 'pays_prefere' => $societe->getPays()->getCodeIso(), 'organisme_social' => $societe->getPays()->getOrganismeSocial(),
                'zones_transport' => $this->zonesTransport->duPays($societe->getPaysPaie(), true)])
            ->add('affectation', AffectationType::class, ['label' => false, 'edition' => true])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Dossier du salarié mis à jour.');

            return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId()]);
        }

        return $this->render('salarie/modifier.html.twig', ['form' => $form, 'affectation' => $affectation]);
    }

    #[Route('/{id}/revaloriser', name: 'app_salarie_revaloriser')]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function revaloriser(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        $actuelle = $affectation->getDerniereRemuneration();
        $remuneration = (new Remuneration())
            ->setDateEffet(new \DateTimeImmutable('first day of next month'))
            ->setSalaireBase($actuelle?->getSalaireBase())
            ->setSursalaire($actuelle?->getSursalaire());

        $form = $this->createForm(RemunerationType::class, $remuneration, ['devise' => $affectation->getSociete()->getDeviseEffective()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->executer($form, fn () => $this->gestion->revaloriser($affectation, $remuneration, $this->utilisateur()), $affectation, 'Nouvelle rémunération enregistrée.', 'remuneration')
                ?? $this->render('salarie/revaloriser.html.twig', ['form' => $form, 'affectation' => $affectation, 'actuelle' => $actuelle]);
        }

        return $this->render('salarie/revaloriser.html.twig', ['form' => $form, 'affectation' => $affectation, 'actuelle' => $actuelle]);
    }

    #[Route('/{id}/remunerations/{remuneration}/corriger', name: 'app_salarie_remuneration_corriger')]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function corriger(Affectation $affectation, #[MapEntity(id: 'remuneration')] Remuneration $remuneration, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        if ($remuneration->getAffectation() !== $affectation) {
            throw $this->createNotFoundException();
        }

        $devise = $affectation->getSociete()->getDeviseEffective();
        $form = $this->createFormBuilder(['salaireBase' => $remuneration->getSalaireBase(), 'sursalaire' => $remuneration->getSursalaire()])
            ->add('salaireBase', MontantType::class, ['label' => 'Salaire de base', 'devise' => $devise, 'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()]])
            ->add('sursalaire', MontantType::class, ['label' => 'Sursalaire', 'devise' => $devise, 'required' => false, 'empty_data' => '0'])
            ->add('commentaire', TextType::class, ['label' => 'Raison de la correction', 'constraints' => [new Assert\NotBlank(message: 'Expliquez la correction.')]])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();

            return $this->executer($form, fn () => $this->gestion->corriger($remuneration, $d['salaireBase'], $d['sursalaire'] ?? '0', $d['commentaire'], $this->utilisateur()), $affectation, 'Rémunération corrigée.', 'remuneration')
                ?? $this->render('salarie/corriger.html.twig', ['form' => $form, 'affectation' => $affectation, 'remuneration' => $remuneration]);
        }

        return $this->render('salarie/corriger.html.twig', ['form' => $form, 'affectation' => $affectation, 'remuneration' => $remuneration]);
    }

    #[Route('/{id}/avantages/ajouter', name: 'app_salarie_avantage_ajouter')]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function ajouterAvantage(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        $avantage = (new Avantage())->setDateDebut(new \DateTimeImmutable('first day of next month'));
        $form = $this->createForm(AvantageType::class, $avantage, [
            'devise' => $affectation->getSociete()->getDeviseEffective(),
            'rubriques' => $this->rubriques->avantagesDisponibles($affectation->getSociete()->getPaysPaie(), $affectation->getSociete()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->executer($form, fn () => $this->gestion->ajouterAvantage($affectation, $avantage, $this->utilisateur()), $affectation, sprintf('Avantage « %s » ajouté.', $avantage->getLibelle()), 'avantages')
                ?? $this->render('salarie/avantage.html.twig', ['form' => $form, 'affectation' => $affectation]);
        }

        return $this->render('salarie/avantage.html.twig', ['form' => $form, 'affectation' => $affectation]);
    }

    #[Route('/{id}/avantages/{avantage}/arreter', name: 'app_salarie_avantage_arreter', methods: ['POST'])]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function arreterAvantage(Affectation $affectation, #[MapEntity(id: 'avantage')] Avantage $avantage, Request $request): Response
    {
        $this->verifierAvantage($affectation, $avantage, $request);
        try {
            $date = new \DateTimeImmutable($request->getPayload()->getString('dateFin') ?: 'today');
            $this->gestion->arreterAvantage($avantage, $date);
            $this->addFlash('success', sprintf('Avantage « %s » arrêté au %s.', $avantage->getLibelle(), $date->format('d/m/Y')));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('danger', 'Date invalide.');
        }

        return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => 'avantages']);
    }

    /** Nouveau montant à partir d'une date : l'ancienne ligne est close, l'historique conservé. */
    #[Route('/{id}/avantages/{avantage}/reviser', name: 'app_salarie_avantage_reviser', methods: ['POST'])]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function reviserAvantage(Affectation $affectation, #[MapEntity(id: 'avantage')] Avantage $avantage, Request $request): Response
    {
        $this->verifierAvantage($affectation, $avantage, $request);
        $montant = str_replace([' ', ','], ['', '.'], $request->getPayload()->getString('montant'));
        try {
            if (!is_numeric($montant)) {
                throw new \DomainException('Montant invalide.');
            }
            $date = new \DateTimeImmutable($request->getPayload()->getString('dateEffet') ?: 'first day of next month');
            $ancien = $avantage->getMontant();
            $this->gestion->reviserAvantage($avantage, number_format((float) $montant, 2, '.', ''), $date, $this->utilisateur());
            $this->addFlash('success', sprintf('« %s » passe de %s à %s à compter du %s.', $avantage->getLibelle(), $ancien + 0, $montant + 0, $date->format('d/m/Y')));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('danger', 'Date invalide.');
        }

        return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => 'avantages']);
    }

    /**
     * Changement de poste : nouvelle fonction, rémunération et avantages revus à une même date.
     */
    #[Route('/{id}/changer-poste', name: 'app_salarie_changer_poste')]
    #[IsGranted(P::SALARIE_MODIFIER)]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function changerPoste(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        $societe = $affectation->getSociete();
        $devise = $societe->getDeviseEffective();
        $dateDefaut = new \DateTimeImmutable('first day of next month');
        // Lignes d'avantages ouvertes (sans date de fin) : ce sont elles qui se poursuivent dans le nouveau poste.
        $actuels = array_values($affectation->getAvantages()->filter(static fn (Avantage $a) => null === $a->getDateFin())->toArray());
        $remuneration = $affectation->getDerniereRemuneration();

        $donnees = [
            'date' => $dateDefaut,
            'poste' => $affectation->getPoste(),
            'metier' => $affectation->getMetier(),
            'service' => $affectation->getService(),
            'categorie' => $affectation->getCategorie(),
            'echelon' => $affectation->getEchelon(),
            'salaireBase' => $remuneration?->getSalaireBase(),
            'sursalaire' => $remuneration?->getSursalaire(),
            'nouveaux' => [],
        ];
        $builder = $this->createFormBuilder($donnees)
            ->add('date', DateType::class, ['label' => 'Date d\'effet', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'constraints' => [new Assert\NotNull()]])
            ->add('poste', TextType::class, ['label' => 'Nouvelle fonction', 'constraints' => [new Assert\NotBlank()]])
            ->add('metier', TextType::class, ['label' => 'Emploi / métier', 'required' => false])
            ->add('service', TextType::class, ['label' => 'Service', 'required' => false])
            ->add('categorie', TextType::class, ['label' => 'Catégorie', 'required' => false])
            ->add('echelon', TextType::class, ['label' => 'Échelon', 'required' => false])
            ->add('salaireBase', MontantType::class, ['label' => 'Salaire catégoriel', 'devise' => $devise, 'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()]])
            ->add('sursalaire', MontantType::class, ['label' => 'Sursalaire', 'devise' => $devise, 'required' => false, 'empty_data' => '0'])
            ->add('commentaire', TextType::class, ['label' => 'Motif / commentaire', 'required' => false, 'attr' => ['placeholder' => 'Ex. Promotion suite à l\'entretien annuel']])
            ->add('nouveaux', CollectionType::class, [
                'label' => false,
                'entry_type' => AvantageType::class,
                'entry_options' => ['label' => false, 'devise' => $devise, 'avec_date' => false, 'rubriques' => $this->rubriques->avantagesDisponibles($societe->getPaysPaie(), $societe)],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
        foreach ($actuels as $avantage) {
            $builder->add('avantage_'.$avantage->getId(), MontantType::class, [
                'label' => $avantage->getLibelle(),
                'devise' => $devise,
                'data' => $avantage->getMontant(),
                'required' => false,
                'help' => '0 pour arrêter cet avantage.',
            ]);
        }
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            $montants = [];
            foreach ($actuels as $avantage) {
                // Champ vidé = inchangé ; seul un 0 explicite arrête l'avantage.
                $montant = $form->get('avantage_'.$avantage->getId())->getData();
                if (null !== $montant && 0 !== bccomp($montant, $avantage->getMontant(), 2)) {
                    $montants[$avantage->getId()] = $montant;
                }
            }
            try {
                $this->gestion->changerPoste(
                    $affectation,
                    $d['date'],
                    array_intersect_key($d, array_flip(['poste', 'metier', 'service', 'categorie', 'echelon'])),
                    $d['salaireBase'],
                    $d['sursalaire'] ?? '0',
                    $montants,
                    $d['nouveaux'] ?? [],
                    $d['commentaire'],
                    $this->utilisateur(),
                );
                $this->addFlash('success', sprintf('%s occupe désormais la fonction « %s » à compter du %s.', $affectation->getSalarie()->getNomComplet(), $d['poste'], $d['date']->format('d/m/Y')));

                return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => 'avantages']);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('salarie/changer_poste.html.twig', [
            'form' => $form,
            'affectation' => $affectation,
            'actuels' => $actuels,
            'remuneration' => $remuneration,
        ]);
    }

    #[Route('/{id}/avantages/{avantage}/supprimer', name: 'app_salarie_avantage_supprimer', methods: ['POST'])]
    #[IsGranted(P::SALARIE_REMUNERATION)]
    public function supprimerAvantage(Affectation $affectation, #[MapEntity(id: 'avantage')] Avantage $avantage, Request $request): Response
    {
        $this->verifierAvantage($affectation, $avantage, $request);
        $this->gestion->supprimerAvantage($avantage);
        $this->addFlash('success', sprintf('Avantage « %s » supprimé.', $avantage->getLibelle()));

        return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => 'avantages']);
    }

    #[Route('/{id}/sortie', name: 'app_salarie_sortie')]
    #[IsGranted(P::SALARIE_SUPPRIMER)]
    public function sortie(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);
        $form = $this->createForm(SortieType::class, ['date' => new \DateTimeImmutable('today')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();

            $suite = MotifSortie::TRANSFERT === $d['motif'] ? '' : sprintf(' Établissez son solde de tout compte : Paie du mois › %s › son nom › « Arrêter le solde de tout compte ».', $d['date']->format('m/Y'));

            return $this->executer($form, fn () => $this->gestion->sortir($affectation, $d['date'], $d['motif'], $d['commentaire']), $affectation, sprintf('Sortie de %s enregistrée.%s', $affectation->getSalarie()->getNomComplet(), $suite))
                ?? $this->render('salarie/sortie.html.twig', ['form' => $form, 'affectation' => $affectation]);
        }

        return $this->render('salarie/sortie.html.twig', ['form' => $form, 'affectation' => $affectation]);
    }

    #[Route('/{id}/transferer', name: 'app_salarie_transferer')]
    #[IsGranted(P::SALARIE_TRANSFERER)]
    public function transferer(Affectation $affectation, Request $request): Response
    {
        $this->verifierModifiable($affectation);

        // Destinations : autres sociétés de la même succursale où l'utilisateur peut enregistrer des salariés.
        $succursale = $affectation->getSociete()->getSuccursale();
        $destinations = array_values(array_filter(
            $this->societeCourante->disponibles(),
            fn ($s) => $s !== $affectation->getSociete() && $s->getSuccursale() === $succursale && $this->isGranted(P::SALARIE_CREER, $s)
        ));

        $actuelle = $affectation->getRemunerationA();
        $form = $this->createForm(TransfertType::class, [
            'date' => new \DateTimeImmutable('first day of next month'),
            'poste' => $affectation->getPoste(),
            'typeContrat' => $affectation->getTypeContrat(),
            'salaireBase' => $actuelle?->getSalaireBase(),
            'sursalaire' => $actuelle?->getSursalaire(),
            'reprendreAvantages' => true,
        ], ['societes' => $destinations]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            try {
                $nouvelle = $this->gestion->transferer($affectation, $d['societe'], $d['date'], $d['matricule'], $d['poste'], $d['typeContrat'], $d['salaireBase'], $d['sursalaire'] ?? '0', (bool) $d['reprendreAvantages'], $this->utilisateur());
                $this->addFlash('success', sprintf('%s est transféré(e) vers %s (matricule %s) à compter du %s.', $affectation->getSalarie()->getNomComplet(), $d['societe']->getRaisonSociale(), $nouvelle->getMatricule(), $d['date']->format('d/m/Y')));
                if ($nonRepris = $this->gestion->getDerniersAvantagesNonRepris()) {
                    $this->addFlash('warning', sprintf('Avantages non repris (aucune rubrique de même code dans le pays de destination) : %s. Ajoutez-les depuis la nouvelle fiche si nécessaire.', implode(', ', $nonRepris)));
                }

                return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => 'parcours']);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('salarie/transferer.html.twig', ['form' => $form, 'affectation' => $affectation, 'destinations' => $destinations]);
    }

    /** Exécute une règle métier ; en cas de refus, l'erreur est attachée au formulaire et null est renvoyé. */
    private function executer(FormInterface $form, callable $action, Affectation $affectation, string $succes, ?string $onglet = null): ?Response
    {
        try {
            $action();
        } catch (\DomainException $e) {
            $form->addError(new FormError($e->getMessage()));

            return null;
        }
        $this->addFlash('success', $succes);

        return $this->redirectToRoute('app_salarie_voir', ['id' => $affectation->getId(), '_fragment' => $onglet]);
    }

    private function verifierModifiable(Affectation $affectation): void
    {
        $this->verifierAppartenance($affectation->getSociete());
        if (!$affectation->isEnCours()) {
            throw $this->createAccessDeniedException('Ce salarié a quitté la société : son dossier est en lecture seule.');
        }
    }

    private function verifierAvantage(Affectation $affectation, Avantage $avantage, Request $request): void
    {
        $this->verifierModifiable($affectation);
        if ($avantage->getAffectation() !== $affectation) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('avantage-'.$avantage->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
