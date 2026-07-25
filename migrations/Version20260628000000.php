<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建 LLM 配置相关表（platform_llm_provider, platform_llm_role）并预置默认角色';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_llm_provider (
            id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            model VARCHAR(100) NOT NULL,
            api_key_encrypted TEXT DEFAULT NULL,
            api_endpoint VARCHAR(255) DEFAULT NULL,
            options JSON DEFAULT NULL,
            is_enabled BOOLEAN DEFAULT true NOT NULL,
            order_num INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            owner_corporation_id UUID DEFAULT NULL,
            owner_company_id UUID DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE platform_llm_role (
            code VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            provider_id UUID DEFAULT NULL,
            system_prompt TEXT DEFAULT NULL,
            options JSON DEFAULT NULL,
            is_enabled BOOLEAN DEFAULT true NOT NULL,
            order_num INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            owner_corporation_id UUID DEFAULT NULL,
            owner_company_id UUID DEFAULT NULL,
            PRIMARY KEY(code)
        )');

        $this->addSql('CREATE INDEX idx_llm_provider_enabled ON platform_llm_provider (is_enabled)');
        $this->addSql('CREATE INDEX idx_llm_role_enabled ON platform_llm_role (is_enabled)');

        // 预置默认角色（按模型能力类型划分）
        $this->addSql("INSERT INTO platform_llm_role (code, label, system_prompt, is_enabled, order_num) VALUES
            ('vision',      '多模态视觉',
             '你是一个多模态 AI 助手，能够理解图像、图表中的内容。回答时请结合视觉信息进行分析。', false, 1),
            ('reasoning',   '深度推理',
             '你是一个深度推理 AI 助手，擅长处理复杂的逻辑推理、数学计算和代码生成任务。请逐步思考并展示推理过程。', false, 2),
            ('general',     '通用对话',
             '你是一个通用 AI 助手，可以进行日常对话、内容创作和知识问答。请给出准确、有条理的回答。', true, 3),
            ('lightweight', '轻量快速',
             '你是一个轻量 AI 助手，适合处理简单、高频的任务。请给出简洁直接的答案，无需过多修饰。', true, 4),
            ('embedding',   '向量嵌入',  NULL, false, 5)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_llm_role');
        $this->addSql('DROP TABLE platform_llm_provider');
    }
}
