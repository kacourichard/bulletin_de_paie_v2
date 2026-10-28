<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Avantage;
use App\Entity\HistoriquePoste;
use App\Entity\Remuneration;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Entity\User;
use App\Enum\MotifRemuneration;
use App\Enum\MotifSortie;
use App\Enum\TypeContrat;
use App\Repository\AffectationRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Règles de gestion du personnel : embauche, évolution du salaire,
 * avantages, sortie et transfert entre sociétés.
 * Les historiques ne sont jamais effacés : on ajoute ou on clôture des lignes.
 */
class GestionSalaries
{
    /** Libellés des avantages non repris lors du dernier transfert (pas de rubrique équivalente). */
    private array $derniersAvantagesNonRepris = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AffectationRepository $affectations,
        private readonly RubriqueRepository $rubriques,
    ) {
    }

    /**
     * Après un changement de législation de paie : rattache les avantages des salariés de la société
     * à la rubrique de même code du nouveau pays (comme lors d'un transfert).
     *
     * @return list<string> avantages sans rubrique équivalente, laissés sur l'ancienne
     */
    public function adapterAuPaysPaie(Societe $societe): array
    {
        $pays = $societe->getPaysPaie();
        $avantages = $this->em->createQueryBuilder()
            ->select('av', 'a', 's', 'r')->from(Avantage::class, 'av')
            ->join('av.affectation', 'a')->join('a.salarie', 's')->join('av.rubrique', 'r')
            ->where('a.societe = :societe')->andWhere('r.pays != :pays')
            ->setParameter('societe', $societe)->setParameter('pays', $pays)
            ->getQuery()->getResult();

        $nonRepris = [];
        foreach ($avantages as $avantage) {
            $ancienne = $avantage->getRubrique();
            $rubrique = $this->rubriques->findOneBy(['pays' => $pays, 'code' => $ancienne->getCode(), 'actif' => true]);
            if ($rubrique) {
                $avantage->setRubrique($rubrique);
            } else {
                $nonRepris[] = sprintf('%s — %s (%s)', $avantage->getAffectation()->getSalarie()->getNomComplet(), $avantage->getLibelle(), $ancienne->getCode());
            }
        }
        $this->em->flush();

        return array_values(array_unique($nonRepris));
    }

    /** @return string[] */
    public function getDerniersAvantagesNonRepris(): array
    {
        return $this->derniersAvantagesNonRepris;
    }

    /**
     * Enregistre un nouveau salarié dans la société avec sa rémunération et ses avantages initiaux.
     *
     * @param Avantage[] $avantages
     *
     * @throws \DomainException
     */
    public function embaucher(Societe $societe, Salarie $salarie, Affectation $affectation, string $salaireBase, string $sursalaire, array $avantages, User $auteur): Affectation
    {
        if (null !== $salarie->getId() && null !== $salarie->getAffectationEnCours()) {
            throw new \DomainException('Ce salarié est déjà en poste dans une société.');
        }

        $affectation->setSociete($societe)->setCreePar($auteur);
        if (!$affectation->getMatricule()) {
            $affectation->setMatricule($this->affectations->prochainMatricule($societe));
        } elseif (!$this->affectations->matriculeDisponible($societe, $affectation->getMatricule())) {
            throw new \DomainException(sprintf('Le matricule %s est déjà utilisé dans la société.', $affectation->getMatricule()));
        }
        foreach ($avantages as $avantage) {
            $avantage->setDateDebut($avantage->getDateDebut() ?? $affectation->getDateEntree());
            $this->verifierAvantage($affectation, $avantage);
        }

        $salarie->addAffectation($affectation);
        $affectation->addHistoriquePoste(HistoriquePoste::depuis($affectation, $affectation->getDateEntree(), 'Embauche', $auteur));
        $affectation->addRemuneration((new Remuneration())
            ->setDateEffet($affectation->getDateEntree())
            ->setSalaireBase($salaireBase)
            ->setSursalaire($sursalaire)
            ->setMotif(MotifRemuneration::EMBAUCHE)
            ->setCreePar($auteur));
        foreach ($avantages as $avantage) {
            $affectation->addAvantage($avantage->setCreePar($auteur));
        }

        $this->em->persist($salarie);
        $this->em->flush();

        return $affectation;
    }

    /**
     * Nouvelle ligne dans l'historique des salaires (revalorisation, promotion...).
     *
     * @throws \DomainException
     */
    public function revaloriser(Affectation $affectation, Remuneration $nouvelle, User $auteur): void
    {
        $this->verifierEnCours($affectation);
        $derniere = $affectation->getDerniereRemuneration();

        if ($nouvelle->getDateEffet() < $affectation->getDateEntree()) {
            throw new \DomainException('La date d\'effet ne peut pas précéder l\'entrée dans la société.');
        }
        if ($derniere && $nouvelle->getDateEffet() <= $derniere->getDateEffet()) {
            throw new \DomainException(sprintf(
                'La date d\'effet doit être postérieure au %s (dernière ligne de l\'historique). Pour rectifier une erreur de saisie, utilisez « Corriger ».',
                $derniere->getDateEffet()->format('d/m/Y')
            ));
        }
        if (MotifRemuneration::EMBAUCHE === $nouvelle->getMotif() || MotifRemuneration::TRANSFERT === $nouvelle->getMotif()) {
            $nouvelle->setMotif(MotifRemuneration::REVALORISATION);
        }

        $affectation->addRemuneration($nouvelle->setCreePar($auteur));
        $this->em->persist($nouvelle);
        $this->em->flush();
    }

    /**
     * Rectifie une erreur de saisie sur la dernière ligne de l'historique (trace conservée).
     *
     * @throws \DomainException
     */
    public function corriger(Remuneration $remuneration, string $salaireBase, string $sursalaire, ?string $commentaire, User $auteur): void
    {
        $this->verifierEnCours($remuneration->getAffectation());
        if ($remuneration->getAffectation()->getDerniereRemuneration() !== $remuneration) {
            throw new \DomainException('Seule la dernière ligne de l\'historique peut être corrigée.');
        }

        $remuneration->setSalaireBase($salaireBase)->setSursalaire($sursalaire)->marquerCorrigee($auteur);
        if ($commentaire) {
            $remuneration->setCommentaire($commentaire);
        }
        $this->em->flush();
    }

    /** @throws \DomainException */
    public function ajouterAvantage(Affectation $affectation, Avantage $avantage, User $auteur): void
    {
        $this->verifierEnCours($affectation);
        $avantage->setDateDebut($avantage->getDateDebut() ?? new \DateTimeImmutable('today'));
        $this->verifierAvantage($affectation, $avantage);

        $affectation->addAvantage($avantage->setCreePar($auteur));
        $this->em->persist($avantage);
        $this->em->flush();
    }

    /**
     * Change le montant d'un avantage à partir d'une date : l'ancienne ligne s'arrête la veille,
     * une nouvelle commence avec le nouveau montant (l'historique est conservé).
     *
     * @throws \DomainException
     */
    public function reviserAvantage(Avantage $avantage, string $montant, \DateTimeImmutable $date, User $auteur, bool $flush = true): Avantage
    {
        $this->verifierEnCours($avantage->getAffectation());
        if (bccomp($montant, '0', 2) <= 0) {
            throw new \DomainException('Le nouveau montant doit être supérieur à zéro. Pour supprimer l\'avantage, utilisez « Arrêter ».');
        }
        if (0 === bccomp($montant, $avantage->getMontant(), 2)) {
            throw new \DomainException(sprintf('Le montant de « %s » est déjà de %s.', $avantage->getLibelle(), $montant));
        }
        if ($date <= $avantage->getDateDebut()) {
            throw new \DomainException(sprintf('La date d\'effet doit être postérieure au %s (début de « %s »).', $avantage->getDateDebut()->format('d/m/Y'), $avantage->getLibelle()));
        }
        if (null !== $avantage->getDateFin() && $avantage->getDateFin() < $date) {
            throw new \DomainException(sprintf('« %s » est arrêté au %s.', $avantage->getLibelle(), $avantage->getDateFin()->format('d/m/Y')));
        }

        $nouveau = $avantage->dupliquer($date)->setMontant($montant)->setCreePar($auteur);
        $nouveau->setDateFin($avantage->getDateFin());
        $avantage->setDateFin($date->modify('-1 day'));
        $avantage->getAffectation()->addAvantage($nouveau);
        $this->em->persist($nouveau);
        if ($flush) {
            $this->em->flush();
        }

        return $nouveau;
    }

    /**
     * Changement de poste : nouveau poste, et le cas échéant nouvelle rémunération,
     * avantages revus (montant modifié, arrêtés si 0) et nouveaux avantages, le tout à la même date.
     *
     * @param array<int, string> $montantsAvantages [id avantage en vigueur => nouveau montant ('0' = arrêter)]
     * @param Avantage[]         $nouveauxAvantages
     *
     * @throws \DomainException
     */
    public function changerPoste(
        Affectation $affectation,
        \DateTimeImmutable $date,
        array $poste,
        ?string $salaireBase,
        ?string $sursalaire,
        array $montantsAvantages,
        array $nouveauxAvantages,
        ?string $commentaire,
        User $auteur,
    ): void {
        $this->verifierEnCours($affectation);
        $dernierPoste = $affectation->getHistoriquePostes()[0] ?? null;
        if ($date <= $affectation->getDateEntree() || ($dernierPoste && $date <= $dernierPoste->getDateEffet())) {
            throw new \DomainException(sprintf('La date d\'effet doit être postérieure au %s.', ($dernierPoste?->getDateEffet() ?? $affectation->getDateEntree())->format('d/m/Y')));
        }

        // Vérifications avant toute modification.
        $remuneration = $affectation->getDerniereRemuneration();
        $nouvelleRemuneration = null;
        if (null !== $salaireBase && (0 !== bccomp($salaireBase, $remuneration?->getSalaireBase() ?? '0', 2) || 0 !== bccomp($sursalaire ?? '0', $remuneration?->getSursalaire() ?? '0', 2))) {
            if ($remuneration && $date <= $remuneration->getDateEffet()) {
                throw new \DomainException(sprintf('Une rémunération est déjà prévue au %s : choisissez une date d\'effet postérieure.', $remuneration->getDateEffet()->format('d/m/Y')));
            }
            $nouvelleRemuneration = (new Remuneration())->setDateEffet($date)->setSalaireBase($salaireBase)->setSursalaire($sursalaire ?? '0')
                ->setMotif(MotifRemuneration::CHANGEMENT_POSTE)->setCommentaire($poste['poste'] ?? null)->setCreePar($auteur);
        }

        $enVigueur = [];
        foreach ($affectation->getAvantagesA($date) as $avantage) {
            $enVigueur[$avantage->getId()] = $avantage;
        }
        $revisions = [];
        foreach ($montantsAvantages as $id => $montant) {
            $avantage = $enVigueur[$id] ?? throw new \DomainException('Un avantage modifié n\'est pas en vigueur à la date du changement de poste (il commence plus tard ou est déjà arrêté).');
            $montant = '' === (string) $montant ? '0' : (string) $montant;
            if (0 !== bccomp($montant, $avantage->getMontant(), 2)) {
                if ($date <= $avantage->getDateDebut()) {
                    throw new \DomainException(sprintf('« %s » commence le %s : la date d\'effet doit être postérieure.', $avantage->getLibelle(), $avantage->getDateDebut()->format('d/m/Y')));
                }
                $revisions[] = [$avantage, $montant];
            }
        }
        foreach ($nouveauxAvantages as $avantage) {
            $avantage->setDateDebut($date);
            $this->verifierAvantage($affectation, $avantage);
        }

        $this->em->wrapInTransaction(function () use ($affectation, $date, $poste, $nouvelleRemuneration, $revisions, $nouveauxAvantages, $commentaire, $auteur) {
            $affectation
                ->setPoste($poste['poste'] ?? $affectation->getPoste())
                ->setMetier($poste['metier'] ?? $affectation->getMetier())
                ->setService($poste['service'] ?? $affectation->getService())
                ->setCategorie($poste['categorie'] ?? $affectation->getCategorie())
                ->setEchelon($poste['echelon'] ?? $affectation->getEchelon());
            $affectation->addHistoriquePoste(HistoriquePoste::depuis($affectation, $date, $commentaire, $auteur));

            if ($nouvelleRemuneration) {
                $affectation->addRemuneration($nouvelleRemuneration);
            }
            foreach ($revisions as [$avantage, $montant]) {
                if (0 === bccomp($montant, '0', 2)) {
                    $avantage->setDateFin($date->modify('-1 day'));
                } else {
                    $this->reviserAvantage($avantage, $montant, $date, $auteur, false);
                }
            }
            foreach ($nouveauxAvantages as $avantage) {
                $affectation->addAvantage($avantage->setCreePar($auteur));
                $this->em->persist($avantage);
            }
            $this->em->flush();
        });
    }

    /** @throws \DomainException */
    public function arreterAvantage(Avantage $avantage, \DateTimeImmutable $dateFin): void
    {
        if ($dateFin < $avantage->getDateDebut()) {
            throw new \DomainException(sprintf('La date de fin doit être postérieure au début (%s).', $avantage->getDateDebut()->format('d/m/Y')));
        }
        if (null !== $avantage->getDateFin() && $avantage->getDateFin() < new \DateTimeImmutable('today')) {
            throw new \DomainException('Cet avantage est déjà arrêté.');
        }

        $avantage->setDateFin($dateFin);
        $this->em->flush();
    }

    /** Suppression d'un avantage saisi par erreur. */
    public function supprimerAvantage(Avantage $avantage): void
    {
        $avantage->getAffectation()->removeAvantage($avantage);
        $this->em->remove($avantage);
        $this->em->flush();
    }

    /**
     * Fin de l'affectation (démission, licenciement...) : les avantages en cours sont arrêtés à la même date.
     *
     * @throws \DomainException
     */
    public function sortir(Affectation $affectation, \DateTimeImmutable $date, MotifSortie $motif, ?string $commentaire): void
    {
        $this->verifierEnCours($affectation);
        if (MotifSortie::TRANSFERT === $motif) {
            throw new \DomainException('Pour un changement de société, utilisez « Transférer ».');
        }

        $this->cloturer($affectation, $date, $motif, $commentaire);
        $this->em->flush();
    }

    /**
     * Transfert vers une autre société : l'affectation actuelle est clôturée la veille,
     * une nouvelle commence à la date du transfert avec son propre matricule et sa rémunération.
     *
     * @throws \DomainException
     */
    public function transferer(
        Affectation $source,
        Societe $cible,
        \DateTimeImmutable $date,
        ?string $matricule,
        ?string $poste,
        TypeContrat $typeContrat,
        string $salaireBase,
        string $sursalaire,
        bool $reprendreAvantages,
        User $auteur,
    ): Affectation {
        $this->verifierEnCours($source);
        if ($cible === $source->getSociete()) {
            throw new \DomainException('Le salarié est déjà dans cette société.');
        }
        if (!$cible->isActive()) {
            throw new \DomainException('La société de destination est inactive.');
        }
        if ($cible->getSuccursale() !== $source->getSociete()->getSuccursale()) {
            throw new \DomainException('Un transfert n\'est possible qu\'entre sociétés de la même succursale.');
        }
        if ($date <= $source->getDateEntree()) {
            throw new \DomainException('La date du transfert doit être postérieure à l\'entrée dans la société actuelle.');
        }
        if ($matricule && !$this->affectations->matriculeDisponible($cible, $matricule)) {
            throw new \DomainException(sprintf('Le matricule %s est déjà utilisé dans %s.', mb_strtoupper($matricule), $cible->getRaisonSociale()));
        }

        // Avantages repris : même rubrique, ou rubrique de même code dans le pays de destination.
        $avantagesRepris = [];
        $nonRepris = [];
        foreach ($reprendreAvantages ? $source->getAvantagesA($date) : [] as $avantage) {
            $rubrique = $avantage->getRubrique();
            if ($rubrique && $rubrique->getPays() !== $cible->getPaysPaie()) {
                $rubrique = $this->rubriques->findOneBy(['pays' => $cible->getPaysPaie(), 'code' => $rubrique->getCode(), 'actif' => true]);
            }
            if (null === $rubrique) {
                $nonRepris[] = $avantage->getLibelle();
                continue;
            }
            $avantagesRepris[] = $avantage->dupliquer($date)->setRubrique($rubrique);
        }
        $this->derniersAvantagesNonRepris = $nonRepris;

        return $this->em->wrapInTransaction(function () use ($source, $cible, $date, $matricule, $poste, $typeContrat, $salaireBase, $sursalaire, $avantagesRepris, $auteur) {
            $this->cloturer($source, $date->modify('-1 day'), MotifSortie::TRANSFERT, sprintf('Transfert vers %s', $cible->getRaisonSociale()));

            $nouvelle = (new Affectation())
                ->setSociete($cible)
                ->setMatricule($matricule ?: $this->affectations->prochainMatricule($cible))
                ->setPoste($poste ?? $source->getPoste())
                ->setMetier($source->getMetier())
                ->setService($source->getService())
                ->setCategorie($source->getCategorie())
                ->setEchelon($source->getEchelon())
                ->setTypeContrat($typeContrat)
                ->setDateEntree($date)
                ->setProvenance($source)
                ->setCreePar($auteur);
            $source->getSalarie()->addAffectation($nouvelle);
            $nouvelle->addHistoriquePoste(HistoriquePoste::depuis($nouvelle, $date, sprintf('Transfert depuis %s', $source->getSociete()->getRaisonSociale()), $auteur));

            $nouvelle->addRemuneration((new Remuneration())
                ->setDateEffet($date)
                ->setSalaireBase($salaireBase)
                ->setSursalaire($sursalaire)
                ->setMotif(MotifRemuneration::TRANSFERT)
                ->setCommentaire(sprintf('Transfert depuis %s', $source->getSociete()->getRaisonSociale()))
                ->setCreePar($auteur));

            foreach ($avantagesRepris as $avantage) {
                $nouvelle->addAvantage($avantage->setCreePar($auteur));
            }

            $this->em->persist($nouvelle);
            $this->em->flush();

            return $nouvelle;
        });
    }

    private function cloturer(Affectation $affectation, \DateTimeImmutable $date, MotifSortie $motif, ?string $commentaire): void
    {
        if ($date < $affectation->getDateEntree()) {
            throw new \DomainException(sprintf('La date de sortie ne peut pas précéder l\'entrée (%s).', $affectation->getDateEntree()->format('d/m/Y')));
        }

        $affectation->setDateSortie($date)->setMotifSortie($motif)->setCommentaireSortie($commentaire);

        // Revalorisations prévues après la sortie : jamais appliquées.
        foreach ($affectation->getRemunerations()->toArray() as $remuneration) {
            if ($remuneration->getDateEffet() > $date) {
                $affectation->getRemunerations()->removeElement($remuneration);
                $this->em->remove($remuneration);
            }
        }

        foreach ($affectation->getAvantages()->toArray() as $avantage) {
            if ($avantage->getDateDebut() > $date) {
                // Prévu après la sortie : il n'a jamais été appliqué.
                $affectation->removeAvantage($avantage);
                $this->em->remove($avantage);
            } elseif (null === $avantage->getDateFin() || $avantage->getDateFin() > $date) {
                $avantage->setDateFin($date);
            }
        }
    }

    private function verifierEnCours(Affectation $affectation): void
    {
        if (!$affectation->isEnCours()) {
            throw new \DomainException('Ce salarié a quitté la société : son dossier est en lecture seule.');
        }
    }

    /** Rubrique valide pour la société (pays, attribuable comme élément fixe, active) et date cohérente. */
    private function verifierAvantage(Affectation $affectation, Avantage $avantage): void
    {
        $rubrique = $avantage->getRubrique();
        if (null === $rubrique) {
            throw new \DomainException('Chaque avantage doit être rattaché à une rubrique.');
        }
        if ($rubrique->getPays() !== $affectation->getSociete()->getPaysPaie()) {
            throw new \DomainException(sprintf('La rubrique %s n\'appartient pas au référentiel de %s.', $rubrique->getCode(), $affectation->getSociete()->getPaysPaie()->getNom()));
        }
        if (!$rubrique->isElementFixe() || !$rubrique->isActif()) {
            throw new \DomainException(sprintf('La rubrique %s ne peut pas être attribuée comme avantage.', $rubrique->getCode()));
        }
        if ($avantage->getDateDebut() < $affectation->getDateEntree()) {
            throw new \DomainException(sprintf('L\'avantage « %s » ne peut pas commencer avant l\'entrée du salarié.', $avantage->getLibelle()));
        }
    }
}
