<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\Societe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ajout d'un utilisateur dans une société. Si l'e-mail correspond déjà à un
 * compte, celui-ci est simplement rattaché ; sinon un compte est créé.
 */
class NouveauMembreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Societe $societe */
        $societe = $options['societe'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
                'help' => 'Si un compte existe déjà avec cet e-mail, il sera simplement ajouté à la société.',
            ])
            ->add('prenoms', TextType::class, ['label' => 'Prénom(s)', 'required' => false])
            ->add('nom', TextType::class, ['label' => 'Nom', 'required' => false])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'label' => 'Rôle dans la société',
                'choices' => $societe->getRoles()->toArray(),
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir un rôle…',
                'constraints' => [new Assert\NotNull(message: 'Choisissez un rôle.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('societe');
        $resolver->setAllowedTypes('societe', Societe::class);
    }
}
