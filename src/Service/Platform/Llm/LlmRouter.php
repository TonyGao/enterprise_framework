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

        try {
            return $gateway->chat($fullMessages, $mergedOptions);
        } catch (\Exception $e) {
            $this->logger->error('LLM call failed', [
                'role' => $roleCode,
                'provider' => $provider->getName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
