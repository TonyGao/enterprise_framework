<?php

namespace App\Service\Platform\Llm;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\Gateway\AnthropicGateway;
use App\Service\Platform\Llm\Gateway\AzureGateway;
use App\Service\Platform\Llm\Gateway\CustomGateway;
use App\Service\Platform\Llm\Gateway\OllamaGateway;
use App\Service\Platform\Llm\Gateway\OpenAiGateway;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LlmGatewayFactory
{
    public function __construct(
        private LlmEncryptor $encryptor,
        private HttpClientInterface $httpClient,
    ) {}

    private const OPENAI_COMPATIBLE = [
        'deepseek', 'moonshot', 'qwen', 'glm', 'ernie',
        'doubao', 'baichuan', 'yi', 'siliconflow', 'custom',
    ];

    public function create(LlmProvider $provider): LlmGatewayInterface
    {
        $p = $provider->getProvider();
        return match (true) {
            $p === 'openai' => new OpenAiGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'anthropic' => new AnthropicGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'ollama' => new OllamaGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'azure' => new AzureGateway($provider, $this->encryptor, $this->httpClient),
            in_array($p, self::OPENAI_COMPATIBLE, true) => new CustomGateway($provider, $this->encryptor, $this->httpClient),
            default => throw new \InvalidArgumentException("Unsupported provider: {$p}"),
        };
    }
}
