<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Pays;
use App\Entity\Salarie;
use App\Entity\Societe;
use App\Enum\ModePaiement;
use App\Enum\TraitementFiscal;
use App\Repository\PeriodePaieRepository;
use Symfony\Component\Intl\Currencies;

/**
 * États de paie de la société, établis à partir des bulletins (mêmes calculs, donc mêmes chiffres) :
 * - état des rubriques : total de chaque rubrique (gains, retenues, part patronale) sur une plage de mois ;
 * - livre de paie : une ligne par salarié et par mois, avec sous-totaux mensuels ;
 * - fiche individuelle : les rubriques d'un salarié mois par mois sur une année civile ;
 * - bordereau des salaires : nets à payer par banque, à remettre avec l'ordre de paiement.
 *
 * Les mois dont la paie n'est pas validée sont repris mais signalés « provisoire ».
 */
class EtatsPaie
{
    public const NATIONAUX = 'nationaux';
    public const NON_NATIONAUX = 'non-nationaux';

    /** Ordre d'affichage des rubriques. */
    private const GROUPE_GAIN = 1;
    private const GROUPE_IMPOT = 2;
    private const GROUPE_COTISATION = 3;
    private const GROUPE_PATRONAL = 4;
    private const GROUPE_RETENUE = 5;
    private const GROUPE_ARRONDI = 6;

    public function __construct(
        private readonly PeriodePaieRepository $periodes,
        private readonly BulletinPaie $bulletins,
        private readonly GestionPaie $paie,
    ) {
    }

    /**
     * Bulletins de la société sur la plage de mois (paies ouvertes uniquement), éventuellement limités
     * aux salariés nationaux ou non-nationaux du pays dont la paie s'applique.
     *
     * @return list<array{periode: \App\Entity\PeriodePaie, affectation: Affectation, recap: array, provisoire: bool}>
     */
    public function bulletins(Societe $societe, \DateTimeImmutable $du, \DateTimeImmutable $au, ?string $nationalite = null): array
    {
        $pays = $societe->getPaysPaie();
        $resultat = [];
        foreach ($this->periodes->entre($societe, $du, $au) as $periode) {
            foreach ($this->bulletins->donnees($periode) as $bulletin) {
                if (null !== $nationalite && $this->estNational($bulletin['affectation'], $pays) !== (self::NATIONAUX === $nationalite)) {
                    continue;
                }
                $resultat[] = ['periode' => $periode] + $bulletin;
            }
        }

        return $resultat;
    }

    /**
     * Montants d'un bulletin regroupés par rubrique. Une cotisation réunit sur une même ligne
     * sa part salariale (retenue) et sa part patronale.
     *
     * @return array<string, array{code: string, libelle: string, groupe: int, gain: string, retenue: string, patronal: string}>
     */
    public function rubriques(array $recap): array
    {
        $lignes = [];
        $ajouter = static function (string $cle, int $groupe, ?string $code, string $libelle, string $colonne, string $montant) use (&$lignes): void {
            $lignes[$cle] ??= ['code' => '', 'libelle' => $libelle, 'groupe' => $groupe, 'gain' => '0', 'retenue' => '0', 'patronal' => '0'];
            $lignes[$cle]['code'] = self::fusionnerCodes($lignes[$cle]['code'], $code);
            $lignes[$cle][$colonne] = bcadd($lignes[$cle][$colonne], $montant, 2);
        };

        foreach ($recap['lignes'] as $l) {
            $rubrique = $l['rubrique'] ?? $l['avantage']?->getRubrique();
            // Libellé du référentiel : l'avantage d'un salarié peut porter un libellé personnalisé.
            $libelle = match (true) {
                null !== $rubrique => $rubrique->getLibelle(),
                'calcule' === $l['origine'] => 'Prime d\'ancienneté',
                default => $l['libelle'],
            };
            $ajouter('r:'.($l['code'] ?? $libelle), $l['gain'] ? self::GROUPE_GAIN : self::GROUPE_RETENUE, $l['code'], $libelle, $l['gain'] ? 'gain' : 'retenue', $l['montant']);
        }

        $bulletin = $recap['bulletin'];
        if ($impot = $bulletin['impot']) {
            $ajouter('impot', self::GROUPE_IMPOT, $impot['code'], $impot['libelle'], 'retenue', $impot['montant']);
        }
        foreach ($bulletin['salariales'] as $c) {
            $ajouter('c:'.$c['libelle'], self::GROUPE_COTISATION, $c['code'], $c['libelle'], 'retenue', $c['montant']);
        }
        foreach (array_merge($bulletin['patronalesSociales'], $bulletin['patronalesFiscales']) as $c) {
            $ajouter('c:'.$c['libelle'], self::GROUPE_PATRONAL, $c['code'], $c['libelle'], 'patronal', $c['montant']);
        }
        // Arrondi du net : > 0 retenu, < 0 versé en plus.
        if (0 !== bccomp($bulletin['arrondi'], '0', 2)) {
            $negatif = bccomp($bulletin['arrondi'], '0', 2) < 0;
            $ajouter('arrondi', self::GROUPE_ARRONDI, $recap['parametre']?->getCodeArrondi(), 'Arrondi du net', $negatif ? 'gain' : 'retenue', $negatif ? bcmul($bulletin['arrondi'], '-1', 2) : $bulletin['arrondi']);
        }

        return $lignes;
    }

