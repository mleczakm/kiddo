<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user opt-out from operational (admin) emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD operational_emails BOOLEAN DEFAULT TRUE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP operational_emails');
    }
}
