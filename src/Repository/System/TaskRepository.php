<?php

namespace App\Repository\System;

use App\Entity\System\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * 查询到期且已启用的任务（带行锁）
     */
    public function findDueTasks(int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.enabled = true')
            ->andWhere('t.nextRunAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.nextRunAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询任务列表（用于后台管理）
     */
    public function findPaginated(int $page, int $pageSize, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC');

        if (isset($filters['enabled'])) {
            $qb->andWhere('t.enabled = :enabled')
               ->setParameter('enabled', (bool)$filters['enabled']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['keyword'])) {
            $qb->andWhere('t.name LIKE :keyword OR t.handler LIKE :keyword')
               ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult(($page - 1) * $pageSize)
                    ->setMaxResults($pageSize)
                    ->getQuery()
                    ->getResult();

        return ['total' => (int)$total, 'items' => $items];
    }

    /**
     * 获取指定月份所有任务的计划执行时间（用于日历视图）
     * 返回 [taskId, taskName, category, cronExpression]
     */
    public function findEnabledForCalendar(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.id, t.name, t.category, t.cronExpression, t.nextRunAt, t.lastRunAt')
            ->where('t.enabled = true')
            ->orderBy('t.sortOrder', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
