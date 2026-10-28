<?php

namespace App\Form;

use App\Entity\Cotisation;
use App\Enum\AssietteCotisation;
use App\Enum\CategorieCotisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CotisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nombre = static fn (string $label, ?string $help = null, int $scale = 2) => ['label' => $label, 'help' => $help, 'required' => false, 'input' => 'string', 'scale' => $scale, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0]];

        $builder
            ->add('libelle', TextType::class, ['label' => 'Libellé', 'attr' => ['placeholder' => 'C.N.P.S / Caisse de retraite']])
            ->add('categorie', EnumType::class, ['class' => CategorieCotisation::class, 'label' => 'Catégorie (charges patronales)', 'choice_label' => static fn (CategorieCotisation $c) => $c->libelle()])
            ->add('assiette', EnumType::class, ['class' => AssietteCotisation::class, 'label' => 'Assiette', 'choice_label' => static fn (AssietteCotisation $a) => $a->libelle()])
            ->add('plafond', NumberType::class, $nombre('Plafond de l\'assiette', 'Vide = pas de plafond (ex. 75 000 pour les prestations familiales en Côte d\'Ivoire).'))
            ->add('forfaitUnitaire', NumberType::class, $nombre('Montant par personne', 'Assiette « forfait par personne » uniquement (ex. CMU : 1 000).'))
            ->add('tauxSalarial', NumberType::class, $nombre('Taux salarial (%)', 'Vide = pas de retenue sur le salarié.', 4))
            ->add('codeSalarial', TextType::class, ['label' => 'Code bulletin (part salariale)', 'required' => false, 'attr' => ['class' => 'font-monospace']])
            ->add('tauxPatronal', NumberType::class, $nombre('Taux patronal (%)', 'Vide = pas de charge employeur.', 4))
            ->add('codePatronal', TextType::class, ['label' => 'Code bulletin (part patronale)', 'required' => false, 'attr' => ['class' => 'font-monospace']])
            ->add('tauxPatronalNonNational', NumberType::class, $nombre('Taux patronal — non-nationaux (%)', 'Vide = même taux pour tous (ex. impôt employeur CI : 12 % pour les non-Ivoiriens).', 4))
            ->add('tauxPatronalSociete', CheckboxType::class, ['label' => 'Utiliser le taux accident du travail de la société (s\'il est renseigné)', 'required' => false])
            ->add('ordre', IntegerType::class, ['label' => 'Ordre d\'affichage', 'required' => false])
            ->add('actif', CheckboxType::class, ['label' => 'Cotisation active', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Cotisation::class]);
    }
}
