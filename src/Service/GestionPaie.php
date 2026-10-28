<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Avantage;
use App\Entity\ElementVariable;
use App\Entity\FichePaie;
use App\Entity\Paiement;
use App\Entity\PeriodePaie;
use App\Entity\Societe;
use App\Entity\User;
use App\Entity\ZoneTransport;
use App\Enum\AssietteCotisation;
use App\Enum\SectionBulletin;
use App\Enum\SituationFamiliale;
use App\Repository\CotisationRepository;
use App\Repository\FichePaieRepository;
use App\Repository\ParametrePaieRepository;
use App\Repository\PeriodePaieRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Préparation de la paie d'un mois : périodes, éléments variables et récapitulatif.
 *
 * La paie d'un salarié pour un mois réunit :
 * - les éléments fixes (salaire catégoriel, sursalaire, avantages en vigueur), jamais modifiés ici ;
 * - les ajustements : montant d'un avantage remplacé pour ce mois seulement ;
 * - les éléments ponctuels : primes, heures supplémentaires, retenues, acomptes...
 *
 * Cotisations sociales et impôt seront calculés à partir de ce récapitulatif (barèmes par pays).
 */
class GestionPaie
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PeriodePaieRepository $periodes,
        private readonly RubriqueRepository $rubriques,
        private readonly ParametrePaieRepository $parametres,
        private readonly CalculPaie $calcul,
        private readonly FichePaieRepository $fiches,
        private readonly CotisationRepository $cotisations,
    ) {
    }

    public function trouver(Societe $societe, \DateTimeInterface $mois): ?PeriodePaie
    {
        return $this->periodes->findOneBy(['societe' => $societe, 'mois' => \DateTimeImmutable::createFromInterface($mois)->modify('first day of this month')->setTime(0, 0)]);
    }

    public function ouvrir(Societe $societe, \DateTimeInterface $mois, User $auteur): PeriodePaie
    {
        if ($periode = $this->trouver($societe, $mois)) {
            return $periode;
        }

        $periode = new PeriodePaie($societe, $mois, $auteur);
        $this->em->persist($periode);
        $this->em->flush();

        return $periode;
    }

    public function cloturer(PeriodePaie $periode, User $auteur): void
    {
        $periode->cloturer($auteur);
        $this->em->flush();
    }

    public function rouvrir(PeriodePaie $periode): void
    {
        $periode->rouvrir();
        $this->em->flush();
    }

    /** Fiche du mois dont le solde de tout compte est arrêté, sinon null. */
    public function soldeArrete(PeriodePaie $periode, Affectation $affectation): ?FichePaie
    {
        $fiche = $this->fiches->findOneBy(['periode' => $periode, 'affectation' => $affectation]);

        return $fiche?->isSoldeArrete() ? $fiche : null;
    }

    /**
     * Arrête le solde de tout compte d'un salarié sorti dans le mois : son bulletin devient définitif
     * (imprimable et envoyable) sans attendre la validation du mois, et ses éléments sont verrouillés.
     *
     * @throws \DomainException
     */
    public function arreterSolde(PeriodePaie $periode, Affectation $affectation, ?User $auteur): void
    {
        $this->verifierModifiable($periode, $affectation);
        if (!$affectation->isSoldeToutCompte($periode->getDebut(), $periode->getFin())) {
            throw new \DomainException(sprintf('%s ne quitte pas la société en %s : enregistrez d\'abord sa sortie.', $affectation->getSalarie()->getNomComplet(), $periode->getLibelle()));
        }
        if (bccomp($this->recapitulatif($affectation, $periode)['net'], '0', 2) < 0) {
            throw new \DomainException('Le net à payer est négatif : corrigez les éléments avant d\'arrêter le solde.');
        }
        $fiche = $this->fiches->obtenir($periode, $affectation)->arreterSolde($auteur);
        $this->em->persist($fiche);
        $this->em->flush();
    }

    /** @throws \DomainException */
    public function annulerSolde(PeriodePaie $periode, Affectation $affectation): void
    {
        if ($periode->isCloturee()) {
            throw new \DomainException('La paie du mois est validée : rouvrez-la pour annuler l\'arrêté du solde.');
        }
        $fiche = $this->soldeArrete($periode, $affectation) ?? throw new \DomainException('Ce solde de tout compte n\'est pas arrêté.');
        if ($this->em->getRepository(Paiement::class)->count(['periode' => $periode, 'affectation' => $affectation, 'annuleLe' => null])) {
            throw new \DomainException('Ce solde est payé : annulez d\'abord le paiement.');
        }
        $fiche->annulerSolde();
        $this->em->flush();
    }

    /**
     * Élément ponctuel (rubrique du référentiel) ou ajustement d'un avantage fixe pour le mois.
     *
     * @throws \DomainException
     */
    public function ajouterElement(PeriodePaie $periode, Affectation $affectation, ElementVariable $element, User $auteur): void
    {
        $this->verifierModifiable($periode, $affectation);

        if ($avantage = $element->getAvantage()) {
            if ($avantage->getAffectation() !== $affectation || !in_array($avantage, $this->avantagesDuMois($affectation, $periode), true)) {
                throw new \DomainException('Cet avantage n\'est pas en vigueur pour ce salarié sur ce mois.');
            }
            foreach ($periode->getElementsDe($affectation) as $existant) {
                if ($existant->getAvantage() === $avantage) {
                    throw new \DomainException(sprintf('Le montant de « %s » est déjà ajusté pour ce mois : supprimez l\'ajustement existant pour le remplacer.', $avantage->getLibelle()));
                }
            }
            $element->setRubrique($avantage->getRubrique());
        } else {
            $rubrique = $element->getRubrique() ?? throw new \DomainException('Choisissez la rubrique.');
            if ($rubrique->getPays() !== $periode->getSociete()->getPaysPaie() || !$rubrique->isActif() || !$rubrique->isElementVariable()) {
                throw new \DomainException(sprintf('La rubrique %s ne peut pas être saisie comme élément variable.', $rubrique->getCode()));
            }
            if ($periode->getSociete()->isRubriqueMasquee($rubrique)) {
                throw new \DomainException(sprintf('La rubrique %s n\'est pas utilisée par la société (voir « Rubriques & barèmes »).', $rubrique->getCode()));
            }
            if (0 === bccomp($element->getMontant(), '0', 2) && null === $element->getBase()) {
                throw new \DomainException($rubrique->isHoraire() ? 'Indiquez le nombre d\'heures.' : 'Indiquez un montant, ou une base et un taux.');
            }
            // Heures supplémentaires : taux = taux horaire du salarié × coefficient de majoration.
            if ($rubrique->isHoraire() && null === $element->getTaux() && null !== $element->getBase()) {
                $element->setTaux($this->tauxHoraire($affectation, $periode, $rubrique->getCoefficientHoraire()));
            }
        }

        // Montant saisi : repris tel quel, sans prorata (la base et le taux saisis ne servent qu'à l'affichage).
        // Montant laissé vide : heures sup. = heures × taux horaire ; autres rubriques = base × jours / jours du mois,
        // le taux étant le nombre de jours comme sur le bulletin (base 30 000, taux 30 → 30 000).
        if (null !== $element->getBase() && null !== $element->getTaux() && 0 === bccomp($element->getMontant(), '0', 2)) {
            $parametre = $this->parametres->pour($periode->getSociete()->getPaysPaie());
            $montant = $element->getRubrique()?->isHoraire()
                ? bcmul($element->getBase(), $element->getTaux(), 6)
                : bcdiv(bcmul($element->getBase(), $element->getTaux(), 6), (string) $parametre->getJoursMois(), 6);
            $element->setMontant(CalculPaie::arrondi($montant, $parametre->getDecimales()));
        }

        $element->setPeriode($periode)->setAffectation($affectation)->setCreePar($auteur);
        $periode->getElements()->add($element);
        $this->em->persist($element);
        $this->em->flush();
    }

    /**
     * Taux horaire majoré : (salaire catégoriel + sursalaire mensuels) / heures mensuelles × coefficient.
     */
    public function tauxHoraire(Affectation $affectation, PeriodePaie $periode, string $coefficient): string
    {
        $parametre = $this->parametres->pour($periode->getSociete()->getPaysPaie());
        $remuneration = $affectation->getRemunerationA(min($periode->getFin(), $affectation->getDateSortie() ?? $periode->getFin()));
        $mensuel = $remuneration?->getSalaireTotal() ?? '0';

        return bcadd(bcmul(bcdiv($mensuel, $parametre->getHeuresMois(), 6), $coefficient, 6), '0.00005', 4);
    }

    /**
     * Jours travaillés du mois : valeur saisie, sinon mois complet (jours de base du pays),
     * ou jours calendaires de présence en cas d'entrée / sortie en cours de mois.
     *
     * @return array{jours: string, auto: bool}
     */
    public function joursDuMois(Affectation $affectation, PeriodePaie $periode): array
    {
        $saisis = $this->fiches->findOneBy(['periode' => $periode, 'affectation' => $affectation])?->getJours();
        if (null !== $saisis) {
            return ['jours' => $saisis, 'auto' => false];
        }

        $base = $this->parametres->pour($periode->getSociete()->getPaysPaie())->getJoursMois();
        $debut = max($periode->getDebut(), $affectation->getDateEntree());
        $fin = min($periode->getFin(), $affectation->getDateSortie() ?? $periode->getFin());
        if ($debut == $periode->getDebut() && $fin == $periode->getFin()) {
            return ['jours' => (string) $base, 'auto' => true];
        }

        return ['jours' => (string) max(0, min($base, $debut->diff($fin)->days + 1)), 'auto' => true];
    }

    /** @throws \DomainException */
    public function definirJours(PeriodePaie $periode, Affectation $affectation, ?string $jours): void
    {
        $this->verifierModifiable($periode, $affectation);
        if (null !== $jours && (!is_numeric($jours) || $jours < 0 || $jours > 31)) {
            throw new \DomainException('Le nombre de jours doit être compris entre 0 et 31.');
        }
        $fiche = $this->fiches->obtenir($periode, $affectation)->setJours($jours);
        $this->em->persist($fiche);
        $this->em->flush();
    }

    /** @throws \DomainException */
    public function supprimerElement(ElementVariable $element): void
    {
        $this->verifierModifiable($element->getPeriode(), $element->getAffectation());
        $element->getPeriode()->getElements()->removeElement($element);
        $this->em->remove($element);
        $this->em->flush();
    }

    /**
     * Avantages fixes applicables au mois : en vigueur au moins un jour du mois,
     * et pour une même rubrique, la ligne la plus récente (en cas de révision en cours de mois).
     * Un avantage sur la rubrique du transport est ignoré quand la zone de résidence fixe l'indemnité.
     *
     * @return Avantage[]
     */
    public function avantagesDuMois(Affectation $affectation, PeriodePaie $periode): array
    {
        $zone = $this->zoneTransport($affectation, $periode);
        $retenus = [];
        foreach ($affectation->getAvantages() as $avantage) {
            if ($avantage->getDateDebut() > $periode->getFin() || ($avantage->getDateFin() && $avantage->getDateFin() < $periode->getDebut())) {
                continue;
            }
            if ($zone && $avantage->getRubrique() === $zone->getRubrique()) {
                continue;
            }
            $cle = ($avantage->getRubrique()?->getId() ?? 'x').'|'.$avantage->getLibelle();
            if (!isset($retenus[$cle]) || $avantage->getDateDebut() > $retenus[$cle]->getDateDebut()) {
                $retenus[$cle] = $avantage;
            }
        }

        return array_values($retenus);
    }

    /**
     * Trie les lignes par section puis par numéro de rubrique : la partie numérique en tête du code
     * (« 0013 », « 067 », « 086B ») ; les codes sans chiffre initial ou absents viennent en fin de section.
     * L'ordre de saisie départage les lignes de même code.
     */
    public static function ordonner(array $lignes): array
    {
        $cle = static function (array $l, int $i): array {
            $code = (string) ($l['code'] ?? '');

            return [$l['section'] ?? 9, preg_match('/^\d+/', $code, $m) ? (int) $m[0] : PHP_INT_MAX, $code, $i];
        };
        $indexees = array_map(null, $lignes, array_keys($lignes));
        usort($indexees, static fn (array $a, array $b) => $cle($a[0], $a[1]) <=> $cle($b[0], $b[1]));

        return array_column($indexees, 0);
    }

    /** Zone de transport du salarié, si elle relève du pays de paie de la société et est active. */
    public function zoneTransport(Affectation $affectation, PeriodePaie $periode): ?ZoneTransport
    {
        $zone = $affectation->getSalarie()->getZoneTransport();

        return $zone && $zone->isActif() && $zone->getPays() === $periode->getSociete()->getPaysPaie() ? $zone : null;
    }

    /**
     * Récapitulatif des éléments de paie du mois pour un salarié.
     *
     * @return array{
     *     lignes: list<array{code: ?string, libelle: string, base: ?string, taux: ?string, montant: string, gain: bool, origine: string, montantHabituel: ?string, element: ?ElementVariable, avantage: ?Avantage}>,
     *     totalGains: string, totalRetenues: string, net: string, nbVariables: int
     * }
     */
    public function recapitulatif(Affectation $affectation, PeriodePaie $periode): array
    {
        $pays = $periode->getSociete()->getPaysPaie();
        $parametres = $this->parametres->pour($pays);
        $lignes = [];
        $remuneration = $affectation->getRemunerationA(min($periode->getFin(), $affectation->getDateSortie() ?? $periode->getFin()));
        $codeSursalaire = $this->rubriques->findOneBy(['pays' => $pays, 'libelle' => 'Sursalaire'])?->getCode();

        // Prorata des éléments fixes : montant mensuel × jours travaillés / jours de base.
        $jours = $this->joursDuMois($affectation, $periode);
        $prorata = function (array $ligne) use ($parametres, $jours): array {
            if ($parametres->isProratiserFixes()) {
                $ligne['base'] = $ligne['montant'];
                $ligne['taux'] = $jours['jours'];
                $ligne['montant'] = CalculPaie::arrondi(bcdiv(bcmul($ligne['montant'], $jours['jours'], 6), (string) $parametres->getJoursMois(), 6), $parametres->getDecimales());
            }

            return $ligne;
        };

        if ($remuneration) {
            $lignes[] = $prorata($this->ligne(null, 'Salaire catégoriel', $remuneration->getSalaireBase(), true, 'fixe'));
            if (bccomp($remuneration->getSursalaire(), '0', 2) > 0) {
                $lignes[] = $prorata($this->ligne($codeSursalaire, 'Sursalaire', $remuneration->getSursalaire(), true, 'fixe'));
            }
        }
        $categorielDuMois = $lignes[0]['montant'] ?? '0';
        $sursalaireDuMois = $lignes[1]['montant'] ?? '0';

        $elements = $periode->getElementsDe($affectation);
        $ajustements = [];
        foreach ($elements as $element) {
            if ($element->isAjustement()) {
                $ajustements[$element->getAvantage()->getId()] = $element;
            }
        }

        // Indemnité de transport selon la ville de résidence (barème du pays de paie, comme la table v1 prime_transport) :
        // elle remplace un avantage saisi sur la même rubrique.
        $zone = $this->zoneTransport($affectation, $periode);

        foreach ($this->avantagesDuMois($affectation, $periode) as $avantage) {
            $ajustement = $ajustements[$avantage->getId()] ?? null;
            $ligne = $this->ligne(
                $avantage->getRubrique()?->getCode(),
                $avantage->getLibelle(),
                $ajustement ? $ajustement->getMontant() : $avantage->getMontant(),
                $avantage->getRubrique()?->isGain() ?? true,
                $ajustement ? 'ajuste' : 'fixe',
            );
            $ligne['montantHabituel'] = $ajustement ? $avantage->getMontant() : null;
            if ($ajustement && bccomp($avantage->getMontant(), '0', 2) > 0) {
                // Lisible sur le bulletin comme une ligne au prorata : base = montant habituel,
                // taux = jours équivalents (30 000 × 15 / 30 = 15 000).
                $ligne['base'] = $avantage->getMontant();
                $ligne['taux'] = bcdiv(bcmul($ajustement->getMontant(), (string) $parametres->getJoursMois(), 6), $avantage->getMontant(), 2);
            }
            $ligne['element'] = $ajustement;
            $ligne['avantage'] = $avantage;
            // Un montant ajusté pour le mois est pris tel quel, de même qu'une rubrique sans prorata
            // (ex. cotisation syndicale) ; sinon prorata des jours.
            $lignes[] = $ajustement || false === $avantage->getRubrique()?->isProratisee() ? $ligne : $prorata($ligne);
        }

        if ($zone && bccomp($zone->getMontant(), '0', 2) > 0) {
            // Libellé de la rubrique seul : la ville de résidence n'apparaît pas sur le bulletin.
            $ligne = $this->ligne($zone->getRubrique()->getCode(), $zone->getRubrique()->getLibelle(), $zone->getMontant(), true, 'fixe');
            $ligne['rubrique'] = $zone->getRubrique();
            $ligne['zoneTransport'] = $zone;
            $lignes[] = $zone->getRubrique()->isProratisee() ? $prorata($ligne) : $ligne;
        }

        foreach ($elements as $element) {
            if (!$element->isAjustement()) {
                // Libellé du référentiel : il est défini une fois pour toutes sur la rubrique.
                $ligne = $this->ligne($element->getRubrique()->getCode(), $element->getRubrique()->getLibelle(), $element->getMontant(), $element->getRubrique()->isGain(), 'variable');
                $ligne['base'] = $element->getBase();
                $ligne['taux'] = $element->getTaux();
                $ligne['element'] = $element;
                $ligne['rubrique'] = $element->getRubrique();
                $lignes[] = $ligne;
            }
        }

        // Calcul selon les paramètres du pays : ancienneté, parts, brut, brut imposable.
        $gainsRubriques = [];
        foreach ($lignes as $ligne) {
            $rubrique = $ligne['rubrique'] ?? $ligne['avantage']?->getRubrique();
            if ($ligne['gain'] && $rubrique) {
                $gainsRubriques[] = [
                    'code' => $rubrique->getCode(),
                    'libelle' => $ligne['libelle'],
                    'montant' => $ligne['montant'],
                    'section' => $rubrique->getSection(),
                    'fiscal' => $rubrique->getTraitementFiscal(),
                    'plafond' => $rubrique->getPlafondExoneration(),
                ];
            }
        }
        $salarie = $affectation->getSalarie();
        $calcul = $this->calcul->calculer($parametres, $this->parametres->grilleParts($pays), [
            'salaireCategoriel' => $categorielDuMois,
            'sursalaire' => $sursalaireDuMois,
            'dateEmbauche' => $this->dateAnciennete($affectation, $parametres->isAncienneteDepuisGroupe()),
            'dateReference' => min($periode->getFin(), $affectation->getDateSortie() ?? $periode->getFin()),
            'situation' => $salarie->getSituationFamiliale(),
            'enfants' => $salarie->getNombreEnfants(),
            'gains' => $gainsRubriques,
        ]);
        if ($calcul['anciennete']['applicable']) {
            // Ligne calculée insérée après le salaire de base / sursalaire.
            $ligne = $this->ligne($calcul['anciennete']['code'], sprintf('Prime d\'ancienneté (%d ans, %s %%)', $calcul['anciennete']['annees'], rtrim(rtrim($calcul['anciennete']['taux'], '0'), '.')), $calcul['anciennete']['montant'], true, 'calcule');
            array_splice($lignes, $remuneration && bccomp($remuneration->getSursalaire(), '0', 2) > 0 ? 2 : 1, 0, [$ligne]);
        }

        $gains = '0';
        $retenues = '0';
        foreach ($lignes as $ligne) {
            if ($ligne['gain']) {
                $gains = bcadd($gains, $ligne['montant'], 2);
            } else {
                $retenues = bcadd($retenues, $ligne['montant'], 2);
            }
        }

        // Lignes comprises dans le brut (affichées avant le brut sur le bulletin) ; les autres viennent après le net.
        foreach ($lignes as $i => $ligne) {
            $rubrique = $ligne['rubrique'] ?? $ligne['avantage']?->getRubrique();
            $lignes[$i]['dansBrut'] = $ligne['gain'] && match (true) {
                'Salaire catégoriel' === $ligne['libelle'] && null === $rubrique => true,
                'calcule' === $ligne['origine'] => $parametres->isBrutInclutAnciennete(),
                null === $rubrique => $parametres->isBrutInclutSursalaire(),
                SectionBulletin::NON_SOUMIS === $rubrique->getSection() => $parametres->isBrutInclutGainsNonSoumis(),
                default => $parametres->isBrutInclutGainsSoumis(),
            };
            if (null === $ligne['code'] && 'Salaire catégoriel' === $ligne['libelle']) {
                $lignes[$i]['code'] = $parametres->getCodeSalaireBase();
            }
            // Section du bulletin : 1 éléments soumis, 2 avec les cotisations salariales, 3 après le salaire net.
            $lignes[$i]['section'] = match (true) {
                $lignes[$i]['dansBrut'] => SectionBulletin::SOUMIS->value,
                !$ligne['gain'] && SectionBulletin::COTISATIONS === $rubrique?->getSection() => SectionBulletin::COTISATIONS->value,
                default => SectionBulletin::NON_SOUMIS->value,
            };
        }

        // Chaque section dans l'ordre des numéros de rubrique, comme sur le bulletin ivoirien
        // (0010 salaire, 0012 sursalaire, 0013 ancienneté, 0014 heures sup., 067…).
        $lignes = self::ordonner($lignes);

        // 2e partie : impôt, cotisations, net. Personnes couvertes (forfaits type CMU) : salarié + conjoint marié + enfants.
        $personnes = 1 + $salarie->getNombreEnfants() + (SituationFamiliale::MARIE === $salarie->getSituationFamiliale() ? 1 : 0);
        $gainsHorsBrut = bcsub($gains, $calcul['brut'], 2);
        $bulletin = $this->calcul->calculerCharges(
            $parametres,
            $this->cotisations->duPays($pays, true),
            $this->cotisations->tranches($pays),
            $this->cotisations->reductions($pays),
            $calcul['brut'],
            $calcul['brutImposable'],
            $calcul['parts'],
            $personnes,
            bccomp($gainsHorsBrut, '0', 2) > 0 ? $gainsHorsBrut : '0',
            $retenues,
            $periode->getSociete()->getTauxAccidentTravail(),
            // Nationalité non renseignée : traité comme national.
            null === $salarie->getNationalite() || $salarie->getNationalite() === $pays->getCodeIso(),
        );

        return [
            'bulletin' => $bulletin,
            'lignes' => $lignes,
            // L'arrondi du net compte en gain (< 0) ou en retenue (> 0).
            'totalGains' => bccomp($bulletin['arrondi'], '0', 2) < 0 ? bcsub($gains, $bulletin['arrondi'], 2) : $gains,
            'totalRetenues' => $retenues,
            // Retenues du bulletin = retenues des rubriques + impôt et cotisations salariales (+ arrondi) ; net = net à payer.
            'chargesSalariales' => $bulletin['totalSalarial'],
            'retenuesTotales' => bcadd(bcadd($retenues, $bulletin['totalSalarial'], 2), bccomp($bulletin['arrondi'], '0', 2) > 0 ? $bulletin['arrondi'] : '0', 2),
            'chargesPatronales' => $bulletin['totalPatronal'],
            'net' => $bulletin['netAPayer'],
            'nbVariables' => count($elements),
            'calcul' => $calcul,
            'parametre' => $parametres->getId() ? $parametres : null,
            // Sortie dans le mois : bulletin « solde de tout compte », définitif dès qu'il est arrêté.
            'soldeToutCompte' => $affectation->isSoldeToutCompte($periode->getDebut(), $periode->getFin()),
            'soldeArrete' => $this->soldeArrete($periode, $affectation),
            'jours' => $jours['jours'],
            'joursAuto' => $jours['auto'],
            'joursBase' => $parametres->getJoursMois(),
        ];
    }

    /**
     * Cumuls de paie du bulletin (repris de la v1, SocieteFicheController::selectDateSociete) :
     * l'année de paie part du mois d'embauche et repart à zéro à chaque anniversaire de ce mois
     * (embauche le 01/05/2025 : mai = 30 jours, juin = 60… avril 2026 = 360, mai 2026 = 30).
     * Chaque cumul additionne les bulletins de l'année jusqu'au mois du bulletin, y compris
     * ceux d'une société de provenance en cas de transfert. Un mois sans paie ouverte ne compte pas.
     *
     * @param array $recap récapitulatif du mois déjà calculé pour $affectation
     *
     * @return array{debut: \DateTimeImmutable, jours: string, brutImposable: string, impot: string, cnps: string, brut: string, joursDepuisConge: string, congeAcquis: string}
     */
    public function cumuls(Affectation $affectation, PeriodePaie $periode, array $recap): array
    {
        $chaine = [];
        for ($a = $affectation; $a; $a = $a->getProvenance()) {
            $chaine[] = $a;
        }
        $embauche = end($chaine)->getDateEntree()->modify('first day of this month')->setTime(0, 0);
        $mois = $periode->getDebut();
        $ecart = ((int) $mois->format('Y') - (int) $embauche->format('Y')) * 12 + (int) $mois->format('n') - (int) $embauche->format('n');
        $debut = $embauche->modify(sprintf('+%d months', max(0, $ecart - $ecart % 12)));

        $totaux = ['jours' => '0', 'brutImposable' => '0', 'impot' => '0', 'cnps' => '0', 'brut' => '0'];
        $ajouter = static function (array $r) use (&$totaux): void {
            $totaux['jours'] = bcadd($totaux['jours'], (string) $r['jours'], 2);
            $totaux['brutImposable'] = bcadd($totaux['brutImposable'], $r['calcul']['brutImposable'], 2);
            $totaux['brut'] = bcadd($totaux['brut'], $r['calcul']['brut'], 2);
            $totaux['impot'] = bcadd($totaux['impot'], $r['bulletin']['impot']['montant'] ?? '0', 2);
            // Retenue CNPS : cotisations salariales assises sur le brut (hors forfaits type CMU).
            foreach ($r['bulletin']['salariales'] as $c) {
                if (AssietteCotisation::BRUT === ($c['assiette'] ?? null)) {
                    $totaux['cnps'] = bcadd($totaux['cnps'], $c['montant'], 2);
                }
            }
        };

        for ($m = $debut; $m <= $mois; $m = $m->modify('+1 month')) {
            $fin = $m->modify('last day of this month');
            foreach ($chaine as $a) {
                if ($a === $affectation && $m == $mois) {
                    $ajouter($recap);
                    continue;
                }
                if ($a->getDateEntree() > $fin || ($a->getDateSortie() && $a->getDateSortie() < $m)) {
                    continue;
                }
                if ($p = $this->trouver($a->getSociete(), $m)) {
                    $ajouter($this->recapitulatif($a, $p));
                }
            }
        }

        // Jours depuis le dernier congé : mêmes jours que l'année de paie ; congé acquis = jours × jours de congé par mois / 30,
        // tronqué à l'entier comme en v1 (Côte d'Ivoire : 2,57).
        $parametre = $this->parametres->pour($periode->getSociete()->getPaysPaie());
        $acquis = bcdiv(bcmul($totaux['jours'], $parametre->getJoursCongeParMois(), 6), (string) $parametre->getJoursMois(), 0);

        return $totaux + ['debut' => $debut, 'joursDepuisConge' => $totaux['jours'], 'congeAcquis' => $acquis];
    }

    /** Date de départ de l'ancienneté : entrée dans la société, ou dans le groupe en remontant les transferts. */
    private function dateAnciennete(Affectation $affectation, bool $depuisGroupe): \DateTimeImmutable
    {
        $premiere = $affectation;
        while ($depuisGroupe && $premiere->getProvenance()) {
            $premiere = $premiere->getProvenance();
        }

        return $premiere->getDateEntree();
    }

    /** @throws \DomainException */
    private function verifierModifiable(PeriodePaie $periode, Affectation $affectation): void
    {
        if ($periode->isCloturee()) {
            throw new \DomainException(sprintf('La paie — %s est clôturée : elle ne peut plus être modifiée.', $periode->getLibelle()));
        }
        if ($affectation->getSociete() !== $periode->getSociete()) {
            throw new \DomainException('Ce salarié n\'appartient pas à la société.');
        }
        if ($affectation->getDateEntree() > $periode->getFin() || ($affectation->getDateSortie() && $affectation->getDateSortie() < $periode->getDebut())) {
            throw new \DomainException(sprintf('Ce salarié n\'est pas présent dans la société en %s.', $periode->getLibelle()));
        }
        if ($this->soldeArrete($periode, $affectation)) {
            throw new \DomainException(sprintf('Le solde de tout compte de %s est arrêté : annulez l\'arrêté pour modifier sa paie.', $affectation->getSalarie()->getNomComplet()));
        }
    }

    private function ligne(?string $code, string $libelle, string $montant, bool $gain, string $origine): array
    {
        return ['code' => $code, 'libelle' => $libelle, 'base' => null, 'taux' => null, 'montant' => $montant, 'gain' => $gain, 'origine' => $origine, 'montantHabituel' => null, 'element' => null, 'avantage' => null, 'rubrique' => null];
    }
}
