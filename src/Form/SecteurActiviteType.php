<?php

namespace App\Form;

use App\Entity\SecteurActivite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecteurActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, ['label' => 'Secteur d\'activité', 'attr' => ['placeholder' => 'BÂTIMENT - TRAVAUX PUBLICS']])
            ->add('tauxRisque', NumberType::class, ['label' => 'Taux de risque accident du travail (%)', 'input' => 'string', 'scale' => 4, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0, 'max' => 100]])
            ->add('actif', CheckboxType::class, ['label' => 'Secteur proposé aux sociétés', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SecteurActivite::class]);
    }
}
