<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810172225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin_note to user, editable from the admin user detail page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD admin_note VARCHAR(1000) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP admin_note');
    }
}
