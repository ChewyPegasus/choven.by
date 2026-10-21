<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250709200916 extends AbstractMigration
{
    public function getDescription(): string
    {
        // The original version filled every empty phone with the same placeholder and made the column
        // NOT NULL, which broke the unique index as soon as two users had no phone. Phone stays optional.
        return 'Configure the unique index of the phone field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_phone');
        $this->addSql("UPDATE users SET phone = NULL WHERE phone = ''");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9444F97DD ON users (phone)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_1483A5E9444F97DD');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_phone ON users (phone)');
    }
}
