<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le rôle par défaut « Responsable RH » peut valider la paie du mois (paie.valider).
 * Seuls les rôles RH livrés par défaut (systeme = 1) sont modifiés, pas les rôles personnalisés.
 */
final class Version20261026170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Responsable RH : droit de valider la paie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO role_permission (role_id, permission_id)
            SELECT r.id, p.id FROM role r JOIN permission p ON p.code = 'paie.valider'
            WHERE r.code = 'RH' AND r.systeme = 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permission rp
            JOIN role r ON r.id = rp.role_id JOIN permission p ON p.id = rp.permission_id
            WHERE r.code = 'RH' AND r.systeme = 1 AND p.code = 'paie.valider'");
    }
}
