<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Подотчёт продавца: смена (cash_sessions) и журнал движения наличных
 * (cash_entries) — четвёртый журнал рядом с debts, stock_movements и profits.
 *
 * Платёж и расход получают ссылку на смену: у платежа она нужна и для наличных
 * (формируют остаток), и для карты с перечислением (входят в оборот смены, но
 * обязательства не создают).
 *
 * «Одна открытая смена на продавца» гарантирует частичный уникальный индекс.
 */
final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cash_sessions and cash_entries, link payments and expenses to a session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_sessions (
                id SERIAL NOT NULL,
                user_id INT NOT NULL,
                opened_by_id INT NOT NULL,
                closed_by_id INT DEFAULT NULL,
                number VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL,
                opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                balance_usd NUMERIC(18, 2) NOT NULL,
                balance_uzs NUMERIC(18, 2) NOT NULL,
                unconfirmed_usd NUMERIC(18, 2) NOT NULL,
                unconfirmed_uzs NUMERIC(18, 2) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_F8E36CDE96901F54 ON cash_sessions (number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F8E36CDEA76ED395 ON cash_sessions (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F8E36CDEAB159F5 ON cash_sessions (opened_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F8E36CDEE1FA7797 ON cash_sessions (closed_by_id)
        SQL);
        // PostgreSQL сам нормализует условие в ((status)::text = 'open'::text) —
        // именно в этом виде оно записано в маппинге CashSession, чтобы сравнение
        // схемы не видело вечного расхождения.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_cash_sessions_open_user ON cash_sessions (user_id) WHERE (status = 'open')
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE cash_entries (
                id SERIAL NOT NULL,
                session_id INT NOT NULL,
                payment_id INT DEFAULT NULL,
                expense_id INT DEFAULT NULL,
                created_by_id INT NOT NULL,
                confirmed_by_id INT DEFAULT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                kind VARCHAR(255) NOT NULL,
                amount NUMERIC(18, 2) NOT NULL,
                currency VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL,
                note TEXT DEFAULT NULL,
                confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_cash_entries_session ON cash_entries (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7314A1994C3A3BB ON cash_entries (payment_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7314A199F395DB7B ON cash_entries (expense_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7314A199B03A8386 ON cash_entries (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7314A1996F45385D ON cash_entries (confirmed_by_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE cash_sessions ADD CONSTRAINT FK_F8E36CDEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_sessions ADD CONSTRAINT FK_F8E36CDEAB159F5 FOREIGN KEY (opened_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_sessions ADD CONSTRAINT FK_F8E36CDEE1FA7797 FOREIGN KEY (closed_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_entries ADD CONSTRAINT FK_7314A199613FECDF FOREIGN KEY (session_id) REFERENCES cash_sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_entries ADD CONSTRAINT FK_7314A1994C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_entries ADD CONSTRAINT FK_7314A199F395DB7B FOREIGN KEY (expense_id) REFERENCES expenses (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_entries ADD CONSTRAINT FK_7314A199B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cash_entries ADD CONSTRAINT FK_7314A1996F45385D FOREIGN KEY (confirmed_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD cash_session_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BD5D1E5D5 FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2496F35BD5D1E5D5 ON expenses (cash_session_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE payments ADD cash_session_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT FK_65D29B32D5D1E5D5 FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_65D29B32D5D1E5D5 ON payments (cash_session_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE payments DROP CONSTRAINT FK_65D29B32D5D1E5D5
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_65D29B32D5D1E5D5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments DROP cash_session_id
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE expenses DROP CONSTRAINT FK_2496F35BD5D1E5D5
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_2496F35BD5D1E5D5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses DROP cash_session_id
        SQL);

        $this->addSql(<<<'SQL'
            DROP TABLE cash_entries
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE cash_sessions
        SQL);
    }
}
