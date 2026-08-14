<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814053313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.token_version to allow revoking issued JWTs on password change/delete/logout';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD token_version INT DEFAULT 0 NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP token_version
        SQL);
    }
}
