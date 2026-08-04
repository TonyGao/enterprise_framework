<?php

namespace App\Service\Platform\Llm;

use App\Repository\Platform\LlmRoleRepository;
use Psr\Log\LoggerInterface;

class LlmRouter
{
    public function __construct(
        private LlmRoleRepository $roleRepo,
        private LlmGatewayFactory $factory,
        private LoggerInterface $logger,
    ) {}

    public function chatByRole(string $roleCode, array $messages, array $options = []): ChatResponse
    {
        $role = $this->roleRepo->find($roleCode);
        if (!$role || !$role->isEnabled() || !$role->getProvider()) {
            throw new LlmException("Role '$roleCode' is not configured or disabled");
        }

        $mergedOptions = array_merge(
            $role->getOptions() ?? [],
            $options,
        );

        $fullMessages = [];
        if ($role->getSystemPrompt()) {
            $fullMessages[] = ['role' => 'system', 'content' => $role->getSystemPrompt()];
        }
        foreach ($messages as $msg) {
            $fullMessages[] = $msg;
        }

        $provider = $role->getProvider();
        $gateway = $this->factory->create($provider);

        $this->logger->info('LLM call', [
            'role' => $roleCode,
            'model' => $gateway->getModelName(),
            'provider' => $provider->getName(),
        ]);

        // 瞬时错误（429/5xx）重试：LLM 网关繁忙时避免一次失败即返回给上层
        $attempts = (int) ($options['retry'] ?? 3);
        $lastException = null;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $gateway->chat($fullMessages, $mergedOptions);
            } catch (LlmException $e) {
                $lastException = $e;
                $statusCode = $e->context['statusCode'] ?? 0;
                $retryable = in_array($statusCode, [408, 429, 500, 502, 503, 504], true);
                if (!$retryable || $i === $attempts) {
                    break;
                }
                $this->logger->warning('LLM 瞬时错误，准备重试', [
                    'role' => $roleCode,
                    'provider' => $provider->getName(),
                    'statusCode' => $statusCode,
                    'attempt' => $i,
                ]);
                usleep($i * 800000); // 0.8s, 1.6s, ...
            } catch (\Exception $e) {
                $lastException = $e;
                break;
            }
        }

        $this->logger->error('LLM call failed', [
            'role' => $roleCode,
            'provider' => $provider->getName(),
            'error' => $lastException ? $lastException->getMessage() : 'unknown',
        ]);
        throw $lastException ?? new LlmException('LLM call failed');
    }

    public function chatByRoleWithFallback(string $roleCode, array $messages, array $options = []): ChatResponse
    {
        $role = $this->roleRepo->find($roleCode);
        if (!$role) {
            throw new LlmException("Role '$roleCode' not found");
        }

        $providers = [$role->getProvider()];

        $lastException = null;
        foreach ($providers as $provider) {
            if (!$provider || !$provider->isEnabled()) continue;
            try {
                return $this->chatByRole($roleCode, $messages, $options);
            } catch (LlmException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new LlmException("All providers failed for role '$roleCode'");
    }
}
