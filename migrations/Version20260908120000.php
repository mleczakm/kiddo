<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_feed_token to user for the staff ICS subscription feed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD calendar_feed_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_calendar_feed_token ON "user" (calendar_feed_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_calendar_feed_token');
        $this->addSql('ALTER TABLE "user" DROP calendar_feed_token');
    }
}
