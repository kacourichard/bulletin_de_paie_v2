<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Côte d'Ivoire : codes de rubrique de la v1 (état des rubriques), qui fixent la place des lignes sur le bulletin,
 * prime d'ancienneté en 0013 et code de la ligne d'arrondi (0954).
 * Les avantages et éléments variables restent liés à leur rubrique : seul le code change.
 */
final class Version20261027154350 extends AbstractMigration
{
    /** Ancien code v2 => code v1. */
    private const CODES_CI = [
        'HS15' => '0014', 'HS50' => '0015', 'HS75' => '0016', 'HS100' => '0017',
        'PFID' => '0037', 'RAPSAL' => '0050', 'PFA' => '0063', 'BONUS' => '0064', 'PREAV' => '0066',
        'ICC' => '0068', 'CP' => '0083', 'PPAN' => '2073', 'ACPT' => '823', 'AVSAL' => '2070',
        'V27' => '2071', 'V28' => '2072',
    ];

    public function getDescription(): string
    {
        return 'Côte d\'Ivoire : codes de rubrique de la v1 (ordre du bulletin), ancienneté 0013, code d\'arrondi 0954';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parametre_paie ADD code_arrondi VARCHAR(20) DEFAULT '0954' NOT NULL");
        foreach (self::CODES_CI as $ancien => $nouveau) {
            $this->addSql("UPDATE rubrique r JOIN pays p ON p.id = r.pays_id SET r.code = :nouveau WHERE p.code_iso = 'CI' AND r.code = :ancien", ['ancien' => $ancien, 'nouveau' => $nouveau]);
        }
        $this->addSql("UPDATE parametre_paie pp JOIN pays p ON p.id = pp.pays_id SET pp.anciennete_code = '0013' WHERE p.code_iso = 'CI' AND pp.anciennete_code = 'ANC'");
    }

    public function down(Schema $schema): void
    {
        foreach (self::CODES_CI as $ancien => $nouveau) {
            $this->addSql("UPDATE rubrique r JOIN pays p ON p.id = r.pays_id SET r.code = :ancien WHERE p.code_iso = 'CI' AND r.code = :nouveau", ['ancien' => $ancien, 'nouveau' => $nouveau]);
        }
        $this->addSql("UPDATE parametre_paie pp JOIN pays p ON p.id = pp.pays_id SET pp.anciennete_code = 'ANC' WHERE p.code_iso = 'CI' AND pp.anciennete_code = '0013'");
        $this->addSql('ALTER TABLE parametre_paie DROP code_arrondi');
    }
}
