<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional image (base64) and mime type to lesson metadata, for the workshop visual theme';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson ADD image_data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD image_mime_type VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson DROP image_data');
        $this->addSql('ALTER TABLE lesson DROP image_mime_type');
    }
}
