<?php

namespace App\Security;

/**
 * Référentiel des actions de l'application et des rôles livrés par défaut
 * à chaque nouvelle société. Toute nouvelle fonctionnalité déclare ici ses
 * permissions, puis « php bin/console app:permissions:sync » les publie en base.
 */
final class PermissionCatalogue
{
    // Société
    public const SOCIETE_VOIR = 'societe.voir';
    public const SOCIETE_MODIFIER = 'societe.modifier';
    // Utilisateurs & rôles
    public const UTILISATEUR_VOIR = 'utilisateur.voir';
    public const UTILISATEUR_GERER = 'utilisateur.gerer';
    public const ROLE_GERER = 'role.gerer';
    // Salariés
    public const SALARIE_VOIR = 'salarie.voir';
    public const SALARIE_CREER = 'salarie.creer';
    public const SALARIE_MODIFIER = 'salarie.modifier';
    public const SALARIE_SUPPRIMER = 'salarie.supprimer';
    public const SALARIE_REMUNERATION = 'salarie.remuneration';
    public const SALARIE_TRANSFERER = 'salarie.transferer';
    // Paie
    public const PAIE_VOIR = 'paie.voir';
    public const PAIE_PREPARER = 'paie.preparer';
    public const PAIE_VALIDER = 'paie.valider';
    public const PAIE_CLOTURER = 'paie.cloturer';
    public const BULLETIN_IMPRIMER = 'bulletin.imprimer';
    public const BULLETIN_ENVOYER = 'bulletin.envoyer';
    // Trésorerie
    public const PAIEMENT_VOIR = 'paiement.voir';
    public const PAIEMENT_EXECUTER = 'paiement.executer';
    // États & déclarations
    public const ETAT_VOIR = 'etat.voir';
    public const ETAT_EXPORTER = 'etat.exporter';
    public const DECLARATION_GERER = 'declaration.gerer';
    // Paramétrage
    public const PARAMETRE_GERER = 'parametre.gerer';

    public const MODULES = [
        'societe' => ['libelle' => 'Société', 'icone' => 'building'],
        'utilisateur' => ['libelle' => 'Utilisateurs & rôles', 'icone' => 'people'],
        'salarie' => ['libelle' => 'Salariés', 'icone' => 'person-badge'],
        'paie' => ['libelle' => 'Paie & bulletins', 'icone' => 'receipt'],
        'tresorerie' => ['libelle' => 'Trésorerie', 'icone' => 'cash-coin'],
        'etat' => ['libelle' => 'États & déclarations', 'icone' => 'bar-chart'],
        'parametre' => ['libelle' => 'Paramétrage', 'icone' => 'sliders'],
    ];

    /** @return array<string, array{libelle: string, module: string}> */
    public static function permissions(): array
    {
        return [
            self::SOCIETE_VOIR => ['libelle' => 'Consulter la fiche société', 'module' => 'societe'],
            self::SOCIETE_MODIFIER => ['libelle' => 'Modifier les informations de la société', 'module' => 'societe'],

            self::UTILISATEUR_VOIR => ['libelle' => 'Voir les utilisateurs', 'module' => 'utilisateur'],
            self::UTILISATEUR_GERER => ['libelle' => 'Ajouter, modifier et désactiver des utilisateurs', 'module' => 'utilisateur'],
            self::ROLE_GERER => ['libelle' => 'Gérer les rôles et leurs permissions', 'module' => 'utilisateur'],

            self::SALARIE_VOIR => ['libelle' => 'Consulter les salariés', 'module' => 'salarie'],
            self::SALARIE_CREER => ['libelle' => 'Enregistrer un salarié', 'module' => 'salarie'],
            self::SALARIE_MODIFIER => ['libelle' => 'Modifier un salarié', 'module' => 'salarie'],
            self::SALARIE_SUPPRIMER => ['libelle' => 'Enregistrer la sortie d\'un salarié', 'module' => 'salarie'],
            self::SALARIE_REMUNERATION => ['libelle' => 'Voir et modifier salaires et avantages', 'module' => 'salarie'],
            self::SALARIE_TRANSFERER => ['libelle' => 'Transférer un salarié vers une autre société', 'module' => 'salarie'],

            self::PAIE_VOIR => ['libelle' => 'Consulter les fiches de paie', 'module' => 'paie'],
            self::PAIE_PREPARER => ['libelle' => 'Saisir et calculer la paie', 'module' => 'paie'],
            self::PAIE_VALIDER => ['libelle' => 'Valider la paie du mois', 'module' => 'paie'],
            self::PAIE_CLOTURER => ['libelle' => 'Clôturer une période de paie', 'module' => 'paie'],
            self::BULLETIN_IMPRIMER => ['libelle' => 'Imprimer / télécharger les bulletins', 'module' => 'paie'],
            self::BULLETIN_ENVOYER => ['libelle' => 'Envoyer les bulletins par e-mail', 'module' => 'paie'],

            self::PAIEMENT_VOIR => ['libelle' => 'Consulter les paiements de salaires', 'module' => 'tresorerie'],
            self::PAIEMENT_EXECUTER => ['libelle' => 'Exécuter / confirmer les paiements', 'module' => 'tresorerie'],

            self::ETAT_VOIR => ['libelle' => 'Consulter les états (livre de paie, masse salariale...)', 'module' => 'etat'],
            self::ETAT_EXPORTER => ['libelle' => 'Exporter les états (PDF, Excel)', 'module' => 'etat'],
            self::DECLARATION_GERER => ['libelle' => 'Préparer les déclarations sociales et fiscales', 'module' => 'etat'],

            self::PARAMETRE_GERER => ['libelle' => 'Gérer les rubriques et barèmes de paie', 'module' => 'parametre'],
        ];
    }

