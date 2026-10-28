<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenoms', TextType::class, ['label' => 'Prénom(s)'])
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('langue', LanguageType::class, [
                'label' => 'Langue préférée',
                'required' => false,
                'placeholder' => 'Celle du pays de la société',
                'preferred_choices' => ['fr', 'en', 'es', 'pt', 'ar'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
