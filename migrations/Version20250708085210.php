<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Comprehensive migration for Choven.by project
 */
final class Version20250708085210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Complete database schema creation for Choven.by project';
    }

    public function up(Schema $schema): void
    {
        // Create users table
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id SERIAL NOT NULL, 
                email VARCHAR(255) NOT NULL, 
                password VARCHAR(255) NOT NULL, 
                roles JSON NOT NULL, 
                confirmation_code VARCHAR(20) DEFAULT NULL, 
                is_confirmed BOOLEAN NOT NULL, 
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');

        // Create orders table with user relationship
        $this->addSql(<<<'SQL'
            CREATE TABLE orders (
                id SERIAL NOT NULL, 
                user_id INT DEFAULT NULL,
                description TEXT DEFAULT NULL, 
                email VARCHAR(255) NOT NULL, 
                river VARCHAR(255) NOT NULL, 
                amount_of_people INT NOT NULL, 
                package VARCHAR(255) NOT NULL, 
                start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                duration VARCHAR(255) NOT NULL,
                locale VARCHAR(5) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN orders.duration IS \'(DC2Type:dateinterval)\'');
        
        // Add foreign key constraint from orders to users
        $this->addSql(<<<'SQL'
            ALTER TABLE orders 
            ADD CONSTRAINT FK_E52FFDEE9D86650F FOREIGN KEY (user_id) 
            REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql('CREATE INDEX IDX_E52FFDEE9D86650F ON orders (user_id)');

        // Create email_queue table
        $this->addSql(<<<'SQL'
            CREATE TABLE email_queue (
                id SERIAL NOT NULL, 
                email_type VARCHAR(255) NOT NULL, 
                context JSON NOT NULL, 
                attempts INT NOT NULL, 
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                last_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
                locale VARCHAR(50) DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN email_queue.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN email_queue.last_attempt_at IS \'(DC2Type:datetime_immutable)\'');

        // Create failed_email table
        $this->addSql(<<<'SQL'
            CREATE TABLE failed (
                id SERIAL NOT NULL, 
                email_type VARCHAR(255) NOT NULL, 
                context JSON NOT NULL, 
                error TEXT DEFAULT NULL, 
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                last_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                attempts INT NOT NULL, 
                locale VARCHAR(50) DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN failed.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN failed.last_attempt_at IS \'(DC2Type:datetime_immutable)\'');

        // Create messenger_messages table for Symfony Messenger
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGSERIAL NOT NULL, 
                body TEXT NOT NULL, 
                headers TEXT NOT NULL, 
                queue_name VARCHAR(190) NOT NULL, 
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        
        // Create notification function for Messenger
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
                BEGIN
                    PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
                    RETURN NEW;
                END;
            $$ LANGUAGE plpgsql;
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages');
        $this->addSql(<<<'SQL'
            CREATE TRIGGER notify_trigger 
            AFTER INSERT OR UPDATE ON messenger_messages 
            FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages()
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE9D86650F');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE failed');
        $this->addSql('DROP TABLE email_queue');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE users');
    }
}
