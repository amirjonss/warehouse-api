<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814052136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize users.email (trim/lowercase) and enforce uniqueness at the DB level';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE users SET email = lower(btrim(email))
        SQL);
        // Functional index rather than a plain UNIQUE(email): app code normalizes via
        // User::setEmail() before persisting, but this guards case/whitespace duplicates
        // even from writes that bypass the entity (raw SQL, other services).
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (lower(btrim(email)))
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_1483A5E9E7927C74
        SQL);
    }
}
