<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250611030518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_package');
        $this->addSql('ALTER TABLE orders 
            ADD CONSTRAINT chk_orders_package CHECK (
                package IN (\'all_inclusive\', \'minimum\', \'rent_only\', \'corporate\', \'other\')
            )');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_1483A5E9E7927C74
        SQL);
    }
}
