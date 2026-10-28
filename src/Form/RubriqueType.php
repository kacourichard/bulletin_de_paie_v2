<?php

namespace App\Form;

use App\Entity\Rubrique;
use App\Enum\SectionBulletin;
use App\Enum\TraitementFiscal;
use App\Enum\TraitementSocial;
use App\Enum\TypeRubrique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RubriqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $libelle = static fn (\UnitEnum $e) => $e->libelle();

        $builder
            ->add('code', TextType::class, ['label' => 'Code', 'attr' => ['class' => 'font-monospace text-uppercase', 'placeholder' => '085']])
            ->add('libelle', TextType::class, ['label' => 'Libellé', 'attr' => ['placeholder' => 'Indemnité de logement']])
            ->add('type', EnumType::class, ['class' => TypeRubrique::class, 'label' => 'Gain / retenue', 'choice_label' => $libelle])
            ->add('section', EnumType::class, ['class' => SectionBulletin::class, 'label' => 'Position sur le bulletin', 'choice_label' => $libelle])
            ->add('traitementSocial', EnumType::class, ['class' => TraitementSocial::class, 'label' => 'Traitement social', 'choice_label' => $libelle])
            ->add('traitementFiscal', EnumType::class, ['class' => TraitementFiscal::class, 'label' => 'Traitement fiscal', 'choice_label' => $libelle])
            ->add('plafondExoneration', NumberType::class, [
                'label' => 'Plafond d\'exonération (% du brut)',
                'required' => false,
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => 0, 'max' => 100, 'placeholder' => '10'],
                'help' => 'Seulement pour « Exonéré dans une limite du brut ».',
            ])
            ->add('coefficientHoraire', NumberType::class, [
                'label' => 'Coefficient horaire (heures supplémentaires)',
                'required' => false,
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => 0, 'placeholder' => 'ex. 1,15'],
                'help' => 'Renseigné : on saisit des heures, le taux = taux horaire du salarié × coefficient.',
            ])
            ->add('elementFixe', CheckboxType::class, ['label' => 'Peut être attribuée comme élément fixe d\'un salarié (gain ou retenue de chaque mois)', 'required' => false])
            ->add('proratisee', CheckboxType::class, ['label' => 'Élément fixe au prorata des jours travaillés', 'required' => false, 'help' => 'Décocher pour un montant dû en entier (ex. cotisation syndicale). Les éléments variables du mois ne sont jamais proratisés.'])
            ->add('elementVariable', CheckboxType::class, ['label' => 'Peut être saisie comme élément variable du mois', 'required' => false])
            ->add('actif', CheckboxType::class, ['label' => 'Rubrique active', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Rubrique::class]);
    }
}
