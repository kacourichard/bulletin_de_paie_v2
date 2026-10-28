<?php

namespace App\Form;

use App\Entity\Affectation;
use App\Enum\TypeContrat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Poste et contrat du salarié dans la société. */
class AffectationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matricule', TextType::class, [
                'label' => 'Matricule',
                'required' => $options['edition'],
                'attr' => ['placeholder' => $options['matricule_propose'], 'class' => 'text-uppercase font-monospace'],
                'help' => $options['edition'] ? null : 'Laisser vide pour attribuer '.$options['matricule_propose'].'.',
                'constraints' => $options['edition'] ? [new Assert\NotBlank(message: 'Le matricule est obligatoire.')] : [],
            ])
            ->add('metier', TextType::class, ['label' => 'Emploi / métier', 'required' => false, 'help' => 'Le métier ou l\'emploi du salarié (ex. Comptabilité).'])
            ->add('poste', TextType::class, [
                'label' => 'Fonction',
                'required' => !$options['edition'],
                'constraints' => $options['edition'] ? [] : [new Assert\NotBlank(message: 'Indiquez la fonction du salarié.')],
                'help' => 'La fonction occupée (ex. Chef comptable).',
            ])
            ->add('service', TextType::class, ['label' => 'Service / département', 'required' => false])
            ->add('categorie', TextType::class, ['label' => 'Catégorie', 'required' => false, 'help' => 'Catégorie de la convention collective.'])
            ->add('echelon', TextType::class, ['label' => 'Échelon', 'required' => false])
            ->add('site', TextType::class, ['label' => 'Ville / lieu de travail', 'required' => false, 'help' => 'Ville où le salarié exerce.'])
            ->add('typeContrat', EnumType::class, ['class' => TypeContrat::class, 'label' => 'Type de contrat', 'choice_label' => static fn (TypeContrat $t) => $t->libelle()])
            ->add('dateEntree', DateType::class, [
                'label' => 'Date d\'embauche',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                // L'historique des salaires part de cette date : non modifiable ensuite.
                'disabled' => $options['edition'],
            ])
            ->add('dateFinContrat', DateType::class, ['label' => 'Fin de contrat prévue', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'help' => 'CDD, stage…'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Affectation::class,
            'edition' => false,
            'matricule_propose' => null,
        ]);
    }
}
