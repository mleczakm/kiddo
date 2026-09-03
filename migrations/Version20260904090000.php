<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Monthly-subscription ticket type (behind the `subscriptions` feature flag,
 * off on prod): a `subscription` row per active monthly ticket, a scalar link
 * from the per-month `Booking` back to it, and a human label on booking-less
 * payments. The monthly price lives in the series' existing `ticket_options`
 * jsonb (TicketType::MONTHLY) - no dedicated column.
 */
final class Version20260904090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monthly-subscription ticket type: subscription table + booking.subscription_id + payment.description';
    }

    public function up(Schema $schema): void
    {
        // Human label for payments not tied to a booking summary - e.g. a monthly fee.
        $this->addSql('ALTER TABLE payment ADD description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD subscription_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN booking.subscription_id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE INDEX IDX_BOOKING_SUBSCRIPTION ON booking (subscription_id)');

        $this->addSql(<<<'SQL'
                CREATE TABLE subscription (
                  id UUID NOT NULL,
                  user_id INT NOT NULL,
                  series_id UUID NOT NULL,
                  child_id UUID DEFAULT NULL,
                  monthly_rate_minor INT NOT NULL,
                  currency VARCHAR(3) NOT NULL,
                  status VARCHAR(16) NOT NULL,
                  created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  last_charged_period VARCHAR(7) DEFAULT NULL,
                  version INT DEFAULT 1 NOT NULL,
                  starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                  PRIMARY KEY(id)
                )
            SQL);
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_USER ON subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_SERIES ON subscription (series_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_CHILD ON subscription (child_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_STATUS ON subscription (status)');
        $this->addSql('COMMENT ON COLUMN subscription.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.series_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.child_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.starts_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.ends_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(
            'ALTER TABLE subscription ADD CONSTRAINT FK_SUBSCRIPTION_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE subscription ADD CONSTRAINT FK_SUBSCRIPTION_SERIES FOREIGN KEY (series_id) REFERENCES series (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE subscription ADD CONSTRAINT FK_SUBSCRIPTION_CHILD FOREIGN KEY (child_id) REFERENCES child (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS subscription');
        $this->addSql('DROP INDEX IF EXISTS IDX_BOOKING_SUBSCRIPTION');
        $this->addSql('ALTER TABLE booking DROP COLUMN IF EXISTS subscription_id');
        $this->addSql('ALTER TABLE payment DROP COLUMN IF EXISTS description');
    }
}
