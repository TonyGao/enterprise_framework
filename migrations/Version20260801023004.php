<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 创建视图 AI 二次加工任务表
 */
final class Version20260801023004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create platform_ai_view_task table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_ai_view_task (id UUID NOT NULL, view_id UUID DEFAULT NULL, owner_corporation_id UUID DEFAULT NULL, owner_company_id UUID DEFAULT NULL, requirement TEXT NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, progress_text TEXT DEFAULT NULL, result TEXT DEFAULT NULL, error_message TEXT DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, order_num INT DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A99401EAF617CBEC ON platform_ai_view_task (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_A99401EAC5F18393 ON platform_ai_view_task (owner_company_id)');
        $this->addSql('CREATE INDEX ai_view_task_view_idx ON platform_ai_view_task (view_id)');
        $this->addSql('CREATE INDEX ai_view_task_status_idx ON platform_ai_view_task (status)');
        $this->addSql('COMMENT ON COLUMN platform_ai_view_task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_view_task.view_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_view_task.owner_corporation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_ai_view_task.owner_company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE platform_ai_view_task ADD CONSTRAINT FK_A99401EA31518C7 FOREIGN KEY (view_id) REFERENCES platform_view (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_view_task ADD CONSTRAINT FK_A99401EAF617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_view_task ADD CONSTRAINT FK_A99401EAC5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_view_task DROP CONSTRAINT FK_A99401EA31518C7');
        $this->addSql('ALTER TABLE platform_ai_view_task DROP CONSTRAINT FK_A99401EAF617CBEC');
        $this->addSql('ALTER TABLE platform_ai_view_task DROP CONSTRAINT FK_A99401EAC5F18393');
        $this->addSql('DROP TABLE platform_ai_view_task');
    }
}
