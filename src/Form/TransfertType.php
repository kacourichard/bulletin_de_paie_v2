<?php

namespace App\Form;

use App\Entity\Societe;
use App\Enum\TypeContrat;
use App\Form\Type\MontantType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Transfert d'un salarié vers une autre société. */
class TransfertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('societe', ChoiceType::class, [
                'label' => 'Société de destination',
                'choices' => $options['societes'],
                'choice_value' => 'id',
                'choice_label' => static fn (Societe $s) => sprintf('%s %s — %s (%s)', $s->getPays()->getDrapeau(), $s->getRaisonSociale(), $s->getPays()->getNom(), $s->getDeviseEffective()),
                'placeholder' => 'Choisir…',
                'constraints' => [new Assert\NotNull(message: 'Choisissez la société de destination.')],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date du transfert',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Premier jour dans la nouvelle société. L\'affectation actuelle se termine la veille.',
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('matricule', TextType::class, ['label' => 'Nouveau matricule', 'required' => false, 'help' => 'Laisser vide pour une attribution automatique.', 'attr' => ['class' => 'text-uppercase font-monospace']])
            ->add('poste', TextType::class, ['label' => 'Poste', 'required' => false])
            ->add('typeContrat', EnumType::class, ['class' => TypeContrat::class, 'label' => 'Type de contrat', 'choice_label' => static fn (TypeContrat $t) => $t->libelle()])
            ->add('salaireBase', MontantType::class, ['label' => 'Salaire de base', 'help' => 'Dans la devise de la société de destination.', 'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()]])
            ->add('sursalaire', MontantType::class, ['label' => 'Sursalaire', 'required' => false, 'empty_data' => '0'])
            ->add('reprendreAvantages', CheckboxType::class, ['label' => 'Reprendre les avantages en cours', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('societes');
        $resolver->setAllowedTypes('societes', 'array');
    }
}
