<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260805133608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add newsletter fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD newsletter_subscribed BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE "user" ADD newsletter_consent_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP newsletter_subscribed');
        $this->addSql('ALTER TABLE "user" DROP newsletter_consent_date');
    }
}
