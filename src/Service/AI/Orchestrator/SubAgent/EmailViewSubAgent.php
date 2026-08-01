<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 商务邮件场景：输出正式、专业的中文商务邮件视图。
 */
class EmailViewSubAgent extends AbstractViewSubAgent
{
    public function intentCode(): string
    {
        return 'email';
    }

    public function promptPath(): string
    {
        return 'src/Service/AI/Runtime/view_sub_agent_email.md';
    }

    public function displayName(): string
    {
        return '商务邮件';
    }
}
