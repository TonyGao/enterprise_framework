<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '用能力分类角色替换旧的使用场景角色';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM platform_llm_role');

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
        $this->addSql('DELETE FROM platform_llm_role');

        $this->addSql("INSERT INTO platform_llm_role (code, label, is_enabled, order_num) VALUES
            ('chat',                '通用对话',      true,  1),
            ('coding',              '代码生成',      false, 2),
            ('entity_generation',   '实体生成',      false, 3),
            ('query_assistant',     '查询助手',      false, 4),
            ('view_editor',         '视图设计器',    true,  5),
            ('workflow_analysis',   '工作流分析',    false, 6)
        ");
    }
}
