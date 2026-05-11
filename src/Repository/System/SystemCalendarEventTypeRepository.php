<?php

namespace App\Repository\System;

use App\Entity\System\SystemCalendarEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemCalendarEventType>
 */
class SystemCalendarEventTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemCalendarEventType::class);
    }

    /**
     * @return SystemCalendarEventType[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNextSortOrder(): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) ($max ?? 0)) + 10;
    }
}