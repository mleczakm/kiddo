<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 9 of the commerce rollout plan: persists PricingRule so it can be
 * managed through the new admin CRUD (behind the pricing_admin flag).
 */
final class Version20260821020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_rule table for admin-managed pricing rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pricing_rule (
              id UUID NOT NULL,
              name VARCHAR(255) NOT NULL,
              adjustment_type VARCHAR(20) NOT NULL,
              adjustment_value INT NOT NULL,
              priority INT NOT NULL,
              stackable BOOLEAN NOT NULL,
              exclusivity_group VARCHAR(60) DEFAULT NULL,
              user_id INT DEFAULT NULL,
              series_id UUID DEFAULT NULL,
              lesson_id UUID DEFAULT NULL,
              ticket_type VARCHAR(30) DEFAULT NULL,
              promotion_code VARCHAR(40) DEFAULT NULL,
              valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              valid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              usage_limit INT DEFAULT NULL,
              per_user_limit INT DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pricing_rule_status ON pricing_rule (status)');
        $this->addSql('CREATE INDEX idx_pricing_rule_exclusivity_group ON pricing_rule (exclusivity_group)');
        $this->addSql('CREATE INDEX idx_pricing_rule_promotion_code ON pricing_rule (promotion_code)');
        $this->addSql('COMMENT ON COLUMN pricing_rule.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule.series_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule.lesson_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule.valid_from IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule.valid_until IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pricing_rule');
    }
}
