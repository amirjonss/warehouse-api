<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260818070838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates DROP CONSTRAINT exchange_rates_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD id SERIAL NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates DROP rate_date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ALTER rate_buy TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ALTER rate_sell TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD PRIMARY KEY (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX exchange_rates_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD rate_date DATE NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates DROP id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ALTER rate_buy TYPE NUMERIC(12, 4)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ALTER rate_sell TYPE NUMERIC(12, 4)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD PRIMARY KEY (rate_date)
        SQL);
    }
}
