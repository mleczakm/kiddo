<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the Messages admin inbox feature (UserMessage entity), removed in
 * favor of the existing in-app notification system.
 */
final class Version20260810220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop user_messages table (Messages admin feature removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT fk_3b8ffa9665395d8d');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT fk_3b8ffa9689fd14d0');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT fk_3b8ffa96a76ed395');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT fk_3b8ffa96f5675cd0');
        $this->addSql('DROP TABLE user_messages');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_messages (id UUID NOT NULL, read_by_id INT DEFAULT NULL, related_booking_id UUID DEFAULT NULL, related_lesson_id UUID DEFAULT NULL, user_id INT NOT NULL, status VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, admin_notes TEXT DEFAULT NULL, subject VARCHAR(255) NOT NULL, message TEXT NOT NULL, type VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_3b8ffa9665395d8d ON user_messages (related_lesson_id)');
        $this->addSql('CREATE INDEX idx_3b8ffa9689fd14d0 ON user_messages (related_booking_id)');
        $this->addSql('CREATE INDEX idx_3b8ffa96a76ed395 ON user_messages (user_id)');
        $this->addSql('CREATE INDEX idx_3b8ffa96f5675cd0 ON user_messages (read_by_id)');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT fk_3b8ffa9665395d8d FOREIGN KEY (related_lesson_id) REFERENCES lesson (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT fk_3b8ffa9689fd14d0 FOREIGN KEY (related_booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT fk_3b8ffa96a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT fk_3b8ffa96f5675cd0 FOREIGN KEY (read_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
