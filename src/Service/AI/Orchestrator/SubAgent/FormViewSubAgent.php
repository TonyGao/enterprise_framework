<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 表单场景：结合视图绑定实体的字段，生成结构化表单视图。
 */
class FormViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'form';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_form.md';
    }

    public function displayName(): string
    {
        return '表单';
    }
}
