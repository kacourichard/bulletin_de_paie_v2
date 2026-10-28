<?php

namespace App\Controller\Plateforme;

use App\Entity\BaremePart;
use App\Entity\Pays;
use App\Entity\ReductionImpot;
use App\Entity\TrancheImpot;
use App\Repository\CotisationRepository;
use App\Entity\User;
use App\Enum\SectionBulletin;
use App\Enum\SituationFamiliale;
use App\Enum\TraitementFiscal;
use App\Form\ParametrePaieType;
use App\Repository\ParametrePaieRepository;
use App\Service\CalculPaie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres de calcul de la paie d'un pays (super-administrateur).
 */
#[Route('/plateforme/pays/{pays}/parametres-paie')]
class ParametrePaieController extends AbstractController
{
    /** Nombre d'enfants affichés dans la grille des parts (au-delà : dernière valeur). */
    private const ENFANTS_MAX = 8;

    public function __construct(
        private readonly ParametrePaieRepository $parametres,
        private readonly EntityManagerInterface $em,
        private readonly CalculPaie $calcul,
        private readonly CotisationRepository $cotisations,
    ) {
    }

    #[Route('', name: 'app_plateforme_parametres_paie')]
    public function modifier(#[MapEntity(id: 'pays')] Pays $pays, Request $request): Response
    {
        $parametre = $this->parametres->pour($pays);
        $form = $this->createForm(ParametrePaieType::class, $parametre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->enregistrerGrille($pays, $request->getPayload()->all('parts'));
            $this->enregistrerImpot($pays, $request->getPayload()->all('tranches'), $request->getPayload()->all('reductions'));
            $user = $this->getUser();
            $parametre->setUpdatedBy($user instanceof User ? $user : null);
            $this->em->persist($parametre);
            $this->em->flush();
            $this->addFlash('success', sprintf('Paramètres de paie de %s enregistrés.', $pays->getNom()));

            return $this->redirectToRoute('app_plateforme_parametres_paie', ['pays' => $pays->getId()]);
        }

        return $this->render('plateforme/parametre_paie/modifier.html.twig', [
            'pays' => $pays,
            'parametre' => $parametre,
            'form' => $form,
            'grille' => $this->parametres->grilleParts($pays),
            'tranches' => $this->cotisations->tranches($pays),
            'reductions' => $this->cotisations->reductions($pays),
            'situations' => SituationFamiliale::cases(),
            'enfantsMax' => self::ENFANTS_MAX,
            'simulation' => $this->simuler($pays, $request),
        ]);
    }

    /**
     * Grille saisie : parts[SITUATION][nombre d'enfants] = valeur ; une case vide supprime la valeur.
     */
    private function enregistrerGrille(Pays $pays, array $saisie): void
    {
        $existants = [];
        foreach ($this->em->getRepository(BaremePart::class)->findBy(['pays' => $pays]) as $ligne) {
            $existants[$ligne->getSituation()->value][$ligne->getNombreEnfants()] = $ligne;
        }

        foreach (SituationFamiliale::cases() as $situation) {
            for ($n = 0; $n <= self::ENFANTS_MAX; ++$n) {
                $valeur = str_replace(',', '.', trim((string) ($saisie[$situation->value][$n] ?? '')));
                $ligne = $existants[$situation->value][$n] ?? null;
                if ('' === $valeur || !is_numeric($valeur)) {
                    if ($ligne) {
                        $this->em->remove($ligne);
                    }
                    continue;
                }
                $valeur = number_format((float) $valeur, 2, '.', '');
                $ligne ? $ligne->setParts($valeur) : $this->em->persist(new BaremePart($pays, $situation, $n, $valeur));
            }
        }
    }

    /**
     * Barème de l'impôt (tranches[i][de|a|taux]) et réductions selon les parts (reductions[i][parts|montant]) :
     * remplacés intégralement par les lignes saisies (lignes vides ignorées).
     */
    private function enregistrerImpot(Pays $pays, array $tranches, array $reductions): void
    {
        $nombre = static function (mixed $v): ?string {
            $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $v));

            return '' !== $v && is_numeric($v) ? number_format((float) $v, 3, '.', '') : null;
        };
        foreach ($this->cotisations->tranches($pays) as $t) {
            $this->em->remove($t);
        }
        foreach ($this->cotisations->reductions($pays) as $r) {
            $this->em->remove($r);
        }
        foreach ($tranches as $t) {
            $de = $nombre($t['de'] ?? '');
            $taux = $nombre($t['taux'] ?? '');
            if (null !== $de && null !== $taux) {
                $this->em->persist(new TrancheImpot($pays, $de, $nombre($t['a'] ?? ''), $taux));
            }
        }
        foreach ($reductions as $r) {
            $parts = $nombre($r['parts'] ?? '');
            $montant = $nombre($r['montant'] ?? '');
            if (null !== $parts && null !== $montant) {
                $this->em->persist(new ReductionImpot($pays, $parts, $montant));
            }
        }
    }

    /** Simulateur : calcule la première partie du bulletin avec les paramètres enregistrés. */
    private function simuler(Pays $pays, Request $request): array
    {
        $q = $request->query;
        $entree = [
            'salaireCategoriel' => $this->montant($q->get('categoriel'), '250000'),
            'sursalaire' => $this->montant($q->get('sursalaire'), '0'),
            'dateEmbauche' => \DateTimeImmutable::createFromFormat('!Y-m-d', $q->getString('embauche')) ?: new \DateTimeImmutable('-5 years'),
            'dateReference' => \DateTimeImmutable::createFromFormat('!Y-m-d', $q->getString('reference')) ?: new \DateTimeImmutable('last day of this month'),
            'situation' => SituationFamiliale::tryFrom($q->getString('situation')) ?? SituationFamiliale::MARIE,
            'enfants' => max(0, $q->getInt('enfants', 2)),
            'gains' => [],
        ];
        $exonerable = $this->montant($q->get('exonerable'), '50000');
        $soumis = $this->montant($q->get('soumis'), '0');
        $nonSoumis = $this->montant($q->get('nonsoumis'), '30000');
        if (bccomp($exonerable, '0', 2) > 0) {
            $entree['gains'][] = ['code' => null, 'libelle' => 'Indemnités exonérables (logement, représentation…)', 'montant' => $exonerable, 'section' => SectionBulletin::SOUMIS, 'fiscal' => TraitementFiscal::EXONERE_PLAFOND, 'plafond' => $this->montant($q->get('plafond'), '10')];
        }
        if (bccomp($soumis, '0', 2) > 0) {
            $entree['gains'][] = ['code' => null, 'libelle' => 'Autres gains soumis (primes…)', 'montant' => $soumis, 'section' => SectionBulletin::SOUMIS, 'fiscal' => TraitementFiscal::IMPOSABLE, 'plafond' => null];
        }
        if (bccomp($nonSoumis, '0', 2) > 0) {
            $entree['gains'][] = ['code' => null, 'libelle' => 'Gains non soumis (transport…)', 'montant' => $nonSoumis, 'section' => SectionBulletin::NON_SOUMIS, 'fiscal' => TraitementFiscal::NON_IMPOSABLE, 'plafond' => null];
        }

        return [
            'entree' => $entree + ['exonerable' => $exonerable, 'soumis' => $soumis, 'nonSoumis' => $nonSoumis, 'plafond' => $this->montant($q->get('plafond'), '10')],
            'resultat' => $this->calcul->calculer($this->parametres->pour($pays), $this->parametres->grilleParts($pays), $entree),
        ];
    }

    private function montant(mixed $valeur, string $defaut): string
    {
        $valeur = str_replace([' ', ','], ['', '.'], (string) $valeur);

        return is_numeric($valeur) && $valeur >= 0 ? number_format((float) $valeur, 2, '.', '') : $defaut;
    }
}
