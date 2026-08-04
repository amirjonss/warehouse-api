<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804144056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent duplicate batch within the same sale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_sale_items_sale_batch ON sale_items (sale_id, batch_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_sale_items_sale_batch
        SQL);
    }
}
