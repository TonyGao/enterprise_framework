<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create AI operation log table
 */
final class Version20260727012329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建 AI 操作日志表 platform_ai_operation_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_ai_operation_log (
            id UUID NOT NULL,
            owner_corporation_id UUID DEFAULT NULL,
            owner_company_id UUID DEFAULT NULL,
            context VARCHAR(64) NOT NULL,
            role_code VARCHAR(64) DEFAULT NULL,
            user_message TEXT NOT NULL,
            assistant_reply TEXT DEFAULT NULL,
            tool_calls JSON DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT DEFAULT NULL,
            elapsed_ms INT DEFAULT NULL,
            order_num INT DEFAULT NULL,
            deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_by VARCHAR(255) DEFAULT NULL,
            updated_by VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX ai_log_user_idx ON platform_ai_operation_log (created_by)');
        $this->addSql('CREATE INDEX ai_log_context_idx ON platform_ai_operation_log (context)');
        $this->addSql('CREATE INDEX IDX_ED9146AAF617CBEC ON platform_ai_operation_log (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_ED9146AAC5F18393 ON platform_ai_operation_log (owner_company_id)');

        $this->addSql('ALTER TABLE platform_ai_operation_log ADD CONSTRAINT FK_ED9146AAF617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_operation_log ADD CONSTRAINT FK_ED9146AAC5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_ai_operation_log');
    }
}