    /**
     * État des rubriques : cumul de chaque rubrique sur l'ensemble des bulletins.
     *
     * @return array{
     *   lignes: list<array{code: string, libelle: string, groupe: int, gain: string, retenue: string, patronal: string, bulletins: int}>,
     *   totaux: array{gain: string, retenue: string, patronal: string, brut: string, net: string},
     *   effectif: int, nbBulletins: int, provisoire: bool
     * }
     */
    public function etatRubriques(array $bulletins): array
    {
        $lignes = [];
        $ordre = [];
        $totaux = ['gain' => '0', 'retenue' => '0', 'patronal' => '0', 'brut' => '0', 'net' => '0'];
        $salaries = [];
        foreach ($bulletins as $b) {
            foreach ($this->rubriques($b['recap']) as $cle => $r) {
                if (!isset($lignes[$cle])) {
                    $ordre[$cle] = count($ordre);
                    $lignes[$cle] = $r + ['bulletins' => 0];
                } else {
                    $lignes[$cle]['code'] = self::fusionnerCodes($lignes[$cle]['code'], $r['code']);
                    $lignes[$cle]['groupe'] = min($lignes[$cle]['groupe'], $r['groupe']);
                    foreach (['gain', 'retenue', 'patronal'] as $colonne) {
                        $lignes[$cle][$colonne] = bcadd($lignes[$cle][$colonne], $r[$colonne], 2);
                    }
                }
                ++$lignes[$cle]['bulletins'];
                foreach (['gain', 'retenue', 'patronal'] as $colonne) {
                    $totaux[$colonne] = bcadd($totaux[$colonne], $r[$colonne], 2);
                }
            }
            $totaux['brut'] = bcadd($totaux['brut'], $b['recap']['calcul']['brut'], 2);
            $totaux['net'] = bcadd($totaux['net'], $b['recap']['net'], 2);
            $salaries[$b['affectation']->getId()] = true;
        }
        uksort($lignes, static fn (string $a, string $b) => [$lignes[$a]['groupe'], $ordre[$a]] <=> [$lignes[$b]['groupe'], $ordre[$b]]);

        return [
            'lignes' => array_values($lignes),
            'totaux' => $totaux,
            'effectif' => count($salaries),
            'nbBulletins' => count($bulletins),
            'provisoire' => self::contientProvisoire($bulletins),
        ];
    }

