<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track refund requests and payment status decisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD refund_requested_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD status_changed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD refund_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD refund_request_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD refund_requested_via_user_panel BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE payment ADD status_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD status_note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_PAYMENT_REFUND_REQUESTED_BY FOREIGN KEY (refund_requested_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_PAYMENT_STATUS_CHANGED_BY FOREIGN KEY (status_changed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_PAYMENT_REFUND_REQUESTED_BY ON payment (refund_requested_by_id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT_STATUS_CHANGED_BY ON payment (status_changed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_PAYMENT_REFUND_REQUESTED_BY');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_PAYMENT_STATUS_CHANGED_BY');
        $this->addSql('DROP INDEX IDX_PAYMENT_REFUND_REQUESTED_BY');
        $this->addSql('DROP INDEX IDX_PAYMENT_STATUS_CHANGED_BY');
        $this->addSql('ALTER TABLE payment DROP refund_requested_by_id');
        $this->addSql('ALTER TABLE payment DROP status_changed_by_id');
        $this->addSql('ALTER TABLE payment DROP refund_requested_at');
        $this->addSql('ALTER TABLE payment DROP refund_request_message');
        $this->addSql('ALTER TABLE payment DROP refund_requested_via_user_panel');
        $this->addSql('ALTER TABLE payment DROP status_changed_at');
        $this->addSql('ALTER TABLE payment DROP status_note');
    }
}
