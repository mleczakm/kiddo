<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store buyer type and company tax identifier on customer orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE customer_order ADD buyer_type VARCHAR(20) DEFAULT 'private' NOT NULL");
        $this->addSql('ALTER TABLE customer_order ADD tax_identifier VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP buyer_type');
        $this->addSql('ALTER TABLE customer_order DROP tax_identifier');
    }
}
