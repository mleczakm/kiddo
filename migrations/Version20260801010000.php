<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable unique telegram_chat_id on user for Telegram channel linking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USER_TELEGRAM_CHAT_ID ON "user" (telegram_chat_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_USER_TELEGRAM_CHAT_ID');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS telegram_chat_id');
    }
}
