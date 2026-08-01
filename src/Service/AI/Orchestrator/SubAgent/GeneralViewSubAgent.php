<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 通用兜底：无匹配专业 Sub Agent 时，依据用户需求描述自由完成视图实现。
 */
class GeneralViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'general';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_general.md';
    }

    public function displayName(): string
    {
        return '通用视图';
    }
}
