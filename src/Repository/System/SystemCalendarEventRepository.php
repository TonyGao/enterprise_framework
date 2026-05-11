<?php

namespace App\Repository\System;

use App\Entity\System\SystemCalendarEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemCalendarEvent>
 */
class SystemCalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemCalendarEvent::class);
    }

    /**
     * 返回指定年月的所有日历事件（固定日期 + 当月匹配的每年重复规则）
     *
     * @return SystemCalendarEvent[]
     */
    public function findForMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end   = $start->modify('last day of this month');

        // 固定日期：属于该月的
        $fixed = $this->createQueryBuilder('e')
            ->where('e.recurring = false')
            ->andWhere('e.date >= :start')
            ->andWhere('e.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        // 每年重复：月份匹配的
        $recurring = $this->createQueryBuilder('e')
            ->where('e.recurring = true')
            ->getQuery()
            ->getResult();

        $result = array_values($fixed);
        foreach ($recurring as $event) {
            $rule = $event->getRecurringRule();
            if (isset($rule['type']) && $rule['type'] === 'annual') {
                if ((int)($rule['month'] ?? 0) === $month) {
                    $result[] = $event;
                }
            }
        }

        usort($result, fn($a, $b) => ($a->getDate()?->getTimestamp() ?? 0) <=> ($b->getDate()?->getTimestamp() ?? 0));

        return $result;
    }

    /**
     * 返回指定年份的所有日历事件
     *
     * @return SystemCalendarEvent[]
     */
    public function findForYear(int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $end   = new \DateTimeImmutable(sprintf('%04d-12-31', $year));

        $fixed = $this->createQueryBuilder('e')
            ->where('e.recurring = false')
            ->andWhere('e.date >= :start')
            ->andWhere('e.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        $recurring = $this->createQueryBuilder('e')
            ->where('e.recurring = true')
            ->getQuery()
            ->getResult();

        return array_merge(array_values($fixed), array_values($recurring));
    }
}
