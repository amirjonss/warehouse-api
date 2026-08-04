<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803225004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent duplicate product within the same receipt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_receipt_items_receipt_product ON receipt_items (receipt_id, product_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_receipt_items_receipt_product
        SQL);
    }
}
