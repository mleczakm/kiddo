<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CMS posts, reusable database files, article attachments, and menu hook links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post (id UUID NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content_json JSONB NOT NULL, content_html TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, linked_workshop_slug VARCHAR(255) DEFAULT NULL, eyebrow VARCHAR(100) DEFAULT NULL, excerpt VARCHAR(320) DEFAULT NULL, seo_title VARCHAR(70) DEFAULT NULL, seo_description VARCHAR(170) DEFAULT NULL, canonical_url VARCHAR(2048) DEFAULT NULL, robots_index BOOLEAN DEFAULT TRUE NOT NULL, robots_follow BOOLEAN DEFAULT TRUE NOT NULL, social_title VARCHAR(70) DEFAULT NULL, social_description VARCHAR(200) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN post.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_post_slug ON post (slug)');
        $this->addSql('CREATE INDEX idx_post_publication ON post (status, published_at)');
        $this->addSql('CREATE INDEX IDX_POST_AUTHOR ON post (author_id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_POST_AUTHOR FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE file (id UUID NOT NULL, uploaded_by_id INT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, checksum VARCHAR(64) NOT NULL, data TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN file.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE INDEX idx_file_checksum ON file (checksum)');
        $this->addSql('CREATE INDEX IDX_FILE_UPLOADER ON file (uploaded_by_id)');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_FILE_UPLOADER FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE post_file (id UUID NOT NULL, post_id UUID NOT NULL, file_id UUID NOT NULL, role VARCHAR(20) NOT NULL, position INT NOT NULL, alt_text VARCHAR(500) DEFAULT NULL, caption VARCHAR(1000) DEFAULT NULL, download_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN post_file.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_post_file ON post_file (post_id, file_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_post_cover ON post_file (post_id) WHERE role = \'cover\'');
        $this->addSql('CREATE INDEX idx_post_file_position ON post_file (post_id, position)');
        $this->addSql('CREATE INDEX IDX_POST_FILE_FILE ON post_file (file_id)');
        $this->addSql('ALTER TABLE post_file ADD CONSTRAINT FK_POST_FILE_POST FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE post_file ADD CONSTRAINT FK_POST_FILE_FILE FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE menu_hook_link (id UUID NOT NULL, slot_key VARCHAR(100) NOT NULL, position INT NOT NULL, target_type VARCHAR(20) NOT NULL, target VARCHAR(2048) NOT NULL, label VARCHAR(255) NOT NULL, active BOOLEAN DEFAULT TRUE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN menu_hook_link.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE INDEX idx_menu_hook_slot_position ON menu_hook_link (slot_key, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post_file');
        $this->addSql('DROP TABLE menu_hook_link');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE post');
    }
}
