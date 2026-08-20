<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820201602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refund_request table (Stage 3: correct manual refunds)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE refund_request (
              id UUID NOT NULL,
              payment_id UUID NOT NULL,
              booking_id UUID NOT NULL,
              lesson_id UUID DEFAULT NULL,
              requested_amount JSON NOT NULL,
              status VARCHAR(20) NOT NULL,
              requested_by_id INT DEFAULT NULL,
              requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              request_message TEXT DEFAULT NULL,
              decided_by_id INT DEFAULT NULL,
              decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              decision_note TEXT DEFAULT NULL,
              approved_amount JSON DEFAULT NULL,
              version INT DEFAULT 1 NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_refund_request_status_requested_at ON refund_request (status, requested_at)');
        $this->addSql('CREATE INDEX IDX_C495B1B14C3A3BB ON refund_request (payment_id)');
        $this->addSql('CREATE INDEX IDX_C495B1B13301C60 ON refund_request (booking_id)');
        $this->addSql('CREATE INDEX IDX_C495B1B1CDF80196 ON refund_request (lesson_id)');
        $this->addSql('CREATE INDEX IDX_C495B1B1B5B63A6B ON refund_request (requested_by_id)');
        $this->addSql('CREATE INDEX IDX_C495B1B1EC7C13B0 ON refund_request (decided_by_id)');
        $this->addSql('COMMENT ON COLUMN refund_request.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.payment_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.booking_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.requested_amount IS \'(DC2Type:json_document)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.decided_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refund_request.approved_amount IS \'(DC2Type:json_document)\'');
        $this->addSql(<<<'SQL'
            ALTER TABLE refund_request
              ADD CONSTRAINT FK_C495B1B14C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              ADD CONSTRAINT FK_C495B1B13301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              ADD CONSTRAINT FK_C495B1B1CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
              ADD CONSTRAINT FK_C495B1B1B5B63A6B FOREIGN KEY (requested_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
              ADD CONSTRAINT FK_C495B1B1EC7C13B0 FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT FK_C495B1B14C3A3BB');
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT FK_C495B1B13301C60');
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT FK_C495B1B1CDF80196');
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT FK_C495B1B1B5B63A6B');
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT FK_C495B1B1EC7C13B0');
        $this->addSql('DROP TABLE refund_request');
    }
}
