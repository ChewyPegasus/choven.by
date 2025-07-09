<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250709144838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a unique phone field with a length of 20 characters to the users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_PHONE ON users (phone)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USERS_PHONE ON users');
        $this->addSql('ALTER TABLE users DROP COLUMN phone');
    }
}
