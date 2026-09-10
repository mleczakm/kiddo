<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'legal_document_version: add notified_at, drop redundant created_at (== published_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE legal_document_version ADD notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN legal_document_version.notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE legal_document_version DROP created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE legal_document_version ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now()',
        );
        $this->addSql('ALTER TABLE legal_document_version ALTER created_at DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN legal_document_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE legal_document_version DROP notified_at');
    }
}
