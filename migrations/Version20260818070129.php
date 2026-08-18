<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260818070129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_3af34668989d9b62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE categories DROP slug
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE categories DROP sort_order
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD created_by_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates ADD CONSTRAINT FK_5AE3E774B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5AE3E774B03A8386 ON exchange_rates (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP sku
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD sku VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE categories ADD slug VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE categories ADD sort_order INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_3af34668989d9b62 ON categories (slug)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates DROP CONSTRAINT FK_5AE3E774B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_5AE3E774B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE exchange_rates DROP created_by_id
        SQL);
    }
}
