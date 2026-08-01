<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 报表场景：数据统计、汇总、指标卡片的报表型视图。
 */
class ReportViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'report';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_report.md';
    }

    public function displayName(): string
    {
        return '数据报表';
    }
}
