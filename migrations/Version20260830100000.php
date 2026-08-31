<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An expense now says where the money came from: a seller's float (cash_session_id, which
 * already existed) or a company account (account_id, added here). Exactly one of the two
 * is ever set.
 *
 * Until the treasury existed an expense with no open session simply recorded itself and
 * no money left anything, which after the wallet landed meant the company's cash was
 * overstated by every such expense. Historical rows keep both columns null: they predate
 * the accounts and there is nothing to attach them to.
 */
final class Version20260830100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give an expense its source: a company account next to the existing float link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expenses ADD account_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_expenses_account ON expenses (account_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD CONSTRAINT FK_EXPENSE_ACCOUNT FOREIGN KEY (account_id)
                REFERENCES cash_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        // An expense comes out of one place or the other, never both.
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD CONSTRAINT chk_expenses_single_source
                CHECK (account_id IS NULL OR cash_session_id IS NULL)
        SQL);

        $this->addSql('ALTER TABLE account_entries ADD expense_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_account_entries_expense ON account_entries (expense_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_EXPENSE FOREIGN KEY (expense_id)
                REFERENCES expenses (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_EXPENSE');
        $this->addSql('DROP INDEX idx_account_entries_expense');
        $this->addSql('ALTER TABLE account_entries DROP expense_id');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT chk_expenses_single_source');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT FK_EXPENSE_ACCOUNT');
        $this->addSql('DROP INDEX idx_expenses_account');
        $this->addSql('ALTER TABLE expenses DROP account_id');
    }
}