    /**
     * Livre de paie : une ligne par bulletin. Les colonnes de cotisations dépendent du pays ;
     * les colonnes facultatives entièrement à zéro sont retirées.
     *
     * @return array{
     *   colonnes: list<array{cle: string, libelle: string, code: string, groupe: string, somme: bool}>,
     *   mois: list<array{periode: \App\Entity\PeriodePaie, lignes: list<array>, total: array<string, string>, provisoire: bool}>,
     *   total: array<string, string>, effectif: int, nbBulletins: int, provisoire: bool
     * }
     */
    public function livre(array $bulletins): array
    {
        $impot = null;
        $salariales = [];
        $patronales = [];
        $lignes = [];
        foreach ($bulletins as $b) {
            $recap = $b['recap'];
            $bulletin = $recap['bulletin'];
            $v = ['jours' => $recap['jours'], 'base' => '0', 'sursalaire' => '0', 'anciennete' => '0', 'imposables' => '0', 'exonerees' => '0', 'horsBrut' => '0', 'retenues' => '0'];
            $gains = '0';
            foreach ($recap['lignes'] as $l) {
                $rubrique = $l['rubrique'] ?? $l['avantage']?->getRubrique();
                // Colonnes de gains dont la somme fait le brut ; les gains versés hors brut à part.
                $colonne = match (true) {
                    !$l['gain'] => 'retenues',
                    !$l['dansBrut'] => 'horsBrut',
                    'calcule' === $l['origine'] => 'anciennete',
                    null === $rubrique && null === $l['avantage'] && 'Salaire catégoriel' === $l['libelle'] => 'base',
                    null === $rubrique && null === $l['avantage'] && 'Sursalaire' === $l['libelle'] => 'sursalaire',
                    null === $rubrique || TraitementFiscal::IMPOSABLE === $rubrique->getTraitementFiscal() => 'imposables',
                    default => 'exonerees',
                };
                $v[$colonne] = bcadd($v[$colonne], $l['montant'], 2);
                if ($l['gain']) {
                    $gains = bcadd($gains, $l['montant'], 2);
                }
            }
            $v['brut'] = $recap['calcul']['brut'];
            $v['brutImposable'] = $recap['calcul']['brutImposable'];
            if ($bulletin['impot']) {
                $impot ??= $bulletin['impot'];
                $v['impot'] = $bulletin['impot']['montant'];
            }
            foreach ($bulletin['salariales'] as $c) {
                $salariales['sal:'.$c['libelle']] ??= ['libelle' => $c['libelle'], 'code' => $c['code'] ?? ''];
                $v['sal:'.$c['libelle']] = bcadd($v['sal:'.$c['libelle']] ?? '0', $c['montant'], 2);
            }
            $v['arrondi'] = $bulletin['arrondi'];
            $v['net'] = $bulletin['netAPayer'];
            foreach (array_merge($bulletin['patronalesSociales'], $bulletin['patronalesFiscales']) as $c) {
                $patronales['pat:'.$c['libelle']] ??= ['libelle' => $c['libelle'], 'code' => $c['code'] ?? ''];
                $v['pat:'.$c['libelle']] = bcadd($v['pat:'.$c['libelle']] ?? '0', $c['montant'], 2);
            }
            $v['totalPatronal'] = $bulletin['totalPatronal'];
            $v['cout'] = bcadd($gains, $bulletin['totalPatronal'], 2);
            $lignes[] = ['periode' => $b['periode'], 'affectation' => $b['affectation'], 'provisoire' => $b['provisoire'], 'valeurs' => $v];
        }

        $colonnes = [
            ['cle' => 'jours', 'libelle' => 'Jours', 'code' => '', 'groupe' => 'identite', 'somme' => false],
            ['cle' => 'base', 'libelle' => 'Salaire de base', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'sursalaire', 'libelle' => 'Sursalaire', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'anciennete', 'libelle' => 'Ancienneté', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'imposables', 'libelle' => 'Primes et indemnités imposables', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'exonerees', 'libelle' => 'Primes et indemnités exonérées', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'brut', 'libelle' => 'Salaire brut', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'brutImposable', 'libelle' => 'Brut imposable', 'code' => '', 'groupe' => 'gains', 'somme' => true],
            ['cle' => 'horsBrut', 'libelle' => 'Gains non soumis (hors brut)', 'code' => '', 'groupe' => 'gains', 'somme' => true],
        ];
        if ($impot) {
            $colonnes[] = ['cle' => 'impot', 'libelle' => $impot['libelle'], 'code' => $impot['code'] ?? '', 'groupe' => 'retenues', 'somme' => true];
        }
        foreach ($salariales as $cle => $c) {
            $colonnes[] = ['cle' => $cle, 'libelle' => $c['libelle'], 'code' => $c['code'], 'groupe' => 'retenues', 'somme' => true];
        }
        $colonnes[] = ['cle' => 'retenues', 'libelle' => 'Autres retenues', 'code' => '', 'groupe' => 'retenues', 'somme' => true];
        $colonnes[] = ['cle' => 'arrondi', 'libelle' => 'Arrondi', 'code' => '', 'groupe' => 'retenues', 'somme' => true];
        $colonnes[] = ['cle' => 'net', 'libelle' => 'Net à payer', 'code' => '', 'groupe' => 'net', 'somme' => true];
        foreach ($patronales as $cle => $c) {
            $colonnes[] = ['cle' => $cle, 'libelle' => $c['libelle'], 'code' => $c['code'], 'groupe' => 'patronal', 'somme' => true];
        }
        $colonnes[] = ['cle' => 'totalPatronal', 'libelle' => 'Total charges patronales', 'code' => '', 'groupe' => 'patronal', 'somme' => true];
        $colonnes[] = ['cle' => 'cout', 'libelle' => 'Coût employeur', 'code' => '', 'groupe' => 'cout', 'somme' => true];

        // Totaux par mois et général.
        $total = [];
        $mois = [];
        foreach ($lignes as $ligne) {
            $code = $ligne['periode']->getCode();
            $mois[$code] ??= ['periode' => $ligne['periode'], 'lignes' => [], 'total' => [], 'provisoire' => false];
            $mois[$code]['lignes'][] = $ligne;
            $mois[$code]['provisoire'] = $mois[$code]['provisoire'] || $ligne['provisoire'];
            foreach ($colonnes as $c) {
                if ($c['somme']) {
                    $valeur = $ligne['valeurs'][$c['cle']] ?? '0';
                    $mois[$code]['total'][$c['cle']] = bcadd($mois[$code]['total'][$c['cle']] ?? '0', $valeur, 2);
                    $total[$c['cle']] = bcadd($total[$c['cle']] ?? '0', $valeur, 2);
                }
            }
        }

        $toujours = ['jours', 'base', 'brut', 'brutImposable', 'net'];
        $colonnes = array_values(array_filter($colonnes, static fn (array $c) => in_array($c['cle'], $toujours, true) || 0 !== bccomp($total[$c['cle']] ?? '0', '0', 2)));

        return [
            'colonnes' => $colonnes,
            'mois' => array_values($mois),
            'total' => $total,
            'effectif' => count(array_unique(array_map(static fn (array $l) => $l['affectation']->getId(), $lignes))),
            'nbBulletins' => count($lignes),
            'provisoire' => self::contientProvisoire($bulletins),
        ];
    }

