<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds nullable slug on lesson (shared across series occurrences with the same title).
 */
final class Version20260721004448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable slug column to lesson (shared across series occurrences).';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            $this->connection->createSchemaManager()->tablesExist(['lesson'])
                && in_array('slug', array_map(
                    static fn ($column) => $column->getName(),
                    $this->connection->createSchemaManager()->listTableColumns('lesson')
                ), true),
            'lesson.slug already present — skipping.'
        );

        $this->addSql('ALTER TABLE lesson ADD slug VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson DROP slug');
    }
}
