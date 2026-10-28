<?php

namespace App\Form;

use App\Entity\Rubrique;
use App\Entity\ZoneTransport;
use App\Enum\TypeRubrique;
use App\Form\Type\MontantType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ZoneTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $pays = $options['data']->getPays();
        $builder
            ->add('libelle', TextType::class, ['label' => 'Ville ou zone de résidence', 'attr' => ['placeholder' => 'Abidjan']])
            ->add('montant', MontantType::class, ['label' => 'Indemnité mensuelle', 'devise' => $pays->getDevise()])
            ->add('rubrique', EntityType::class, [
                'class' => Rubrique::class,
                'label' => 'Rubrique du bulletin',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('r')
                    ->where('r.pays = :pays')->andWhere('r.type = :gain')->andWhere('r.actif = true')
                    ->setParameter('pays', $pays)->setParameter('gain', TypeRubrique::GAIN)->orderBy('r.code'),
                'choice_label' => static fn (Rubrique $r) => sprintf('%s — %s', $r->getCode(), $r->getLibelle()),
            ])
            ->add('ordre', IntegerType::class, ['label' => 'Ordre d\'affichage', 'required' => false, 'empty_data' => '0'])
            ->add('actif', CheckboxType::class, ['label' => 'Zone proposée aux salariés', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ZoneTransport::class]);
    }
}
