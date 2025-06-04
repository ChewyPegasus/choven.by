<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание структуры таблицы orders на основе Entity класса Order';
    }

    public function up(Schema $schema): void
    {
        // Создание таблицы
        $this->addSql('CREATE TABLE orders (
                id SERIAL PRIMARY KEY,
                description TEXT DEFAULT NULL,
                email VARCHAR(255) NOT NULL,
                river VARCHAR(50) NOT NULL,
                amount_of_people INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                duration VARCHAR(100) NOT NULL
            )');

        // Индексы (каждый в отдельном вызове)
        $this->addSql('CREATE INDEX idx_orders_river ON orders (river)');
        $this->addSql('CREATE INDEX idx_orders_type ON orders (type)');
        $this->addSql('CREATE INDEX idx_orders_start_date ON orders (start_date)');

        // Ограничения (каждое в отдельном вызове)
        $this->addSql('ALTER TABLE orders 
            ADD CONSTRAINT chk_orders_river CHECK (
                river IN (\'svisloch\', \'isloch\', \'uzlyanka\', \'narochanka\', \'stracha\', \'saryanka\', 
                          \'sluch\', \'viliya\', \'sula\', \'usa\', \'smerd\', \'other\')
            )');
            
        $this->addSql('ALTER TABLE orders 
            ADD CONSTRAINT chk_orders_type CHECK (
                type IN (\'all_inclusive\', \'minimum\', \'rent_only\', \'other\')
            )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS orders CASCADE');
    }
}