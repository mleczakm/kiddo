<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821050000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add normalized booking occurrences'; }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE booking_occurrence (
              id UUID NOT NULL, booking_id UUID NOT NULL, lesson_id UUID NOT NULL,
              rescheduled_to_id UUID DEFAULT NULL, cancelled_by_id INT DEFAULT NULL,
              status VARCHAR(20) NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              cancellation_reason TEXT DEFAULT NULL, PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_booking_occurrence_booking_lesson ON booking_occurrence (booking_id, lesson_id)');
        $this->addSql('CREATE INDEX idx_booking_occurrence_lesson_status ON booking_occurrence (lesson_id, status)');
        $this->addSql('CREATE INDEX IDX_BOOKING_OCCURRENCE_BOOKING ON booking_occurrence (booking_id)');
        $this->addSql('ALTER TABLE booking_occurrence ADD CONSTRAINT FK_BOOKING_OCCURRENCE_BOOKING FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking_occurrence ADD CONSTRAINT FK_BOOKING_OCCURRENCE_LESSON FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking_occurrence ADD CONSTRAINT FK_BOOKING_OCCURRENCE_RESCHEDULED FOREIGN KEY (rescheduled_to_id) REFERENCES lesson (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking_occurrence ADD CONSTRAINT FK_BOOKING_OCCURRENCE_CANCELLED_BY FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        foreach (['id', 'booking_id', 'lesson_id', 'rescheduled_to_id'] as $column) {
            $this->addSql(sprintf("COMMENT ON COLUMN booking_occurrence.%s IS '(DC2Type:ulid)'", $column));
        }
        $this->addSql("COMMENT ON COLUMN booking_occurrence.cancelled_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE booking_occurrence'); }
}
