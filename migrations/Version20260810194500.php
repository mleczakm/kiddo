<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_occurrence_date to series, an alternative to cancelling a series outright';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE series ADD last_occurrence_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE series DROP last_occurrence_date');
    }
}
