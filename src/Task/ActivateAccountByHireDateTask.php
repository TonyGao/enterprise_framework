<?php

namespace App\Task;

use App\Entity\Organization\Employee;
use App\Service\Task\TaskHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 按入职日期启用账号的任务处理器
 */
class ActivateAccountByHireDateTask implements TaskHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {}

    public function handle(array $payload): void
    {
        $employeeId = $payload['employeeId'] ?? null;
        if (!$employeeId) {
            throw new \InvalidArgumentException('Missing employeeId in payload');
        }

        $employee = $this->em->getRepository(Employee::class)->find($employeeId);
        if (!$employee) {
            $this->logger->warning('ActivateAccountTask: Employee not found', ['id' => $employeeId]);
            return;
        }

        if ($employee->getIsActive() && $employee->getEmploymentStatus() === 'active') {
            $this->logger->info('ActivateAccountTask: Employee account already active', ['id' => $employeeId]);
            return;
        }

        $employee->setEmploymentStatus('active');
        $employee->setIsActive(true);
        $this->em->flush();

        $this->logger->info('ActivateAccountTask: Employee account activated successfully', [
            'id' => $employeeId,
            'name' => $employee->getName(),
            'employeeNo' => $employee->getEmployeeNo()
        ]);

        echo sprintf("Successfully activated account for employee %s (%s)", $employee->getName(), $employeeId);
    }
}
