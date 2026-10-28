<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paie appliquée explicite pour les sociétés existantes (celle de leur pays)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE societe SET legislation_paie_id = pays_id WHERE legislation_paie_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE societe SET legislation_paie_id = NULL WHERE legislation_paie_id = pays_id');
    }
}
