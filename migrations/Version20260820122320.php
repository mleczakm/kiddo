<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820122320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workshop_file: reusable file attachments (documents, terms-of-use) for workshops';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workshop_file (id UUID NOT NULL, lesson_metadata_id UUID NOT NULL, file_id UUID NOT NULL, role VARCHAR(20) NOT NULL, position INT NOT NULL, caption VARCHAR(1000) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN workshop_file.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_workshop_file ON workshop_file (lesson_metadata_id, file_id)');
        $this->addSql('CREATE INDEX idx_workshop_file_position ON workshop_file (lesson_metadata_id, position)');
        $this->addSql('CREATE INDEX IDX_WORKSHOP_FILE_FILE ON workshop_file (file_id)');
        $this->addSql('ALTER TABLE workshop_file ADD CONSTRAINT FK_WORKSHOP_FILE_METADATA FOREIGN KEY (lesson_metadata_id) REFERENCES lesson_metadata (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE workshop_file ADD CONSTRAINT FK_WORKSHOP_FILE_FILE FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workshop_file');
    }
}
