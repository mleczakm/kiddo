<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022010000 extends AbstractMigration
{
    private const string SEED_ID = '019a0b91-2a00-7000-8000-000000000001';

    public function getDescription(): string
    {
        return 'Import 20 real parent recommendations transcribed from supplied Google and Facebook screenshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO setting (id, key, content) SELECT :id, :key, :content WHERE NOT EXISTS (SELECT 1 FROM setting WHERE key = :key)',
            ['id' => self::SEED_ID, 'key' => 'recommendations', 'content' => $this->seedContent()],
        );
    }

    public function down(Schema $schema): void
    {
        // Keep any reviews subsequently edited by an administrator.
        $this->addSql(
            'DELETE FROM setting WHERE id = :id AND key = :key AND content::jsonb = CAST(:content AS jsonb)',
            ['id' => self::SEED_ID, 'key' => 'recommendations', 'content' => $this->seedContent()],
        );
    }

    private function seedContent(): string
    {
        $content = file_get_contents(__DIR__ . '/data/recommendations.json');
        if ($content === false) {
            throw new \RuntimeException('The recommendations seed file is missing.');
        }

        return $content;
    }
}
