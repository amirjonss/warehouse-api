<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807131054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused Product.pack_qty/pack_unit/purchase_price — never read by any real calculation (cost basis lives on Batch.purchasePrice)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP pack_qty
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP pack_unit
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP purchase_price
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD pack_qty NUMERIC(14, 3) NOT NULL DEFAULT 1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD pack_unit VARCHAR(255) NOT NULL DEFAULT 'kg'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD purchase_price NUMERIC(14, 2) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ALTER COLUMN pack_qty DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ALTER COLUMN pack_unit DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ALTER COLUMN purchase_price DROP DEFAULT
        SQL);
    }
}
