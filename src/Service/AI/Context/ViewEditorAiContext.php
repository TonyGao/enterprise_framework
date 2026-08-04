<?php

namespace App\Service\AI\Context;

use App\Entity\Platform\View;
use App\Service\AI\Runtime\AiContextProviderInterface;
use App\Service\AI\Tool\ChromeDevToolsToolProvider;
use App\Service\AI\Tool\ViewEditorToolProvider;
use App\Service\Platform\View\ViewPathResolver;
use Doctrine\ORM\EntityManagerInterface;

class ViewEditorAiContext implements AiContextProviderInterface
{
    public function __construct(
        private readonly ViewEditorToolProvider $toolProvider,
        private readonly ChromeDevToolsToolProvider $cdpToolProvider,
        private readonly ViewPathResolver $pathResolver,
        private readonly EntityManagerInterface $em,
    ) {}

    public function getName(): string
    {
        return 'view_editor';
    }

    public function getRoleCode(): string
    {
        return 'general';
    }

    public function getSystemPrompt(): string
    {
        return \file_get_contents(__DIR__ . '/../Runtime/view_editor_prompt.md');
    }

    public function getToolProviders(): array
    {
        return [$this->toolProvider, $this->cdpToolProvider];
    }

    public function buildPrompt(string $message, string $contextId): string
    {
        // contextId 可能是完整 URL（含 ?version=）或视图 UUID
        $parsed = parse_url($contextId);
        $path = $parsed['path'] ?? $contextId;

        // 视图ID = 路径最后一段
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        $viewId = $parts ? end($parts) : '';

        // 目标版本 = ?version= 参数；缺省用当前激活版本
        $activeVersion = null;
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            $v = $query['version'] ?? null;
            if ($v && \App\Service\Platform\View\VersionNumber::isValid($v)) {
                $activeVersion = $v;
            }
        }

        $lines = [];
        $lines[] = '[当前视图ID: ' . $viewId . ']';

        if ($viewId) {
            $view = $this->em->getRepository(View::class)->find($viewId);
            if ($view) {
                $lines[] = '[视图名称: ' . $view->getName() . ']';
                $lines[] = '[目标版本: ' . ($activeVersion ?: $this->pathResolver->currentVersion($view)) . ']';
                // 目标编辑器 URL：必须带上 version 参数，AI 的 CDP 操作应保证浏览器处于该 URL
                if (isset($parsed['path']) || isset($parsed['query'])) {
                    $targetUrl = (isset($parsed['scheme']) ? $parsed['scheme'] . '://' . ($parsed['host'] ?? '') : '')
                        . ($parsed['path'] ?? '')
                        . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
                    $lines[] = '[目标编辑器URL: ' . $targetUrl . ']';
                }
                $designFile = $this->pathResolver->designFile($view, $activeVersion);
                if ($designFile) {
                    $lines[] = '[设计文件: views/' . ltrim(str_replace($this->pathResolver->baseDir(), '', $designFile), '/') . ']';
                }
            }
        }

        return implode("\n", $lines) . "\n\n" . $message;
    }
}
