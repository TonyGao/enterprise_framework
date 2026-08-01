<?php

namespace App\Service\AI\Runtime;

use App\Service\Platform\Llm\LlmRouter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\ModelCatalog\InMemoryModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Tool\Tool;

class LlmRouterPlatform implements PlatformInterface
{
    public function __construct(
        private readonly LlmRouter $router,
        private readonly ChatResultConverter $converter,
    ) {}

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        \assert($input instanceof MessageBag);

        $messages = [];

        foreach ($input->getMessages() as $msg) {
            $converted = $this->convertMessage($msg);
            if ($converted !== null) {
                $messages[] = $converted;
            }
        }

        $routerOptions = $options;

        if (isset($options['tools']) && \is_array($options['tools'])) {
            $converted = array_map(
                fn (Tool $tool) => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                        'parameters' => (function () use ($tool) {
                            $p = $tool->getParameters();
                            return \is_array($p) && !empty($p) ? $p : ['type' => 'object', 'properties' => (object) []];
                        })(),
                    ],
                ],
                array_filter($options['tools'], fn ($t) => $t instanceof Tool),
            );
            if (\count($converted) > 0) {
                $routerOptions['tools'] = $converted;
            }
        }

        $response = $this->router->chatByRole($model, $messages, $routerOptions);

        return new DeferredResult(
            $this->converter,
            new InMemoryRawResult([
                'content' => $response->content,
                'tool_calls' => $response->toolCalls,
                'finish_reason' => $response->finishReason,
            ]),
        );
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return new InMemoryModelCatalog([]);
    }

    private function convertMessage(MessageInterface $msg): ?array
    {
        $role = match ($msg::class) {
            SystemMessage::class => 'system',
            UserMessage::class => 'user',
            AssistantMessage::class => 'assistant',
            ToolCallMessage::class => 'tool',
            default => 'user',
        };

        $content = $this->extractContent($msg);

        if ($msg instanceof ToolCallMessage) {
            return [
                'role' => 'tool',
                'tool_call_id' => $msg->getToolCall()->getId(),
                'content' => $content,
            ];
        }

        if ($msg instanceof AssistantMessage && $msg->hasToolCalls()) {
            $apiMsg = [
                'role' => 'assistant',
                'content' => $content,
            ];
            $apiMsg['tool_calls'] = array_map(
                fn (\Symfony\AI\Platform\Result\ToolCall $tc) => $tc->jsonSerialize(),
                $msg->getToolCalls(),
            );
            return $apiMsg;
        }

        return [
            'role' => $role,
            'content' => $content,
        ];
    }

    private function extractContent(MessageInterface $msg): array|string
    {
        if ($msg instanceof UserMessage) {
            $text = $msg->asText();
            if ($text !== null && $text !== '') {
                return [['type' => 'text', 'text' => $text]];
            }
            return '';
        }

        if ($msg instanceof SystemMessage) {
            $content = $msg->getContent();
            if (\is_string($content)) {
                return [['type' => 'text', 'text' => $content]];
            }
            return $content ?? '';
        }

        if ($msg instanceof AssistantMessage) {
            $content = $msg->getContent();
            if (\is_string($content)) {
                return [['type' => 'text', 'text' => $content]];
            }
            return $content ?? '';
        }

        if (\method_exists($msg, 'getContent')) {
            $content = $msg->getContent();
            if (\is_string($content)) {
                return [['type' => 'text', 'text' => $content]];
            }
            return $content ?? '';
        }

        return '';
    }
}
