<?php

namespace App\Form;

use App\Entity\Salarie;
use App\Entity\ZoneTransport;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Enum\ModePaiement;
use App\Enum\SituationFamiliale;
use App\Enum\Sexe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Identité, état civil, coordonnées et paiement du salarié. */
class SalarieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $libelle = static fn (?\UnitEnum $e) => $e?->libelle();

        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('prenoms', TextType::class, ['label' => 'Prénom(s)'])
            ->add('sexe', EnumType::class, ['class' => Sexe::class, 'label' => 'Sexe', 'required' => false, 'placeholder' => '—', 'choice_label' => $libelle])
            ->add('dateNaissance', DateType::class, ['label' => 'Date de naissance', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('lieuNaissance', TextType::class, ['label' => 'Lieu de naissance', 'required' => false])
            ->add('nationalite', CountryType::class, [
                'label' => 'Nationalité',
                'placeholder' => 'Sélectionner une nationalité',
                'preferred_choices' => $options['pays_prefere'] ? [$options['pays_prefere']] : [],
                'constraints' => [new Assert\NotBlank(message: 'Sélectionnez la nationalité du salarié.')],
            ])
            ->add('situationFamiliale', EnumType::class, [
                'class' => SituationFamiliale::class,
                'label' => 'Situation matrimoniale',
                'placeholder' => 'Sélectionner une situation',
                'choice_label' => $libelle,
                'help' => 'Utile au calcul de l\'impôt (nombre de parts).',
                'constraints' => [new Assert\NotNull(message: 'Sélectionnez la situation matrimoniale.')],
            ])
            ->add('nombreEnfants', IntegerType::class, ['label' => 'Nombre d\'enfants à charge', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 20]])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false, 'help' => 'Pour l\'envoi des bulletins de paie.'])
            ->add('adresse', TextType::class, ['label' => 'Adresse', 'required' => false])
        ;
        // Pays de paie avec un barème de transport par zone : ville de résidence obligatoire.
        if ($options['zones_transport']) {
            $builder->add('zoneTransport', EntityType::class, [
                'class' => ZoneTransport::class,
                'choices' => $options['zones_transport'],
                'label' => 'Ville de résidence (transport)',
                'placeholder' => 'Sélectionner la zone',
                'choice_label' => static fn (ZoneTransport $z) => sprintf('%s — %s', $z->getLibelle(), number_format((float) $z->getMontant(), 0, ',', ' ')),
                'help' => 'Fixe l\'indemnité de transport mensuelle.',
                'constraints' => [new Assert\NotNull(message: 'Sélectionnez la ville de résidence (indemnité de transport).')],
            ]);
        }
        $builder
            ->add('numeroPiece', TextType::class, ['label' => 'N° pièce d\'identité', 'required' => false])
            ->add('numeroSecuriteSociale', TextType::class, ['label' => $options['organisme_social'] ? 'N° '.$options['organisme_social'] : 'N° sécurité sociale', 'required' => false])
            ->add('numeroAssuranceMaladie', TextType::class, ['label' => 'N° assurance maladie (CMU…)', 'required' => false])
            ->add('modePaiement', EnumType::class, ['class' => ModePaiement::class, 'label' => 'Mode de paiement', 'choice_label' => $libelle])
            ->add('banque', TextType::class, ['label' => 'Banque / opérateur', 'required' => false])
            ->add('numeroCompte', TextType::class, ['label' => 'N° de compte / RIB / téléphone', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Salarie::class,
            'pays_prefere' => null,
            'organisme_social' => null,
            'zones_transport' => [],
        ]);
    }
}
