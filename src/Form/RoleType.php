<?php

namespace App\Form;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public const COULEURS = [
        'Gris' => 'secondary',
        'Bleu' => 'primary',
        'Turquoise' => 'info',
        'Vert' => 'success',
        'Orange' => 'warning',
        'Rouge' => 'danger',
        'Noir' => 'dark',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, ['label' => 'Nom du rôle', 'attr' => ['placeholder' => 'Ex. Chef de paie']])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'disabled' => $options['code_verrouille'],
                'attr' => ['placeholder' => 'CHEF_PAIE', 'class' => 'text-uppercase font-monospace'],
            ])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
            ->add('couleur', ChoiceType::class, ['label' => 'Couleur du badge', 'choices' => self::COULEURS])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => 'libelle',
                'choice_attr' => static fn (Permission $p) => ['data-module' => $p->getModule(), 'data-code' => $p->getCode()],
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('p')->orderBy('p.ordre'),
                'by_reference' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'code_verrouille' => false,
        ]);
    }
}
