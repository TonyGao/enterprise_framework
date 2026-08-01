<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 品牌落地页场景：强视觉、品牌化的宣传/落地页视图。
 */
class LandingViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'landing';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_landing.md';
    }

    public function displayName(): string
    {
        return '品牌落地页';
    }
}
