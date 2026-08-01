<?php

namespace App\Service\AI\Context;

use App\Entity\Platform\View;
use App\Service\AI\Runtime\AiContextProviderInterface;
use App\Service\AI\Tool\ChromeDevToolsToolProvider;
use App\Service\AI\Tool\ViewEditorToolProvider;
use Doctrine\ORM\EntityManagerInterface;

class ViewEditorAiContext implements AiContextProviderInterface
{
    public function __construct(
        private readonly ViewEditorToolProvider $toolProvider,
        private readonly ChromeDevToolsToolProvider $cdpToolProvider,
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
        // Extract view ID from URL path: /admin/platform/view/editor/{uuid}
        $parts = explode('/', trim($contextId, '/'));
        $viewId = end($parts);

        $lines = [];
        $lines[] = '[当前视图ID: ' . $viewId . ']';

        if ($viewId) {
            $view = $this->em->getRepository(View::class)->find($viewId);
            if ($view) {
                $lines[] = '[视图名称: ' . $view->getName() . ']';
                if ($view->getPath()) {
                    $lines[] = '[设计文件: views/' . $view->getPath() . '/' . $view->getName() . '.design.twig]';
                }
            }
        }

        return implode("\n", $lines) . "\n\n" . $message;
    }
}