    /**
     * Fiche individuelle : rubriques d'un salarié mois par mois sur une année civile (mois de paie ouverts
     * où il est présent dans la société), avec la synthèse brut / net / charges.
     *
     * @return array{
     *   mois: array<int, \DateTimeImmutable>, presents: array<int, bool>, provisoires: list<int>,
     *   sections: list<array{titre: string, lignes: list<array{code: string, libelle: string, mois: array<int, string>, total: string}>, total: array{mois: array<int, string>, total: string}}>,
     *   synthese: list<array{libelle: string, mois: array<int, ?string>, total: ?string, important: bool}>
     * }
     */
    public function ficheIndividuelle(Affectation $affectation, int $annee): array
    {
        $recaps = [];
        $provisoires = [];
        foreach ($this->periodes->entre($affectation->getSociete(), new \DateTimeImmutable($annee.'-01-01'), new \DateTimeImmutable($annee.'-12-01')) as $periode) {
            if ($affectation->getDateEntree() > $periode->getFin() || ($affectation->getDateSortie() && $affectation->getDateSortie() < $periode->getDebut())) {
                continue;
            }
            $m = (int) $periode->getMois()->format('n');
            $recaps[$m] = $this->paie->recapitulatif($affectation, $periode);
            if (!$periode->isCloturee() && null === $recaps[$m]['soldeArrete']) {
                $provisoires[] = $m;
            }
        }

        $mois = [];
        for ($m = 1; $m <= 12; ++$m) {
            $mois[$m] = new \DateTimeImmutable(sprintf('%d-%02d-01', $annee, $m));
        }

        // Rubriques par mois, puis une section par sens (une cotisation figure en retenue et en part patronale).
        $rubriques = [];
        $ordre = [];
        foreach ($recaps as $m => $recap) {
            foreach ($this->rubriques($recap) as $cle => $r) {
                $ordre[$cle] ??= [$r['groupe'], count($ordre)];
                $rubriques[$cle]['code'] = self::fusionnerCodes($rubriques[$cle]['code'] ?? '', $r['code']);
                $rubriques[$cle]['libelle'] = $r['libelle'];
                $rubriques[$cle]['mois'][$m] = $r;
            }
        }
        uksort($rubriques, static fn (string $a, string $b) => $ordre[$a] <=> $ordre[$b]);

        $sections = [];
        foreach (['gain' => 'Gains', 'retenue' => 'Retenues salariales', 'patronal' => 'Charges patronales'] as $colonne => $titre) {
            $section = ['titre' => $titre, 'lignes' => [], 'total' => ['mois' => [], 'total' => '0']];
            foreach ($rubriques as $r) {
                $ligne = ['code' => $r['code'], 'libelle' => $r['libelle'], 'mois' => [], 'total' => '0'];
                foreach ($r['mois'] as $m => $montants) {
                    if (0 !== bccomp($montants[$colonne], '0', 2)) {
                        $ligne['mois'][$m] = $montants[$colonne];
                        $ligne['total'] = bcadd($ligne['total'], $montants[$colonne], 2);
                        $section['total']['mois'][$m] = bcadd($section['total']['mois'][$m] ?? '0', $montants[$colonne], 2);
                    }
                }
                if ($ligne['mois']) {
                    $section['lignes'][] = $ligne;
                    $section['total']['total'] = bcadd($section['total']['total'], $ligne['total'], 2);
                }
            }
            if ($section['lignes']) {
                $sections[] = $section;
            }
        }

        $synthese = [];
        foreach ([
            ['Jours travaillés', static fn (array $r) => $r['jours'], false],
            ['Salaire brut', static fn (array $r) => $r['calcul']['brut'], true],
            ['Brut imposable', static fn (array $r) => $r['calcul']['brutImposable'], false],
            ['Total des retenues', static fn (array $r) => $r['retenuesTotales'], false],
            ['Net à payer', static fn (array $r) => $r['net'], true],
            ['Charges patronales', static fn (array $r) => $r['chargesPatronales'], false],
            ['Coût employeur', static fn (array $r) => bcadd(bcsub($r['totalGains'], bccomp($r['bulletin']['arrondi'], '0', 2) < 0 ? bcmul($r['bulletin']['arrondi'], '-1', 2) : '0', 2), $r['chargesPatronales'], 2), false],
        ] as [$libelle, $valeur, $important]) {
            $ligne = ['libelle' => $libelle, 'mois' => [], 'total' => '0', 'important' => $important];
            foreach ($recaps as $m => $recap) {
                $ligne['mois'][$m] = (string) $valeur($recap);
                $ligne['total'] = bcadd($ligne['total'], $ligne['mois'][$m], 2);
            }
            $synthese[] = $ligne;
        }

        return [
            'mois' => $mois,
            'presents' => array_fill_keys(array_keys($recaps), true),
            'provisoires' => $provisoires,
            'sections' => $sections,
            'synthese' => $synthese,
        ];
    }

