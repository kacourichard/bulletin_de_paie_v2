<?php

namespace App\Form;

use App\Entity\Avantage;
use App\Entity\Rubrique;
use App\Form\Type\MontantType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Avantage d'un salarié, rattaché à une rubrique du référentiel du pays. */
class AvantageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rubrique', EntityType::class, [
                'class' => Rubrique::class,
                'label' => 'Rubrique',
                'choices' => $options['rubriques'],
                'choice_label' => static fn (Rubrique $r) => sprintf('%s — %s%s', $r->getCode(), $r->getLibelle(), $r->isProratisee() ? '' : ' (sans prorata)'),
                'group_by' => static fn (Rubrique $r) => $r->isGain() ? 'Gains (primes, indemnités)' : 'Retenues fixes',
                'choice_attr' => static fn (Rubrique $r) => ['data-fiscal' => $r->getTraitementFiscalDetaille(), 'data-social' => $r->getTraitementSocial()->libelle()],
                'placeholder' => 'Choisir une rubrique…',
            ])
            ->add('libelleSpecifique', TextType::class, [
                'label' => 'Libellé sur le bulletin',
                'required' => false,
                'attr' => ['placeholder' => 'Celui de la rubrique'],
            ])
            ->add('montant', MontantType::class, [
                'label' => 'Montant mensuel',
                'devise' => $options['devise'],
                'constraints' => [new Assert\NotBlank(), new Assert\Positive(message: 'Le montant doit être supérieur à zéro.')],
            ])
            ->add('enNature', CheckboxType::class, ['label' => 'En nature', 'required' => false])
        ;

        if ($options['avec_date']) {
            $builder
                ->add('dateDebut', DateType::class, ['label' => 'À partir du', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
                ->add('commentaire', TextType::class, ['label' => 'Commentaire', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avantage::class,
            'devise' => null,
            'avec_date' => true,
            'rubriques' => [],
        ]);
        $resolver->setAllowedTypes('rubriques', 'array');
    }
}
