<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021221907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember when the trip reminder email was sent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP reminder_sent_at');
    }
}
