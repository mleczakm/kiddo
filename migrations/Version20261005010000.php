<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idempotency receipts for bank notifications received through IMAP and Cloudflare';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_mail_receipt (
                id VARCHAR(255) NOT NULL,
                received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                status VARCHAR(32) NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_bank_mail_receipt_received_at ON bank_mail_receipt (received_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bank_mail_receipt');
    }
}
