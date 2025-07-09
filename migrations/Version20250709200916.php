<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250709200916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configure phone field for libphonenumber with proper constraints and make it NOT NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_phone');
        $this->addSql("UPDATE users SET phone = '+375290000000' WHERE phone IS NULL OR phone = ''");
        $this->addSql('ALTER TABLE users ALTER COLUMN phone SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9444F97DD ON users (phone)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN phone DROP NOT NULL');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_1483A5E9444F97DD');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_phone ON users (phone)');
    }
}
