<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801131157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform_view_version table and View.current_version column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_view_version (id UUID NOT NULL, view_id UUID DEFAULT NULL, owner_corporation_id UUID DEFAULT NULL, owner_company_id UUID DEFAULT NULL, version VARCHAR(32) NOT NULL, label VARCHAR(255) DEFAULT NULL, is_current BOOLEAN DEFAULT false NOT NULL, order_num INT DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DEBC577431518C7 ON platform_view_version (view_id)');
        $this->addSql('CREATE INDEX IDX_DEBC5774F617CBEC ON platform_view_version (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_DEBC5774C5F18393 ON platform_view_version (owner_company_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_view_version ON platform_view_version (view_id, version)');
        $this->addSql('COMMENT ON COLUMN platform_view_version.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_view_version.view_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_view_version.owner_corporation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_view_version.owner_company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE platform_view_version ADD CONSTRAINT FK_DEBC577431518C7 FOREIGN KEY (view_id) REFERENCES platform_view (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_view_version ADD CONSTRAINT FK_DEBC5774F617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_view_version ADD CONSTRAINT FK_DEBC5774C5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_view ADD current_version VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_view_version DROP CONSTRAINT FK_DEBC577431518C7');
        $this->addSql('ALTER TABLE platform_view_version DROP CONSTRAINT FK_DEBC5774F617CBEC');
        $this->addSql('ALTER TABLE platform_view_version DROP CONSTRAINT FK_DEBC5774C5F18393');
        $this->addSql('DROP TABLE platform_view_version');
        $this->addSql('ALTER TABLE platform_view DROP current_version');
    }
}
