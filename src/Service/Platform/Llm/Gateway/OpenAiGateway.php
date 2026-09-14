<?php

namespace App\Service\Platform\Llm\Gateway;

use App\Entity\Platform\LlmProvider;
use App\Service\Platform\Llm\ChatResponse;
use App\Service\Platform\Llm\LlmErrorFormatter;
use App\Service\Platform\Llm\LlmException;
use App\Service\Platform\Llm\LlmGatewayInterface;
use App\Service\Platform\LlmEncryptor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiGateway implements LlmGatewayInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;
    protected string $providerType;
    protected array $defaultOptions;
    protected HttpClientInterface $httpClient;

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
        $this->endpoint = rtrim($provider->getApiEndpoint() ?: 'https://api.openai.com/v1', '/');
        $this->providerType = $provider->getProvider();
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
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens' => $opts['maxTokens'] ?? 4096,
        ];

        if (isset($opts['topP'])) {
            $payload['top_p'] = $opts['topP'];
        }

        if (isset($opts['tools'])) {
            $payload['tools'] = $opts['tools'];
        }

        $payload = $this->applyThinkingParams($payload, $opts);

        try {
            $response = $this->httpClient->request('POST', $this->endpoint . '/chat/completions', [
                'headers' => $this->getAuthHeaders(),
                'json' => $payload,
                'timeout' => $opts['timeout'] ?? 60,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            // 连接拒绝 / 超时 / DNS 等传输层错误 → 友好提示，技术细节仅日志
            throw new LlmException(
                LlmErrorFormatter::friendlyMessage($this->getProviderName(), 0),
                0,
                $e,
                ['statusCode' => 0, 'reason' => $e->getMessage()],
            );
        }

        if ($statusCode !== 200) {
            throw new LlmException(
                $this->getProviderName() . " API error (HTTP $statusCode): " . mb_substr($body, 0, 500),
                0,
                null,
                ['statusCode' => $statusCode, 'body' => mb_substr($body, 0, 500)],
            );
        }

        $data = json_decode($body, true);
        $elapsed = (microtime(true) - $start) * 1000;
        $choice = $data['choices'][0] ?? [];

        return new ChatResponse(
            content: $choice['message']['content'] ?? '',
            finishReason: $choice['finish_reason'] ?? 'stop',
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            elapsedMs: $elapsed,
            toolCalls: $choice['message']['tool_calls'] ?? null,
        );
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $opts = array_merge($this->defaultOptions, $options);

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens' => $opts['maxTokens'] ?? 4096,
        ];

        if (isset($opts['topP'])) {
            $payload['top_p'] = $opts['topP'];
        }

        $payload = $this->applyThinkingParams($payload, $opts);

        $response = $this->httpClient->request('POST', $this->endpoint . '/chat/completions', [
            'headers' => $this->getAuthHeaders(),
            'json' => $payload,
            'timeout' => $opts['timeout'] ?? 300,
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
        // 推理模型会把 max_tokens 用在 thinking 上，过小会导致无 content，
        // 因此给足配额（相对 testConnection 的轻量用途足够）
        $res = $this->chat([
            ['role' => 'user', 'content' => '回复"OK"即可，不要多余文字。'],
        ], ['maxTokens' => 512]);
        return ['success' => true, 'reply' => $res->content, 'elapsed' => round($res->elapsedMs)];
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }

    protected function applyThinkingParams(array $payload, array $opts): array
    {
        if ($this->providerType !== 'deepseek') {
            return $payload;
        }

        if (isset($opts['thinking'])) {
            $payload['thinking'] = $opts['thinking'];
        }

        if (isset($opts['reasoning_effort'])) {
            $payload['reasoning_effort'] = $opts['reasoning_effort'];
        }

        return $payload;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getModelName(): string
    {
        return $this->model;
    }
}
