<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026171628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trésorerie : paiements des salaires';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(15, 2) NOT NULL, mode VARCHAR(20) NOT NULL, banque VARCHAR(120) DEFAULT NULL, compte VARCHAR(60) DEFAULT NULL, reference VARCHAR(80) DEFAULT NULL, paye_le DATE NOT NULL, enregistre_le DATETIME NOT NULL, annule_le DATETIME DEFAULT NULL, motif_annulation VARCHAR(255) DEFAULT NULL, periode_id INT NOT NULL, affectation_id INT NOT NULL, enregistre_par_id INT DEFAULT NULL, annule_par_id INT DEFAULT NULL, INDEX paiement_idx (periode_id, affectation_id), INDEX IDX_B1DC7A1EF384C1CF (periode_id), INDEX IDX_B1DC7A1E6D0ABA22 (affectation_id), INDEX IDX_B1DC7A1ECB5FDB3E (enregistre_par_id), INDEX IDX_B1DC7A1EF376B95 (annule_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EF384C1CF FOREIGN KEY (periode_id) REFERENCES periode_paie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E6D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1ECB5FDB3E FOREIGN KEY (enregistre_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EF376B95 FOREIGN KEY (annule_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EF384C1CF');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E6D0ABA22');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1ECB5FDB3E');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EF376B95');
        $this->addSql('DROP TABLE paiement');
    }
}
