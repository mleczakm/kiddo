<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\LessonMetadata;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop incorrect unique slug index (series share titles) and backfill slugs from titles.
 */
final class Version20260721010203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unique lesson.slug index and backfill slugs from titles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_F87474F3989D9B62');
        $this->addSql('DROP INDEX IF EXISTS uniq_f87474f3989d9b62');
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title FROM lesson WHERE slug IS NULL OR slug = ''"
        );

        foreach ($rows as $row) {
            $this->connection->update(
                'lesson',
                [
                    'slug' => LessonMetadata::slugify((string) $row['title']),
                ],
                [
                    'id' => $row['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F87474F3989D9B62 ON lesson (slug)');
    }
}
