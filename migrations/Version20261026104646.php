<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026104646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rubriques de paie par pays, avantages rattachés à une rubrique, historique des postes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE historique_poste (id INT AUTO_INCREMENT NOT NULL, date_effet DATE NOT NULL, poste VARCHAR(150) DEFAULT NULL, metier VARCHAR(150) DEFAULT NULL, service VARCHAR(100) DEFAULT NULL, categorie VARCHAR(50) DEFAULT NULL, echelon VARCHAR(30) DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, affectation_id INT NOT NULL, cree_par_id INT DEFAULT NULL, INDEX IDX_3209D5D86D0ABA22 (affectation_id), INDEX IDX_3209D5D8FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rubrique (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(150) NOT NULL, type VARCHAR(10) NOT NULL, section INT NOT NULL, traitement_social VARCHAR(20) NOT NULL, traitement_fiscal VARCHAR(20) NOT NULL, plafond_exoneration NUMERIC(5, 2) DEFAULT NULL, element_fixe TINYINT NOT NULL, actif TINYINT NOT NULL, pays_id INT NOT NULL, UNIQUE INDEX rubrique_code_unique (pays_id, code), INDEX IDX_8FA4097CA6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE historique_poste ADD CONSTRAINT FK_3209D5D86D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE historique_poste ADD CONSTRAINT FK_3209D5D8FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rubrique ADD CONSTRAINT FK_8FA4097CA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE affectation ADD metier VARCHAR(150) DEFAULT NULL');
        // Poste initial de chaque salarié déjà enregistré (date d'embauche).
        $this->addSql("INSERT INTO historique_poste (affectation_id, date_effet, poste, metier, service, categorie, echelon, commentaire, cree_par_id, created_at) SELECT id, date_entree, poste, NULL, service, categorie, echelon, 'Poste à l''embauche (reprise)', cree_par_id, NOW() FROM affectation");
        $this->addSql('ALTER TABLE avantage ADD rubrique_id INT DEFAULT NULL, DROP nature, DROP imposable, CHANGE libelle libelle VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT FK_A95D71E53BD38833 FOREIGN KEY (rubrique_id) REFERENCES rubrique (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_A95D71E53BD38833 ON avantage (rubrique_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE historique_poste DROP FOREIGN KEY FK_3209D5D86D0ABA22');
        $this->addSql('ALTER TABLE historique_poste DROP FOREIGN KEY FK_3209D5D8FC29C013');
        $this->addSql('ALTER TABLE rubrique DROP FOREIGN KEY FK_8FA4097CA6E44244');
        $this->addSql('DROP TABLE historique_poste');
        $this->addSql('DROP TABLE rubrique');
        $this->addSql('ALTER TABLE affectation DROP metier');
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY FK_A95D71E53BD38833');
        $this->addSql('DROP INDEX IDX_A95D71E53BD38833 ON avantage');
        $this->addSql('ALTER TABLE avantage ADD nature VARCHAR(20) NOT NULL, ADD imposable TINYINT NOT NULL, DROP rubrique_id, CHANGE libelle libelle VARCHAR(150) NOT NULL');
    }
}
