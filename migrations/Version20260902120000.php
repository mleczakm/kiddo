<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique message_id to transfer so the same bank e-mail cannot be imported twice';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfer ADD message_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_transfer_message_id ON transfer (message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_transfer_message_id');
        $this->addSql('ALTER TABLE transfer DROP message_id');
    }
}
