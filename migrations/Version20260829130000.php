<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supplier payables: what we owe, mirroring debts, which is what customers owe us.
 *
 * Posting a receipt opens the obligation, a supplier payment closes it, and the journal
 * is append-only — a cancellation is a compensating row, never an update.
 *
 * A separate table rather than nullable columns on debts: there both client_id and
 * sale_id are NOT NULL, and receivables are readable by sellers while payables are the
 * owner's business.
 *
 * No backfill: the business starts payables from a clean slate, so receipts posted
 * before this migration carry no debt.
 */
final class Version20260829130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier payables: supplier_debts journal, supplier payments and allocations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE suppliers ADD debt_usd NUMERIC(18, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql("ALTER TABLE suppliers ADD debt_uzs NUMERIC(18, 2) DEFAULT '0.00' NOT NULL");

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_payments (
                id SERIAL NOT NULL,
                supplier_id INT NOT NULL,
                account_id INT NOT NULL,
                paid_by_id INT NOT NULL,
                number VARCHAR(255) NOT NULL,
                doc_date DATE NOT NULL,
                posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                amount NUMERIC(18, 2) NOT NULL,
                currency VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL,
                note TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_payments_number ON supplier_payments (number)');
        $this->addSql('CREATE INDEX idx_supplier_payments_supplier ON supplier_payments (supplier_id)');
        $this->addSql('CREATE INDEX idx_supplier_payments_account ON supplier_payments (account_id)');
        $this->addSql('CREATE INDEX idx_supplier_payments_paid_by ON supplier_payments (paid_by_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE supplier_payments ADD CONSTRAINT chk_supplier_payments_amount_positive CHECK (amount > 0)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_payment_allocations (
                id SERIAL NOT NULL,
                supplier_payment_id INT NOT NULL,
                receipt_id INT NOT NULL,
                currency VARCHAR(255) NOT NULL,
                amount_spent NUMERIC(18, 2) NOT NULL,
                amount_closed NUMERIC(18, 2) NOT NULL,
                pay_rate NUMERIC(12, 4) DEFAULT NULL,
                rounding_write_off NUMERIC(18, 2) DEFAULT '0.00' NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_spa_payment ON supplier_payment_allocations (supplier_payment_id)');
        $this->addSql('CREATE INDEX idx_spa_receipt ON supplier_payment_allocations (receipt_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE supplier_payment_allocations
                ADD CONSTRAINT chk_spa_amount_spent_positive CHECK (amount_spent > 0)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_debts (
                id SERIAL NOT NULL,
                supplier_id INT NOT NULL,
                receipt_id INT NOT NULL,
                supplier_payment_id INT DEFAULT NULL,
                created_by_id INT NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                amount NUMERIC(18, 2) NOT NULL,
                currency VARCHAR(255) NOT NULL,
                doc_type VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_supplier_debts_supplier ON supplier_debts (supplier_id)');
        $this->addSql('CREATE INDEX idx_supplier_debts_receipt ON supplier_debts (receipt_id)');
        $this->addSql('CREATE INDEX idx_supplier_debts_payment ON supplier_debts (supplier_payment_id)');
        $this->addSql('CREATE INDEX idx_supplier_debts_created_by ON supplier_debts (created_by_id)');

        $this->addSql('ALTER TABLE account_entries ADD supplier_payment_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_account_entries_supplier_payment ON account_entries (supplier_payment_id)');

        foreach ([
            ['supplier_payments', 'FK_SPAY_SUPPLIER', 'supplier_id', 'suppliers'],
            ['supplier_payments', 'FK_SPAY_ACCOUNT', 'account_id', 'cash_accounts'],
            ['supplier_payments', 'FK_SPAY_PAID_BY', 'paid_by_id', 'users'],
            ['supplier_payment_allocations', 'FK_SPA_RECEIPT', 'receipt_id', 'receipts'],
            ['supplier_debts', 'FK_SDEBT_SUPPLIER', 'supplier_id', 'suppliers'],
            ['supplier_debts', 'FK_SDEBT_RECEIPT', 'receipt_id', 'receipts'],
            ['supplier_debts', 'FK_SDEBT_PAYMENT', 'supplier_payment_id', 'supplier_payments'],
            ['supplier_debts', 'FK_SDEBT_CREATED_BY', 'created_by_id', 'users'],
            ['account_entries', 'FK_ACCENT_SUPPLIER_PAYMENT', 'supplier_payment_id', 'supplier_payments'],
        ] as [$table, $name, $column, $target]) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
                $table,
                $name,
                $column,
                $target
            ));
        }

        // Allocations die with their payment, exactly as payment_allocations do.
        $this->addSql(<<<'SQL'
            ALTER TABLE supplier_payment_allocations ADD CONSTRAINT FK_SPA_PAYMENT
                FOREIGN KEY (supplier_payment_id) REFERENCES supplier_payments (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_entries DROP CONSTRAINT FK_ACCENT_SUPPLIER_PAYMENT');
        $this->addSql('DROP INDEX idx_account_entries_supplier_payment');
        $this->addSql('ALTER TABLE account_entries DROP supplier_payment_id');
        $this->addSql('DROP TABLE supplier_debts');
        $this->addSql('DROP TABLE supplier_payment_allocations');
        $this->addSql('DROP TABLE supplier_payments');
        $this->addSql('ALTER TABLE suppliers DROP debt_usd');
        $this->addSql('ALTER TABLE suppliers DROP debt_uzs');
    }
}
