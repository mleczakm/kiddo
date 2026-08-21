<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 10 of the commerce rollout plan: cart/cart_item backend, behind the
 * (already-registered, disabled) cart frontend flag. The "one open cart per
 * customer/currency" rule is a partial unique index rather than a plain
 * unique constraint, since a customer can accumulate any number of
 * converted/abandoned carts over time - only one *open* one at a time.
 */
final class Version20260821030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cart and cart_item tables for the cart backend';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cart (
              id UUID NOT NULL,
              customer_id INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              status VARCHAR(20) NOT NULL,
              promotion_code VARCHAR(40) DEFAULT NULL,
              converted_order_id UUID DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart_item (
              id UUID NOT NULL,
              cart_id UUID NOT NULL,
              lesson_id UUID NOT NULL,
              ticket_type VARCHAR(30) NOT NULL,
              participant_id UUID DEFAULT NULL,
              base_price_minor INT NOT NULL,
              final_price_minor INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              pricing_quote_hash VARCHAR(64) DEFAULT NULL,
              quoted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_cart_customer_currency_status ON cart (customer_id, currency, status)');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_cart_open_customer_currency ON cart (customer_id, currency) WHERE (status = \'open\')',
        );
        $this->addSql('CREATE INDEX idx_cart_item_cart_id ON cart_item (cart_id)');
        $this->addSql('COMMENT ON COLUMN cart.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart.converted_order_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cart.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.cart_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.participant_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.quoted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cart_item.added_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE cart');
    }
}
