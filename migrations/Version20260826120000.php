<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add independently controlled visibility to workshop series and lessons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson ADD visible BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE series ADD visible BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson DROP visible');
        $this->addSql('ALTER TABLE series DROP visible');
    }
}
