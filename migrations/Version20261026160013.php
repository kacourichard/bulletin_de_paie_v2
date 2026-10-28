<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026160013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jours travaillés (fiche de paie), prorata et heures supplémentaires paramétrables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fiche_paie (id INT AUTO_INCREMENT NOT NULL, jours NUMERIC(5, 2) DEFAULT NULL, periode_id INT NOT NULL, affectation_id INT NOT NULL, UNIQUE INDEX fiche_paie_unique (periode_id, affectation_id), INDEX IDX_60390C25F384C1CF (periode_id), INDEX IDX_60390C256D0ABA22 (affectation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fiche_paie ADD CONSTRAINT FK_60390C25F384C1CF FOREIGN KEY (periode_id) REFERENCES periode_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fiche_paie ADD CONSTRAINT FK_60390C256D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parametre_paie ADD jours_mois INT DEFAULT 30 NOT NULL, ADD proratiser_fixes TINYINT DEFAULT 1 NOT NULL, ADD heures_mois NUMERIC(6, 2) DEFAULT \'173.33\' NOT NULL');
        $this->addSql('ALTER TABLE rubrique ADD coefficient_horaire NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fiche_paie DROP FOREIGN KEY FK_60390C25F384C1CF');
        $this->addSql('ALTER TABLE fiche_paie DROP FOREIGN KEY FK_60390C256D0ABA22');
        $this->addSql('DROP TABLE fiche_paie');
        $this->addSql('ALTER TABLE parametre_paie DROP jours_mois, DROP proratiser_fixes, DROP heures_mois');
        $this->addSql('ALTER TABLE rubrique DROP coefficient_horaire');
    }
}
