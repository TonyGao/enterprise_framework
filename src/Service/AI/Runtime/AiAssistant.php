<?php

namespace App\Service\AI\Runtime;

use App\Service\Platform\Llm\LlmRouter;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

class AiAssistant
{
    public function __construct(
        private readonly LlmRouter $router,
        private readonly ChatResultConverter $converter,
    ) {}

    /**
     * @param array[] $history  [{role: string, content: string}, ...]
     * @return array{reply: string, history: array[]}
     */
    public function chat(
        string $roleCode,
        string $systemPrompt,
        array $toolProviders,
        string $userMessage,
        ?array $toolNames = null,
        array $history = [],
    ): array {
        $platform = new LlmRouterPlatform($this->router, $this->converter);
        $toolbox = new FaultTolerantToolbox(new Toolbox($toolProviders));

        $agent = new Agent(
            platform: $platform,
            model: $roleCode,
            inputProcessors: [
                new SystemPromptInputProcessor($systemPrompt),
                new AgentProcessor($toolbox),
            ],
            outputProcessors: [
                new AgentProcessor($toolbox),
            ],
            name: 'ai-assistant',
        );

        $messages = [];
        foreach ($history as $entry) {
            $role = $entry['role'] ?? '';
            $content = $entry['content'] ?? '';
            if ($role === 'user') {
                $messages[] = Message::ofUser($content);
            } elseif ($role === 'assistant') {
                $messages[] = Message::ofAssistant($content);
            }
        }
        $messages[] = Message::ofUser($userMessage);

        $options = [];
        if ($toolNames !== null) {
            $options['tools'] = $toolNames;
        }

        $result = $agent->call(new MessageBag(...$messages), $options);

        $reply = $this->getContent($result);

        $newHistory = $history;
        $newHistory[] = ['role' => 'user', 'content' => $userMessage];
        $newHistory[] = ['role' => 'assistant', 'content' => $reply];

        return ['reply' => $reply, 'history' => $newHistory];
    }

    private function getContent(ResultInterface $result): string
    {
        if ($result instanceof TextResult) {
            return $result->getContent();
        }
        if ($result instanceof ToolCallResult) {
            $parts = [];
            foreach ($result->getContent() as $tc) {
                $parts[] = \sprintf('[%s: %s]', $tc->getName(), json_encode($tc->getArguments()));
            }
            return implode("\n", $parts);
        }
        return (string) $result;
    }
}