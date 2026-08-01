<?php

namespace App\Service\AI\Runtime;

interface AiContextProviderInterface
{
    public function getName(): string;

    public function getRoleCode(): string;

    public function getSystemPrompt(): string;

    public function getToolProviders(): array;

    /**
     * 构建 AI 提示词，将上下文信息附加到用户消息前
     */
    public function buildPrompt(string $message, string $contextId): string;
}
