<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiResponse;
use App\Entity\Platform\AiChatMessage;
use App\Entity\Platform\AiChatSession;
use App\Entity\Platform\AiOperationLog;
use App\Service\AI\Orchestrator\ViewSubAgentChatRouter;
use App\Service\AI\Runtime\AiAssistant;
use App\Service\AI\Runtime\AiContextRegistry;
use App\Service\Platform\View\VersionNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #[IsGranted('ROLE_ADMIN')]
class AiChatController extends AbstractController
{
    public function __construct(
        private readonly AiContextRegistry $contextRegistry,
        private readonly AiAssistant $assistant,
        private readonly EntityManagerInterface $em,
        private readonly ViewSubAgentChatRouter $subAgentChatRouter,
        private readonly HubInterface $hub,
        private readonly TranslatorInterface $translator,
        private readonly \App\Service\AI\ImageStyleAnalyzer $imageStyleAnalyzer,
    ) {}

    #[Route(
        '/api/admin/ai/chat',
        name: 'api_admin_ai_chat',
        methods: ['POST']
    )]
    public function chat(Request $request): ApiResponse
    {
        $startTime = microtime(true);
        $payload = $request->toArray();
        $context = $payload['context'] ?? '';
        $message = $payload['message'] ?? '';
        $contextId = $payload['context_id'] ?? $payload['contextId'] ?? '';
        $sessionId = $payload['session_id'] ?? $payload['sessionId'] ?? null;
        $roleCode = '';

        if (!$context || !$message) {
            return ApiResponse::error('msg.ai.context_message_required', 400);
        }

        if (!$contextId) {
            $contextId = $request->headers->get('Referer') ?? '';
        }

        if (!$this->contextRegistry->has($context)) {
            return ApiResponse::error("不支持的 AI 上下文: $context", 400);
        }

        try {
            $session = $this->getOrCreateSession($context, $contextId, $sessionId);
            $history = $this->loadHistory($session);

            $provider = $this->contextRegistry->get($context);
            $roleCode = $provider->getRoleCode();
            $aiPrompt = $provider->buildPrompt($message, $contextId);

            // 视图编辑器统一走 main/sub agent：每条消息由 IntentAgent（LLM）判定意图/模式/澄清
            $systemPrompt = $provider->getSystemPrompt();
            $tools = $provider->getToolProviders();
            $redesignApplied = false;
            if ($context === 'view_editor') {
                $turn = $this->subAgentChatRouter->classifyTurn($message, $contextId, $session);
                if ($turn['needsClarification']) {
                    // 持久化澄清问答（用户原始消息 + 澄清问题/选项），保证聊天历史完整
                    $optionsText = implode("\n", array_map(
                        fn(string $o) => '- ' . $o,
                        $turn['clarificationOptions']
                    ));
                    $clarificationReply = $turn['clarificationQuestion'] . ($optionsText !== '' ? "\n\n" . $optionsText : '');
                    $assistantMsgId = $this->saveMessages(
                        $session,
                        $message,
                        $clarificationReply,
                        [
                            'type' => 'clarification',
                            'question' => $turn['clarificationQuestion'],
                            'options' => $turn['clarificationOptions'],
                        ]
                    );
                    $this->em->flush();

                    return ApiResponse::success(json_encode([
                        'type' => 'clarification',
                        'question' => $turn['clarificationQuestion'],
                        'options' => $turn['clarificationOptions'],
                        'assistantMessageId' => $assistantMsgId,
                    ]));
                }
                $systemPrompt = $turn['systemPrompt'];
                $tools = $turn['tools'];
                $redesignApplied = $turn['redesignApplied'];

                // 若用户上传了图片：调用 vision 分析，把"参考图片风格"注入系统提示
                $imageIds = $payload['image_ids'] ?? [];
                $imagePaths = $this->resolveImagePaths($imageIds);
                if ($imagePaths !== []) {
                    try {
                        $imageSpec = $this->imageStyleAnalyzer->analyze($imagePaths, $message);
                        if ($imageSpec !== []) {
                            $systemPrompt .= "\n\n" . \App\Service\AI\Orchestrator\SubAgent\AbstractViewSubAgent::imageSpecBlock($imageSpec);
                        }
                        $this->em->flush();
                    } catch (\Throwable $ve) {
                        // vision 未绑定或分析失败：不阻断主流程，仅提示（见回复注释）
                    }
                }
                $this->em->flush();
            }

            // 文件工具写入用户当前查看的版本（contextId 中的 ?version=），而非视图 current_version
            $request->attributes->set('ai_view_version', $this->versionFromContext($contextId));

            // 释放 PHP session 文件锁（长 LLM 调用期间不能让同会话其它请求阻塞）
            $request->getSession()?->save();

            // 进度推送 + 执行日志：为本次请求建独立分发器，记录 AI 实际调用的工具
            $topic = $this->chatProgressTopic($contextId);
            $dispatcher = new EventDispatcher();
            $executedTools = [];
            $dispatcher->addListener(ToolCallSucceeded::class, function ($event) use ($topic, &$executedTools): void {
                $tool = '工具操作';
                $args = [];
                try {
                    $tool = (string) $event->getMetadata()->getName();
                    $args = $event->getArguments();
                } catch (\Throwable) {
                }
                $executedTools[] = [
                    'tool' => $tool,
                    'args' => $this->normalizeToolArgs($args),
                    'status' => 'ok',
                ];
                $this->publishProgress($topic, ['status' => 'tool', 'tool' => $tool]);
            });
            $dispatcher->addListener(ToolCallFailed::class, function ($event) use ($topic, &$executedTools): void {
                $tool = '工具操作';
                $args = [];
                $error = '';
                try {
                    $tool = (string) $event->getMetadata()->getName();
                    $args = $event->getArguments();
                    $error = $event->getThrowable()->getMessage();
                } catch (\Throwable) {
                }
                $executedTools[] = [
                    'tool' => $tool,
                    'args' => $this->normalizeToolArgs($args),
                    'status' => 'error',
                    'error' => $error,
                ];
                $this->publishProgress($topic, ['status' => 'tool_error', 'tool' => $tool]);
            });
            $this->publishProgress($topic, ['status' => 'started']);

            $result = $this->assistant->chat(
                roleCode: $roleCode,
                systemPrompt: $systemPrompt,
                toolProviders: $tools,
                userMessage: $aiPrompt,
                history: $history,
                eventDispatcher: $dispatcher,
            );

            // 记录 AI 实际执行的工具调用，便于诊断"描述但不执行"
            $this->logExecutedTools($context, $contextId, $executedTools);

            // 表单布局/页面壳已由服务器端落盘：编辑器 DOM 不会自动同步，强制前端刷新以展示新布局
            $layoutTools = ['form_applyLayout', 'page_applyShell'];
            if (!$redesignApplied && !empty($executedTools)) {
                $redesignApplied = (bool) array_filter(
                    $executedTools,
                    fn (array $t) => in_array($t['tool'] ?? '', $layoutTools, true) && ($t['status'] ?? '') === 'ok'
                );
            }

            $this->publishProgress($topic, ['status' => 'done']);

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $assistantMessageId = $this->saveMessages(
                $session,
                $message,
                $result['reply'],
                [
                    'elapsedMs' => $elapsedMs,
                    'toolCount' => count($executedTools),
                    'redesignApplied' => $redesignApplied,
                ]
            );

            // Fix history: replace augmented prompt with original text
            $history = $result['history'];
            $lastIdx = count($history) - 2;
            if ($lastIdx >= 0 && ($history[$lastIdx]['role'] ?? '') === 'user') {
                $history[$lastIdx]['content'] = $message;
            }

            $this->logOperation($context, $roleCode, $message, $result['reply'], null, 'success', null, $elapsedMs);

            return ApiResponse::success(json_encode([
                'reply' => $result['reply'],
                'history' => $history,
                'sessionId' => (string) $session->getId(),
                'assistantMessageId' => $assistantMessageId,
                'redesignApplied' => $redesignApplied,
                'elapsedMs' => $elapsedMs,
                'toolCount' => count($executedTools),
            ]));
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logOperation($context, $roleCode, $message, null, null, 'error', $e->getMessage(), $elapsedMs);

            // 面向用户：给准确、可操作的提示（技术细节已在日志）
            $key = \App\Service\Platform\Llm\LlmErrorFormatter::keyFor($e);

            return ApiResponse::error('', 500, $this->translator->trans($key));
        }
    }

    private function getOrCreateSession(string $context, string $contextId, ?string $sessionId): AiChatSession
    {
        if ($sessionId) {
            $session = $this->em->getRepository(AiChatSession::class)->find($sessionId);
            if ($session) {
                return $session;
            }
        }

        $session = $this->em->getRepository(AiChatSession::class)->findOneBy([
            'context' => $context,
            'contextId' => $contextId,
            'isActive' => true,
        ]);

        if (!$session) {
            $session = new AiChatSession();
            $session->setContext($context);
            $session->setContextId($contextId);
            $session->setTitle('New Conversation');
            $this->em->persist($session);
            $this->em->flush();
        }

        return $session;
    }

    private function loadHistory(AiChatSession $session): array
    {
        $messages = $session->getMessages();
        $history = [];

        foreach ($messages as $msg) {
            $history[] = [
                'role' => $msg->getRole(),
                'content' => $msg->getContent(),
            ];
        }

        return $history;
    }

    private function saveMessages(AiChatSession $session, string $userMessage, string $assistantReply, ?array $assistantMeta = null): string
    {
        $userMsg = new AiChatMessage();
        $userMsg->setSession($session);
        $userMsg->setRole('user');
        $userMsg->setContent($userMessage);
        $this->em->persist($userMsg);

        $assistantMsg = new AiChatMessage();
        $assistantMsg->setSession($session);
        $assistantMsg->setRole('assistant');
        $assistantMsg->setContent($assistantReply);
        if ($assistantMeta !== null) {
            $assistantMsg->setMeta($assistantMeta);
        }
        $this->em->persist($assistantMsg);

        $this->em->flush();

        return (string) $assistantMsg->getId();
    }

    private function logOperation(
        string $context,
        string $roleCode,
        string $userMessage,
        ?string $assistantReply,
        ?array $toolCalls,
        string $status,
        ?string $errorMessage = null,
        ?int $elapsedMs = null,
    ): void {
        try {
            $log = new AiOperationLog();
            $log->setContext($context);
            $log->setRoleCode($roleCode);
            $log->setUserMessage($userMessage);
            $log->setAssistantReply($assistantReply);
            $log->setToolCalls($toolCalls);
            $log->setStatus($status);
            $log->setErrorMessage($errorMessage);
            $log->setElapsedMs($elapsedMs);

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // 静默失败，不影响主流程
        }
    }

    private function chatProgressTopic(string $contextId): string
    {
        return 'https://enterprise.local/ai/chat/' . rawurlencode($contextId);
    }

    /**
     * 归一化工具参数供日志记录：字符串值超长（如 writeDesign 的整页 html）时截断并标注长度，
     * 避免大字符串在请求内存中堆积导致 OOM。
     */
    private function normalizeToolArgs(mixed $args): mixed
    {
        if (is_array($args)) {
            $out = [];
            foreach ($args as $k => $v) {
                $out[$k] = is_string($v) ? $this->truncateArg($v) : $v;
            }
            return $out;
        }
        return is_string($args) ? $this->truncateArg($args) : $args;
    }

    private function truncateArg(string $s, int $max = 300): string
    {
        $len = mb_strlen($s);
        if ($len <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max) . '…(截断, 总长 ' . $len . ')';
    }

    private function versionFromContext(string $contextId): ?string
    {
        $parsed = parse_url($contextId);
        if (!isset($parsed['query'])) {
            return null;
        }
        parse_str($parsed['query'], $query);
        $v = $query['version'] ?? null;
        return ($v && VersionNumber::isValid((string) $v)) ? (string) $v : null;
    }

    private function filterToolNames(array $toolProviders, array $blocked): ?array
    {
        try {
            $toolbox = new \Symfony\AI\Agent\Toolbox\Toolbox($toolProviders);
            $names = array_map(fn (\Symfony\AI\Platform\Tool\Tool $t) => $t->getName(), $toolbox->getTools());
            $filtered = array_values(array_diff($names, $blocked));
            return count($filtered) === count($names) ? null : $filtered;
        } catch (\Throwable) {
            return null;
        }
    }

    private function logExecutedTools(string $context, string $contextId, array $tools): void
    {
        if (empty($tools)) {
            return;
        }
        try {
            $logFile = $this->getParameter('kernel.project_dir') . '/var/log/ai_tool_calls.log';
            $line = json_encode([
                'time' => date('Y-m-d H:i:s'),
                'context' => $context,
                'contextId' => mb_substr($contextId, 0, 120),
                'tools' => $tools,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            file_put_contents($logFile, $line . "\n", FILE_APPEND);
        } catch (\Throwable) {
            // 日志失败不影响主流程
        }
    }

    private function publishProgress(string $topic, array $data): void
    {
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode(array_merge(['type' => 'ai_chat_progress'], $data), JSON_UNESCAPED_UNICODE),
                true
            ));
        } catch (\Throwable) {
            // Mercure 推送失败不影响主流程
        }
    }

    #[Route(
        '/api/admin/ai/chat/history',
        name: 'api_admin_ai_chat_history',
        methods: ['POST']
    )]
    public function history(Request $request): ApiResponse
    {
        $payload = $request->toArray();
        $context = $payload['context'] ?? '';
        $contextId = $payload['context_id'] ?? '';
        $sessionId = $payload['session_id'] ?? null;

        if (!$context || !$contextId) {
            return ApiResponse::error('msg.ai.context_id_required', 400);
        }

        try {
            $session = null;

            if ($sessionId) {
                $session = $this->em->getRepository(AiChatSession::class)->find($sessionId);
            }

            if (!$session) {
                $session = $this->em->getRepository(AiChatSession::class)->findOneBy([
                    'context' => $context,
                    'contextId' => $contextId,
                    'isActive' => true,
                ]);
            }

            if (!$session) {
                return ApiResponse::success(json_encode([
                    'session_id' => null,
                    'messages' => [],
                ]));
            }

            $messages = [];
            foreach ($session->getMessages() as $msg) {
                $messages[] = [
                    'id' => (string) $msg->getId(),
                    'role' => $msg->getRole(),
                    'content' => $msg->getContent(),
                    'meta' => $msg->getMeta(),
                ];
            }

            return ApiResponse::success(json_encode([
                'session_id' => (string) $session->getId(),
                'messages' => $messages,
            ]));
        } catch (\Exception $e) {
            $key = \App\Service\Platform\Llm\LlmErrorFormatter::keyFor($e);

            return ApiResponse::error('', 500, $this->translator->trans($key));
        }
    }

    /**
     * 上传一张图片供 AI 风格分析。存放到 var/data/ai_uploads/{uuid}.{ext}，
     * 上传成功后返回 imageId（uuid，不含扩展名），前端随后在消息里带 image_ids 提交。
     */
    #[Route('/api/admin/ai/chat/upload', name: 'api_admin_ai_chat_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): ApiResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return ApiResponse::error('msg.ai.image_required', 400);
        }
        if (!$file->isValid() || !str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return ApiResponse::error('msg.ai.image_invalid', 400);
        }

        $dir = $this->imageUploadDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $imageId = bin2hex(random_bytes(16));
        $ext = strtolower($file->guessExtension() ?: 'png');
        $filename = $imageId . '.' . $ext;
        $file->move($dir, $filename);

        return ApiResponse::success(json_encode([
            'imageId' => $imageId,
            'name' => $request->request->get('name') ?: $file->getClientOriginalName(),
        ]));
    }

    /** 把前端传来的 image_ids 解析成磁盘路径（var/data/ai_uploads/{id}.*） */
    private function resolveImagePaths(array $imageIds): array
    {
        $paths = [];
        $dir = $this->imageUploadDir();
        if (!is_dir($dir)) {
            return $paths;
        }
        foreach ($imageIds as $id) {
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
                continue;
            }
            $found = glob($dir . '/' . $id . '.*');
            if ($found) {
                $paths[] = $found[0];
            }
        }
        return $paths;
    }

    private function imageUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/var/data/ai_uploads';
    }
}