    /**
     * Bordereau des salaires (comme en v1) : nets à payer regroupés par banque (virement, mobile money)
     * ou par moyen de paiement (chèque, espèces), un bordereau par groupe, avec le total en lettres.
     * $banque limite à un groupe (nom de banque ou libellé du moyen de paiement).
     *
     * @return array{
     *   groupes: list<array{nom: string, bancaire: bool, lignes: list<array{periode: \App\Entity\PeriodePaie, affectation: Affectation, net: string}>, total: string, enLettres: string}>,
     *   choix: list<string>, total: string, provisoire: bool, plusieursMois: bool
     * }
     */
    public function bordereau(array $bulletins, ?string $banque, string $devise): array
    {
        $groupes = [];
        $total = '0';
        $mois = [];
        foreach ($bulletins as $b) {
            [$nom, $bancaire] = self::groupePaiement($b['affectation']->getSalarie());
            $groupes[$nom] ??= ['nom' => $nom, 'bancaire' => $bancaire, 'lignes' => [], 'total' => '0'];
            $groupes[$nom]['lignes'][] = ['periode' => $b['periode'], 'affectation' => $b['affectation'], 'net' => $b['recap']['net']];
            $mois[$b['periode']->getCode()] = true;
        }
        // Banques par ordre alphabétique, puis chèques et espèces.
        uasort($groupes, static fn (array $a, array $b) => [!$a['bancaire'], $a['nom']] <=> [!$b['bancaire'], $b['nom']]);
        $choix = array_keys($groupes);

        if (null !== $banque && '' !== $banque) {
            $groupes = array_intersect_key($groupes, [$banque => true]);
        }
        foreach ($groupes as $nom => $groupe) {
            usort($groupe['lignes'], static fn (array $a, array $b) => [$a['periode']->getCode(), $a['affectation']->getSalarie()->getNomComplet()] <=> [$b['periode']->getCode(), $b['affectation']->getSalarie()->getNomComplet()]);
            foreach ($groupe['lignes'] as $ligne) {
                $groupe['total'] = bcadd($groupe['total'], $ligne['net'], 2);
            }
            $groupe['enLettres'] = self::enLettres($groupe['total'], $devise);
            $total = bcadd($total, $groupe['total'], 2);
            $groupes[$nom] = $groupe;
        }

        return [
            'groupes' => array_values($groupes),
            'choix' => $choix,
            'total' => $total,
            'provisoire' => self::contientProvisoire($bulletins),
            'plusieursMois' => count($mois) > 1,
        ];
    }

