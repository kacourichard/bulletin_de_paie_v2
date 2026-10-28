<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027074618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calcul CI aligné sur la v1 : taux AT par société, impôt employeur non-nationaux, arrondi du net';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cotisation ADD taux_patronal_non_national NUMERIC(7, 4) DEFAULT NULL, ADD taux_patronal_societe TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE parametre_paie ADD arrondi_net VARCHAR(20) DEFAULT \'AUCUN\' NOT NULL');
        $this->addSql('ALTER TABLE societe ADD taux_accident_travail NUMERIC(7, 4) DEFAULT NULL');

        // Paramètres Côte d'Ivoire repris de la v1 (SocieteFicheController).
        $ci = '(SELECT id FROM pays WHERE code_iso = \'CI\')';
        $this->addSql("UPDATE parametre_paie SET mode_exoneration = 'FORFAITAIRE', arrondi_net = 'CINQ_DIX' WHERE pays_id = $ci");
        $this->addSql("UPDATE cotisation SET assiette = 'BRUT_IMPOSABLE' WHERE pays_id = $ci AND code_patronal IN ('3953', '3954')");
        $this->addSql("UPDATE cotisation SET taux_patronal_societe = 1 WHERE pays_id = $ci AND code_patronal = '3920'");
        $this->addSql("UPDATE cotisation SET taux_patronal_non_national = 10.4 WHERE pays_id = $ci AND code_patronal = '3930'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cotisation DROP taux_patronal_non_national, DROP taux_patronal_societe');
        $this->addSql('ALTER TABLE parametre_paie DROP arrondi_net');
        $this->addSql('ALTER TABLE societe DROP taux_accident_travail');
    }
}
