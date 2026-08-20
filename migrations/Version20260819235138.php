<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819235138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visibility to posts (public / logged-in / staff-only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE post ADD visibility VARCHAR(20) DEFAULT 'public' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP visibility');
    }
}
