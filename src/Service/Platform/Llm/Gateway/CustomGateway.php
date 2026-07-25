<?php

namespace App\Service\Platform\Llm\Gateway;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\LlmGatewayInterface;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CustomGateway extends OpenAiGateway implements LlmGatewayInterface
{
    public function __construct(
        LlmProvider $provider,
        LlmEncryptor $encryptor,
        HttpClientInterface $httpClient,
    ) {
        parent::__construct($provider, $encryptor, $httpClient);
    }

    public function getProviderName(): string
    {
        return 'custom';
    }
}
