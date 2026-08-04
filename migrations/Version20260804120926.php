<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804120926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent duplicate batch within the same writeoff';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_writeoff_items_writeoff_batch ON writeoff_items (writeoff_id, batch_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_writeoff_items_writeoff_batch
        SQL);
    }
}
