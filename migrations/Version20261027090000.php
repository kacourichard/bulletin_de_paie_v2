<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Impôt employeur CI : 12 % pour tous les non-Ivoiriens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE cotisation SET taux_patronal_non_national = 12 WHERE pays_id = (SELECT id FROM pays WHERE code_iso = 'CI') AND code_patronal = '3930'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE cotisation SET taux_patronal_non_national = 10.4 WHERE pays_id = (SELECT id FROM pays WHERE code_iso = 'CI') AND code_patronal = '3930'");
    }
}
