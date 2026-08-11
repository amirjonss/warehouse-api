<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811112924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expenses table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE expenses (id SERIAL NOT NULL, created_by_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, doc_date DATE NOT NULL, description TEXT NOT NULL, amount NUMERIC(18, 2) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2496F35BB03A8386 ON expenses (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expenses DROP CONSTRAINT FK_2496F35BB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE expenses
        SQL);
    }
}
