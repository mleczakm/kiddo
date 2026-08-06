<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806204017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity_log table for the admin "Ostatnie zmiany klientów" feed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_log (id UUID NOT NULL, subject_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, dedupe_key VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary TEXT DEFAULT NULL, url VARCHAR(512) DEFAULT NULL, context JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FD06F64723EDC87 ON activity_log (subject_id)');
        $this->addSql('CREATE INDEX idx_activity_log_created_at ON activity_log (created_at)');
        $this->addSql('CREATE INDEX idx_activity_log_dedupe_key ON activity_log (dedupe_key)');
        $this->addSql('COMMENT ON COLUMN activity_log.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN activity_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64723EDC87 FOREIGN KEY (subject_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP CONSTRAINT FK_FD06F64723EDC87');
        $this->addSql('DROP TABLE activity_log');
    }
}
