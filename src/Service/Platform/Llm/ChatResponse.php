<?php

namespace App\Service\Platform\Llm;

class ChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $finishReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $elapsedMs,
        public readonly ?array $toolCalls = null,
    ) {}
}
