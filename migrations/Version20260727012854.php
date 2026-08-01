<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create AI chat session and message tables
 */
final class Version20260727012854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建 AI 聊天会话和消息表';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_ai_chat_session (
            id UUID NOT NULL,
            owner_corporation_id UUID DEFAULT NULL,
            owner_company_id UUID DEFAULT NULL,
            context VARCHAR(64) NOT NULL,
            context_id VARCHAR(128) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN NOT NULL,
            order_num INT DEFAULT NULL,
            deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_by VARCHAR(255) DEFAULT NULL,
            updated_by VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX ai_chat_session_user_idx ON platform_ai_chat_session (created_by)');
        $this->addSql('CREATE INDEX ai_chat_session_context_idx ON platform_ai_chat_session (context)');
        $this->addSql('CREATE INDEX IDX_14ECFB32F617CBEC ON platform_ai_chat_session (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_14ECFB32C5F18393 ON platform_ai_chat_session (owner_company_id)');

        $this->addSql('ALTER TABLE platform_ai_chat_session ADD CONSTRAINT FK_14ECFB32F617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_chat_session ADD CONSTRAINT FK_14ECFB32C5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE platform_ai_chat_message (
            id UUID NOT NULL,
            session_id UUID DEFAULT NULL,
            owner_corporation_id UUID DEFAULT NULL,
            owner_company_id UUID DEFAULT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            tool_calls JSON DEFAULT NULL,
            order_num INT DEFAULT NULL,
            deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_by VARCHAR(255) DEFAULT NULL,
            updated_by VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX ai_chat_msg_session_idx ON platform_ai_chat_message (session_id)');
        $this->addSql('CREATE INDEX IDX_72151E99F617CBEC ON platform_ai_chat_message (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_72151E99C5F18393 ON platform_ai_chat_message (owner_company_id)');

        $this->addSql('ALTER TABLE platform_ai_chat_message ADD CONSTRAINT FK_72151E99613FECDF FOREIGN KEY (session_id) REFERENCES platform_ai_chat_session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_chat_message ADD CONSTRAINT FK_72151E99F617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_ai_chat_message ADD CONSTRAINT FK_72151E99C5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_ai_chat_message');
        $this->addSql('DROP TABLE platform_ai_chat_session');
    }
}
