<?php

namespace App\Service\AI\Orchestrator;

use App\Entity\Platform\AiChatSession;
use App\Entity\Platform\View;
use App\Service\AI\Tool\ChromeDevToolsToolProvider;
use App\Service\AI\Tool\ViewEditorToolProvider;
use App\Service\AI\Tool\ViewFileToolProvider;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 会话级 Sub Agent 路由：供交互式 AI 聊天（如视图编辑器）统一走 main/sub agent 逻辑。
 * 每条消息都用 IntentAgent（LLM）做意图判断；领域意图（选哪个 Sub Agent）会话内保持稳定。
 * 重构/微调模式与澄清需求每条消息实时判定。
 *
 * 关键：**整体重构走"文件重写"**——提取原内容后用文件工具全新生成整份 design 文件，
 * 等价于一次全量替换，比 CDP 增量编辑可靠（同样的模型用文件工具能产出真正不同的视图）。
 */
class ViewSubAgentChatRouter
{
    public function __construct(
        private readonly IntentAgent $intentAgent,
        private readonly ViewEnhanceAgentOrchestrator $orchestrator,
        private readonly ViewFileToolProvider $viewFileToolProvider,
        private readonly ViewEditorToolProvider $viewEditorToolProvider,
        private readonly ChromeDevToolsToolProvider $cdpToolProvider,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * 对当前消息做一次 LLM 意图判定。
     * - needsClarification=true：不执行，先返回澄清问题。
     * - 否则返回按本轮模式装配的提示词与工具集：
     *   - 重构 → 文件重写模式（工具：viewfile_*，前端完成后需刷新编辑器）
     *   - 微调 → CDP 就地微调模式（工具：cdp_*）
     *
     * @return array{systemPrompt: ?string, tools: array, needsClarification: bool, clarificationQuestion: ?string, clarificationOptions: string[], redesignApplied: bool}
     */
    public function classifyTurn(string $message, string $contextId, AiChatSession $session): array
    {
        $view = $this->loadView($contextId);
        $result = $this->intentAgent->classify($view, $message);

        if (($result['needsClarification'] ?? false) === true) {
            return [
                'systemPrompt' => null,
                'tools' => [],
                'needsClarification' => true,
                'clarificationQuestion' => ($result['clarificationQuestion'] ?? '') ?: '请确认你的需求',
                'clarificationOptions' => $result['clarificationOptions'] ?? [],
                'redesignApplied' => false,
            ];
        }

        // 领域意图：会话内稳定（首次确定后不再随每轮变化），避免 Sub Agent 漂移
        $intent = $session->getIntent();
        if (!$intent) {
            $intent = $result['intent'] ?? 'general';
            $session->setIntent($intent);
        }

        $redesign = (bool) ($result['redesign'] ?? false);
        $session->setMode($redesign ? 'redesign' : 'refine');
        $this->em->persist($session);

        $agent = $this->orchestrator->resolveForIntent($intent);

        if ($redesign) {
            // 表单视图与其它视图一致走"文件重写"：AI 用 viewfile_writeDesign 生成完全自定义的
            // Twig 表单设计（Symfony form + inline 样式），不再受 ef-form 控件布局约束。
            return [
                'systemPrompt' => $agent->systemPromptForFileRedesign(),
                'tools' => [$this->viewFileToolProvider, $this->viewEditorToolProvider],
                'needsClarification' => false,
                'clarificationQuestion' => null,
                'clarificationOptions' => [],
                'redesignApplied' => true,
            ];
        }

        // 微调：CDP 就地编辑
        return [
            'systemPrompt' => $agent->systemPromptForCdp(false),
            'tools' => [$this->viewEditorToolProvider, $this->cdpToolProvider],
            'needsClarification' => false,
            'clarificationQuestion' => null,
            'clarificationOptions' => [],
            'redesignApplied' => false,
        ];
    }

    private function loadView(string $contextId): ?View
    {
        if (!$contextId) {
            return null;
        }

        // contextId 可能是完整 URL（含 ?version=）或视图 UUID
        $parsed = parse_url($contextId);
        $path = $parsed['path'] ?? $contextId;
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        $viewId = $parts ? end($parts) : '';
        if (!$viewId) {
            return null;
        }

        return $this->em->getRepository(View::class)->find($viewId);
    }
}
