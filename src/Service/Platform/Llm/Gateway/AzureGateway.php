<?php

namespace App\Service\Platform\Llm\Gateway;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\LlmGatewayInterface;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AzureGateway extends OpenAiGateway implements LlmGatewayInterface
{
    public function __construct(
        LlmProvider $provider,
        LlmEncryptor $encryptor,
        HttpClientInterface $httpClient,
    ) {
        parent::__construct($provider, $encryptor, $httpClient);
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'api-key' => $this->apiKey,
        ];
    }

    public function getProviderName(): string
    {
        return 'azure';
    }
}
