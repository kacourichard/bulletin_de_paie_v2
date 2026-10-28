<?php

namespace App\Form;

use App\Enum\MotifSortie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** Fin de l'affectation dans la société (hors transfert). */
class SortieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, ['label' => 'Date de sortie', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'help' => 'Dernier jour travaillé.', 'constraints' => [new Assert\NotNull()]])
            ->add('motif', EnumType::class, [
                'class' => MotifSortie::class,
                'label' => 'Motif',
                'choices' => array_filter(MotifSortie::cases(), static fn (MotifSortie $m) => MotifSortie::TRANSFERT !== $m),
                'choice_label' => static fn (MotifSortie $m) => $m->libelle(),
                'placeholder' => 'Choisir…',
                'constraints' => [new Assert\NotNull(message: 'Choisissez le motif.')],
            ])
            ->add('commentaire', TextType::class, ['label' => 'Commentaire', 'required' => false])
        ;
    }
}
