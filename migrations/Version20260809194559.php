<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809194559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Denormalize debtUsd/debtUzs onto Client (kept in sync by DebtFactory), replacing the unpaginated /clients/debt action';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE clients ADD debt_usd NUMERIC(18, 2) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE clients ADD debt_uzs NUMERIC(18, 2) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE clients SET debt_usd = COALESCE(
                (SELECT SUM(d.amount) FROM debts d WHERE d.client_id = clients.id AND d.currency = 'USD'), 0
            )
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE clients SET debt_uzs = COALESCE(
                (SELECT SUM(d.amount) FROM debts d WHERE d.client_id = clients.id AND d.currency = 'UZS'), 0
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE clients ALTER COLUMN debt_usd DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE clients ALTER COLUMN debt_uzs DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE clients DROP debt_usd
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE clients DROP debt_uzs
        SQL);
    }
}
