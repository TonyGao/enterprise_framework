<?php

namespace App\Service\AI;

use App\Service\Platform\Llm\LlmException;
use App\Service\Platform\Llm\LlmRouter;

/**
 * 图片风格分析：把用户上传的图片喂给 vision 模型，产出结构化设计规格（JSON），
 * 供视图子代理按"参考图片"风格重构。vision 角色需绑定一个支持图片的多模态 provider。
 *
 * Analyzes an uploaded image with a vision LLM and returns a structured design spec (JSON),
 * used to restyle the current view to match the reference image.
 */
class ImageStyleAnalyzer
{
    public const PROMPT = <<<MD
你是一个资深 UI 视觉分析专家。请仔细查看这张参考图片，提取它的**视觉风格与布局构成**，并只输出一个 JSON 对象（不要额外文字/代码块）。尽量给出具体的可执行取值：

{
  "layout_mode": "cards|split|sidebar|steps|hero_flow|single|immersive|table|free|null",
  "style": "cool|enterprise|minimal|dark|light|luxury|null",
  "degree": "full|skin|refine",
  "compact": "compact|spacious|null",
  "colors": "主色/辅色/背景的具体 hex 或渐变描述，如 '深蓝黑渐变 #0b1120→#1e293b，霓虹点缀 #6366f1/#a855f7'",
  "fields_arrangement": "字段排列方式，如 '多列卡片/非对称/单列' 或 null",
  "notes": "其它视觉细节：字体、圆角、阴影、装饰、图标、卡片结构等，尽量具体",
  "content_hints": "图片里若含可识别的文案/分类/标题（如卡片标题、导航、按钮文字），列出来；无则空字符串"
}

只输出这个 JSON 对象本身。
MD;

    public function __construct(private readonly LlmRouter $router)
    {
    }

    /**
     * @param array<string> $imagePaths 图片本地路径列表
     * @return array 结构化设计规格（design_spec 结构）
     * @throws LlmException 当 vision 角色未绑定或图片无法发送
     */
    public function analyze(array $imagePaths, ?string $goal = null): array
    {
        if ($imagePaths === []) {
            return [];
        }

        $images = [];
        foreach ($imagePaths as $path) {
            $images[] = [
                'mime' => $this->mimeOf($path),
                'data' => base64_encode((string) file_get_contents($path)),
            ];
        }

        return $this->analyzeBase64($images, $goal);
    }

    /**
     * @param array<int, array{mime: string, data: string}> $images base64 图片列表
     * @return array 结构化设计规格
     */
    public function analyzeBase64(array $images, ?string $goal = null): array
    {
        if ($images === []) {
            return [];
        }

        $parts = [
            [
                'type' => 'text',
                'text' => self::PROMPT . ($goal ? "\n\n用户目标：{$goal}" : ''),
            ],
        ];

        foreach ($images as $img) {
            $parts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => sprintf('data:%s;base64,%s', $img['mime'] ?? 'image/png', $img['data'] ?? ''),
                ],
            ];
        }

        // 直接调 vision 角色（多模态）；vision 未绑定会抛 LlmException
        $resp = $this->router->chatByRole('vision', [
            ['role' => 'user', 'content' => $parts],
        ], ['maxTokens' => 1024]);

        return $this->parse($resp->content);
    }

    private function parse(string $reply): array
    {
        $reply = trim($reply);
        if ($reply === '') {
            return [];
        }
        // 去掉可能的 ```json ... ``` 包裹
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $reply, $m)) {
            $reply = $m[1];
        }
        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $data = json_decode(substr($reply, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            return [];
        }

        // 归一为 design_spec 结构（借 IntentAgent 的白名单过滤）
        $allowedLayout = ['cards', 'split', 'sidebar', 'steps', 'hero_flow', 'single', 'immersive', 'table', 'free'];
        $allowedStyle = ['cool', 'enterprise', 'minimal', 'dark', 'light', 'luxury'];
        $allowedDegree = ['full', 'skin', 'refine'];
        $allowedCompact = ['compact', 'spacious'];

        $pick = function (string $key, array $enum) use ($data): ?string {
            $v = $data[$key] ?? null;
            return (is_string($v) && in_array(strtolower($v), $enum, true)) ? strtolower($v) : null;
        };

        $spec = [
            'from_image' => true,
            'layout_mode' => $pick('layout_mode', $allowedLayout),
            'style' => $pick('style', $allowedStyle),
            'degree' => $pick('degree', $allowedDegree),
            'compact' => $pick('compact', $allowedCompact),
            'colors' => is_string($data['colors'] ?? null) ? mb_substr($data['colors'], 0, 200) : null,
            'fields_arrangement' => is_string($data['fields_arrangement'] ?? null) ? mb_substr($data['fields_arrangement'], 0, 200) : null,
            'notes' => is_string($data['notes'] ?? null) ? mb_substr(trim($data['notes']), 0, 500) : '',
            'content_hints' => is_string($data['content_hints'] ?? null) ? mb_substr($data['content_hints'], 0, 300) : '',
        ];

        return array_filter($spec, fn ($v) => $v !== null && $v !== '');
    }

    private function mimeOf(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'image/png',
        };
    }
}
