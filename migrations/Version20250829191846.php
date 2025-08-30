<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829191846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_messages (id UUID NOT NULL, user_id INT NOT NULL, read_by_id INT DEFAULT NULL, related_booking_id UUID DEFAULT NULL, related_lesson_id UUID DEFAULT NULL, subject VARCHAR(255) NOT NULL, message TEXT NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, admin_notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3B8FFA96A76ED395 ON user_messages (user_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA96F5675CD0 ON user_messages (read_by_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA9689FD14D0 ON user_messages (related_booking_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA9665395D8D ON user_messages (related_lesson_id)');
        $this->addSql('COMMENT ON COLUMN user_messages.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.related_booking_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.related_lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT FK_3B8FFA96A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT FK_3B8FFA96F5675CD0 FOREIGN KEY (read_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT FK_3B8FFA9689FD14D0 FOREIGN KEY (related_booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_messages ADD CONSTRAINT FK_3B8FFA9665395D8D FOREIGN KEY (related_lesson_id) REFERENCES lesson (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking DROP rescheduled_at');
        $this->addSql('ALTER TABLE booking DROP cancelled_at');
        $this->addSql('ALTER TABLE booking DROP refunded_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA96A76ED395');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA96F5675CD0');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA9689FD14D0');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA9665395D8D');
        $this->addSql('DROP TABLE user_messages');
        $this->addSql('ALTER TABLE booking ADD rescheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN booking.rescheduled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.cancelled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.refunded_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
