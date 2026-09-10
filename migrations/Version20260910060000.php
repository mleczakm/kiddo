<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Account lifecycle timestamps on user: deactivated_at, deletion_requested_at, anonymized_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD deletion_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD anonymized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".deactivated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".deletion_requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".anonymized_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP deactivated_at');
        $this->addSql('ALTER TABLE "user" DROP deletion_requested_at');
        $this->addSql('ALTER TABLE "user" DROP anonymized_at');
    }
}
