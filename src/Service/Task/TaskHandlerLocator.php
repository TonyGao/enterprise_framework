<?php

namespace App\Service\Task;

use Psr\Container\ContainerInterface;

/**
 * 通过 Symfony ServiceLocator 按注册 key 获取任务 Handler，
 * 避免直接依赖完整的 ServiceContainer（符合最小权限原则）。
 */
class TaskHandlerLocator
{
    public function __construct(
        private readonly ContainerInterface $locator
    ) {}

    /**
     * @throws \RuntimeException 当 handler key 未注册时
     */
    public function get(string $key): TaskHandlerInterface
    {
        if (!$this->locator->has($key)) {
            throw new \RuntimeException(
                "Task handler '{$key}' not found. " .
                "Ensure the class is tagged with 'app.task_handler' and key is set correctly."
            );
        }

        return $this->locator->get($key);
    }

    public function has(string $key): bool
    {
        return $this->locator->has($key);
    }
}
