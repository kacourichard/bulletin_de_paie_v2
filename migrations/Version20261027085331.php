<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027085331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Société : législation de paie d\'un autre pays';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE societe ADD legislation_paie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE societe ADD CONSTRAINT FK_19653DBD9DD91BF9 FOREIGN KEY (legislation_paie_id) REFERENCES pays (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_19653DBD9DD91BF9 ON societe (legislation_paie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE societe DROP FOREIGN KEY FK_19653DBD9DD91BF9');
        $this->addSql('DROP INDEX IDX_19653DBD9DD91BF9 ON societe');
        $this->addSql('ALTER TABLE societe DROP legislation_paie_id');
    }
}
