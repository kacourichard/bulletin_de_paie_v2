<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026093756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE affectation (id INT AUTO_INCREMENT NOT NULL, matricule VARCHAR(30) NOT NULL, poste VARCHAR(150) DEFAULT NULL, service VARCHAR(100) DEFAULT NULL, categorie VARCHAR(50) DEFAULT NULL, echelon VARCHAR(30) DEFAULT NULL, site VARCHAR(100) DEFAULT NULL, type_contrat VARCHAR(20) NOT NULL, date_entree DATE NOT NULL, date_fin_contrat DATE DEFAULT NULL, date_sortie DATE DEFAULT NULL, motif_sortie VARCHAR(30) DEFAULT NULL, commentaire_sortie VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, salarie_id INT NOT NULL, societe_id INT NOT NULL, provenance_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F4DD61D3C24AFBDB (provenance_id), INDEX affectation_en_cours_idx (societe_id, date_sortie), UNIQUE INDEX affectation_matricule_unique (societe_id, matricule), INDEX IDX_F4DD61D35859934A (salarie_id), INDEX IDX_F4DD61D3FCF77503 (societe_id), INDEX IDX_F4DD61D3FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE avantage (id INT AUTO_INCREMENT NOT NULL, nature VARCHAR(20) NOT NULL, libelle VARCHAR(150) NOT NULL, montant NUMERIC(15, 2) NOT NULL, en_nature TINYINT NOT NULL, imposable TINYINT NOT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, affectation_id INT NOT NULL, cree_par_id INT DEFAULT NULL, INDEX IDX_A95D71E56D0ABA22 (affectation_id), INDEX IDX_A95D71E5FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE remuneration (id INT AUTO_INCREMENT NOT NULL, date_effet DATE NOT NULL, salaire_base NUMERIC(15, 2) NOT NULL, sursalaire NUMERIC(15, 2) NOT NULL, motif VARCHAR(20) NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, corrige_le DATETIME DEFAULT NULL, affectation_id INT NOT NULL, cree_par_id INT DEFAULT NULL, corrige_par_id INT DEFAULT NULL, UNIQUE INDEX remuneration_date_unique (affectation_id, date_effet), INDEX IDX_1969358B6D0ABA22 (affectation_id), INDEX IDX_1969358BFC29C013 (cree_par_id), INDEX IDX_1969358BC99BFB58 (corrige_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE salarie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenoms VARCHAR(150) NOT NULL, sexe VARCHAR(1) DEFAULT NULL, date_naissance DATE DEFAULT NULL, lieu_naissance VARCHAR(100) DEFAULT NULL, nationalite VARCHAR(2) DEFAULT NULL, situation_familiale VARCHAR(20) DEFAULT NULL, nombre_enfants INT NOT NULL, telephone VARCHAR(30) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, numero_piece VARCHAR(50) DEFAULT NULL, numero_securite_sociale VARCHAR(50) DEFAULT NULL, numero_assurance_maladie VARCHAR(50) DEFAULT NULL, mode_paiement VARCHAR(20) NOT NULL, banque VARCHAR(100) DEFAULT NULL, numero_compte VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D35859934A FOREIGN KEY (salarie_id) REFERENCES salarie (id)');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3C24AFBDB FOREIGN KEY (provenance_id) REFERENCES affectation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE affectation ADD CONSTRAINT FK_F4DD61D3FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT FK_A95D71E56D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT FK_A95D71E5FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE remuneration ADD CONSTRAINT FK_1969358B6D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE remuneration ADD CONSTRAINT FK_1969358BFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE remuneration ADD CONSTRAINT FK_1969358BC99BFB58 FOREIGN KEY (corrige_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D35859934A');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3FCF77503');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3C24AFBDB');
        $this->addSql('ALTER TABLE affectation DROP FOREIGN KEY FK_F4DD61D3FC29C013');
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY FK_A95D71E56D0ABA22');
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY FK_A95D71E5FC29C013');
        $this->addSql('ALTER TABLE remuneration DROP FOREIGN KEY FK_1969358B6D0ABA22');
        $this->addSql('ALTER TABLE remuneration DROP FOREIGN KEY FK_1969358BFC29C013');
        $this->addSql('ALTER TABLE remuneration DROP FOREIGN KEY FK_1969358BC99BFB58');
        $this->addSql('DROP TABLE affectation');
        $this->addSql('DROP TABLE avantage');
        $this->addSql('DROP TABLE remuneration');
        $this->addSql('DROP TABLE salarie');
    }
}
