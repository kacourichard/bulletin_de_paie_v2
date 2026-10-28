<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026165011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cotisation (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(150) NOT NULL, code_salarial VARCHAR(20) DEFAULT NULL, code_patronal VARCHAR(20) DEFAULT NULL, assiette VARCHAR(30) NOT NULL, forfait_unitaire NUMERIC(15, 2) DEFAULT NULL, plafond NUMERIC(15, 2) DEFAULT NULL, taux_salarial NUMERIC(7, 4) DEFAULT NULL, taux_patronal NUMERIC(7, 4) DEFAULT NULL, categorie VARCHAR(20) NOT NULL, ordre INT NOT NULL, actif TINYINT NOT NULL, pays_id INT NOT NULL, INDEX IDX_AE64D2EDA6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE envoi_bulletin (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, succes TINYINT NOT NULL, erreur VARCHAR(255) DEFAULT NULL, envoye_le DATETIME NOT NULL, periode_id INT NOT NULL, affectation_id INT NOT NULL, envoye_par_id INT DEFAULT NULL, INDEX envoi_bulletin_idx (periode_id, affectation_id), INDEX IDX_EBCDFFB1F384C1CF (periode_id), INDEX IDX_EBCDFFB16D0ABA22 (affectation_id), INDEX IDX_EBCDFFB1D603292 (envoye_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reduction_impot (id INT AUTO_INCREMENT NOT NULL, parts NUMERIC(4, 2) NOT NULL, montant NUMERIC(15, 2) NOT NULL, pays_id INT NOT NULL, INDEX IDX_568882E4A6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tranche_impot (id INT AUTO_INCREMENT NOT NULL, de NUMERIC(15, 2) NOT NULL, a NUMERIC(15, 2) DEFAULT NULL, taux NUMERIC(6, 3) NOT NULL, pays_id INT NOT NULL, INDEX IDX_1D187D30A6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cotisation ADD CONSTRAINT FK_AE64D2EDA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE envoi_bulletin ADD CONSTRAINT FK_EBCDFFB1F384C1CF FOREIGN KEY (periode_id) REFERENCES periode_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE envoi_bulletin ADD CONSTRAINT FK_EBCDFFB16D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE envoi_bulletin ADD CONSTRAINT FK_EBCDFFB1D603292 FOREIGN KEY (envoye_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reduction_impot ADD CONSTRAINT FK_568882E4A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tranche_impot ADD CONSTRAINT FK_1D187D30A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parametre_paie ADD impot_actif TINYINT DEFAULT 0 NOT NULL, ADD impot_code VARCHAR(20) DEFAULT \'600\' NOT NULL, ADD impot_libelle VARCHAR(100) DEFAULT \'Impôt sur le salaire\' NOT NULL, ADD impot_assiette VARCHAR(30) DEFAULT \'BRUT_IMPOSABLE\' NOT NULL, ADD code_salaire_base VARCHAR(20) DEFAULT \'0010\' NOT NULL, ADD code_brut VARCHAR(20) DEFAULT \'2000\' NOT NULL, ADD code_brut_imposable VARCHAR(20) DEFAULT \'2001\' NOT NULL, ADD code_salaire_net VARCHAR(20) DEFAULT \'4000\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cotisation DROP FOREIGN KEY FK_AE64D2EDA6E44244');
        $this->addSql('ALTER TABLE envoi_bulletin DROP FOREIGN KEY FK_EBCDFFB1F384C1CF');
        $this->addSql('ALTER TABLE envoi_bulletin DROP FOREIGN KEY FK_EBCDFFB16D0ABA22');
        $this->addSql('ALTER TABLE envoi_bulletin DROP FOREIGN KEY FK_EBCDFFB1D603292');
        $this->addSql('ALTER TABLE reduction_impot DROP FOREIGN KEY FK_568882E4A6E44244');
        $this->addSql('ALTER TABLE tranche_impot DROP FOREIGN KEY FK_1D187D30A6E44244');
        $this->addSql('DROP TABLE cotisation');
        $this->addSql('DROP TABLE envoi_bulletin');
        $this->addSql('DROP TABLE reduction_impot');
        $this->addSql('DROP TABLE tranche_impot');
        $this->addSql('ALTER TABLE parametre_paie DROP impot_actif, DROP impot_code, DROP impot_libelle, DROP impot_assiette, DROP code_salaire_base, DROP code_brut, DROP code_brut_imposable, DROP code_salaire_net');
    }
}
