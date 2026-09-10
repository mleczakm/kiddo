<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lesson waitlist: waitlist_entry table + per-lesson waitlist_enabled override';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE waitlist_entry (
              id UUID NOT NULL,
              lesson_id UUID NOT NULL,
              user_id INT NOT NULL,
              email_snapshot VARCHAR(255) NOT NULL,
              name_snapshot VARCHAR(255) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              queued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              offered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              offer_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              version INT DEFAULT 1 NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_waitlist_lesson_status_queued ON waitlist_entry (lesson_id, status, queued_at)');
        $this->addSql('CREATE INDEX idx_waitlist_status_offer_expires ON waitlist_entry (status, offer_expires_at)');
        $this->addSql('CREATE INDEX idx_waitlist_entry_user ON waitlist_entry (user_id)');

        $this->addSql('COMMENT ON COLUMN waitlist_entry.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.queued_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.offered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.offer_expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_entry.closed_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE waitlist_entry ADD CONSTRAINT fk_waitlist_entry_lesson FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE waitlist_entry ADD CONSTRAINT fk_waitlist_entry_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE lesson ADD waitlist_enabled BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson DROP waitlist_enabled');
        $this->addSql('DROP TABLE waitlist_entry');
    }
}
