<?php

namespace App\Service\Platform\Llm;

class Message
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
    ) {}
}
