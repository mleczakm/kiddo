<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821060000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add order-line and occurrence scope to refund requests'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refund_request ADD order_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_request ADD order_line_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_request ADD occurrence_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_request ADD requested_amount_minor INT DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_request ADD approved_amount_minor INT DEFAULT NULL');
        $this->addSql('ALTER TABLE refund_request ADD currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_refund_request_order_line ON refund_request (order_line_id)');
        $this->addSql('CREATE INDEX idx_refund_request_occurrence ON refund_request (occurrence_id)');
        $this->addSql('ALTER TABLE refund_request ADD CONSTRAINT fk_refund_request_occurrence FOREIGN KEY (occurrence_id) REFERENCES booking_occurrence (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        foreach (['order_id', 'order_line_id', 'occurrence_id'] as $column) {
            $this->addSql(sprintf("COMMENT ON COLUMN refund_request.%s IS '(DC2Type:ulid)'", $column));
        }
        $this->addSql(<<<'SQL'
            UPDATE refund_request r
            SET order_id = p.order_id,
                order_line_id = b.order_line_id,
                requested_amount_minor = ((r.requested_amount ->> 'amount')::numeric * 100)::int,
                currency = COALESCE(r.requested_amount -> 'currency' ->> 'currencyCode', 'PLN')
            FROM payment p, booking b
            WHERE r.payment_id = p.id AND r.booking_id = b.id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refund_request DROP CONSTRAINT fk_refund_request_occurrence');
        $this->addSql('ALTER TABLE refund_request DROP order_id, DROP order_line_id, DROP occurrence_id, DROP requested_amount_minor, DROP approved_amount_minor, DROP currency');
    }
}
