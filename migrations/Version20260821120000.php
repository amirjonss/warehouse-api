<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Курс прихода больше не обязателен для UZS-позиций: снимаем NOT NULL с rate у позиции
 * прихода и с производных курсов у партии и распределения себестоимости.
 */
final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make receipt_items.rate, batches.rate_sell and sale_item_allocations.cost_rate nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items ALTER rate DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ALTER rate_sell DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_item_allocations ALTER cost_rate DROP NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items ALTER rate SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ALTER rate_sell SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_item_allocations ALTER cost_rate SET NOT NULL
        SQL);
    }
}
