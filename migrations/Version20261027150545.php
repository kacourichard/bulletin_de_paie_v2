<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027150545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indemnité de transport par zone de résidence (table v1 prime_transport)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE zone_transport (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, montant NUMERIC(14, 2) NOT NULL, ordre INT NOT NULL, actif TINYINT NOT NULL, pays_id INT NOT NULL, rubrique_id INT NOT NULL, INDEX IDX_E83D725BA6E44244 (pays_id), INDEX IDX_E83D725B3BD38833 (rubrique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE zone_transport ADD CONSTRAINT FK_E83D725BA6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE zone_transport ADD CONSTRAINT FK_E83D725B3BD38833 FOREIGN KEY (rubrique_id) REFERENCES rubrique (id)');
        $this->addSql('ALTER TABLE salarie ADD zone_transport_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE salarie ADD CONSTRAINT FK_828E3A1A43C953EC FOREIGN KEY (zone_transport_id) REFERENCES zone_transport (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_828E3A1A43C953EC ON salarie (zone_transport_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE zone_transport DROP FOREIGN KEY FK_E83D725BA6E44244');
        $this->addSql('ALTER TABLE zone_transport DROP FOREIGN KEY FK_E83D725B3BD38833');
        $this->addSql('DROP TABLE zone_transport');
        $this->addSql('ALTER TABLE salarie DROP FOREIGN KEY FK_828E3A1A43C953EC');
        $this->addSql('DROP INDEX IDX_828E3A1A43C953EC ON salarie');
        $this->addSql('ALTER TABLE salarie DROP zone_transport_id');
    }
}
