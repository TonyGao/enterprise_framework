<?php

namespace App\Service\Task;

use App\Entity\System\Task;
use App\Repository\System\TaskRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\RunTaskMessage;
use Cron\CronExpression;

class TaskScheduler
{
    private const BATCH_LIMIT = 50;

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly MessageBusInterface $bus,
        private readonly Connection $connection,
    ) {}

    /**
     * 主调度入口：查找到期任务，分布式加锁后投递 Messenger 消息
     */
    public function run(): int
    {
        $tasks = $this->taskRepository->findDueTasks(self::BATCH_LIMIT);
        $dispatched = 0;

        foreach ($tasks as $task) {
            $lockKey = $this->taskLockKey($task);

            try {
                // pg_try_advisory_lock 使用 bigint，取 id 的 CRC32
                $locked = (bool) $this->connection->fetchOne(
                    'SELECT pg_try_advisory_lock(?)',
                    [$lockKey]
                );

                if (!$locked) {
                    continue; // 其他节点已在处理此任务
                }

                // 投递到 Messenger 队列异步执行
                $this->bus->dispatch(new RunTaskMessage((string)$task->getId()));

                // 更新本次执行时间，防止重入
                $nextRunAt = $this->calcNextRun($task->getCronExpression());
                $this->connection->executeStatement(
                    'UPDATE sys_task SET last_run_at = NOW(), next_run_at = ? WHERE id = ?',
                    [
                        $nextRunAt->format('Y-m-d H:i:s'),
                        (string)$task->getId(),
                    ]
                );

                $dispatched++;
            } finally {
                // 显式释放内存锁，防止长连接环境下产生僵尸锁
                $this->connection->executeStatement(
                    'SELECT pg_advisory_unlock(?)',
                    [$lockKey]
                );
            }
        }

        return $dispatched;
    }

    /**
     * 计算下次执行时间（使用 dragonmantank/cron-expression）
     */
    private function calcNextRun(string $expr): \DateTimeImmutable
    {
        $cron = new CronExpression($expr);
        $next = $cron->getNextRunDate('now', 0, false);
        return \DateTimeImmutable::createFromMutable($next);
    }

    /**
     * 将 UUID 字符串转换为稳定的 bigint 锁键
     */
    private function taskLockKey(Task $task): int
    {
        // crc32 返回有符号 32 位整数，PostgreSQL advisory lock 接受 bigint
        return abs(crc32((string)$task->getId()));
    }
}
