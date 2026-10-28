<?php

namespace App\Form;

use App\Form\Type\MontantType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'embauche : identité + poste + salaire de départ + avantages fixes.
 * Données : ['salarie' => Salarie, 'affectation' => Affectation, 'avantages' => Avantage[]].
 */
class NouveauSalarieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('salarie', SalarieType::class, ['label' => false, 'pays_prefere' => $options['pays_prefere'], 'organisme_social' => $options['organisme_social'], 'zones_transport' => $options['zones_transport']])
            ->add('affectation', AffectationType::class, ['label' => false, 'matricule_propose' => $options['matricule_propose']])
            ->add('salaireBase', MontantType::class, [
                'label' => 'Salaire catégoriel (de base)',
                'help' => 'Salaire de base correspondant à la catégorie du salarié.',
                'devise' => $options['devise'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le salaire de base.'), new Assert\PositiveOrZero()],
            ])
            ->add('sursalaire', MontantType::class, ['label' => 'Sursalaire', 'devise' => $options['devise'], 'required' => false, 'empty_data' => '0'])
            ->add('avantages', CollectionType::class, [
                'label' => false,
                'entry_type' => AvantageType::class,
                'entry_options' => ['label' => false, 'devise' => $options['devise'], 'avec_date' => false, 'rubriques' => $options['rubriques']],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'devise' => null,
            'pays_prefere' => null,
            'matricule_propose' => null,
            'rubriques' => [],
            'organisme_social' => null,
            'zones_transport' => [],
        ]);
    }
}
