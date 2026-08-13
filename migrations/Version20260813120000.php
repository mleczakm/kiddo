<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pg_trgm indexes for ranked admin global search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql(
            'CREATE INDEX idx_search_user ON "user" USING gin (lower(name || \' \' || email || \' \' || COALESCE(phone, \'\') || \' \' || COALESCE(admin_note, \'\') || \' \' || id::text) gin_trgm_ops)'
        );
        $this->addSql(
            'CREATE INDEX idx_search_child ON child USING gin (lower(name || \' \' || id::text) gin_trgm_ops)'
        );
        $this->addSql(
            'CREATE INDEX idx_search_booking ON booking USING gin (lower(id::text || \' \' || status || \' \' || COALESCE(notes, \'\')) gin_trgm_ops)'
        );
        $this->addSql(
            'CREATE INDEX idx_search_lesson_metadata ON lesson_metadata USING gin (lower(title || \' \' || lead || \' \' || category || \' \' || description) gin_trgm_ops)'
        );
        $this->addSql(
            'CREATE INDEX idx_search_transfer ON transfer USING gin (lower(id::text || \' \' || sender || \' \' || title || \' \' || account_number || \' \' || amount) gin_trgm_ops)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_search_user');
        $this->addSql('DROP INDEX IF EXISTS idx_search_child');
        $this->addSql('DROP INDEX IF EXISTS idx_search_booking');
        $this->addSql('DROP INDEX IF EXISTS idx_search_lesson_metadata');
        $this->addSql('DROP INDEX IF EXISTS idx_search_transfer');
    }
}
