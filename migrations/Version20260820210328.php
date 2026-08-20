<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 4 of the commerce rollout plan: additive order schema only.
 * No runtime code reads or writes these tables yet - dual-write for new
 * fast reservations is Stage 5, behind a disabled feature flag.
 */
final class Version20260820210328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add commerce order schema (customer_order, order_line, order_line_adjustment) and nullable bridge columns on payment/booking - additive only, unused';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_order (
              id UUID NOT NULL,
              order_number VARCHAR(32) NOT NULL,
              customer_id INT NOT NULL,
              status VARCHAR(20) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              subtotal_minor INT NOT NULL,
              discount_total_minor INT NOT NULL,
              total_minor INT NOT NULL,
              placed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              checkout_key VARCHAR(64) NOT NULL,
              source VARCHAR(20) NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              PRIMARY KEY(id),
              CONSTRAINT chk_customer_order_subtotal_non_negative CHECK (subtotal_minor >= 0),
              CONSTRAINT chk_customer_order_discount_non_negative CHECK (discount_total_minor >= 0),
              CONSTRAINT chk_customer_order_total_non_negative CHECK (total_minor >= 0)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_order_order_number ON customer_order (order_number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_order_checkout_key ON customer_order (checkout_key)');
        $this->addSql('CREATE INDEX idx_customer_order_customer_id ON customer_order (customer_id)');
        $this->addSql('CREATE INDEX idx_customer_order_status ON customer_order (status)');
        $this->addSql('CREATE INDEX idx_customer_order_placed_at ON customer_order (placed_at)');
        $this->addSql('COMMENT ON COLUMN customer_order.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN customer_order.placed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN customer_order.expires_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            CREATE TABLE order_line (
              id UUID NOT NULL,
              order_id UUID NOT NULL,
              lesson_id UUID DEFAULT NULL,
              series_id UUID DEFAULT NULL,
              ticket_type VARCHAR(30) NOT NULL,
              title VARCHAR(255) NOT NULL,
              schedule_description VARCHAR(255) DEFAULT NULL,
              participant_id UUID DEFAULT NULL,
              base_price_minor INT NOT NULL,
              final_price_minor INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              pricing_quote_hash VARCHAR(64) DEFAULT NULL,
              booking_id UUID DEFAULT NULL,
              PRIMARY KEY(id),
              CONSTRAINT chk_order_line_base_price_non_negative CHECK (base_price_minor >= 0),
              CONSTRAINT chk_order_line_final_price_non_negative CHECK (final_price_minor >= 0)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_order_line_order_id ON order_line (order_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_line_booking_id ON order_line (booking_id)');
        $this->addSql(
            'ALTER TABLE order_line ADD CONSTRAINT fk_order_line_order_id FOREIGN KEY (order_id) '
            . 'REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('COMMENT ON COLUMN order_line.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line.order_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line.series_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line.participant_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line.booking_id IS \'(DC2Type:ulid)\'');

        $this->addSql(<<<'SQL'
            CREATE TABLE order_line_adjustment (
              id UUID NOT NULL,
              order_line_id UUID NOT NULL,
              type VARCHAR(20) NOT NULL,
              amount_minor INT NOT NULL,
              label VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_order_line_adjustment_order_line_id ON order_line_adjustment (order_line_id)');
        $this->addSql(
            'ALTER TABLE order_line_adjustment ADD CONSTRAINT fk_order_line_adjustment_order_line_id '
            . 'FOREIGN KEY (order_line_id) REFERENCES order_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('COMMENT ON COLUMN order_line_adjustment.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN order_line_adjustment.order_line_id IS \'(DC2Type:ulid)\'');

        $this->addSql('ALTER TABLE payment ADD order_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN payment.order_id IS \'(DC2Type:ulid)\'');
        $this->addSql(
            'ALTER TABLE payment ADD CONSTRAINT fk_payment_order_id FOREIGN KEY (order_id) '
            . 'REFERENCES customer_order (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('CREATE INDEX idx_payment_order_id ON payment (order_id)');

        $this->addSql('ALTER TABLE booking ADD order_line_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN booking.order_line_id IS \'(DC2Type:ulid)\'');
        $this->addSql(
            'ALTER TABLE booking ADD CONSTRAINT fk_booking_order_line_id FOREIGN KEY (order_line_id) '
            . 'REFERENCES order_line (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('CREATE INDEX idx_booking_order_line_id ON booking (order_line_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT fk_booking_order_line_id');
        $this->addSql('ALTER TABLE booking DROP COLUMN order_line_id');

        $this->addSql('ALTER TABLE payment DROP CONSTRAINT fk_payment_order_id');
        $this->addSql('ALTER TABLE payment DROP COLUMN order_id');

        $this->addSql('ALTER TABLE order_line_adjustment DROP CONSTRAINT fk_order_line_adjustment_order_line_id');
        $this->addSql('DROP TABLE order_line_adjustment');

        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT fk_order_line_order_id');
        $this->addSql('DROP TABLE order_line');

        $this->addSql('DROP TABLE customer_order');
    }
}
