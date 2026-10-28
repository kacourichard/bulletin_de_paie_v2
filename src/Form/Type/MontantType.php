<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Montant décimal (stocké en chaîne pour éviter les erreurs d'arrondi),
 * affiché avec la devise en suffixe (voir templates/form/theme.html.twig).
 */
class MontantType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'devise' => null,
            'input' => 'string',
            'scale' => 2,
            'html5' => true,
            'attr' => ['step' => '0.01', 'min' => '0', 'inputmode' => 'decimal', 'class' => 'text-end'],
            'constraints' => [new Assert\PositiveOrZero()],
            'invalid_message' => 'Montant invalide.',
        ]);
        $resolver->setAllowedTypes('devise', ['null', 'string']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['devise'] = $options['devise'];
    }

    public function getParent(): string
    {
        return NumberType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'montant';
    }
}
