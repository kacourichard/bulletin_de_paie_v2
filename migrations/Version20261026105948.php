<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026105948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paie du mois : périodes et éléments variables ; rubriques saisissables comme élément variable';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE element_variable (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(150) DEFAULT NULL, base NUMERIC(15, 4) DEFAULT NULL, taux NUMERIC(15, 4) DEFAULT NULL, montant NUMERIC(15, 2) NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, periode_id INT NOT NULL, affectation_id INT NOT NULL, rubrique_id INT NOT NULL, avantage_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, UNIQUE INDEX ajustement_unique (periode_id, avantage_id), INDEX IDX_A0BCF920F384C1CF (periode_id), INDEX IDX_A0BCF9206D0ABA22 (affectation_id), INDEX IDX_A0BCF9203BD38833 (rubrique_id), INDEX IDX_A0BCF920EA96B22C (avantage_id), INDEX IDX_A0BCF920FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE periode_paie (id INT AUTO_INCREMENT NOT NULL, mois DATE NOT NULL, cloturee TINYINT NOT NULL, cloturee_le DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, societe_id INT NOT NULL, cloturee_par_id INT DEFAULT NULL, ouverte_par_id INT DEFAULT NULL, UNIQUE INDEX periode_unique (societe_id, mois), INDEX IDX_43E5471BFCF77503 (societe_id), INDEX IDX_43E5471BFD9AF8EE (cloturee_par_id), INDEX IDX_43E5471BA3FE4415 (ouverte_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920F384C1CF FOREIGN KEY (periode_id) REFERENCES periode_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF9206D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF9203BD38833 FOREIGN KEY (rubrique_id) REFERENCES rubrique (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920EA96B22C FOREIGN KEY (avantage_id) REFERENCES avantage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_variable ADD CONSTRAINT FK_A0BCF920FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE periode_paie ADD CONSTRAINT FK_43E5471BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE periode_paie ADD CONSTRAINT FK_43E5471BFD9AF8EE FOREIGN KEY (cloturee_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE periode_paie ADD CONSTRAINT FK_43E5471BA3FE4415 FOREIGN KEY (ouverte_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rubrique ADD element_variable TINYINT DEFAULT 1 NOT NULL');
        // Le sursalaire a son propre champ dans la rémunération : pas d'élément variable.
        $this->addSql("UPDATE rubrique SET element_variable = 0 WHERE code = '0012'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920F384C1CF');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF9206D0ABA22');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF9203BD38833');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920EA96B22C');
        $this->addSql('ALTER TABLE element_variable DROP FOREIGN KEY FK_A0BCF920FC29C013');
        $this->addSql('ALTER TABLE periode_paie DROP FOREIGN KEY FK_43E5471BFCF77503');
        $this->addSql('ALTER TABLE periode_paie DROP FOREIGN KEY FK_43E5471BFD9AF8EE');
        $this->addSql('ALTER TABLE periode_paie DROP FOREIGN KEY FK_43E5471BA3FE4415');
        $this->addSql('DROP TABLE element_variable');
        $this->addSql('DROP TABLE periode_paie');
        $this->addSql('ALTER TABLE rubrique DROP element_variable');
    }
}
