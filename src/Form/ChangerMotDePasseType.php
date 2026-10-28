<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

class ChangerMotDePasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actuel', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'constraints' => [new UserPassword(message: 'Mot de passe actuel incorrect.')],
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('nouveau', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password'], 'help' => '10 caractères minimum.'],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 10, max: 4096, minMessage: 'Au moins {{ limit }} caractères.'),
                    new Assert\NotCompromisedPassword(skipOnError: true),
                ],
            ])
        ;
    }
}
