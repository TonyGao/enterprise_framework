<?php

namespace App\Service\Platform\Llm\Gateway;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\ChatResponse;
use App\Service\Platform\Llm\LlmException;
use App\Service\Platform\Llm\LlmGatewayInterface;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AnthropicGateway implements LlmGatewayInterface
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private array $defaultOptions;
    private HttpClientInterface $httpClient;

    public function __construct(
        LlmProvider $provider,
        LlmEncryptor $encryptor,
        HttpClientInterface $httpClient,
    ) {
        $decrypted = $encryptor->decrypt($provider->getApiKeyEncrypted() ?? '');
        if ($decrypted === null) {
            throw new LlmException('API Key decryption failed');
        }
        $this->apiKey = $decrypted;
        $this->model = $provider->getModel();
        $this->endpoint = rtrim($provider->getApiEndpoint() ?: 'https://api.anthropic.com', '/');
        $this->defaultOptions = $provider->getOptions() ?? [];
        $this->httpClient = $httpClient;
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $start = microtime(true);
        $opts = array_merge($this->defaultOptions, $options);

        $systemPrompt = null;
        $chatMessages = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemPrompt = ($systemPrompt ? $systemPrompt . "\n" : '') . ($msg['content'] ?? '');
            } else {
                $chatMessages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        $payload = [
            'model' => $this->model,
            'messages' => $chatMessages,
            'max_tokens' => $opts['maxTokens'] ?? 4096,
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        if (isset($opts['temperature'])) {
            $payload['temperature'] = $opts['temperature'];
        }

        $response = $this->httpClient->request('POST', $this->endpoint . '/v1/messages', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => $payload,
            'timeout' => $opts['timeout'] ?? 60,
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);

        if ($statusCode !== 200) {
            throw new LlmException("Anthropic API error (HTTP $statusCode): " . mb_substr($body, 0, 500));
        }

        $data = json_decode($body, true);
        $elapsed = (microtime(true) - $start) * 1000;

        $content = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'] ?? '';
            }
        }

        return new ChatResponse(
            content: $content,
            finishReason: $data['stop_reason'] ?? 'stop',
            inputTokens: $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['usage']['output_tokens'] ?? 0,
            elapsedMs: $elapsed,
        );
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $opts = array_merge($this->defaultOptions, $options);

        $systemPrompt = null;
        $chatMessages = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemPrompt = ($systemPrompt ? $systemPrompt . "\n" : '') . ($msg['content'] ?? '');
            } else {
                $chatMessages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        $payload = [
            'model' => $this->model,
            'messages' => $chatMessages,
            'max_tokens' => $opts['maxTokens'] ?? 4096,
            'stream' => true,
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        $response = $this->httpClient->request('POST', $this->endpoint . '/v1/messages', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => $payload,
            'timeout' => $opts['timeout'] ?? 120,
        ]);

        $stream = $response->toStream();
        while (!feof($stream)) {
            $line = fgets($stream);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ':')) continue;
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;
                yield json_decode($data, true);
            }
        }
    }

    public function testConnection(): array
    {
        $res = $this->chat([
            ['role' => 'user', 'content' => 'Please reply with just "OK".'],
        ], ['maxTokens' => 10]);
        return ['success' => true, 'reply' => $res->content, 'elapsed' => round($res->elapsedMs)];
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function getModelName(): string
    {
        return $this->model;
    }
}
