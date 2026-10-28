<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028084857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètre de paie : jours de congé acquis par mois (cumul « Congé acquis », 2,57 en Côte d\'Ivoire comme en v1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_paie ADD jours_conge_par_mois NUMERIC(5, 2) DEFAULT \'2.50\' NOT NULL');
        $this->addSql('UPDATE parametre_paie SET jours_conge_par_mois = 2.57 WHERE pays_id IN (SELECT id FROM pays WHERE code_iso = \'CI\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parametre_paie DROP jours_conge_par_mois');
    }
}
