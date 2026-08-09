<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807122109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Denormalize remainingQty onto product/batches (kept in sync by StockMovementFactory), replacing the on-read ledger providers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ADD remaining_qty NUMERIC(14, 3) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD remaining_qty NUMERIC(14, 3) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE batches SET remaining_qty = COALESCE(
                (SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.batch_id = batches.id), 0
            )
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE product SET remaining_qty = COALESCE(
                (SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.product_id = product.id), 0
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ALTER COLUMN remaining_qty DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ALTER COLUMN remaining_qty DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE batches DROP remaining_qty
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP remaining_qty
        SQL);
    }
}
