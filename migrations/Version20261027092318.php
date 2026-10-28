<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027092318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Solde de tout compte : arrêté du bulletin d\'un salarié sorti dans le mois';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiche_paie ADD solde_arrete_le DATETIME DEFAULT NULL, ADD solde_arrete_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fiche_paie ADD CONSTRAINT FK_60390C2586844273 FOREIGN KEY (solde_arrete_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_60390C2586844273 ON fiche_paie (solde_arrete_par_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiche_paie DROP FOREIGN KEY FK_60390C2586844273');
        $this->addSql('DROP INDEX IDX_60390C2586844273 ON fiche_paie');
        $this->addSql('ALTER TABLE fiche_paie DROP solde_arrete_le, DROP solde_arrete_par_id');
    }
}
