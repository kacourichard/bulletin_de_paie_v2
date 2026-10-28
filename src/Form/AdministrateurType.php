<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** Administrateur de succursale : compte existant (par e-mail) ou à créer. */
class AdministrateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
                'help' => 'Si un compte existe déjà avec cet e-mail, il sera réutilisé.',
            ])
            ->add('prenoms', TextType::class, ['label' => 'Prénom(s)', 'required' => false])
            ->add('nom', TextType::class, ['label' => 'Nom', 'required' => false])
        ;
    }
}
