<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The company treasury: accounts (cash_accounts), the journal of what moves through
 * them (account_entries) and the document that moves money between two of them
 * (money_transfers).
 *
 * A cash_session is one seller's shift — what is in their bag right now. Until now the
 * money simply vanished once it was handed over, and card or transfer payments never
 * created a money record at all. cash_accounts is where it all lands.
 *
 * The account set is closed: (kind, currency) is its identity. The business has no
 * currency bank account and no dollar card, so USD exists only as cash — a CHECK says
 * so. The seed below repeats App\Component\Account\CashAccountCatalog on purpose:
 * migrations are frozen in time and must not reach into application code.
 */
final class Version20260829120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the wallet: cash_accounts, the account_entries journal and money transfers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_accounts (
                id SERIAL NOT NULL,
                kind VARCHAR(255) NOT NULL,
                currency VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                balance NUMERIC(18, 2) DEFAULT '0.00' NOT NULL,
                is_active BOOLEAN DEFAULT TRUE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_cash_accounts_kind_currency ON cash_accounts (kind, currency)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_accounts ADD CONSTRAINT chk_cash_accounts_usd_is_cash_only
                CHECK (currency <> 'USD' OR kind = 'cash')
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO cash_accounts (kind, currency, name, balance, is_active) VALUES
                ('cash', 'UZS', 'Наличные UZS', 0, TRUE),
                ('cash', 'USD', 'Наличные USD', 0, TRUE),
                ('card', 'UZS', 'Карта', 0, TRUE),
                ('bank', 'UZS', 'Счёт / банк', 0, TRUE)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE money_transfers (
                id SERIAL NOT NULL,
                from_account_id INT NOT NULL,
                to_account_id INT NOT NULL,
                created_by_id INT NOT NULL,
                number VARCHAR(255) NOT NULL,
                doc_date DATE NOT NULL,
                posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                amount_sent NUMERIC(18, 2) NOT NULL,
                amount_received NUMERIC(18, 2) NOT NULL,
                rate NUMERIC(12, 4) DEFAULT NULL,
                status VARCHAR(255) NOT NULL,
                note TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_money_transfers_number ON money_transfers (number)');
        $this->addSql('CREATE INDEX idx_money_transfers_from ON money_transfers (from_account_id)');
        $this->addSql('CREATE INDEX idx_money_transfers_to ON money_transfers (to_account_id)');
        $this->addSql('CREATE INDEX idx_money_transfers_created_by ON money_transfers (created_by_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE money_transfers ADD CONSTRAINT chk_money_transfers_accounts_differ
                CHECK (from_account_id <> to_account_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE money_transfers ADD CONSTRAINT chk_money_transfers_amounts_positive
                CHECK (amount_sent > 0 AND amount_received > 0)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE account_entries (
                id SERIAL NOT NULL,
                account_id INT NOT NULL,
                cash_entry_id INT DEFAULT NULL,
                payment_id INT DEFAULT NULL,
                money_transfer_id INT DEFAULT NULL,
                created_by_id INT NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                kind VARCHAR(255) NOT NULL,
                amount NUMERIC(18, 2) NOT NULL,
                note TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_account_entries_account ON account_entries (account_id)');
        $this->addSql('CREATE INDEX idx_account_entries_cash_entry ON account_entries (cash_entry_id)');
        $this->addSql('CREATE INDEX idx_account_entries_payment ON account_entries (payment_id)');
        $this->addSql('CREATE INDEX idx_account_entries_money_transfer ON account_entries (money_transfer_id)');
        $this->addSql('CREATE INDEX idx_account_entries_created_by ON account_entries (created_by_id)');
        // One opening balance per account, guaranteed by the database rather than by a
        // read-then-write, which two concurrent requests would slip through.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_account_entries_opening ON account_entries (account_id) WHERE (kind = 'opening')
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT chk_account_entries_amount_nonzero CHECK (amount <> 0)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_ACCOUNT FOREIGN KEY (account_id)
                REFERENCES cash_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_CASH_ENTRY FOREIGN KEY (cash_entry_id)
                REFERENCES cash_entries (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_PAYMENT FOREIGN KEY (payment_id)
                REFERENCES payments (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_MONEY_TRANSFER FOREIGN KEY (money_transfer_id)
                REFERENCES money_transfers (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE account_entries ADD CONSTRAINT FK_ACCENT_CREATED_BY FOREIGN KEY (created_by_id)
                REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE money_transfers ADD CONSTRAINT FK_MTRANS_FROM FOREIGN KEY (from_account_id)
                REFERENCES cash_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE money_transfers ADD CONSTRAINT FK_MTRANS_TO FOREIGN KEY (to_account_id)
                REFERENCES cash_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE money_transfers ADD CONSTRAINT FK_MTRANS_CREATED_BY FOREIGN KEY (created_by_id)
                REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_ACCOUNT');
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_CASH_ENTRY');
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_PAYMENT');
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_MONEY_TRANSFER');
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_CREATED_BY');
        $this->addSql('ALTER TABLE money_transfers DROP CONSTRAINT FK_MTRANS_FROM');
        $this->addSql('ALTER TABLE money_transfers DROP CONSTRAINT FK_MTRANS_TO');
        $this->addSql('ALTER TABLE money_transfers DROP CONSTRAINT FK_MTRANS_CREATED_BY');
        $this->addSql('DROP TABLE account_entries');
        $this->addSql('DROP TABLE money_transfers');
        $this->addSql('DROP TABLE cash_accounts');
    }
}
