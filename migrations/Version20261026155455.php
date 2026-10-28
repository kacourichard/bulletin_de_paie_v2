<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026155455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètres de paie par pays (ancienneté, parts, brut, brut imposable)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bareme_part (id INT AUTO_INCREMENT NOT NULL, situation VARCHAR(20) NOT NULL, nombre_enfants INT NOT NULL, parts NUMERIC(4, 2) NOT NULL, pays_id INT NOT NULL, UNIQUE INDEX bareme_part_unique (pays_id, situation, nombre_enfants), INDEX IDX_7B14F0DBA6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parametre_paie (id INT AUTO_INCREMENT NOT NULL, anciennete_active TINYINT NOT NULL, anciennete_annees_min INT NOT NULL, anciennete_taux_initial NUMERIC(5, 2) NOT NULL, anciennete_taux_par_an NUMERIC(5, 2) NOT NULL, anciennete_taux_max NUMERIC(5, 2) NOT NULL, anciennete_base VARCHAR(30) NOT NULL, anciennete_depuis_groupe TINYINT NOT NULL, anciennete_code VARCHAR(20) NOT NULL, parts_actives TINYINT NOT NULL, brut_inclut_sursalaire TINYINT NOT NULL, brut_inclut_anciennete TINYINT NOT NULL, brut_inclut_gains_soumis TINYINT NOT NULL, brut_inclut_gains_non_soumis TINYINT NOT NULL, mode_exoneration VARCHAR(20) NOT NULL, decimales INT NOT NULL, updated_at DATETIME DEFAULT NULL, pays_id INT NOT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_992D08C8A6E44244 (pays_id), INDEX IDX_992D08C8896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bareme_part ADD CONSTRAINT FK_7B14F0DBA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parametre_paie ADD CONSTRAINT FK_992D08C8A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parametre_paie ADD CONSTRAINT FK_992D08C8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bareme_part DROP FOREIGN KEY FK_7B14F0DBA6E44244');
        $this->addSql('ALTER TABLE parametre_paie DROP FOREIGN KEY FK_992D08C8A6E44244');
        $this->addSql('ALTER TABLE parametre_paie DROP FOREIGN KEY FK_992D08C8896DBBDE');
        $this->addSql('DROP TABLE bareme_part');
        $this->addSql('DROP TABLE parametre_paie');
    }
}
