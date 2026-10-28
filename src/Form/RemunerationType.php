<?php

namespace App\Form;

use App\Entity\Remuneration;
use App\Enum\MotifRemuneration;
use App\Form\Type\MontantType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Nouvelle ligne de l'historique des salaires (revalorisation, promotion...). */
class RemunerationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateEffet', DateType::class, ['label' => 'Date d\'effet', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('motif', EnumType::class, [
                'class' => MotifRemuneration::class,
                'label' => 'Motif',
                'choices' => [MotifRemuneration::REVALORISATION, MotifRemuneration::PROMOTION, MotifRemuneration::AJUSTEMENT],
                'choice_label' => static fn (MotifRemuneration $m) => $m->libelle(),
            ])
            ->add('salaireBase', MontantType::class, ['label' => 'Nouveau salaire de base', 'devise' => $options['devise']])
            ->add('sursalaire', MontantType::class, ['label' => 'Nouveau sursalaire', 'devise' => $options['devise'], 'required' => false, 'empty_data' => '0'])
            ->add('commentaire', TextType::class, ['label' => 'Commentaire', 'required' => false, 'attr' => ['placeholder' => 'Ex. Revalorisation annuelle, avenant n°2…']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Remuneration::class,
            'devise' => null,
        ]);
    }
}
