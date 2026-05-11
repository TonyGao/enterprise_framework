<?php

namespace App\Repository\System;

use App\Entity\System\Task;
use App\Entity\System\TaskLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskLog>
 */
class TaskLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskLog::class);
    }

    /**
     * 分页查询某任务的执行日志
     */
    public function findByTaskPaginated(Task $task, int $page = 1, int $pageSize = 20): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.task = :task')
            ->setParameter('task', $task)
            ->orderBy('l.startedAt', 'DESC');

        $total = (clone $qb)->select('COUNT(l.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $pageSize)
                    ->setMaxResults($pageSize)
                    ->getQuery()
                    ->getResult();

        return ['total' => (int)$total, 'items' => $items];
    }

    /**
     * 获取各任务在指定日期范围内的执行统计（用于日历视图）
     * 返回 [taskId, status, date, count]
     */
    public function getCalendarStats(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Use native SQL for date extraction to ensure compatibility across DB platforms
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT task_id AS "taskId", status, DATE(started_at) AS "date", COUNT(*) AS cnt
FROM sys_task_log
WHERE started_at >= :from AND started_at <= :to
GROUP BY task_id, status, DATE(started_at)
SQL;
        $rows = $conn->fetchAllAssociative($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        // Normalize keys to match previous DQL expectations (taskId, status, date, cnt)
        return array_map(function ($r) {
            return [
                'taskId' => (string) ($r['taskId'] ?? $r['task_id'] ?? $r['taskid'] ?? ''),
                'status' => $r['status'] ?? null,
                'date'   => $r['date'] ?? null,
                'cnt'    => (int) ($r['cnt'] ?? $r['count'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * 统计指定时间范围内的执行总次数（用于今日执行统计）
     */
    public function countBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.startedAt >= :from')
            ->andWhere('l.startedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取 N 天内的任务执行成功率统计
     */
    public function getSuccessRate(int $days = 7): array
    {
        $from = new \DateTimeImmutable("-{$days} days");

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT task_id, status, COUNT(*) AS cnt
             FROM sys_task_log
             WHERE started_at >= :from
             GROUP BY task_id, status',
            ['from' => $from->format('Y-m-d H:i:s')]
        );
    }
}
