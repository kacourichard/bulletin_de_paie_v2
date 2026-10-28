<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027141225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rubriques du pays masquées par une société';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE societe_rubrique_masquee (societe_id INT NOT NULL, rubrique_id INT NOT NULL, INDEX IDX_7C260E31FCF77503 (societe_id), INDEX IDX_7C260E313BD38833 (rubrique_id), PRIMARY KEY (societe_id, rubrique_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE societe_rubrique_masquee ADD CONSTRAINT FK_7C260E31FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE societe_rubrique_masquee ADD CONSTRAINT FK_7C260E313BD38833 FOREIGN KEY (rubrique_id) REFERENCES rubrique (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE societe_rubrique_masquee DROP FOREIGN KEY FK_7C260E31FCF77503');
        $this->addSql('ALTER TABLE societe_rubrique_masquee DROP FOREIGN KEY FK_7C260E313BD38833');
        $this->addSql('DROP TABLE societe_rubrique_masquee');
    }
}
