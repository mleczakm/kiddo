<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable, file-backed versions of public legal documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_document (
              id UUID NOT NULL,
              type VARCHAR(40) NOT NULL,
              slug VARCHAR(80) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_document_version (
              id UUID NOT NULL,
              document_id UUID NOT NULL,
              file_id UUID NOT NULL,
              published_by_id INT DEFAULT NULL,
              version INT NOT NULL,
              checksum VARCHAR(64) NOT NULL,
              effective_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              change_summary VARCHAR(1000) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_legal_document_type ON legal_document (type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_legal_document_slug ON legal_document (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_legal_document_version ON legal_document_version (document_id, version)');
        $this->addSql('CREATE INDEX idx_legal_document_publication ON legal_document_version (document_id, published_at, effective_from)');
        $this->addSql('CREATE INDEX idx_legal_document_version_file ON legal_document_version (file_id)');
        $this->addSql('CREATE INDEX idx_legal_document_version_publisher ON legal_document_version (published_by_id)');

        $this->addSql('COMMENT ON COLUMN legal_document.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN legal_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.document_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.file_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.effective_from IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN legal_document_version.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE legal_document_version ADD CONSTRAINT fk_legal_document_version_document FOREIGN KEY (document_id) REFERENCES legal_document (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE legal_document_version ADD CONSTRAINT fk_legal_document_version_file FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE legal_document_version ADD CONSTRAINT fk_legal_document_version_publisher FOREIGN KEY (published_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE legal_document_version');
        $this->addSql('DROP TABLE legal_document');
    }
}
