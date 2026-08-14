<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814062041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.first_name (required) and users.last_name (optional)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD first_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD last_name VARCHAR(255) DEFAULT NULL
        SQL);
        // Backfill existing rows (email local-part) so first_name can be made NOT NULL below.
        $this->addSql(<<<'SQL'
            UPDATE users SET first_name = split_part(email, '@', 1) WHERE first_name IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER COLUMN first_name SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER COLUMN first_name DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP first_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP last_name
        SQL);
    }
}
