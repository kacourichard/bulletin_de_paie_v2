<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026080745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE membre (id INT AUTO_INCREMENT NOT NULL, actif TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, societe_id INT NOT NULL, role_id INT NOT NULL, UNIQUE INDEX membre_unique (user_id, societe_id), INDEX IDX_F6B4FB29A76ED395 (user_id), INDEX IDX_F6B4FB29FCF77503 (societe_id), INDEX IDX_F6B4FB29D60322AC (role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pays (id INT AUTO_INCREMENT NOT NULL, code_iso VARCHAR(2) NOT NULL, nom VARCHAR(100) NOT NULL, devise VARCHAR(3) NOT NULL, langue VARCHAR(10) NOT NULL, fuseau_horaire VARCHAR(64) NOT NULL, indicatif_telephone VARCHAR(10) DEFAULT NULL, organisme_social VARCHAR(100) DEFAULT NULL, actif TINYINT NOT NULL, UNIQUE INDEX UNIQ_349F3CAE897848EB (code_iso), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(80) NOT NULL, libelle VARCHAR(150) NOT NULL, module VARCHAR(50) NOT NULL, ordre INT NOT NULL, UNIQUE INDEX UNIQ_E04992AA77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, couleur VARCHAR(20) NOT NULL, systeme TINYINT NOT NULL, societe_id INT NOT NULL, UNIQUE INDEX role_unique (societe_id, code), INDEX IDX_57698A6AFCF77503 (societe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role_permission (role_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_6F7DF886D60322AC (role_id), INDEX IDX_6F7DF886FED90CCA (permission_id), PRIMARY KEY (role_id, permission_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, numero_employeur VARCHAR(100) DEFAULT NULL, numero_fiscal VARCHAR(100) DEFAULT NULL, registre_commerce VARCHAR(100) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, devise VARCHAR(3) DEFAULT NULL, date_creation DATE DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, pays_id INT NOT NULL, INDEX IDX_19653DBDA6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(100) NOT NULL, prenoms VARCHAR(150) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, password VARCHAR(255) NOT NULL, super_admin TINYINT NOT NULL, actif TINYINT NOT NULL, doit_changer_mot_de_passe TINYINT NOT NULL, langue VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, derniere_connexion DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE membre ADD CONSTRAINT FK_F6B4FB29A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre ADD CONSTRAINT FK_F6B4FB29FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre ADD CONSTRAINT FK_F6B4FB29D60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6AFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE societe ADD CONSTRAINT FK_19653DBDA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE membre DROP FOREIGN KEY FK_F6B4FB29A76ED395');
        $this->addSql('ALTER TABLE membre DROP FOREIGN KEY FK_F6B4FB29FCF77503');
        $this->addSql('ALTER TABLE membre DROP FOREIGN KEY FK_F6B4FB29D60322AC');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6AFCF77503');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886D60322AC');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886FED90CCA');
        $this->addSql('ALTER TABLE societe DROP FOREIGN KEY FK_19653DBDA6E44244');
        $this->addSql('DROP TABLE membre');
        $this->addSql('DROP TABLE pays');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE role_permission');
        $this->addSql('DROP TABLE societe');
        $this->addSql('DROP TABLE `user`');
    }
}
