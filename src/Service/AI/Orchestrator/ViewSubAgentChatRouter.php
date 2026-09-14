<?php

namespace App\Service\AI\Orchestrator;

use App\Entity\Platform\AiChatSession;
use App\Entity\Platform\View;
use App\Service\AI\Tool\ChromeDevToolsToolProvider;
use App\Service\AI\Tool\ViewEditorToolProvider;
use App\Service\AI\Tool\ViewFileToolProvider;
use App\Service\Platform\View\ViewPathResolver;
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
        private readonly ViewPathResolver $pathResolver,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * 视图设计源码是否为"自定义 Twig 表单设计"（含 form_start/form_widget 等）。
     * 这类视图画布只是渲染预览，保存会被保护拦截——因此必须走文件重写，不能走 CDP。
     */
    private function isCustomTwigForm(?View $view): bool
    {
        if (!$view) {
            return false;
        }
        $version = $this->pathResolver->currentVersion($view);
        $designFile = $this->pathResolver->designFile($view, $version);
        if (!$designFile || !file_exists($designFile)) {
            return false;
        }

        return (bool) preg_match(
            '/\{(form_start|form_end|form_rest|form_widget|form_label|form_errors|form_row)\}|\{\{\s*(form\b|form_)|form_start\(|form_end\(|form_widget\(|form_label\(|form_errors\(/',
            (string) file_get_contents($designFile),
        );
    }

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

        // 风格模仿：消息中含 http(s) 链接 → 抓取该网页并模仿其风格重构当前视图 /
        // style-mimic: if the message contains an http(s) URL, fetch that page and mimic its style
        if (preg_match('#https?://[^\s<>"\'，。；、]+#i', $message, $urlMatch)) {
            $url = rtrim($urlMatch[0], '.,;!?');
            $session->setIntent('general');
            $session->setMode('style_mimic');
            $this->em->persist($session);

            $agent = $this->orchestrator->resolveForIntent('general');

            return [
                'systemPrompt' => $agent->systemPromptForStyleMimic($url),
                'tools' => [$this->viewFileToolProvider, $this->viewEditorToolProvider, $this->cdpToolProvider],
                'needsClarification' => false,
                'clarificationQuestion' => null,
                'clarificationOptions' => [],
                'redesignApplied' => true,
            ];
        }

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

        // 自定义 Twig 表单设计：画布不可保存 → 无论重构还是微调，都走文件重写 /
        // custom Twig form design: canvas save is blocked → always use file rewrite
        $forceFileMode = $this->isCustomTwigForm($view);

        if ($redesign || $forceFileMode) {
            // 表单视图与其它视图一致走"文件重写"：AI 用 viewfile_writeDesign 生成完全自定义的
            // Twig 表单设计（Symfony form + inline 样式），不再受 ef-form 控件布局约束。
            // 把用户的设计规格（design_spec）+ 执行要点（plan）动态注入，避免子代理从自然语言里猜。
            // 非重构（forceFileMode 下的微调）用"就地修改"工作流，避免全量重排。
            $prompt = (!$redesign && $forceFileMode)
                ? $agent->systemPromptForFileRefine()
                : $agent->systemPromptForFileRedesign(
                    $result['design_spec'] ?? null,
                    $result['plan'] ?? null,
                );

            return [
                'systemPrompt' => $prompt,
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
