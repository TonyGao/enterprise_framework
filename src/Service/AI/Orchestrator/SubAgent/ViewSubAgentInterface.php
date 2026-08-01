<?php

namespace App\Service\AI\Orchestrator\SubAgent;

/**
 * 视图 Sub Agent：专注于某一类场景的视图实现。
 * 通过意图代码与 Main Agent 的分类结果路由匹配；没有匹配时由 General 兜底。
 */
interface ViewSubAgentInterface
{
    /**
     * 意图代码，如 email / form / landing / info / report / general。
     */
    public function intentCode(): string;

    /**
     * 领域提示词相对路径（以项目根为基准），如 src/Service/AI/Runtime/view_sub_agent_email.md。
     */
    public function promptPath(): string;

    /**
     * 完整系统提示词（共享技术约束 + 领域提示词）。
     */
    public function systemPrompt(): string;

    /**
     * 展示名，用于进度提示，如「商务邮件」「信息展示」。
     */
    public function displayName(): string;
}