    /**
     * Groupe du bordereau : la banque (ou l'opérateur mobile) du salarié, sinon son moyen de paiement.
     *
     * @return array{0: string, 1: bool} nom du groupe, groupe bancaire
     */
    public static function groupePaiement(Salarie $salarie): array
    {
        $banque = trim((string) $salarie->getBanque());
        if ('' !== $banque && in_array($salarie->getModePaiement(), [ModePaiement::VIREMENT, ModePaiement::MOBILE_MONEY], true)) {
            return [$banque, true];
        }

        return [$salarie->getModePaiement()->libelle(), false];
    }

    /**
     * Groupes possibles du bordereau pour ces salariés : banques par ordre alphabétique, puis autres moyens.
     *
     * @param Affectation[] $affectations
     *
     * @return list<string>
     */
    public static function groupesPaiement(array $affectations): array
    {
        $groupes = [];
        foreach ($affectations as $affectation) {
            [$nom, $bancaire] = self::groupePaiement($affectation->getSalarie());
            $groupes[$nom] = [!$bancaire, $nom];
        }
        asort($groupes);

        return array_keys($groupes);
    }

    /** 1234565 XOF →« un million deux cent trente-quatre mille cinq cent soixante-cinq francs CFA (BCEAO) ». */
    public static function enLettres(string $montant, string $devise): string
    {
        $lettres = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);
        [$entier, $centimes] = explode('.', bcadd($montant, '0', 2));
        $nomDevise = Currencies::exists($devise) ? Currencies::getName($devise, 'fr') : $devise;
        // « euro » → « euros », « franc CFA (BCEAO) » → « francs CFA (BCEAO) ».
        if ((int) $entier > 1) {
            $nomDevise = preg_replace('/^(\p{L}+)(?<!s)(?=\s|$)/u', '$1s', $nomDevise);
        }
        $texte = $lettres->format((int) $entier).' '.$nomDevise;
        if ((int) $centimes > 0) {
            $texte .= ' et '.$lettres->format((int) $centimes).' centime'.((int) $centimes > 1 ? 's' : '');
        }

        return $texte;
    }

    private function estNational(Affectation $affectation, Pays $pays): bool
    {
        // Même règle que le calcul de la paie : nationalité non renseignée = nationale.
        $nationalite = $affectation->getSalarie()->getNationalite();

        return null === $nationalite || $nationalite === $pays->getCodeIso();
    }

    private static function contientProvisoire(array $bulletins): bool
    {
        return [] !== array_filter($bulletins, static fn (array $b) => $b['provisoire']);
    }

    /** « 210 » + « 410 » → « 210 / 410 » (part salariale et patronale d'une même cotisation). */
    private static function fusionnerCodes(string $codes, ?string $code): string
    {
        $liste = '' === $codes ? [] : explode(' / ', $codes);
        foreach (null === $code || '' === $code ? [] : explode(' / ', $code) as $c) {
            if (!in_array($c, $liste, true)) {
                $liste[] = $c;
            }
        }

        return implode(' / ', $liste);
    }
}
