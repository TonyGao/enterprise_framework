<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiResponse;
use App\Entity\Platform\AiChatMessage;
use App\Entity\Platform\AiChatSession;
use App\Entity\Platform\AiOperationLog;
use App\Service\AI\Orchestrator\ViewSubAgentChatRouter;
use App\Service\AI\Runtime\AiAssistant;
use App\Service\AI\Runtime\AiContextRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
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
            return ApiResponse::error('context 和 message 不能为空', 400);
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

            // 视图编辑器统一走 main/sub agent：按会话意图选 Sub Agent 的 CDP 提示词
            $systemPrompt = $provider->getSystemPrompt();
            if ($context === 'view_editor') {
                try {
                    $systemPrompt = $this->subAgentChatRouter->resolveSystemPrompt($message, $contextId, $session);
                    $this->em->flush();
                } catch (\Throwable $e) {
                    // 意图路由失败时回退到默认提示词，保证对话不中断
                }
            }

            $result = $this->assistant->chat(
                roleCode: $roleCode,
                systemPrompt: $systemPrompt,
                toolProviders: $provider->getToolProviders(),
                userMessage: $aiPrompt,
                history: $history,
            );

            $this->saveMessages($session, $message, $result['reply']);

            // Fix history: replace augmented prompt with original text
            $history = $result['history'];
            $lastIdx = count($history) - 2;
            if ($lastIdx >= 0 && ($history[$lastIdx]['role'] ?? '') === 'user') {
                $history[$lastIdx]['content'] = $message;
            }

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logOperation($context, $roleCode, $message, $result['reply'], null, 'success', null, $elapsedMs);

            return ApiResponse::success(json_encode([
                'reply' => $result['reply'],
                'history' => $history,
                'sessionId' => (string) $session->getId(),
            ]));
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logOperation($context, $roleCode, $message, null, null, 'error', $e->getMessage(), $elapsedMs);

            return ApiResponse::error('', 500, 'AI 响应失败: ' . $e->getMessage());
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

    private function saveMessages(AiChatSession $session, string $userMessage, string $assistantReply): void
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
        $this->em->persist($assistantMsg);

        $this->em->flush();
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
            return ApiResponse::error('context 和 context_id 不能为空', 400);
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
                    'role' => $msg->getRole(),
                    'content' => $msg->getContent(),
                ];
            }

            return ApiResponse::success(json_encode([
                'session_id' => (string) $session->getId(),
                'messages' => $messages,
            ]));
        } catch (\Exception $e) {
            return ApiResponse::error('', 500, '获取历史记录失败: ' . $e->getMessage());
        }
    }
}
