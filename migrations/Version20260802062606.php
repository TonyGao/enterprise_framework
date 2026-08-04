<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802062606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform_ai_chat_checkpoint table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_ai_chat_checkpoint (id UUID NOT NULL, owner_corporation_id UUID DEFAULT NULL, owner_company_id UUID DEFAULT NULL, view_id UUID NOT NULL, version VARCHAR(32) NOT NULL, message_id UUID DEFAULT NULL, snapshot_dir VARCHAR(255) NOT NULL, content_hash VARCHAR(64) DEFAULT NULL, order_num INT DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7165799EF617CBEC ON platform_ai_chat_checkpoint (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_7165799EC5F18393 ON platform_ai_chat_checkpoint (owner_company_id)');
        $this->addSql('CREATE INDEX ai_chat_checkpoint_view_idx ON platform_ai_chat_checkpoint (view_id, version)');
        $this->addSql('COMMENT ON COLUMN platform_ai_chat_checkpoint.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_chat_checkpoint.owner_corporation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_chat_checkpoint.owner_company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_chat_checkpoint.view_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_chat_checkpoint.message_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE platform_ai_chat_checkpoint ADD CONSTRAINT FK_7165799EF617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_chat_checkpoint ADD CONSTRAINT FK_7165799EC5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_checkpoint DROP CONSTRAINT FK_7165799EF617CBEC');
        $this->addSql('ALTER TABLE platform_ai_chat_checkpoint DROP CONSTRAINT FK_7165799EC5F18393');
        $this->addSql('DROP TABLE platform_ai_chat_checkpoint');
    }
}
