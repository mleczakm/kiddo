<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251108211800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add child table and optional booking.child_id reference';
    }

    public function up(Schema $schema): void
    {
        // Child table
        $this->addSql('CREATE TABLE child (id INT NOT NULL, owner_id INT NOT NULL, name VARCHAR(120) NOT NULL, birthday DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_22B3549C7E3C61F9 ON child (owner_id)');
        $this->addSql("COMMENT ON COLUMN child.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE child ADD CONSTRAINT FK_22B3549C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Booking -> child
        $this->addSql('ALTER TABLE booking ADD child_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_E00CEDDEDD62C21B ON booking (child_id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEDD62C21B FOREIGN KEY (child_id) REFERENCES child (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEDD62C21B');
        $this->addSql('DROP INDEX IDX_E00CEDDEDD62C21B');
        $this->addSql('ALTER TABLE booking DROP child_id');

        $this->addSql('ALTER TABLE child DROP CONSTRAINT FK_22B3549C7E3C61F9');
        $this->addSql('DROP INDEX IDX_22B3549C7E3C61F9');
        $this->addSql('DROP TABLE child');
    }
}
