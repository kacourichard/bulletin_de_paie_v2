<?php

namespace App\Form;

use App\Entity\ParametrePaie;
use App\Enum\ArrondiNet;
use App\Enum\BaseAnciennete;
use App\Enum\ModeExoneration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParametrePaieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taux = static fn (string $label, ?string $help = null) => ['label' => $label, 'help' => $help, 'input' => 'string', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.01', 'min' => 0, 'max' => 100]];
        $case = static fn (string $label, ?string $help = null) => ['label' => $label, 'help' => $help, 'required' => false];

        $builder
            // Prime d'ancienneté
            ->add('ancienneteActive', CheckboxType::class, $case('Le pays prévoit une prime d\'ancienneté'))
            ->add('ancienneteAnneesMin', IntegerType::class, ['label' => 'À partir de (années révolues)', 'attr' => ['min' => 0]])
            ->add('ancienneteTauxInitial', NumberType::class, $taux('Taux à cette ancienneté (%)'))
            ->add('ancienneteTauxParAn', NumberType::class, $taux('Taux ajouté par année supplémentaire (%)'))
            ->add('ancienneteTauxMax', NumberType::class, $taux('Taux maximum (%)'))
            ->add('ancienneteBase', EnumType::class, ['class' => BaseAnciennete::class, 'label' => 'Base de calcul', 'choice_label' => static fn (BaseAnciennete $b) => $b->libelle()])
            ->add('ancienneteDepuisGroupe', CheckboxType::class, $case('Compter l\'ancienneté depuis l\'entrée dans le groupe (transferts entre sociétés)'))
            ->add('ancienneteCode', TextType::class, ['label' => 'Code sur le bulletin', 'attr' => ['class' => 'font-monospace text-uppercase']])
            // Temps de travail
            ->add('joursMois', IntegerType::class, ['label' => 'Jours de base d\'un mois complet', 'attr' => ['min' => 1, 'max' => 31], 'help' => 'Prorata = jours travaillés / jours de base (30 en Côte d\'Ivoire).'])
            ->add('proratiserFixes', CheckboxType::class, $case('Proratiser salaire, sursalaire et avantages selon les jours travaillés'))
            ->add('joursCongeParMois', NumberType::class, ['label' => 'Jours de congé acquis par mois', 'input' => 'string', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.01', 'min' => 0], 'help' => 'Congé acquis du bulletin = jours depuis le dernier congé × ce nombre / jours de base (2,57 en Côte d\'Ivoire).'])
            ->add('heuresMois',NumberType::class, ['label' => 'Heures mensuelles légales', 'input' => 'string', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.01', 'min' => 1], 'help' => 'Taux horaire = (salaire catégoriel + sursalaire) / heures (173,33 en Côte d\'Ivoire).'])
            // Nombre de parts
            ->add('partsActives', CheckboxType::class, $case('Le pays utilise un nombre de parts (quotient familial) pour l\'impôt'))
            // Salaire brut
            ->add('brutInclutSursalaire', CheckboxType::class, $case('Sursalaire'))
            ->add('brutInclutAnciennete', CheckboxType::class, $case('Prime d\'ancienneté'))
            ->add('brutInclutGainsSoumis', CheckboxType::class, $case('Gains « soumis » (position 1 du bulletin)'))
            ->add('brutInclutGainsNonSoumis', CheckboxType::class, $case('Gains « non soumis » (position 3 du bulletin)'))
            // Brut imposable
            ->add('modeExoneration', EnumType::class, [
                'class' => ModeExoneration::class,
                'label' => 'Indemnités « exonérées dans une limite du brut »',
                'expanded' => true,
                'choice_label' => static fn (ModeExoneration $m) => $m->libelle(),
            ])
            // Impôt sur le salaire (barème et réductions saisis dans des grilles sur la page)
            ->add('impotActif', CheckboxType::class, $case('Retenir un impôt sur le salaire (barème progressif par tranches)'))
            ->add('impotLibelle', TextType::class, ['label' => 'Libellé sur le bulletin', 'attr' => ['placeholder' => 'Retenue ITS']])
            ->add('impotCode', TextType::class, ['label' => 'Code', 'attr' => ['class' => 'font-monospace']])
            ->add('impotAssiette', EnumType::class, ['class' => \App\Enum\AssietteCotisation::class, 'label' => 'Assiette', 'choices' => [\App\Enum\AssietteCotisation::BRUT_IMPOSABLE, \App\Enum\AssietteCotisation::BRUT], 'choice_label' => static fn ($a) => $a->libelle()])
            // Codes des lignes de synthèse
            ->add('codeSalaireBase', TextType::class, ['label' => 'Salaire de base', 'attr' => ['class' => 'font-monospace']])
            ->add('codeBrut', TextType::class, ['label' => 'Salaire brut', 'attr' => ['class' => 'font-monospace']])
            ->add('codeBrutImposable', TextType::class, ['label' => 'Brut imposable', 'attr' => ['class' => 'font-monospace']])
            ->add('codeSalaireNet', TextType::class, ['label' => 'Salaire net', 'attr' => ['class' => 'font-monospace']])
            ->add('codeArrondi', TextType::class, ['label' => 'Arrondi du net', 'attr' => ['class' => 'font-monospace']])
            ->add('decimales', ChoiceType::class, ['label' => 'Arrondi des montants', 'choices' => ['À l\'unité (F CFA…)' => 0, 'Au dixième' => 1, 'Au centime (euro…)' => 2]])
            ->add('arrondiNet', EnumType::class, ['class' => ArrondiNet::class, 'label' => 'Arrondi du net à payer', 'choice_label' => static fn (ArrondiNet $a) => $a->libelle()])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ParametrePaie::class]);
    }
}
