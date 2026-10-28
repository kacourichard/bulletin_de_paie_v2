<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027142341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Heures supplémentaires : jours fériés, imports de pointage, identifiant de pointage';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE import_pointage (id INT AUTO_INCREMENT NOT NULL, mois DATE NOT NULL, nom_fichier VARCHAR(255) NOT NULL, regime_par_defaut VARCHAR(3) NOT NULL, du DATE NOT NULL, au DATE NOT NULL, nombre_lignes INT NOT NULL, donnees JSON NOT NULL, importe_le DATETIME NOT NULL, reporte_le DATETIME DEFAULT NULL, montant_panier NUMERIC(14, 2) DEFAULT NULL, societe_id INT NOT NULL, importe_par_id INT DEFAULT NULL, reporte_par_id INT DEFAULT NULL, INDEX import_pointage_mois_idx (societe_id, mois), INDEX IDX_AEF6A5DAFCF77503 (societe_id), INDEX IDX_AEF6A5DAB8AA8770 (importe_par_id), INDEX IDX_AEF6A5DACB23539C (reporte_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE jour_ferie (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, libelle VARCHAR(120) NOT NULL, chome_paye TINYINT NOT NULL, veille TINYINT NOT NULL, lendemain TINYINT NOT NULL, a_confirmer TINYINT NOT NULL, pays_id INT NOT NULL, societe_id INT DEFAULT NULL, INDEX jour_ferie_date_idx (pays_id, date), INDEX IDX_122AB5A6E44244 (pays_id), INDEX IDX_122AB5FCF77503 (societe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE import_pointage ADD CONSTRAINT FK_AEF6A5DAFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE import_pointage ADD CONSTRAINT FK_AEF6A5DAB8AA8770 FOREIGN KEY (importe_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE import_pointage ADD CONSTRAINT FK_AEF6A5DACB23539C FOREIGN KEY (reporte_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE jour_ferie ADD CONSTRAINT FK_122AB5A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jour_ferie ADD CONSTRAINT FK_122AB5FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE affectation ADD identifiant_pointage VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE import_pointage DROP FOREIGN KEY FK_AEF6A5DAFCF77503');
        $this->addSql('ALTER TABLE import_pointage DROP FOREIGN KEY FK_AEF6A5DAB8AA8770');
        $this->addSql('ALTER TABLE import_pointage DROP FOREIGN KEY FK_AEF6A5DACB23539C');
        $this->addSql('ALTER TABLE jour_ferie DROP FOREIGN KEY FK_122AB5A6E44244');
        $this->addSql('ALTER TABLE jour_ferie DROP FOREIGN KEY FK_122AB5FCF77503');
        $this->addSql('DROP TABLE import_pointage');
        $this->addSql('DROP TABLE jour_ferie');
        $this->addSql('ALTER TABLE affectation DROP identifiant_pointage');
    }
}
