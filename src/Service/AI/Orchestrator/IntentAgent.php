<?php

namespace App\Service\AI\Orchestrator;

use App\Entity\Platform\View;
use App\Service\AI\Runtime\AiAssistant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Main Agent：对用户的加工需求做意图判断，决定走哪个 Sub Agent，
 * 并抽取执行要点（plan）交给 Sub Agent 参考。仅输出结构化 JSON，不生成 HTML。
 */
class IntentAgent
{
    public const AVAILABLE_INTENTS = [
        'email' => '商务邮件模板：录用通知、欢迎信、通知类正式信函',
        'form' => '表单视图：需要录入/提交数据的结构化表单',
        'landing' => '品牌落地页：强视觉、宣传推广型页面',
        'info' => '信息展示：卡片化呈现业务信息、详情数据',
        'report' => '数据报表：统计汇总、指标卡、图表型页面',
        'general' => '其他 / 不确定的通用场景',
    ];

    public function __construct(
        private readonly AiAssistant $assistant,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    /**
     * @return array{intent: string, confidence: float, plan: string, reason: string}
     */
    public function classify(View $view, string $requirement): array
    {
        $systemPrompt = (string) file_get_contents(
            $this->projectDir . '/src/Service/AI/Runtime/view_enhance_intent_prompt.md'
        );

        $userMessage = implode("\n", [
            '[视图名称: ' . ($view->getName() ?? '') . ']',
            '[视图标签: ' . ($view->getLabel() ?? '') . ']',
            '[视图类型: ' . ($view->getType() ?? '') . ']',
            '',
            '[用户加工需求]',
            $requirement,
        ]);

        $result = $this->assistant->chat(
            roleCode: 'lightweight',
            systemPrompt: $systemPrompt,
            toolProviders: [],
            userMessage: $userMessage,
        );

        return $this->parse($result['reply'] ?? '');
    }

    /**
     * 从模型回复中提取 {intent, redesign, needsClarification, clarificationQuestion, clarificationOptions, plan, reason}。
     * 解析失败时回退到 general + 微调模式，保证任务不中断。
     */
    private function parse(string $reply): array
    {
        $fallback = [
            'intent' => 'general',
            'redesign' => false,
            'needsClarification' => false,
            'clarificationQuestion' => null,
            'clarificationOptions' => [],
            'confidence' => 0.0,
            'plan' => '',
            'reason' => '意图识别失败，走通用兜底',
        ];

        $reply = trim($reply);
        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');
        if ($start === false || $end === false || $end <= $start) {
            return $fallback;
        }

        $json = substr($reply, $start, $end - $start + 1);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $fallback;
        }

        $intent = (string) ($data['intent'] ?? 'general');
        if (!array_key_exists($intent, self::AVAILABLE_INTENTS)) {
            $intent = 'general';
        }

        $options = $data['clarification_options'] ?? $data['clarificationOptions'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }
        $options = array_values(array_map('strval', array_filter($options, 'is_string')));

        return [
            'intent' => $intent,
            'redesign' => (bool) ($data['redesign'] ?? false),
            'needsClarification' => (bool) ($data['needs_clarification'] ?? $data['needsClarification'] ?? false),
            'clarificationQuestion' => (string) ($data['clarification_question'] ?? $data['clarificationQuestion'] ?? ''),
            'clarificationOptions' => $options,
            'design_spec' => $this->parseDesignSpec($data),
            'confidence' => (float) ($data['confidence'] ?? 0.0),
            'plan' => (string) ($data['plan'] ?? ''),
            'reason' => (string) ($data['reason'] ?? ''),
        ];
    }

    /**
     * 抽取结构化的设计规格（布局/风格/程度/紧凑/配色/字段排列），供下游子代理直接执行。
     * 用户的具体诉求翻译成明确的 spec，而不是让子代理从自然语言里猜。
     */
    private function parseDesignSpec(array $data): array
    {
        $allowed = [
            'layout_mode' => ['cards', 'split', 'sidebar', 'steps', 'hero_flow', 'single', 'immersive', 'table', 'free'],
            'style' => ['cool', 'enterprise', 'minimal', 'dark', 'light', 'luxury'],
            'degree' => ['full', 'skin', 'refine'],
            'compact' => ['compact', 'spacious'],
        ];

        $spec = $data['design_spec'] ?? [];
        if (!is_array($spec)) {
            $spec = [];
        }

        $out = [
            'layout_mode' => null,
            'style' => null,
            'degree' => null,
            'compact' => null,
            'colors' => null,
            'fields_arrangement' => null,
            'notes' => '',
        ];

        foreach ($allowed as $field => $enum) {
            $v = $spec[$field] ?? null;
            if (is_string($v) && in_array(strtolower($v), $enum, true)) {
                $out[$field] = strtolower($v);
            }
        }
        foreach (['colors', 'fields_arrangement'] as $field) {
            if (isset($spec[$field]) && is_string($spec[$field]) && $spec[$field] !== '') {
                $out[$field] = mb_substr($spec[$field], 0, 80);
            }
        }
        if (isset($spec['notes']) && is_string($spec['notes'])) {
            $out['notes'] = mb_substr(trim($spec['notes']), 0, 300);
        }

        return $out;
    }
}
