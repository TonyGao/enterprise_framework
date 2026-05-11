<?php

namespace App\Service\Task;

/**
 * 所有定时任务 Handler 必须实现此接口，
 * 并在 services.yaml 中使用 app.task_handler tag 注册。
 *
 * 示例服务注册：
 *   App\Task\SendEmailTask:
 *     tags:
 *       - { name: 'app.task_handler', key: 'App\Task\SendEmailTask' }
 */
interface TaskHandlerInterface
{
    /**
     * 执行任务业务逻辑。
     *
     * @param array $payload 来自 sys_task.payload 的 JSON 参数
     * @throws \Throwable 发生错误时抛出，由 MessageHandler 记录并触发 Messenger 重试
     */
    public function handle(array $payload): void;
}
