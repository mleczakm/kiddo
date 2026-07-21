<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline schema matching production before the lesson slug column.
 * Safe to run on existing databases: skips when core tables already exist.
 */
final class Version20260721004421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create baseline application schema (pre-slug).';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            $this->connection->createSchemaManager()->tablesExist(['lesson']),
            'Baseline schema already present — skipping (existing production/dev database).'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE booking (
              id UUID NOT NULL,
              cancelled_by_id INT DEFAULT NULL,
              child_id UUID DEFAULT NULL,
              user_id INT NOT NULL,
              payment_id UUID DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              lessons_map JSON DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              notes VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_E00CEDDE187B2D12 ON booking (cancelled_by_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEDD62C21B ON booking (child_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEA76ED395 ON booking (user_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE4C3A3BB ON booking (payment_id)');
        $this->addSql('COMMENT ON COLUMN booking.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN booking.child_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN booking.payment_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN booking.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN booking.lessons_map IS \'(DC2Type:lesson_map)\'');
        $this->addSql('COMMENT ON COLUMN booking.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE booking_lesson (
              booking_id UUID NOT NULL,
              lesson_id UUID NOT NULL,
              PRIMARY KEY(booking_id, lesson_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_43EE4F0D3301C60 ON booking_lesson (booking_id)');
        $this->addSql('CREATE INDEX IDX_43EE4F0DCDF80196 ON booking_lesson (lesson_id)');
        $this->addSql('COMMENT ON COLUMN booking_lesson.booking_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN booking_lesson.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE child (
              id UUID NOT NULL,
              owner_id INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              name VARCHAR(120) NOT NULL,
              birthday DATE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_22B354297E3C61F9 ON child (owner_id)');
        $this->addSql('COMMENT ON COLUMN child.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN child.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN child.birthday IS \'(DC2Type:date_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE lesson (
              id UUID NOT NULL,
              series_id UUID DEFAULT NULL,
              status VARCHAR(255) DEFAULT 'active' NOT NULL,
              ticket_options JSONB DEFAULT '[]' NOT NULL,
              title VARCHAR(255) NOT NULL,
              lead TEXT NOT NULL,
              visual_theme VARCHAR(255) NOT NULL,
              description TEXT NOT NULL,
              capacity INT NOT NULL,
              schedule TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              duration INT NOT NULL,
              category VARCHAR(50) NOT NULL,
              age_range_min INT NOT NULL,
              age_range_max INT NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_F87474F35278319C ON lesson (series_id)');
        $this->addSql('COMMENT ON COLUMN lesson.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN lesson.series_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN lesson.ticket_options IS \'(DC2Type:json_document)\'');
        $this->addSql('COMMENT ON COLUMN lesson.schedule IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE notification (
              id UUID NOT NULL,
              user_id INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              severity VARCHAR(255) NOT NULL,
              title VARCHAR(255) NOT NULL,
              body TEXT DEFAULT NULL,
              url VARCHAR(512) DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_user_state_created ON notification (
              user_id, read_at, deleted_at, created_at
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN notification.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN notification.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notification.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notification.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (
              id UUID NOT NULL,
              user_id INT NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              amount JSON NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_6D28840DA76ED395 ON payment (user_id)');
        $this->addSql('COMMENT ON COLUMN payment.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN payment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payment.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payment.amount IS \'(DC2Type:json_document)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_code (
              id SERIAL NOT NULL,
              payment_id UUID NOT NULL,
              code VARCHAR(4) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5696A7EC4C3A3BB ON payment_code (payment_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_code ON payment_code (code)');
        $this->addSql('COMMENT ON COLUMN payment_code.payment_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN payment_code.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE series (
              id UUID NOT NULL,
              type VARCHAR(255) NOT NULL,
              ticket_options JSONB DEFAULT '[]' NOT NULL,
              status VARCHAR(255) DEFAULT 'active' NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN series.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN series.ticket_options IS \'(DC2Type:json_document)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE setting (
              id UUID NOT NULL,
              key VARCHAR(255) NOT NULL,
              content JSONB DEFAULT '{}' NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN setting.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN setting.content IS \'(DC2Type:json_document)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE transfer (
              id SERIAL NOT NULL,
              payment_id UUID DEFAULT NULL,
              account_number VARCHAR(255) NOT NULL,
              sender VARCHAR(255) NOT NULL,
              title VARCHAR(255) NOT NULL,
              amount VARCHAR(255) NOT NULL,
              transferred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4034A3C04C3A3BB ON transfer (payment_id)');
        $this->addSql('COMMENT ON COLUMN transfer.payment_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN transfer.transferred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (
              id SERIAL NOT NULL,
              roles JSONB DEFAULT '[]' NOT NULL,
              email VARCHAR(255) NOT NULL,
              phone VARCHAR(35) DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('COMMENT ON COLUMN "user".phone IS \'(DC2Type:phone_number)\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".confirmed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_messages (
              id UUID NOT NULL,
              read_by_id INT DEFAULT NULL,
              related_booking_id UUID DEFAULT NULL,
              related_lesson_id UUID DEFAULT NULL,
              user_id INT NOT NULL,
              status VARCHAR(50) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              admin_notes TEXT DEFAULT NULL,
              subject VARCHAR(255) NOT NULL,
              message TEXT NOT NULL,
              type VARCHAR(50) NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_3B8FFA96F5675CD0 ON user_messages (read_by_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA9689FD14D0 ON user_messages (related_booking_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA9665395D8D ON user_messages (related_lesson_id)');
        $this->addSql('CREATE INDEX IDX_3B8FFA96A76ED395 ON user_messages (user_id)');
        $this->addSql('COMMENT ON COLUMN user_messages.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.related_booking_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.related_lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_messages.read_at IS \'(DC2Type:datetime_immutable)\'');
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
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (
              queue_name, available_at, delivered_at,
              id
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE cache_items (
              item_id VARCHAR(255) NOT NULL,
              item_data BYTEA NOT NULL,
              item_lifetime INT DEFAULT NULL,
              item_time INT NOT NULL,
              PRIMARY KEY(item_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
              sess_id VARCHAR(128) NOT NULL,
              sess_data BYTEA NOT NULL,
              sess_lifetime INT NOT NULL,
              sess_time INT NOT NULL,
              PRIMARY KEY(sess_id)
            )
        SQL);
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking
            ADD
              CONSTRAINT FK_E00CEDDE187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking
            ADD
              CONSTRAINT FK_E00CEDDEDD62C21B FOREIGN KEY (child_id) REFERENCES child (id) ON DELETE
            SET
              NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking
            ADD
              CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking
            ADD
              CONSTRAINT FK_E00CEDDE4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking_lesson
            ADD
              CONSTRAINT FK_43EE4F0D3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              booking_lesson
            ADD
              CONSTRAINT FK_43EE4F0DCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              child
            ADD
              CONSTRAINT FK_22B354297E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              lesson
            ADD
              CONSTRAINT FK_F87474F35278319C FOREIGN KEY (series_id) REFERENCES series (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              notification
            ADD
              CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment
            ADD
              CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment_code
            ADD
              CONSTRAINT FK_5696A7EC4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer
            ADD
              CONSTRAINT FK_4034A3C04C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE
            SET
              NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_messages
            ADD
              CONSTRAINT FK_3B8FFA96F5675CD0 FOREIGN KEY (read_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_messages
            ADD
              CONSTRAINT FK_3B8FFA9689FD14D0 FOREIGN KEY (related_booking_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_messages
            ADD
              CONSTRAINT FK_3B8FFA9665395D8D FOREIGN KEY (related_lesson_id) REFERENCES lesson (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_messages
            ADD
              CONSTRAINT FK_3B8FFA96A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE187B2D12');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEDD62C21B');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEA76ED395');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE4C3A3BB');
        $this->addSql('ALTER TABLE booking_lesson DROP CONSTRAINT FK_43EE4F0D3301C60');
        $this->addSql('ALTER TABLE booking_lesson DROP CONSTRAINT FK_43EE4F0DCDF80196');
        $this->addSql('ALTER TABLE child DROP CONSTRAINT FK_22B354297E3C61F9');
        $this->addSql('ALTER TABLE lesson DROP CONSTRAINT FK_F87474F35278319C');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE payment_code DROP CONSTRAINT FK_5696A7EC4C3A3BB');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C04C3A3BB');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA96F5675CD0');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA9689FD14D0');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA9665395D8D');
        $this->addSql('ALTER TABLE user_messages DROP CONSTRAINT FK_3B8FFA96A76ED395');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE booking_lesson');
        $this->addSql('DROP TABLE child');
        $this->addSql('DROP TABLE lesson');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payment_code');
        $this->addSql('DROP TABLE series');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE transfer');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE user_messages');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE cache_items');
        $this->addSql('DROP TABLE sessions');
    }
}
