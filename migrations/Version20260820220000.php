<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 6 of the commerce rollout plan: adds an admin-review marker set when
 * a payment is matched paid by a transfer (or cumulative transfers)
 * exceeding the amount owed.
 */
final class Version20260820220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment.flagged_for_review for overpayment/admin-review marking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD flagged_for_review BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP COLUMN flagged_for_review');
    }
}
