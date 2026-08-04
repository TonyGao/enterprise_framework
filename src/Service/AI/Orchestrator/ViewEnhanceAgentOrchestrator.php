<?php

namespace App\Service\AI\Orchestrator;

use App\Entity\Platform\View;
use App\Service\AI\Orchestrator\SubAgent\GeneralViewSubAgent;
use App\Service\AI\Orchestrator\SubAgent\ViewSubAgentInterface;
use App\Service\AI\Runtime\AiAssistant;
use App\Service\AI\Tool\ViewEditorToolProvider;
use App\Service\AI\Tool\ViewFileToolProvider;
use App\Service\Platform\View\ViewPathResolver;

/**
 * 视图增强编排器：
 * 1. Main Agent（IntentAgent）判断需求意图
 * 2. 按意图路由到专业 Sub Agent；无匹配时用通用 Sub Agent 兜底
 * 3. Sub Agent 持有领域提示词 + 共享技术约束 + 文件/编辑器工具，完成视图实现
 */
class ViewEnhanceAgentOrchestrator
{
    /** @param iterable<ViewSubAgentInterface> $subAgents */
    public function __construct(
        private readonly IntentAgent $intentAgent,
        private readonly AiAssistant $assistant,
        private readonly ViewFileToolProvider $viewFileToolProvider,
        private readonly ViewEditorToolProvider $viewEditorToolProvider,
        private readonly ViewPathResolver $pathResolver,
        private readonly GeneralViewSubAgent $fallback,
        private readonly iterable $subAgents,
    ) {}

    /**
     * @param callable(string): void $progress
     * @return array{reply: string, history: array[]}
     */
    public function run(View $view, string $requirement, callable $progress): array
    {
        $progress('正在分析需求意图…');
        $intent = $this->intentAgent->classify($view, $requirement);

        $agent = $this->resolve($intent['intent'] ?? 'general');
        $progress(sprintf('正在按「%s」方案生成…', $agent->displayName()));

        $userMessage = $this->buildUserMessage($view, $requirement, $intent);

        return $this->assistant->chat(
            roleCode: 'general',
            systemPrompt: $agent->systemPrompt(),
            toolProviders: [$this->viewFileToolProvider, $this->viewEditorToolProvider],
            userMessage: $userMessage,
        );
    }

    private function resolve(string $intent): ViewSubAgentInterface
    {
        return $this->resolveForIntent($intent);
    }

    public function resolveForIntent(string $intent): ViewSubAgentInterface
    {
        foreach ($this->subAgents as $agent) {
            if ($agent->intentCode() === $intent) {
                return $agent;
            }
        }

        return $this->fallback;
    }

    private function buildUserMessage(View $view, string $requirement, array $intent): string
    {
        $designFile = $this->pathResolver->designFile($view);
        $designRef = $designFile
            ? '[设计文件: views/' . ltrim(str_replace($this->pathResolver->baseDir(), '', $designFile), '/') . ']'
            : '[内置视图，设计文件位于 builtin 目录]';

        return implode("\n", [
            '[任务: 对视图进行 AI 二次加工]',
            '[视图ID: ' . $view->getId() . ']',
            '[视图名称: ' . ($view->getName() ?? '') . ']',
            '[视图标签: ' . ($view->getLabel() ?? '') . ']',
            '[当前版本: ' . $this->pathResolver->currentVersion($view) . ']',
            $designRef,
            '[意图判断: ' . ($intent['intent'] ?? 'general') . ']',
            '[执行要点]',
            ($intent['plan'] ?? '') ?: '按用户需求自由设计',
            '',
            '[用户加工需求]',
            $requirement,
        ]);
    }
}
