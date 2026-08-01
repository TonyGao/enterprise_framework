<?php

namespace App\Service\Platform\Llm;

class ModelRegistry
{
    private const MODELS = [
        'openai' => [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'o1-mini' => 'o1 Mini',
            'text-embedding-3-small' => 'Embedding v3 Small',
        ],
        'anthropic' => [
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
            'claude-opus-4-20250514' => 'Claude Opus 4',
        ],
        'ollama' => [
            'qwen2.5-coder' => 'Qwen 2.5 Coder',
            'qwen2.5' => 'Qwen 2.5',
            'llama3.2' => 'Llama 3.2',
            'deepseek-coder' => 'DeepSeek Coder',
            'mistral' => 'Mistral',
        ],
        'azure' => [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
        ],
        'deepseek' => [
            'deepseek-v4-flash' => 'DeepSeek V4 Flash',
            'deepseek-v4-pro' => 'DeepSeek V4 Pro',
            'deepseek-chat' => 'DeepSeek V3 (deepseek-chat)',
            'deepseek-reasoner' => 'DeepSeek R1 (deepseek-reasoner)',
        ],
        'moonshot' => [
            'moonshot-v1-8k' => 'Moonshot v1 8K',
            'moonshot-v1-32k' => 'Moonshot v1 32K',
            'moonshot-v1-128k' => 'Moonshot v1 128K',
        ],
        'qwen' => [
            'qwen-plus' => '通义千问 Plus',
            'qwen-max' => '通义千问 Max',
            'qwen-turbo' => '通义千问 Turbo',
            'qwen2.5-72b-instruct' => 'Qwen 2.5 72B',
        ],
        'glm' => [
            'glm-4-plus' => 'GLM-4 Plus',
            'glm-4-air' => 'GLM-4 Air',
            'glm-4-flash' => 'GLM-4 Flash',
        ],
        'ernie' => [
            'ernie-4.0-8k' => 'ERNIE 4.0 8K',
            'ernie-3.5-8k' => 'ERNIE 3.5 8K',
            'ernie-speed-128k' => 'ERNIE Speed 128K',
        ],
        'doubao' => [
            'doubao-pro-32k' => '豆包 Pro 32K',
            'doubao-pro-128k' => '豆包 Pro 128K',
            'doubao-lite-32k' => '豆包 Lite 32K',
        ],
        'baichuan' => [
            'baichuan4' => '百川 4',
            'baichuan3-turbo' => '百川 3 Turbo',
        ],
        'yi' => [
            'yi-lightning' => 'Yi Lightning',
            'yi-large' => 'Yi Large',
            'yi-large-turbo' => 'Yi Large Turbo',
        ],
        'siliconflow' => [
            'deepseek-ai/DeepSeek-V3' => 'DeepSeek V3',
            'deepseek-ai/DeepSeek-R1' => 'DeepSeek R1',
            'Qwen/Qwen2.5-72B-Instruct' => 'Qwen 2.5 72B',
            'Pro/Qwen/Qwen2.5-7B-Instruct' => 'Qwen 2.5 7B',
        ],
    ];

    public function getModels(string $provider): array
    {
        return self::MODELS[$provider] ?? [];
    }

    public function getAll(): array
    {
        return self::MODELS;
    }
}
