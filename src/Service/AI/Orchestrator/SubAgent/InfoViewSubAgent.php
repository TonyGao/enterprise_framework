<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 信息展示场景：卡片化呈现业务信息、详情数据的视图。
 */
class InfoViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'info';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_info.md';
    }

    public function displayName(): string
    {
        return '信息展示';
    }
}
