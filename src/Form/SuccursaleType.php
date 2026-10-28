<?php

namespace App\Form;

use App\Entity\Succursale;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuccursaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom de la succursale', 'attr' => ['placeholder' => 'Ex. Groupe EPSA Afrique de l\'Ouest']])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
        ;

        if ($options['avec_statut']) {
            $builder->add('active', CheckboxType::class, ['label' => 'Succursale active', 'required' => false, 'help' => 'Désactivée, ses sociétés ne sont plus accessibles à ses administrateurs.']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Succursale::class,
            'avec_statut' => false,
        ]);
    }
}
