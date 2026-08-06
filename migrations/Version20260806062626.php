<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806062626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add finance contacts, multi-instructor support, booking approval fields, and payment method';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE finance_contact (id UUID NOT NULL, user_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A1F00A9CA76ED395 ON finance_contact (user_id)');
        $this->addSql('COMMENT ON COLUMN finance_contact.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN finance_contact.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE lesson_instructor (lesson_id UUID NOT NULL, user_id INT NOT NULL, PRIMARY KEY(lesson_id, user_id))');
        $this->addSql('CREATE INDEX IDX_3CF50A0CCDF80196 ON lesson_instructor (lesson_id)');
        $this->addSql('CREATE INDEX IDX_3CF50A0CA76ED395 ON lesson_instructor (user_id)');
        $this->addSql('COMMENT ON COLUMN lesson_instructor.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE TABLE series_instructor (series_id UUID NOT NULL, user_id INT NOT NULL, PRIMARY KEY(series_id, user_id))');
        $this->addSql('CREATE INDEX IDX_48B0178F5278319C ON series_instructor (series_id)');
        $this->addSql('CREATE INDEX IDX_48B0178FA76ED395 ON series_instructor (user_id)');
        $this->addSql('COMMENT ON COLUMN series_instructor.series_id IS \'(DC2Type:ulid)\'');
        $this->addSql('ALTER TABLE finance_contact ADD CONSTRAINT FK_A1F00A9CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE lesson_instructor ADD CONSTRAINT FK_3CF50A0CCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE lesson_instructor ADD CONSTRAINT FK_3CF50A0CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE series_instructor ADD CONSTRAINT FK_48B0178F5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE series_instructor ADD CONSTRAINT FK_48B0178FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD approved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN booking.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE2D234F6A FOREIGN KEY (approved_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E00CEDDE2D234F6A ON booking (approved_by_id)');
        $this->addSql('ALTER TABLE payment ADD method VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ALTER newsletter_subscribed DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER newsletter_consent_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN "user".newsletter_consent_date IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE finance_contact DROP CONSTRAINT FK_A1F00A9CA76ED395');
        $this->addSql('ALTER TABLE lesson_instructor DROP CONSTRAINT FK_3CF50A0CCDF80196');
        $this->addSql('ALTER TABLE lesson_instructor DROP CONSTRAINT FK_3CF50A0CA76ED395');
        $this->addSql('ALTER TABLE series_instructor DROP CONSTRAINT FK_48B0178F5278319C');
        $this->addSql('ALTER TABLE series_instructor DROP CONSTRAINT FK_48B0178FA76ED395');
        $this->addSql('DROP TABLE finance_contact');
        $this->addSql('DROP TABLE lesson_instructor');
        $this->addSql('DROP TABLE series_instructor');
        $this->addSql('ALTER TABLE payment DROP method');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE2D234F6A');
        $this->addSql('DROP INDEX IDX_E00CEDDE2D234F6A');
        $this->addSql('ALTER TABLE booking DROP approved_by_id');
        $this->addSql('ALTER TABLE booking DROP approved_at');
        $this->addSql('ALTER TABLE "user" ALTER newsletter_subscribed SET DEFAULT false');
        $this->addSql('ALTER TABLE "user" ALTER newsletter_consent_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN "user".newsletter_consent_date IS NULL');
    }
}
