<?php

namespace App\Service\AI\Orchestrator;

use App\Entity\Platform\AiChatSession;
use App\Entity\Platform\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 会话级 Sub Agent 路由：供交互式 AI 聊天（如视图编辑器）统一走 main/sub agent 逻辑。
 * 会话首次消息判定意图并持久化到会话，后续轮次复用同一 Sub Agent，
 * 避免多轮对话中领域上下文漂移。
 */
class ViewSubAgentChatRouter
{
    public function __construct(
        private readonly IntentAgent $intentAgent,
        private readonly ViewEnhanceAgentOrchestrator $orchestrator,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * 返回当前会话应使用的系统提示词（CDP 模式）。
     * 首次调用会判定意图并写入会话（调用方需 flush 持久化）。
     */
    public function resolveSystemPrompt(string $message, string $contextId, AiChatSession $session): string
    {
        $intent = $session->getIntent();

        if (!$intent) {
            $view = $this->loadView($contextId);
            $result = $this->intentAgent->classify($view, $message);
            $intent = $result['intent'] ?? 'general';

            $session->setIntent($intent);
            $this->em->persist($session);
        }

        $agent = $this->orchestrator->resolveForIntent($intent);

        return $agent->systemPromptForCdp();
    }

    private function loadView(string $contextId): ?View
    {
        if (!$contextId) {
            return null;
        }

        $parts = explode('/', trim($contextId, '/'));
        $viewId = end($parts);
        if (!$viewId) {
            return null;
        }

        return $this->em->getRepository(View::class)->find($viewId);
    }
}
