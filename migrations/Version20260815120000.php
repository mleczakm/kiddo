<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve payment codes on payments for historical search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD payment_code_snapshot VARCHAR(4) DEFAULT NULL');
        $this->addSql('UPDATE payment p SET payment_code_snapshot = pc.code FROM payment_code pc WHERE pc.payment_id = p.id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP payment_code_snapshot');
    }
}
