<?php

namespace App\Form;

use App\Entity\Pays;
use App\Entity\Societe;
use App\Entity\Succursale;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use App\Entity\SecteurActivite;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocieteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nouvelle = null === ($options['data'] ?? null)?->getId();

        $builder
            ->add('raisonSociale', TextType::class, ['label' => 'Raison sociale'])
            ->add('pays', EntityType::class, [
                'class' => Pays::class,
                'label' => 'Pays',
                'placeholder' => 'Choisir un pays…',
                'choice_label' => static fn (Pays $p) => $p->getDrapeau().' '.$p->getNom().' ('.$p->getDevise().')',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('p')->where('p.actif = true')->orderBy('p.nom'),
                'disabled' => !$options['modifier_pays'],
            ])
            // Obligatoire à la création : on décide dès le départ selon quelle paie la société fonctionne.
            ->add('legislationPaie', EntityType::class, [
                'class' => Pays::class,
                'label' => 'Paie appliquée (législation)',
                'required' => $nouvelle,
                'placeholder' => $nouvelle ? 'Choisir le pays dont la paie s\'applique…' : 'Celle du pays de la société',
                'constraints' => $nouvelle ? [new Assert\NotNull(message: 'Choisissez la paie appliquée à la société.')] : [],
                'help' => 'Paramètres, cotisations, rubriques et secteurs de ce pays (ex. la paie ivoirienne pour une société au Sénégal). '
                    .'Un changement ultérieur rattache les avantages des salariés aux rubriques de même code du nouveau pays.',
                'choice_label' => static fn (Pays $p) => $p->getDrapeau().' '.$p->getNom(),
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('p')->where('p.actif = true')->orderBy('p.nom'),
                'disabled' => !($options['modifier_legislation'] ?? $options['modifier_pays']),
            ])
            ->add('devise', CurrencyType::class, [
                'label' => 'Devise de paie',
                'required' => false,
                'placeholder' => 'Celle du pays',
                'help' => 'Laisser vide pour utiliser la devise du pays.',
            ])
            ->add('adresse', TextType::class, ['label' => 'Adresse / siège', 'required' => false])
            ->add('ville', TextType::class, ['label' => 'Ville', 'required' => false])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false])
            ->add('numeroEmployeur', TextType::class, ['label' => 'N° employeur (sécurité sociale)', 'required' => false])
            ->add('numeroFiscal', TextType::class, ['label' => 'Identifiant fiscal', 'required' => false])
            ->add('registreCommerce', TextType::class, ['label' => 'Registre du commerce', 'required' => false])
            ->add('secteurActivite', EntityType::class, [
                'class' => SecteurActivite::class,
                'label' => 'Secteur d\'activité',
                'required' => false,
                'placeholder' => 'Non renseigné (taux AT du pays)',
                'help' => 'Fixe le taux de risque accident du travail (Côte d\'Ivoire : 2 à 5 %).',
                // Secteurs actifs, regroupés par pays : la validation vérifie qu'il correspond au pays choisi.
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('s')->join('s.pays', 'p')->addSelect('p')->where('s.actif = true')->orderBy('p.nom')->addOrderBy('s.libelle'),
                'group_by' => static fn (SecteurActivite $s) => $s->getPays()->getNom(),
            ])
            ->add('dateCreation', DateType::class, ['label' => 'Date de création', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
        ;

        // Logo imprimé sur les bulletins : traité par App\Service\LogoSociete.
        $builder
            ->add('logoFichier', FileType::class, [
                'label' => 'Logo de la société',
                'mapped' => false,
                'required' => false,
                'help' => 'JPG, PNG ou WebP, 2 Mo maximum. Il figurera en en-tête de chaque bulletin de paie.',
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                'constraints' => [new Assert\Image(
                    maxSize: '2M',
                    mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                    mimeTypesMessage: 'Formats acceptés : JPG, PNG ou WebP.',
                    maxSizeMessage: 'Le logo ne doit pas dépasser 2 Mo.',
                    minWidth: 80,
                    minWidthMessage: 'Le logo est trop petit (80 px de large minimum) : il serait flou sur les bulletins.',
                )],
            ])
            ->add('supprimerLogo', CheckboxType::class, ['label' => 'Retirer le logo actuel', 'mapped' => false, 'required' => false]);

        if ($options['avec_statut']) {
            $builder->add('active', CheckboxType::class, ['label' => 'Société active', 'required' => false]);
        }

        // Rattachement à une succursale : choisi par la plateforme uniquement.
        if (null !== $options['succursales']) {
            $builder->add('succursale', EntityType::class, [
                'class' => Succursale::class,
                'label' => 'Succursale',
                'choices' => $options['succursales'],
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une succursale…',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Societe::class,
            'modifier_pays' => true,
            // null = comme le pays
            'modifier_legislation' => null,
            'avec_statut' => false,
            'succursales' => null,
        ]);
        $resolver->setAllowedTypes('succursales', ['null', 'array']);
        $resolver->setAllowedTypes('modifier_pays', 'bool');
        $resolver->setAllowedTypes('modifier_legislation', ['null', 'bool']);
        $resolver->setAllowedTypes('avec_statut', 'bool');
    }
}
