<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027082410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Secteurs d\'activité (taux de risque AT) repris de la v1 ; la société choisit son secteur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE secteur_activite (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(150) NOT NULL, taux_risque NUMERIC(7, 4) NOT NULL, actif TINYINT NOT NULL, pays_id INT NOT NULL, INDEX IDX_5CD9BFE1A6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE secteur_activite ADD CONSTRAINT FK_5CD9BFE1A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE societe ADD secteur_activite_id INT DEFAULT NULL, DROP taux_accident_travail');
        $this->addSql('ALTER TABLE societe ADD CONSTRAINT FK_19653DBD5233A7FC FOREIGN KEY (secteur_activite_id) REFERENCES secteur_activite (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_19653DBD5233A7FC ON societe (secteur_activite_id)');

        // Secteurs de Côte d'Ivoire (table secteur_activite de la v1).
        $secteurs = [
            ['MÉCANIQUE GÉNÉRALE', 3], ['INDUSTRIES EXTRACTIVES ET PROSPECTION', 4], ['MINIÈRE', 5],
            ['INDUSTRIES ALIMENTAIRES', 4], ['INDUSTRIES DES CORPS GRAS', 4], ['INDUSTRIES CHIMIQUES', 4],
            ['AUTRES INDUSTRIES', 3], ['TRANSPORT', 3], ['BOIS', 3], ['TEXTILE', 3], ['TRANSFORMATION DU THON', 2],
            ['POLYGRAPHIQUE', 3], ['HÔTELLERIE ET TOURISME', 3], ['PRODUCTION AGRICOLE', 3], ['SUCRE', 3],
            ['AUXILIAIRES DE TRANSPORT', 5], ['BÂTIMENT - TRAVAUX PUBLICS ET ACTIVITÉS CONNEXES', 5],
            ['COMMERCE - NÉGOCE - PROFESSIONS LIBÉRALES', 2], ['AGRICOLE - ÉLEVAGE - FORESTIER', 2], ['BANQUES', 2],
            ['ASSURANCES', 5], ['ENTREPRISES PÉTROLIÈRES', 2], ['INSTITUTS DE RECHERCHE', 2],
            ['TRANSPORT DE FONDS ET VALEURS', 2], ['SÉCURITÉ PRIVÉE', 2], ['DOCKERS', 2], ['GENS DE MAISON', 2],
            ['ENTREPRISES PÉTROLIÈRES (DISTRIBUTION)', 3], ['IMPRIMERIE ET SERVICES', 2],
        ];
        foreach ($secteurs as [$libelle, $taux]) {
            $this->addSql('INSERT INTO secteur_activite (pays_id, libelle, taux_risque, actif) SELECT id, ?, ?, 1 FROM pays WHERE code_iso = \'CI\'', [$libelle, $taux]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE secteur_activite DROP FOREIGN KEY FK_5CD9BFE1A6E44244');
        $this->addSql('DROP TABLE secteur_activite');
        $this->addSql('ALTER TABLE societe DROP FOREIGN KEY FK_19653DBD5233A7FC');
        $this->addSql('DROP INDEX IDX_19653DBD5233A7FC ON societe');
        $this->addSql('ALTER TABLE societe ADD taux_accident_travail NUMERIC(7, 4) DEFAULT NULL, DROP secteur_activite_id');
    }
}
