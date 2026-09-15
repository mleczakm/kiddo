<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hand-written rather than via doctrine:migrations:diff: this dev environment's
 * installed doctrine/dbal (4.4.4) is one minor below what doctrine/orm's schema
 * comparison now requires ("The setSchema() method requires the DBAL
 * Schema::edit() API... requires doctrine/dbal ^4.5 or higher"), so diff/
 * schema:update currently fail for ANY entity change, not just this one.
 * Pre-existing version mismatch, left alone here as out of scope.
 */
final class Version20260915010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create push_subscription table for Web Push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE push_subscription (
              id UUID NOT NULL,
              user_id INT NOT NULL,
              endpoint VARCHAR(512) NOT NULL,
              p256dh VARCHAR(255) NOT NULL,
              auth VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_push_subscription_endpoint ON push_subscription (endpoint)');
        $this->addSql('CREATE INDEX idx_push_subscription_user ON push_subscription (user_id)');
        $this->addSql('COMMENT ON COLUMN push_subscription.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN push_subscription.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              push_subscription
            ADD
              CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_subscription DROP CONSTRAINT FK_562830F3A76ED395');
        $this->addSql('DROP TABLE push_subscription');
    }
}