    /**
     * Rôles opérationnels créés automatiquement pour chaque nouvelle société.
     * L'administration (utilisateurs, rôles, création de sociétés) revient aux
     * administrateurs de la succursale, qui ont toutes les permissions.
     * 'permissions' => '*' signifie toutes les permissions.
     *
     * @return array<string, array{libelle: string, description: string, couleur: string, permissions: string[]|string}>
     */
    public static function rolesParDefaut(): array
    {
        return [
            'RH' => [
                'libelle' => 'Responsable RH',
                'description' => 'Gère le personnel, prépare et valide la paie du mois.',
                'couleur' => 'info',
                'permissions' => [
                    self::SOCIETE_VOIR, self::UTILISATEUR_VOIR,
                    self::SALARIE_VOIR, self::SALARIE_CREER, self::SALARIE_MODIFIER, self::SALARIE_SUPPRIMER,
                    self::SALARIE_REMUNERATION, self::SALARIE_TRANSFERER,
                    self::PAIE_VOIR, self::PAIE_PREPARER, self::PAIE_VALIDER, self::BULLETIN_IMPRIMER, self::BULLETIN_ENVOYER,
                    self::ETAT_VOIR,
                ],
            ],
            'COMPTABLE' => [
                'libelle' => 'Comptable',
                'description' => 'Calcule et valide la paie, produit les états et déclarations.',
                'couleur' => 'primary',
                'permissions' => [
                    self::SOCIETE_VOIR,
                    self::SALARIE_VOIR, self::SALARIE_REMUNERATION,
                    self::PAIE_VOIR, self::PAIE_PREPARER, self::PAIE_VALIDER, self::PAIE_CLOTURER,
                    self::BULLETIN_IMPRIMER, self::BULLETIN_ENVOYER,
                    self::PAIEMENT_VOIR,
                    self::ETAT_VOIR, self::ETAT_EXPORTER, self::DECLARATION_GERER,
                    self::PARAMETRE_GERER,
                ],
            ],
            'TRESORIER' => [
                'libelle' => 'Trésorier',
                'description' => 'Exécute le paiement des salaires validés.',
                'couleur' => 'success',
                'permissions' => [
                    self::SOCIETE_VOIR,
                    self::SALARIE_VOIR,
                    self::PAIE_VOIR,
                    self::PAIEMENT_VOIR, self::PAIEMENT_EXECUTER,
                    self::ETAT_VOIR, self::ETAT_EXPORTER,
                ],
            ],
            'LECTEUR' => [
                'libelle' => 'Consultant (lecture seule)',
                'description' => 'Consulte les informations sans rien modifier (auditeur, direction).',
                'couleur' => 'secondary',
                'permissions' => [
                    self::SOCIETE_VOIR, self::SALARIE_VOIR, self::PAIE_VOIR, self::PAIEMENT_VOIR, self::ETAT_VOIR,
                ],
            ],
        ];
    }
}
