<?php

namespace App\Controller\Societe;

use App\Controller\AbstractSocieteController;
use App\Entity\Affectation;
use App\Entity\Avantage;
use App\Entity\ElementVariable;
use App\Entity\PeriodePaie;
use App\Entity\Rubrique;
use App\Form\Type\MontantType;
use App\Repository\AffectationRepository;
use App\Repository\RubriqueRepository;
use App\Repository\ZoneTransportRepository;
use App\Security\PermissionCatalogue as P;
use App\Service\GestionPaie;
use App\Service\GestionPaiements;
use App\Service\RapportTravailImport;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Préparation de la paie du mois : éléments fixes repris automatiquement,
 * saisie des éléments variables (ajustements d'avantages, rubriques ponctuelles).
 */
#[Route('/paie')]
class PaieController extends AbstractSocieteController
{
    public function __construct(
        private readonly GestionPaie $paie,
        private readonly AffectationRepository $affectations,
        private readonly RubriqueRepository $rubriques,
        private readonly ZoneTransportRepository $zonesTransport,
    ) {
    }

    #[Route('', name: 'app_paie')]
    #[IsGranted(P::PAIE_VOIR)]
    public function index(Request $request): Response
    {
        $societe = $this->societe();
        $mois = $this->mois($request->query->getString('mois'));
        $periode = $this->paie->trouver($societe, $mois);
        $fin = $mois->modify('last day of this month');

        $lignes = [];
        $totaux = ['gains' => '0', 'retenues' => '0', 'net' => '0', 'patronal' => '0'];
        foreach ($this->affectations->presentesSurPeriode($societe, $mois, $fin) as $affectation) {
            $recap = $periode ? $this->paie->recapitulatif($affectation, $periode) : null;
            $lignes[] = ['affectation' => $affectation, 'recap' => $recap];
            if ($recap) {
                $totaux['gains'] = bcadd($totaux['gains'], $recap['totalGains'], 2);
                $totaux['retenues'] = bcadd($totaux['retenues'], $recap['retenuesTotales'], 2);
                $totaux['patronal'] = bcadd($totaux['patronal'], $recap['chargesPatronales'], 2);
                $totaux['net'] = bcadd($totaux['net'], $recap['net'], 2);
            }
        }

        return $this->render('paie/index.html.twig', [
            'societe' => $societe,
            'mois' => $mois,
            'periode' => $periode,
            'lignes' => $lignes,
            'totaux' => $totaux,
            'periodePrecedente' => $mois->modify('-1 month'),
            'periodeSuivante' => $mois->modify('+1 month'),
        ]);
    }

    #[Route('/{mois}/ouvrir', name: 'app_paie_ouvrir', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function ouvrir(string $mois, Request $request): Response
    {
        $this->verifierCsrf($request, 'periode-'.$mois);
        $periode = $this->paie->ouvrir($this->societe(), $this->mois($mois), $this->utilisateur());
        $this->addFlash('success', sprintf('Paie — %s ouverte : les éléments fixes sont repris, saisissez les éléments variables du mois.', $periode->getLibelle()));

        return $this->redirectToRoute('app_paie', ['mois' => $mois]);
    }

    /**
     * Contrôle avant validation : récapitulatif de tous les salariés et anomalies détectées.
     * L'import Excel est facultatif : on peut valider avec les seuls éléments fixes.
     */
    #[Route('/{mois}/validation', name: 'app_paie_validation', requirements: ['mois' => '\d{4}-\d{2}'])]
    #[IsGranted(P::PAIE_VOIR)]
    public function validation(string $mois): Response
    {
        $periode = $this->periode($mois);
        $lignes = [];
        $alertes = [];
        $totaux = ['brut' => '0', 'brutImposable' => '0', 'gains' => '0', 'retenues' => '0', 'net' => '0', 'patronal' => '0'];
        foreach ($this->affectations->presentesSurPeriode($periode->getSociete(), $periode->getDebut(), $periode->getFin()) as $affectation) {
            $recap = $this->paie->recapitulatif($affectation, $periode);
            $lignes[] = ['affectation' => $affectation, 'recap' => $recap];
            foreach (['brut' => $recap['calcul']['brut'], 'brutImposable' => $recap['calcul']['brutImposable'], 'gains' => $recap['totalGains'], 'retenues' => $recap['retenuesTotales'], 'net' => $recap['net'], 'patronal' => $recap['chargesPatronales']] as $cle => $valeur) {
                $totaux[$cle] = bcadd($totaux[$cle], $valeur, 2);
            }
            $alertes = array_merge($alertes, $this->controles($affectation, $periode, $recap));
        }
        if (!$this->rubriques->count(['pays' => $periode->getSociete()->getPaysPaie()])) {
            $alertes[] = ['niveau' => 'danger', 'texte' => 'Aucune rubrique de paie n\'est définie pour ce pays.', 'affectation' => null];
        }
        if ($lignes && null === $lignes[0]['recap']['parametre']) {
            $alertes[] = ['niveau' => 'warning', 'texte' => sprintf('Les paramètres de paie de %s ne sont pas définis par la plateforme : valeurs par défaut utilisées.', $periode->getSociete()->getPaysPaie()->getNom()), 'affectation' => null];
        }

        return $this->render('paie/validation.html.twig', [
            'periode' => $periode,
            'lignes' => $lignes,
            'totaux' => $totaux,
            'alertes' => $alertes,
            'bloquant' => (bool) array_filter($alertes, static fn ($a) => 'danger' === $a['niveau']),
            'devise' => $periode->getSociete()->getDeviseEffective(),
        ]);
    }

    #[Route('/{mois}/valider', name: 'app_paie_valider', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIE_VALIDER)]
    public function valider(string $mois, Request $request): Response
    {
        $this->verifierCsrf($request, 'periode-'.$mois);
        $periode = $this->periode($mois);
        if (!$periode->isCloturee()) {
            $this->paie->cloturer($periode, $this->utilisateur());
            $this->addFlash('success', sprintf('Paie — %s validée : elle est verrouillée et ne peut plus être modifiée.', $periode->getLibelle()));
        }

        return $this->redirectToRoute('app_paie', ['mois' => $mois]);
    }

    /** Solde de tout compte d'un salarié sorti dans le mois : bulletin définitif sans attendre la validation du mois. */
    #[Route('/{mois}/salaries/{id}/solde', name: 'app_paie_solde_arreter', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_VALIDER)]
    public function arreterSolde(string $mois, Affectation $affectation, Request $request): Response
    {
        $this->verifierCsrf($request, 'solde-'.$affectation->getId());
        $periode = $this->periode($mois);
        $this->verifierAppartenance($affectation->getSociete());
        try {
            $this->paie->arreterSolde($periode, $affectation, $this->utilisateur());
            $this->addFlash('success', sprintf('Solde de tout compte de %s arrêté : son bulletin est définitif et peut être imprimé ou envoyé. Il sera repris avec les autres à la validation du mois.', $affectation->getSalarie()->getNomComplet()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paie_salarie', ['mois' => $mois, 'id' => $affectation->getId()]);
    }

    #[Route('/{mois}/salaries/{id}/solde/annuler', name: 'app_paie_solde_annuler', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_VALIDER)]
    public function annulerSolde(string $mois, Affectation $affectation, Request $request): Response
    {
        $this->verifierCsrf($request, 'solde-'.$affectation->getId());
        $periode = $this->periode($mois);
        $this->verifierAppartenance($affectation->getSociete());
        try {
            $this->paie->annulerSolde($periode, $affectation);
            $this->addFlash('success', sprintf('Arrêté du solde de %s annulé : sa paie peut de nouveau être modifiée.', $affectation->getSalarie()->getNomComplet()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paie_salarie', ['mois' => $mois, 'id' => $affectation->getId()]);
    }

    #[Route('/{mois}/rouvrir', name: 'app_paie_rouvrir', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIE_CLOTURER)]
    public function rouvrir(string $mois, Request $request, GestionPaiements $paiements): Response
    {
        $this->verifierCsrf($request, 'periode-'.$mois);
        $periode = $this->periode($mois);
        if ($nombre = $paiements->nombrePaiements($periode)) {
            $this->addFlash('danger', sprintf('Impossible de rouvrir la paie de %s : %d salaire(s) déjà payé(s). Annulez d\'abord ces paiements (menu Paiements).', $periode->getLibelle(), $nombre));

            return $this->redirectToRoute('app_paie', ['mois' => $mois]);
        }
        $this->paie->rouvrir($periode);
        $this->addFlash('warning', sprintf('Paie — %s rouverte : elle peut de nouveau être modifiée.', $periode->getLibelle()));

        return $this->redirectToRoute('app_paie', ['mois' => $mois]);
    }

    /** Rapport individuel imprimable du mois (à contrôler avant validation). */
    #[Route('/{mois}/salaries/{id}/rapport', name: 'app_paie_rapport', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'])]
    #[IsGranted(P::PAIE_VOIR)]
    public function rapport(string $mois, Affectation $affectation): Response
    {
        $periode = $this->periode($mois);
        $this->verifierAppartenance($affectation->getSociete());

        return $this->render('paie/rapport.html.twig', [
            'periode' => $periode,
            'affectations' => [$affectation],
            'recaps' => [$affectation->getId() => $this->paie->recapitulatif($affectation, $periode)],
            'devise' => $periode->getSociete()->getDeviseEffective(),
        ]);
    }

    /** Rapports de tous les salariés du mois, un par page à l'impression. */
    #[Route('/{mois}/rapports', name: 'app_paie_rapports', requirements: ['mois' => '\d{4}-\d{2}'])]
    #[IsGranted(P::PAIE_VOIR)]
    public function rapports(string $mois): Response
    {
        $periode = $this->periode($mois);
        $affectations = $this->affectations->presentesSurPeriode($periode->getSociete(), $periode->getDebut(), $periode->getFin());
        $recaps = [];
        foreach ($affectations as $affectation) {
            $recaps[$affectation->getId()] = $this->paie->recapitulatif($affectation, $periode);
        }

        return $this->render('paie/rapport.html.twig', [
            'periode' => $periode,
            'affectations' => $affectations,
            'recaps' => $recaps,
            'devise' => $periode->getSociete()->getDeviseEffective(),
        ]);
    }

    /** @return list<array{niveau: string, texte: string, affectation: ?Affectation}> */
    private function controles(Affectation $affectation, PeriodePaie $periode, array $recap): array
    {
        $nom = sprintf('%s (%s)', $affectation->getSalarie()->getNomComplet(), $affectation->getMatricule());
        $alertes = [];
        $alerte = static function (string $niveau, string $texte) use (&$alertes, $affectation, $nom) {
            $alertes[] = ['niveau' => $niveau, 'texte' => $nom.' : '.$texte, 'affectation' => $affectation];
        };

        if (!$affectation->getRemunerationA($periode->getFin())) {
            $alerte('danger', 'aucune rémunération enregistrée.');
        }
        if (0 === bccomp($recap['jours'], '0', 2)) {
            $alerte('warning', '0 jour travaillé : aucun élément fixe ne sera payé.');
        } elseif (!$recap['joursAuto'] && bccomp($recap['jours'], (string) $recap['joursBase'], 2) < 0) {
            $alerte('info', sprintf('%s jours sur %d (prorata).', $recap['jours'] + 0, $recap['joursBase']));
        }
        if ($recap['parametre']?->isPartsActives() && null === $recap['calcul']['parts']) {
            $alerte('warning', 'nombre de parts introuvable (situation familiale non renseignée ou absente de la grille).');
        }
        if ($affectation->getDateEntree() >= $periode->getDebut()) {
            $alerte('info', sprintf('entré(e) le %s.', $affectation->getDateEntree()->format('d/m/Y')));
        }
        if ($affectation->getDateSortie() && $affectation->getDateSortie() <= $periode->getFin()) {
            $alerte('info', sprintf('sorti(e) le %s.', $affectation->getDateSortie()->format('d/m/Y')));
        }
        foreach ($recap['lignes'] as $ligne) {
            if (null === ($ligne['rubrique'] ?? $ligne['avantage']?->getRubrique()) && in_array($ligne['origine'], ['fixe', 'ajuste'], true) && null !== $ligne['avantage']) {
                $alerte('warning', sprintf('l\'avantage « %s » n\'a pas de rubrique.', $ligne['libelle']));
            }
        }
        if (bccomp($recap['net'], '0', 2) < 0) {
            $alerte('danger', 'le net à payer est négatif (retenues supérieures aux gains).');
        }
        if (null === $this->paie->zoneTransport($affectation, $periode) && $this->zonesTransport->duPays($periode->getSociete()->getPaysPaie(), true)) {
            $alerte('warning', 'ville de résidence non renseignée sur la fiche : indemnité de transport non calculée selon le barème.');
        }

        return $alertes;
    }

    #[Route('/{mois}/salaries/{id}', name: 'app_paie_salarie', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'])]
    #[IsGranted(P::PAIE_VOIR)]
    public function salarie(string $mois, Affectation $affectation, Request $request): Response
    {
        $periode = $this->periode($mois);
        $this->verifierAppartenance($affectation->getSociete());
        $devise = $periode->getSociete()->getDeviseEffective();
        $modifiable = !$periode->isCloturee() && $this->isGranted(P::PAIE_PREPARER) && !$this->paie->soldeArrete($periode, $affectation);
        $avantages = $this->paie->avantagesDuMois($affectation, $periode);

        $formPonctuel = $this->formPonctuel($periode, $devise);
        $formAjustement = $this->formAjustement($avantages, $devise);

        if ($modifiable) {
            foreach ([$formPonctuel, $formAjustement] as $form) {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    try {
                        $this->paie->ajouterElement($periode, $affectation, $form->getData(), $this->utilisateur());
                        $this->addFlash('success', 'Élément variable enregistré.');

                        return $this->redirectToRoute('app_paie_salarie', ['mois' => $mois, 'id' => $affectation->getId()]);
                    } catch (\DomainException $e) {
                        $form->addError(new FormError($e->getMessage()));
                    }
                }
            }
        }

        return $this->render('paie/salarie.html.twig', [
            'periode' => $periode,
            'affectation' => $affectation,
            'recap' => $this->paie->recapitulatif($affectation, $periode),
            'formPonctuel' => $formPonctuel,
            'formAjustement' => $formAjustement,
            'avantages' => $avantages,
            'modifiable' => $modifiable,
            'devise' => $devise,
        ]);
    }

    /** Modèle Excel du rapport de travail, pré-rempli avec les salariés du mois. */
    #[Route('/{mois}/import/modele', name: 'app_paie_import_modele', requirements: ['mois' => '\d{4}-\d{2}'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function modeleImport(string $mois, RapportTravailImport $import): Response
    {
        $periode = $this->periode($mois);
        $classeur = $import->modele($periode);
        $nom = sprintf('rapport-travail-%s-%s.xlsx', (new AsciiSlugger())->slug($periode->getSociete()->getRaisonSociale())->lower(), $periode->getCode());

        $reponse = new StreamedResponse(static fn () => (new Xlsx($classeur))->save('php://output'));
        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nom));

        return $reponse;
    }

    /** Envoi du rapport de travail : analyse et aperçu avant enregistrement. */
    #[Route('/{mois}/import', name: 'app_paie_import', requirements: ['mois' => '\d{4}-\d{2}'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function import(string $mois, Request $request, RapportTravailImport $import): Response
    {
        $periode = $this->periode($mois);
        if ($periode->isCloturee()) {
            $this->addFlash('danger', 'La paie de ce mois est clôturée.');

            return $this->redirectToRoute('app_paie', ['mois' => $mois]);
        }

        $form = $this->createFormBuilder()
            ->add('fichier', FileType::class, [
                'label' => 'Rapport de travail',
                'help' => 'Classeur Excel (.xlsx, .xls) ou CSV, 2 Mo maximum.',
                'attr' => ['accept' => '.xlsx,.xls,.csv'],
                'constraints' => [new Assert\NotNull(message: 'Choisissez le fichier.'), new Assert\File(
                    maxSize: '2M',
                    extensions: ['xlsx', 'xls', 'csv'],
                    mimeTypesMessage: 'Ce fichier n\'est pas un classeur Excel ou un CSV valide.',
                    extensionsMessage: 'Formats acceptés : .xlsx, .xls ou .csv.',
                    maxSizeMessage: 'Le fichier ne doit pas dépasser 2 Mo.',
                )],
            ])
            ->getForm();
        $form->handleRequest($request);

        $analyse = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $fichier */
            $fichier = $form->get('fichier')->getData();
            $analyse = $import->analyser($periode, $fichier->getPathname());
            $analyse['fichier'] = $fichier->getClientOriginalName();
            $request->getSession()->set($this->cleImport($periode), $analyse);
        }

        return $this->render('paie/import.html.twig', [
            'periode' => $periode,
            'form' => $form,
            'analyse' => $analyse,
            'devise' => $periode->getSociete()->getDeviseEffective(),
            'rubriques' => $analyse ? $this->rubriquesParCode($periode) : [],
        ]);
    }

    #[Route('/{mois}/import/valider', name: 'app_paie_import_valider', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function validerImport(string $mois, Request $request, RapportTravailImport $import): Response
    {
        $periode = $this->periode($mois);
        $this->verifierCsrf($request, 'import-'.$mois);
        $analyse = $request->getSession()->get($this->cleImport($periode));
        if (!$analyse || [] !== $analyse['erreurs'] || [] === $analyse['lignes']) {
            $this->addFlash('danger', 'Aucun import valide à enregistrer : renvoyez le fichier.');

            return $this->redirectToRoute('app_paie_import', ['mois' => $mois]);
        }

        try {
            $stats = $import->appliquer($periode, $analyse, $this->utilisateur());
            $request->getSession()->remove($this->cleImport($periode));
            $this->addFlash('success', sprintf('Rapport de travail importé : %d salarié(s), %d élément(s) variable(s), %d saisie(s) de jours.', $stats['salaries'], $stats['elements'], $stats['jours']));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paie', ['mois' => $mois]);
    }

    private function cleImport(PeriodePaie $periode): string
    {
        return 'import_rapport_'.$periode->getId();
    }

    /** @return array<string, Rubrique> */
    private function rubriquesParCode(PeriodePaie $periode): array
    {
        $liste = [];
        foreach ($this->rubriques->variablesDisponibles($periode->getSociete()->getPaysPaie(), $periode->getSociete()) as $rubrique) {
            $liste[$rubrique->getCode()] = $rubrique;
        }

        return $liste;
    }

    /** Jours travaillés du mois (vide = calcul automatique). */
    #[Route('/{mois}/salaries/{id}/jours', name: 'app_paie_jours', requirements: ['mois' => '\d{4}-\d{2}', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function jours(string $mois, Affectation $affectation, Request $request): Response
    {
        $periode = $this->periode($mois);
        $this->verifierAppartenance($affectation->getSociete());
        $this->verifierCsrf($request, 'jours-'.$affectation->getId());
        $saisie = trim(str_replace(',', '.', $request->getPayload()->getString('jours')));
        try {
            $this->paie->definirJours($periode, $affectation, '' === $saisie ? null : $saisie);
            $this->addFlash('success', '' === $saisie ? 'Jours travaillés : calcul automatique.' : sprintf('Jours travaillés : %s.', $saisie + 0));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paie_salarie', ['mois' => $mois, 'id' => $affectation->getId()]);
    }

    #[Route('/{mois}/elements/{element}/supprimer', name: 'app_paie_element_supprimer', requirements: ['mois' => '\d{4}-\d{2}'], methods: ['POST'])]
    #[IsGranted(P::PAIE_PREPARER)]
    public function supprimer(string $mois, #[MapEntity(id: 'element')] ElementVariable $element, Request $request): Response
    {
        $this->verifierAppartenance($element->getPeriode()->getSociete());
        $this->verifierCsrf($request, 'element-'.$element->getId());
        $affectation = $element->getAffectation();
        try {
            $this->paie->supprimerElement($element);
            $this->addFlash('success', 'Élément variable supprimé.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_paie_salarie', ['mois' => $mois, 'id' => $affectation->getId()]);
    }

    private function formPonctuel(PeriodePaie $periode, ?string $devise): FormInterface
    {
        return $this->container->get('form.factory')->createNamedBuilder('ponctuel', options: ['data_class' => ElementVariable::class])
            ->setData(new ElementVariable())
            ->add('rubrique', EntityType::class, [
                'class' => Rubrique::class,
                'label' => 'Rubrique',
                'choices' => $this->rubriques->variablesDisponibles($periode->getSociete()->getPaysPaie(), $periode->getSociete()),
                'choice_label' => static fn (Rubrique $r) => sprintf('%s — %s', $r->getCode(), $r->getLibelle()),
                'group_by' => static fn (Rubrique $r) => $r->isGain() ? 'Gains' : 'Retenues',
                'choice_attr' => static fn (Rubrique $r) => ['data-fiscal' => $r->getTraitementFiscalDetaille(), 'data-social' => $r->getTraitementSocial()->libelle()],
                'placeholder' => 'Choisir une rubrique…',
                'constraints' => [new Assert\NotNull(message: 'Choisissez la rubrique.')],
            ])
            ->add('base', NumberType::class, ['label' => 'Base / heures', 'required' => false, 'input' => 'string', 'scale' => 4, 'html5' => true, 'attr' => ['step' => 'any', 'placeholder' => 'ex. 30 000 ou 10 h'], 'help' => 'Montant mensuel ; heures sup. : nombre d\'heures.'])
            ->add('taux', NumberType::class, ['label' => 'Taux / jours', 'required' => false, 'input' => 'string', 'scale' => 4, 'html5' => true, 'attr' => ['step' => 'any', 'placeholder' => 'ex. 30'], 'help' => 'Nombre de jours (30 = mois complet) ; heures sup. : vide = taux horaire × majoration.'])
            ->add('montant', MontantType::class, ['label' => 'Montant', 'devise' => $devise, 'required' => false, 'empty_data' => '0', 'help' => 'Montant saisi = repris tel quel, sans prorata. Vide : base × jours / 30 (heures sup. : heures × taux).'])
            ->add('commentaire', TextType::class, ['label' => 'Commentaire', 'required' => false])
            ->getForm();
    }

    /** @param Avantage[] $avantages */
    private function formAjustement(array $avantages, ?string $devise): FormInterface
    {
        return $this->container->get('form.factory')->createNamedBuilder('ajustement', options: ['data_class' => ElementVariable::class])
            ->setData(new ElementVariable())
            ->add('avantage', EntityType::class, [
                'class' => Avantage::class,
                'label' => 'Avantage fixe',
                'choices' => $avantages,
                'choice_label' => static fn (Avantage $a) => sprintf('%s — %s (%s habituellement)', $a->getRubrique()?->getCode() ?? '—', $a->getLibelle(), number_format((float) $a->getMontant(), 2, ',', ' ')),
                'placeholder' => 'Choisir l\'avantage…',
                'constraints' => [new Assert\NotNull(message: 'Choisissez l\'avantage.')],
            ])
            ->add('montant', MontantType::class, ['label' => 'Montant pour ce mois', 'devise' => $devise, 'help' => '0 pour ne pas le verser ce mois-ci.', 'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()]])
            ->add('commentaire', TextType::class, ['label' => 'Motif', 'required' => false, 'attr' => ['placeholder' => 'Ex. absence non rémunérée, prorata…']])
            ->getForm();
    }

    private function periode(string $mois): PeriodePaie
    {
        return $this->paie->trouver($this->societe(), $this->mois($mois))
            ?? throw $this->createNotFoundException('La paie de ce mois n\'est pas ouverte.');
    }

    private function mois(string $code): \DateTimeImmutable
    {
        $mois = '' !== $code ? \DateTimeImmutable::createFromFormat('!Y-m', $code) : false;

        return ($mois ?: new \DateTimeImmutable('first day of this month'))->modify('first day of this month')->setTime(0, 0);
    }

    private function verifierCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }
}
