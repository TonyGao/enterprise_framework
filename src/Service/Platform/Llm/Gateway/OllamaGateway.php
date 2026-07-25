<?php

namespace App\Service\Platform\Llm\Gateway;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\ChatResponse;
use App\Service\Platform\Llm\LlmException;
use App\Service\Platform\Llm\LlmGatewayInterface;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaGateway implements LlmGatewayInterface
{
    private string $model;
    private string $endpoint;
    private array $defaultOptions;
    private HttpClientInterface $httpClient;

    public function __construct(
        LlmProvider $provider,
        LlmEncryptor $encryptor,
        HttpClientInterface $httpClient,
    ) {
        $this->model = $provider->getModel();
        $this->endpoint = rtrim($provider->getApiEndpoint() ?: 'http://localhost:11434', '/');
        $this->defaultOptions = $provider->getOptions() ?? [];
        $this->httpClient = $httpClient;
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $start = microtime(true);
        $opts = array_merge($this->defaultOptions, $options);

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        $ollamaOptions = [];
        if (isset($opts['temperature'])) {
            $ollamaOptions['temperature'] = $opts['temperature'];
        }
        if (!empty($ollamaOptions)) {
            $payload['options'] = $ollamaOptions;
        }

        $response = $this->httpClient->request('POST', $this->endpoint . '/api/chat', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => $opts['timeout'] ?? 120,
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);

        if ($statusCode !== 200) {
            throw new LlmException("Ollama API error (HTTP $statusCode): " . mb_substr($body, 0, 500));
        }

        $data = json_decode($body, true);
        $elapsed = (microtime(true) - $start) * 1000;

        return new ChatResponse(
            content: $data['message']['content'] ?? '',
            finishReason: $data['done'] ? 'stop' : 'unknown',
            inputTokens: 0,
            outputTokens: 0,
            elapsedMs: $elapsed,
        );
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $opts = array_merge($this->defaultOptions, $options);

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ];

        $response = $this->httpClient->request('POST', $this->endpoint . '/api/chat', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => $opts['timeout'] ?? 120,
        ]);

        $stream = $response->toStream();
        while (!feof($stream)) {
            $line = fgets($stream);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '') continue;
            yield json_decode($line, true);
        }
    }

    public function testConnection(): array
    {
        $res = $this->chat([
            ['role' => 'user', 'content' => 'Reply with just "OK".'],
        ]);
        return ['success' => true, 'reply' => $res->content, 'elapsed' => round($res->elapsedMs)];
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }

    public function getModelName(): string
    {
        return $this->model;
    }
}
