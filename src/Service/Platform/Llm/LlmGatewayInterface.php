<?php

namespace App\Service\Platform\Llm;

interface LlmGatewayInterface
{
    public function chat(array $messages, array $options = []): ChatResponse;

    public function chatStream(array $messages, array $options = []): \Generator;

    public function testConnection(): array;

    public function getProviderName(): string;

    public function getModelName(): string;
}
