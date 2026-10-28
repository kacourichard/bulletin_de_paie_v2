<?php

namespace App\Service;

use App\Entity\Societe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Logo des sociétés, imprimé sur les bulletins de paie.
 * Fichiers stockés dans public/uploads/logos, redimensionnés pour rester légers dans les PDF.
 */
class LogoSociete
{
    public const DOSSIER_PUBLIC = 'uploads/logos';
    private const LARGEUR_MAX = 600;
    private const HAUTEUR_MAX = 300;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $dossierPublic,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Applique les champs « logoFichier » / « supprimerLogo » d'un SocieteType soumis et valide.
     * Ne fait pas de flush.
     */
    public function traiterFormulaire(FormInterface $form, Societe $societe): void
    {
        if (!$form->has('logoFichier')) {
            return;
        }

        $fichier = $form->get('logoFichier')->getData();
        if ($fichier instanceof UploadedFile) {
            $this->enregistrer($societe, $fichier);
        } elseif ($form->has('supprimerLogo') && $form->get('supprimerLogo')->getData()) {
            $this->supprimer($societe);
        }
    }

    public function enregistrer(Societe $societe, UploadedFile $fichier): void
    {
        $extension = match ($fichier->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $nom = sprintf('logo-%s.%s', bin2hex(random_bytes(8)), $extension);
        $dossier = $this->dossierPublic.'/'.self::DOSSIER_PUBLIC;
        $this->filesystem->mkdir($dossier);

        $fichier->move($dossier, $nom);
        $this->redimensionner($dossier.'/'.$nom, $extension);

        $this->supprimer($societe);
        $societe->setLogo($nom);
    }

    public function supprimer(Societe $societe): void
    {
        if ($societe->getLogo()) {
            $this->filesystem->remove($this->cheminAbsolu($societe));
            $societe->setLogo(null);
        }
    }

    /** Chemin web relatif à public/ (pour asset()). */
    public function cheminPublic(Societe $societe): ?string
    {
        return $societe->getLogo() ? self::DOSSIER_PUBLIC.'/'.$societe->getLogo() : null;
    }

    /** Chemin sur disque (pour la génération des PDF). */
    public function cheminAbsolu(Societe $societe): ?string
    {
        return $societe->getLogo() ? $this->dossierPublic.'/'.self::DOSSIER_PUBLIC.'/'.$societe->getLogo() : null;
    }

    /** Réduit l'image si elle dépasse la taille utile pour un en-tête de bulletin (transparence conservée). */
    private function redimensionner(string $chemin, string $extension): void
    {
        [$largeur, $hauteur] = getimagesize($chemin) ?: [0, 0];
        $ratio = min(self::LARGEUR_MAX / max(1, $largeur), self::HAUTEUR_MAX / max(1, $hauteur), 1);
        if ($ratio >= 1) {
            return;
        }

        $source = match ($extension) {
            'png' => imagecreatefrompng($chemin),
            'webp' => imagecreatefromwebp($chemin),
            default => imagecreatefromjpeg($chemin),
        };
        if (false === $source) {
            return;
        }

        $l = (int) round($largeur * $ratio);
        $h = (int) round($hauteur * $ratio);
        $cible = imagecreatetruecolor($l, $h);
        imagealphablending($cible, false);
        imagesavealpha($cible, true);
        imagefill($cible, 0, 0, imagecolorallocatealpha($cible, 255, 255, 255, 127));
        imagecopyresampled($cible, $source, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);

        match ($extension) {
            'png' => imagepng($cible, $chemin, 8),
            'webp' => imagewebp($cible, $chemin, 90),
            default => imagejpeg($cible, $chemin, 90),
        };
    }
}
