<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The expense was the only money-carrying entity without a currency. Every existing row
 * is in UZS (a USD expense simply could not be entered until now), so backfill UZS first
 * and only then add NOT NULL.
 */
final class Version20260827100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add currency to expenses, backfill existing rows as UZS';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD currency VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE expenses SET currency = 'UZS' WHERE currency IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ALTER currency SET NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses DROP currency
        SQL);
    }
}
