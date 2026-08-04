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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        ?EventDispatcherInterface $eventDispatcher = null,
    ): array {
        $platform = new LlmRouterPlatform($this->router, $this->converter);
        $toolbox = new FaultTolerantToolbox(new Toolbox($toolProviders, eventDispatcher: $eventDispatcher));

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
        // 整页 HTML 生成（如 viewfile_writeDesign 输出上万字符）需要远大于默认 4096 的 max_tokens，
        // 否则输出被截断导致工具参数不完整、反复失败重试（曾造成每次重构数百秒浪费）。
        $options['maxTokens'] = $options['maxTokens'] ?? 20000;

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
            // 工具调用参数可能含整页 HTML（如 writeDesign 的 html 达上万字符），
            // 直接序列化会撑大聊天历史导致内存与 prompt 膨胀；仅保留工具名+截断摘要。
            $parts = [];
            foreach ($result->getContent() as $tc) {
                $parts[] = \sprintf('[%s: %s]', $tc->getName(), $this->truncate((string) json_encode($tc->getArguments())));
            }
            return implode("\n", $parts);
        }
        return (string) $result;
    }

    private function truncate(string $s, int $max = 400): string
    {
        $len = mb_strlen($s);
        if ($len <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max) . '…(截断, 总长 ' . $len . ')';
    }
}