<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add transactional promotion redemption reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE promotion_redemption (
              id UUID NOT NULL,
              pricing_rule_id UUID NOT NULL,
              order_id UUID NOT NULL,
              order_line_id UUID NOT NULL,
              customer_id INT NOT NULL,
              status VARCHAR(20) NOT NULL,
              reserved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_promotion_redemption_line_rule ON promotion_redemption (order_line_id, pricing_rule_id)');
        $this->addSql('CREATE INDEX idx_promotion_redemption_rule_status ON promotion_redemption (pricing_rule_id, status)');
        $this->addSql('CREATE INDEX idx_promotion_redemption_customer ON promotion_redemption (pricing_rule_id, customer_id, status)');
        $this->addSql('CREATE INDEX idx_promotion_redemption_order ON promotion_redemption (order_id)');
        $this->addSql('ALTER TABLE promotion_redemption ADD CONSTRAINT fk_promotion_redemption_rule FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rule (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE promotion_redemption ADD CONSTRAINT fk_promotion_redemption_order FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE promotion_redemption ADD CONSTRAINT fk_promotion_redemption_line FOREIGN KEY (order_line_id) REFERENCES order_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN promotion_redemption.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.pricing_rule_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.order_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.order_line_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.reserved_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.consumed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN promotion_redemption.released_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE promotion_redemption');
    }
}
