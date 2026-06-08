<?php

namespace App\Service\Task;

use App\Entity\System\Task;
use App\Repository\System\TaskRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\RunTaskMessage;

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
                // 单次任务执行后会被禁用，无需更新 next_run_at（否则会计算出下一年同日期）
                if (($task->getPayload()['once'] ?? false) !== true) {
                    $nextRunAt = $this->calcNextRun($task->getCronExpression());
                    $this->connection->executeStatement(
                        'UPDATE sys_task SET last_run_at = NOW(), next_run_at = ? WHERE id = ?',
                        [
                            $nextRunAt->format('Y-m-d H:i:s'),
                            (string)$task->getId(),
                        ]
                    );
                } else {
                    $this->connection->executeStatement(
                        'UPDATE sys_task SET last_run_at = NOW() WHERE id = ?',
                        [(string)$task->getId()]
                    );
                }

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
        $base = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai'));

        $cronClass = 'Cron\\CronExpression';
        if (class_exists($cronClass)) {
            $cron = new $cronClass($expr);
            $next = $cron->getNextRunDate(\DateTime::createFromImmutable($base), 0, false);
            return \DateTimeImmutable::createFromMutable($next)->setTimezone($base->getTimezone());
        }

        $fallback = $this->calcNextRunByMinuteScan($expr, $base);
        if ($fallback instanceof \DateTimeImmutable) {
            return $fallback;
        }

        // 兜底：无法解析时至少推进到下一天同一时刻，避免任务反复重入。
        return $base->modify('+1 day');
    }

    private function calcNextRunByMinuteScan(string $expr, \DateTimeImmutable $base): ?\DateTimeImmutable
    {
        $parts = preg_split('/\s+/', trim($expr));
        if (!is_array($parts) || count($parts) < 5) {
            return null;
        }

        [$minExpr, $hourExpr, $dayExpr, $monthExpr, $weekExpr] = array_slice($parts, 0, 5);

        $cursor = $base->setTime((int) $base->format('H'), (int) $base->format('i'))->modify('+1 minute');

        for ($i = 0; $i < 525600; $i++) {
            $minute = (int) $cursor->format('i');
            $hour = (int) $cursor->format('G');
            $day = (int) $cursor->format('j');
            $month = (int) $cursor->format('n');
            $week = (int) $cursor->format('w');

            if (
                $this->cronFieldMatches($minute, $minExpr, 0, 59)
                && $this->cronFieldMatches($hour, $hourExpr, 0, 23)
                && $this->cronFieldMatches($day, $dayExpr, 1, 31)
                && $this->cronFieldMatches($month, $monthExpr, 1, 12)
                && $this->cronFieldMatches($week, $weekExpr, 0, 6)
            ) {
                return $cursor;
            }

            $cursor = $cursor->modify('+1 minute');
        }

        return null;
    }

    private function cronFieldMatches(int $value, string $expr, int $min, int $max): bool
    {
        $expr = trim($expr);
        if ($expr === '*') {
            return true;
        }

        $chunks = explode(',', $expr);
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\*\/(\d+)$/', $chunk, $m)) {
                $step = max(1, (int) $m[1]);
                if (($value - $min) % $step === 0) {
                    return true;
                }
                continue;
            }

            if (preg_match('/^(\d+)-(\d+)$/', $chunk, $m)) {
                $a = (int) $m[1];
                $b = (int) $m[2];
                if ($value >= $a && $value <= $b) {
                    return true;
                }
                continue;
            }

            if (ctype_digit($chunk)) {
                $n = (int) $chunk;
                // 兼容周字段 7 代表周日
                if ($max === 6 && $n === 7) {
                    $n = 0;
                }
                if ($n >= $min && $n <= $max && $value === $n) {
                    return true;
                }
            }
        }

        return false;
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
