<?php

namespace App\Form;

use App\Entity\Pays;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaysType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codeIso', CountryType::class, [
                'label' => 'Pays',
                'placeholder' => 'Choisir un pays…',
                'help' => 'Le nom est renseigné automatiquement depuis la norme ISO.',
            ])
            ->add('nom', TextType::class, ['label' => 'Nom affiché', 'required' => false, 'help' => 'Laisser vide pour le nom officiel.'])
            ->add('devise', CurrencyType::class, ['label' => 'Devise de paie', 'placeholder' => 'Choisir…'])
            ->add('langue', LanguageType::class, ['label' => 'Langue', 'preferred_choices' => ['fr', 'en', 'es', 'pt', 'ar']])
            ->add('fuseauHoraire', TimezoneType::class, ['label' => 'Fuseau horaire'])
            ->add('indicatifTelephone', TextType::class, ['label' => 'Indicatif téléphonique', 'required' => false, 'attr' => ['placeholder' => '+225']])
            ->add('organismeSocial', TextType::class, ['label' => 'Organisme de sécurité sociale', 'required' => false, 'attr' => ['placeholder' => 'CNPS, URSSAF, CSS…']])
            ->add('actif', CheckboxType::class, ['label' => 'Pays ouvert à la création de sociétés', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Pays::class]);
    }
}
