<?php

namespace App\Service\AI\Runtime;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

class ChatResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        $toolCalls = $data['tool_calls'] ?? null;
        $content = $data['content'] ?? '';
        $finishReason = $data['finish_reason'] ?? 'stop';

        if ($toolCalls !== null && \count($toolCalls) > 0) {
            $calls = [];
            foreach ($toolCalls as $tc) {
                $arguments = isset($tc['function']['arguments']) && $tc['function']['arguments'] !== ''
                    ? $this->decodeToolArguments($tc['function']['arguments'])
                    : [];
                $calls[] = new ToolCall(
                    $tc['id'],
                    $tc['function']['name'],
                    $arguments,
                );
            }
            return new ToolCallResult(...$calls);
        }

        return new TextResult($content);
    }

    private function decodeToolArguments(string $raw): array
    {
        try {
            return json_decode($this->sanitizeJsonControlChars($raw), true, 512, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException) {
            return [];
        }
    }

    private function sanitizeJsonControlChars(string $json): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $json)) {
            $json = preg_replace('/[\x00-\x1F\x7F]/', ' ', $json);
        }
        return trim($json);
    }

    public function getTokenUsageExtractor(): ?\Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface
    {
        return null;
    }
}
