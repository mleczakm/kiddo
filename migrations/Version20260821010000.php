<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 8 of the commerce rollout plan: stores the full PriceQuote (every
 * adjustment applied/rejected), not just its hash, alongside each order
 * line for audit purposes.
 */
final class Version20260821010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order_line.pricing_snapshot_json for the full PriceQuote audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_line ADD pricing_snapshot_json JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_line DROP COLUMN pricing_snapshot_json');
    }
}
