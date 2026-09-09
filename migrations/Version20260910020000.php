<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auditable user consent records with document-version evidence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_consent (
              id UUID NOT NULL,
              user_id INT NOT NULL,
              document_version_id UUID DEFAULT NULL,
              type VARCHAR(40) NOT NULL,
              document_type VARCHAR(40) DEFAULT NULL,
              document_ref VARCHAR(255) DEFAULT NULL,
              document_checksum VARCHAR(64) DEFAULT NULL,
              granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              source VARCHAR(40) NOT NULL,
              ip VARCHAR(45) DEFAULT NULL,
              user_agent VARCHAR(1000) DEFAULT NULL,
              text_checksum VARCHAR(64) NOT NULL,
              context VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_user_consent_active ON user_consent (user_id, type, revoked_at)');
        $this->addSql('CREATE INDEX idx_user_consent_document_version ON user_consent (document_version_id)');
        $this->addSql('COMMENT ON COLUMN user_consent.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_consent.document_version_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_consent.granted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_consent.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_consent ADD CONSTRAINT fk_user_consent_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_consent ADD CONSTRAINT fk_user_consent_document_version FOREIGN KEY (document_version_id) REFERENCES legal_document_version (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_consent');
    }
}
