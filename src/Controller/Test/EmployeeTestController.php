<?php

namespace App\Controller\Test;

use App\Repository\Organization\CompanyRepository;
use App\Repository\Organization\DepartmentRepository;
use App\Repository\Organization\EmployeeRepository;
use App\Repository\Organization\PositionRepository;
use App\Repository\Security\PasswordPolicyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmployeeTestController extends AbstractController
{
    #[Route('/test/employee/list', name: 'test_employee_list')]
    public function list(Request $request): Response
    {
        // Mock Tree Data
        $tree = [
            [
                'id' => 'root',
                'name' => '集团总部',
                'type' => 'root',
                '__children' => [
                    [
                        'id' => 'comp-1',
                        'name' => '北京分公司',
                        'type' => 'company',
                        '__children' => [
                            ['id' => 'dept-1', 'name' => '研发部', 'type' => 'department', '__children' => []],
                            ['id' => 'dept-2', 'name' => '人事部', 'type' => 'department', '__children' => []],
                            ['id' => 'dept-3', 'name' => '财务部', 'type' => 'department', '__children' => []],
                        ]
                    ],
                    [
                        'id' => 'comp-2',
                        'name' => '上海分公司',
                        'type' => 'company',
                        '__children' => []
                    ]
                ]
            ]
        ];

        // Mock Table Data - Generate 100 items for pagination testing
        $allEmployees = [];
        for ($i = 1; $i <= 100; $i++) {
            $allEmployees[] = (object)[
                'empNo' => 'EMP' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'name' => '员工' . $i,
                'email' => 'employee' . $i . '@example.com',
                'avatar' => 'https://i.pravatar.cc/150?u=' . $i,
                'gender' => $i % 2 == 0 ? '女' : '男',
                'department' => '研发部',
                'position' => '高级工程师',
                'status' => $i % 3 == 0 ? 'Inactive' : ($i % 3 == 1 ? 'Active' : 'Vacation'),
                'entryDate' => date('Y-m-d', strtotime("-{$i} month")),
                'mobile' => '1380013800' . $i,
            ];
        }

        // Pagination Logic
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $totalItems = count($allEmployees);
        $totalPages = ceil($totalItems / $limit);
        $offset = ($page - 1) * $limit;
        
        $employees = array_slice($allEmployees, $offset, $limit);

        $tableHeaders = ['empNo', 'name', 'email', 'department', 'position', 'status', 'entryDate'];

        // Mock Stats Data
        $stats = [
            'total_employee' => ['value' => 120, 'change' => '+2', 'trend' => 'up'],
            'active_employee' => ['value' => 118, 'change' => '-2', 'trend' => 'down'],
            'total_department' => ['value' => 5, 'change' => '+1', 'trend' => 'up'],
            'average_tenure' => ['value' => '3.2', 'unit' => 'years', 'change' => '+1.2%', 'trend' => 'up'],
        ];

        return $this->render('test/employee/list.html.twig', [
            'tree' => $tree,
            'tableHeaders' => $tableHeaders,
            'entities' => $employees,
            'stats' => $stats,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'limit' => $limit,
                'start' => $offset + 1,
                'end' => min($offset + $limit, $totalItems)
            ]
        ]);
    }

    #[Route('/test/employee/detail', name: 'test_employee_detail')]
    public function detail(): Response
    {
        // Mock Employee Data (combining OrgUser and Employee fields)
        $employee = (object)[
            // Basic Info
            'id' => 'uuid-1234-5678',
            'name' => 'Raden Arma',
            'englishName' => 'Raden',
            'avatar' => 'https://i.pravatar.cc/300?u=raden',
            'position' => 'Web Development',
            'employeeNo' => 'AY 2881234',
            'status' => 'Active',
            'hireDate' => '2023-01-09',
            'email' => 'radenarma@gmail.com',
            'mobile' => '+1 854 945 343',
            
            // Org Info
            'department' => '研发部',
            'company' => '北京分公司',
            'manager' => (object)['id' => 'm1', 'name' => '李四', 'avatar' => 'https://i.pravatar.cc/300?u=lisi'],
            'subordinates' => [
                (object)['id' => 's1', 'name' => 'Sub1', 'avatar' => 'https://i.pravatar.cc/300?u=sub1'],
                (object)['id' => 's2', 'name' => 'Sub2', 'avatar' => 'https://i.pravatar.cc/300?u=sub2'],
                (object)['id' => 's3', 'name' => 'Sub3', 'avatar' => 'https://i.pravatar.cc/300?u=sub3']
            ],
            
            // Personal Info
            'gender' => 'Male',
            'birthDate' => '1990-01-01',
            'idCard' => '110101199001011234',
            'address' => '北京市海淀区中关村大街1号',
            'emergencyContact' => 'Jane Doe',
            'emergencyPhone' => '+1 854 945 344',
            
            // Education Info
            'education' => 'Bachelor',
            'school' => 'Tsinghua University',
            'major' => 'Computer Science',
            'graduationDate' => '2012-07-01',
            
            // Account Info
            'username' => 'raden.arma',
            'lastLoginAt' => '2024-02-26 09:30:00',
            'roles' => ['ROLE_USER', 'ROLE_MANAGER'],
            'isActive' => true,
            
            // Stats (Mock for Dashboard)
            'payout' => [
                'total' => 17877,
                'base' => 15000,
                'overtime' => 2877
            ],
            'timeWorked' => '47h 21m',
            'reimbursement' => 235.00,
            'kpiScore' => 900,
            'kpiTrend' => [
                ['month' => 'Jan', 'score' => 700],
                ['month' => 'Feb', 'score' => 720],
                ['month' => 'Mar', 'score' => 750],
                ['month' => 'Apr', 'score' => 740],
                ['month' => 'May', 'score' => 780],
                ['month' => 'Jun', 'score' => 820],
                ['month' => 'Jul', 'score' => 800],
                ['month' => 'Aug', 'score' => 850],
                ['month' => 'Sep', 'score' => 880],
                ['month' => 'Oct', 'score' => 900],
            ],
            
            // Tasks
            'activeTasks' => [
                [
                    'title' => 'Develop marketing strategy',
                    'deadline' => 'Feb 10, 2024 at 6:00 pm',
                    'status' => 'In Progress',
                    'color' => '#f97316' // Orange
                ],
                [
                    'title' => 'Re-branding oaxel (Logo, Website and Colors)',
                    'deadline' => 'Feb 10, 2024 at 6:00 pm',
                    'status' => 'Pending',
                    'color' => '#22c55e' // Green
                ],
                [
                    'title' => 'Re-design Landing Page for Upwork Client',
                    'deadline' => 'Feb 10, 2024 at 6:00 pm',
                    'status' => 'Review',
                    'color' => '#ec4899' // Pink
                ]
            ],
            
            // Activity Log
            'activityLog' => [
                [
                    'time' => '09:30',
                    'action' => 'Clock-in',
                    'status' => 'Early',
                    'statusColor' => '#22c55e'
                ],
                [
                    'time' => '11:00 - 13:00',
                    'action' => 'Break - Lunch',
                    'status' => 'Late',
                    'statusColor' => '#ef4444'
                ],
                [
                    'time' => '09:30',
                    'action' => 'Break - Asr prayer',
                    'status' => '',
                    'statusColor' => ''
                ],
                [
                    'time' => '23:59',
                    'action' => 'Clock-out',
                    'status' => 'GOOD',
                    'statusColor' => '#8b5cf6'
                ]
            ],
            
            // Documents
            'documents' => [
                ['name' => 'NDA - Raden Arma 2025', 'size' => '12mb', 'date' => '12 April', 'type' => 'pdf'],
                ['name' => 'Design Test ( UX Revam )', 'size' => '12mb', 'date' => '12 April', 'type' => 'fig'],
                ['name' => 'English - Video Introduction', 'size' => '12mb', 'date' => '12 April', 'type' => 'mp4']
            ]
        ];

        return $this->render('test/employee/detail.html.twig', [
            'employee' => $employee
        ]);
    }

    #[Route('/test/employee/onboarding', name: 'test_employee_onboarding')]
    public function onboarding(
        CompanyRepository $companyRepo,
        DepartmentRepository $departmentRepo,
        PositionRepository $positionRepo,
        EmployeeRepository $employeeRepo,
        PasswordPolicyRepository $passwordPolicyRepo
    ): Response
    {
        $companies = $companyRepo->createQueryBuilder('c')
            ->where('c.lvl > :lvl')
            ->setParameter('lvl', 0)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
        $departments = $departmentRepo->findAll();
        $positions = $positionRepo->findAll();
        
        $managers = $employeeRepo->createQueryBuilder('e')
            ->select('e.id', 'e.name')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
        
        $passwordPolicy = $passwordPolicyRepo->findOneBy([]);
        $defaultPassword = $passwordPolicy?->getDefaultPassword() ?? 'Welcome@123';

        return $this->render('test/employee/onboarding.html.twig', [
            'companies' => $companies,
            'departments' => $departments,
            'positions' => $positions,
            'managers' => $managers,
            'defaultPassword' => $defaultPassword,
        ]);
    }

    #[Route('/test/employee/dashboard', name: 'test_employee_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('test/employee/dashboard.html.twig', []);
    }

    #[Route('/test/employee/transfer', name: 'test_employee_transfer')]
    public function transfer(): Response
    {
        // Mock Data for Transfer Page
        $employee = (object)[
            'id' => 'emp-001',
            'name' => 'Raden Arma',
            'employeeNo' => 'AY 2881234',
            'avatar' => 'https://i.pravatar.cc/300?u=raden',
            'department' => '研发部',
            'position' => '高级工程师',
            'rank' => 'P7',
            'hireDate' => '2023-01-09',
            'status' => 'Active',
        ];

        $departments = [
            '研发部', '产品部', '设计部', '市场部', '销售部', '人事部', '财务部'
        ];

        $positions = [
            '初级工程师', '中级工程师', '高级工程师', '资深工程师', '架构师', '技术专家', '技术总监'
        ];

        return $this->render('test/employee/transfer.html.twig', [
            'employee' => $employee,
            'departments' => $departments,
            'positions' => $positions
        ]);
    }
}
