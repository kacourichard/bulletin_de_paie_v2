<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Succursales : regroupent les sociétés et portent leurs administrateurs.
 * Les données existantes sont reprises : toutes les sociétés rejoignent une succursale
 * « Succursale principale », les anciens administrateurs de société en deviennent
 * administrateurs, et le rôle de société ADMIN disparaît.
 */
final class Version20261026095547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des succursales et transfert des administrateurs de société vers la succursale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE succursale (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_BC0811936C6E55B5 (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE succursale_administrateur (succursale_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_255010F8C4166807 (succursale_id), INDEX IDX_255010F8A76ED395 (user_id), PRIMARY KEY (succursale_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE succursale_administrateur ADD CONSTRAINT FK_255010F8C4166807 FOREIGN KEY (succursale_id) REFERENCES succursale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE succursale_administrateur ADD CONSTRAINT FK_255010F8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // Reprise des sociétés existantes dans une succursale par défaut.
        $this->addSql('ALTER TABLE societe ADD succursale_id INT DEFAULT NULL');
        $this->addSql("INSERT INTO succursale (nom, description, active, created_at) SELECT 'Succursale principale', 'Créée lors de la mise à jour', 1, NOW() FROM DUAL WHERE EXISTS (SELECT 1 FROM societe)");
        $this->addSql("UPDATE societe SET succursale_id = (SELECT id FROM succursale WHERE nom = 'Succursale principale')");

        // Les anciens administrateurs de société deviennent administrateurs de la succursale.
        $this->addSql("INSERT IGNORE INTO succursale_administrateur (succursale_id, user_id)
            SELECT s.succursale_id, m.user_id FROM membre m
            JOIN role r ON r.id = m.role_id JOIN societe s ON s.id = m.societe_id
            WHERE r.code = 'ADMIN' AND m.actif = 1");
        $this->addSql("DELETE m FROM membre m JOIN role r ON r.id = m.role_id WHERE r.code = 'ADMIN'");
        $this->addSql("DELETE FROM role WHERE code = 'ADMIN' AND systeme = 1");

        $this->addSql('ALTER TABLE societe MODIFY succursale_id INT NOT NULL');
        $this->addSql('ALTER TABLE societe ADD CONSTRAINT FK_19653DBDC4166807 FOREIGN KEY (succursale_id) REFERENCES succursale (id)');
        $this->addSql('CREATE INDEX IDX_19653DBDC4166807 ON societe (succursale_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE succursale_administrateur DROP FOREIGN KEY FK_255010F8C4166807');
        $this->addSql('ALTER TABLE succursale_administrateur DROP FOREIGN KEY FK_255010F8A76ED395');
        $this->addSql('DROP TABLE succursale');
        $this->addSql('DROP TABLE succursale_administrateur');
        $this->addSql('ALTER TABLE societe DROP FOREIGN KEY FK_19653DBDC4166807');
        $this->addSql('DROP INDEX IDX_19653DBDC4166807 ON societe');
        $this->addSql('ALTER TABLE societe DROP succursale_id');
    }
}
