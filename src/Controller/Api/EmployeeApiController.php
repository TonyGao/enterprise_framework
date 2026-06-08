<?php

namespace App\Controller\Api;

use App\Controller\Api\ApiResponse;
use App\Entity\Organization\Employee;
use App\Repository\Organization\CompanyRepository;
use App\Repository\Organization\DepartmentRepository;
use App\Repository\Organization\PositionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employee')]
#[IsGranted('ROLE_SYS_ADMIN')]
class EmployeeApiController extends AbstractController
{
    #[Route('/create', name: 'api_employee_create', methods: ['POST'])]
    public function create(
        \Symfony\Component\HttpFoundation\Request $request,
        EntityManagerInterface $em,
        CompanyRepository $companyRepo,
        DepartmentRepository $departmentRepo,
        PositionRepository $positionRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['success' => false, 'error' => 'Name is required'], 400);
        }

        $employee = new Employee();
        $employee->setName($data['name']);
        
        // Auto-generate employee number if not provided
        $employeeNo = $data['employeeNo'] ?? null;
        if (empty($employeeNo)) {
            $employeeNo = 'EMP' . date('YmdHis');
        }
        $employee->setEmployeeNo($employeeNo);
        
        // Always set default role from backend, ignore frontend roles
        $employee->setRoles(['ROLE_USER']);
        
        if (!empty($data['englishName'])) {
            $employee->setEnglishName($data['englishName']);
        }
        
        if (!empty($data['gender'])) {
            $employee->setGender($data['gender']);
        }
        
        if (!empty($data['birthDate'])) {
            try {
                $employee->setBirthDate(new \DateTime($data['birthDate']));
            } catch (\Exception $e) {}
        }
        
        if (!empty($data['mobile'])) {
            $employee->setMobile($data['mobile']);
        }
        
        if (!empty($data['email'])) {
            $employee->setEmail($data['email']);
        }
        
        if (!empty($data['idCard'])) {
            $employee->setIdCard($data['idCard']);
        }
        
        if (!empty($data['address'])) {
            $employee->setAddress($data['address']);
        }
        
        if (!empty($data['school'])) {
            $employee->setSchool($data['school']);
        }
        
        if (!empty($data['major'])) {
            $employee->setMajor($data['major']);
        }
        
        if (!empty($data['education'])) {
            $employee->setEducation($data['education']);
        }
        
        if (!empty($data['graduationDate'])) {
            try {
                $employee->setGraduationDate(new \DateTime($data['graduationDate']));
            } catch (\Exception $e) {}
        }
        
        if (!empty($data['emergencyContact'])) {
            $employee->setEmergencyContact($data['emergencyContact']);
        }
        
        if (!empty($data['emergencyPhone'])) {
            $employee->setEmergencyPhone($data['emergencyPhone']);
        }
        
        if (!empty($data['employeeNo'])) {
            $employee->setEmployeeNo($data['employeeNo']);
        }
        
        if (!empty($data['status'])) {
            $employee->setStatus($data['status']);
        }
        
        if (!empty($data['hireDate'])) {
            try {
                $employee->setHireDate(new \DateTime($data['hireDate']));
            } catch (\Exception $e) {}
        }
        
        if (!empty($data['company'])) {
            $company = $companyRepo->find($data['company']);
            if ($company) {
                $employee->setCompany($company);
            }
        }
        
        if (!empty($data['department'])) {
            $department = $departmentRepo->find($data['department']);
            if ($department) {
                $employee->setDepartment($department);
            }
        }
        
        if (!empty($data['position'])) {
            $position = $positionRepo->find($data['position']);
            if ($position) {
                $employee->setPosition($position);
            }
        }
        
        if (!empty($data['manager'])) {
            $manager = $em->getRepository(Employee::class)->find($data['manager']);
            if ($manager) {
                $employee->setManager($manager);
            }
        }
        
        if (!empty($data['username'])) {
            $employee->setUsername($data['username']);
        }
        
        if (!empty($data['password'])) {
            $employee->setPassword($data['password']);
        }
        
        if (isset($data['isActive'])) {
            $employee->setIsActive($data['isActive']);
        }

        try {
            $em->persist($employee);
            $em->flush();

            // 如果勾选了“按入职日期启用账号”
            if (!empty($data['activateOnHireDate']) && !empty($data['hireDate'])) {
                $executeAt = $data['scheduledTask']['executeAt'] ?? null;
                $this->createActivationTask($employee, $data['hireDate'], $em, $executeAt);
            }

            return ApiResponse::success(json_encode([
                'employee' => [
                    'id'         => (string) $employee->getId(),
                    'name'       => $employee->getName(),
                    'employeeNo' => $employee->getEmployeeNo(),
                ],
            ]));
        } catch (UniqueConstraintViolationException $e) {
            $msgKey = $this->resolveUniqueViolationKey($e->getMessage());
            return ApiResponse::error(json_encode([]), 409, $msgKey);
        } catch (\Exception $e) {
            return ApiResponse::error(json_encode([]), 500, 'employee.error.create_failed');
        }
    }

    /** 根据 UniqueConstraintViolationException 消息推断违反了哪个字段约束 */
    private function resolveUniqueViolationKey(string $message): string
    {
        if (preg_match('/Key \(([^)]+)\)=/', $message, $matches)) {
            return match ($matches[1]) {
                'email'       => 'employee.error.duplicate_email',
                'username'    => 'employee.error.duplicate_username',
                'employee_no' => 'employee.error.duplicate_employee_no',
                default       => 'employee.error.duplicate_field',
            };
        }
        return 'employee.error.create_failed';
    }

    #[Route('/delete', name: 'api_employee_delete', methods: ['POST'])]
    public function delete(
        \Symfony\Component\HttpFoundation\Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            return $this->json(['success' => false, 'error' => 'No IDs provided'], 400);
        }

        $deleted = 0;
        $errors = [];

        foreach ($ids as $id) {
            $employee = $em->getRepository(Employee::class)->find($id);
            
            if (!$employee) {
                $errors[] = "Employee not found: $id";
                continue;
            }

            if ($employee->getIsSystem()) {
                $errors[] = "Cannot delete system user: {$employee->getUsername()}";
                continue;
            }

            $em->remove($employee);
            $deleted++;
        }

        try {
            $em->flush();
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage(), 'deleted' => $deleted], 500);
        }

        return $this->json([
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors
        ]);
    }

    private function createActivationTask(Employee $employee, string $hireDateStr, EntityManagerInterface $em, ?string $executeAt = null): void
    {
        try {
            $tz = new \DateTimeZone('Asia/Shanghai');
            $hireDate = new \DateTime($hireDateStr, $tz);

            // 如果传入了具体执行时间，优先使用；否则默认为入职当天 00:05
            if ($executeAt) {
                $scheduledAt = new \DateTimeImmutable($executeAt, $tz);
            } else {
                $scheduledAt = \DateTimeImmutable::createFromMutable($hireDate)->setTime(0, 5);
            }

            // Cron 格式: 分 时 日 月 *
            $cron = sprintf('%d %d %d %d *',
                (int)$scheduledAt->format('i'),
                (int)$scheduledAt->format('G'),
                (int)$scheduledAt->format('j'),
                (int)$scheduledAt->format('n')
            );
            
            $task = new \App\Entity\System\Task();
            $task->setName('入职账号启用: ' . $employee->getName());
            $task->setDescription(sprintf('为员工 %s (%s) 在入职日期 %s 自动启用账号', $employee->getName(), $employee->getEmployeeNo(), $hireDateStr));
            $task->setCronExpression($cron);
            $task->setHandler('App\Task\ActivateAccountByHireDateTask');
            $task->setPayload([
                'employeeId' => (string)$employee->getId(),
                'once' => true,
            ]);
            $task->setEnabled(true);
            $task->setCategory('security');

            // 如果执行时间在过去，立即触发
            $now = new \DateTimeImmutable('now', $tz);
            if ($scheduledAt < $now) {
                $scheduledAt = $now;
            }
            $task->setNextRunAt($scheduledAt);

            $em->persist($task);
            $em->flush();
        } catch (\Exception $e) {
            // Log error but don't fail the whole request
        }
    }
}
