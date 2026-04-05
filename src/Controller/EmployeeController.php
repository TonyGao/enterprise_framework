<?php

namespace App\Controller;

use App\Entity\Organization\Department;
use App\Entity\Organization\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Entity\Platform\UserPreference;
use App\Entity\Traits\OrganizationTrait;

use App\Entity\Security\WebauthnCredential;
use App\Form\Organization\EmployeeType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ExportEmployeeMessage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[IsGranted('ROLE_USER')]
class EmployeeController extends AbstractController
{
    #[Route('/employee/{id}/edit', name: 'employee_edit', requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function edit(string $id, EntityManagerInterface $em, Request $request, HubInterface $hub, TranslatorInterface $translator): Response
    {
        $employee = $em->getRepository(Employee::class)->find($id);

        if (!$employee) {
            throw $this->createNotFoundException('Employee not found');
        }

        $form = $this->createForm(EmployeeType::class, $employee);
        $form->handleRequest($request);

        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'action.edit_success');
            
            $statusTransKey = [
                'active' => 'employee.employment_status.active',
                'inactive' => 'employee.employment_status.inactive'
            ][$employee->getEmploymentStatus()] ?? 'employee.employment_status.active';

            // Broadcast the updated info via Mercure SSE
            $update = new Update(
                '/entity/employee/' . $employee->getId(),
                json_encode([
                    'type' => 'sync',
                    'entity' => 'Employee',
                    'id' => $employee->getId(),
                    'name' => $employee->getName(),
                    'employeeNo' => $employee->getEmployeeNo(),
                    'department' => $employee->getDepartment() ? $employee->getDepartment()->getName() : '',
                    'position' => $employee->getPosition() ? $employee->getPosition()->getName() : '',
                    'employmentStatus' => $employee->getEmploymentStatus(),
                    'workStatus' => $employee->getWorkStatus(),
                    'statusTrans' => $translator->trans($statusTransKey, [], 'messages'),
                    'workStatusTrans' => $translator->trans('employee.work_status.' . $employee->getWorkStatus(), [], 'messages'),
                    'hireDate' => $employee->getHireDate() ? $employee->getHireDate()->format('Y-m-d') : ''
                ])
            );
            $hub->publish($update);
            
            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }
            
            // Can redirect back to the edit page or list
            return $this->redirectToRoute('employee_edit', ['id' => $id]);
        }

        if ($request->isXmlHttpRequest()) {
            $response = new Response(null, $form->isSubmitted() ? 422 : 200);
            return $this->render('employee/edit_drawer.html.twig', [
                'employee' => $employee,
                'form' => $form->createView(),
                'drawerId' => 'employee-drawer-' . $id,
            ], $response);
        }

        return $this->render('employee/edit.html.twig', [
            'employee' => $employee,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/employee/export', name: 'employee_export')]
    public function export(Request $request, MessageBusInterface $bus): Response
    {
        $filters = [
            'search' => $request->query->get('q'),
            'employmentStatus' => $request->query->get('employment_status', 'active'),
            'departmentId' => $request->query->get('department_id'),
            'companyId' => $request->query->get('company_id'),
            'includeSub' => $request->query->getBoolean('include_sub', true),
        ];

        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? (string)$user->getId() : $user->getUserIdentifier();

        // Dispatch async message to RabbitMQ/Redis
        $bus->dispatch(new ExportEmployeeMessage($userId, $filters));

        // Since the previous implementation was a direct download via `window.location.href`,
        // and we are switching to async, we return a JSON response or redirect back with a flash message.
        // Assuming the frontend might still be doing a direct window.open or location.href,
        // we can return an HTML snippet that closes the window or redirects back.
        // If it's an AJAX call, we return JSON. Let's handle both.
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'code' => 200,
                'message' => '导出任务已提交，系统将在后台处理。生成完毕后会自动发送通知。'
            ]);
        }

        $this->addFlash('success', '导出任务已提交，系统将在后台处理。生成完毕后会自动发送通知。');
        return $this->redirectToRoute('employee_list');
    }

    #[Route('/employee/export/download/{filename}', name: 'employee_export_download')]
    public function downloadExport(string $filename, #[Autowire('%kernel.project_dir%')] string $projectDir): Response
    {
        $exportDir = $projectDir . '/var/exports';
        $filePath = $exportDir . '/' . basename($filename);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('导出的文件不存在或已过期');
        }

        $response = new BinaryFileResponse($filePath);
        
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $now = new \DateTime('now', $timezone);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            '花名册导出_' . $now->format('Ymd_His') . '.xlsx'
        );

        // Optional: Delete file after download to save space
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/employee/import/template', name: 'employee_import_template')]
    public function importTemplate(): Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = ['工号', '姓名', '英文名', '部门', '职位', '性别', '邮箱', '手机号', '在职状态', '工作状态', '入职日期', '出生日期', '身份证号'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }

        // Enable autofilter
        $lastColumn = chr(ord('A') + count($headers) - 1);
        $sheet->setAutoFilter('A1:' . $lastColumn . '1');

        // Feishu/Lark-style Header Styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF1F2328'],
                'size' => 13,
                'name' => 'PingFang SC',
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFE3E5E8'],
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['argb' => 'FFF1F3F4'],
            ],
        ];
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Add sample data rows with styling
        $sampleData = [
            ['EMP001', '张三', 'Zhang San', '技术部', '工程师', '男', 'zhangsan@company.com', '13800138000', '在职', '工作', '2024-01-15', '1990-05-20', '110101199005201234'],
            ['EMP002', '李四', 'Li Si', '市场部', '经理', '女', 'lisi@company.com', '13800138001', '在职', '休假', '2023-06-01', '1992-08-15', '110101199208151234'],
        ];
        $row = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Apply data styling
        $dataStyle = [
            'font' => [
                'color' => ['argb' => 'FF464952'],
                'size' => 12,
                'name' => 'PingFang SC',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFE3E5E8'],
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:' . $lastColumn . ($row - 1))->applyFromArray($dataStyle);

        for ($i = 2; $i < $row; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(32);
            if ($i % 2 === 0) {
                $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->getFill()
                      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFFFFFFF');
            } else {
                $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->getFill()
                      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFF6F8FA');
            }
        }

        // Set column widths
        $columnWidths = [
            'A' => 12,  // 工号
            'B' => 10,  // 姓名
            'C' => 12,  // 英文名
            'D' => 18,  // 部门
            'E' => 12,  // 职位
            'F' => 6,   // 性别
            'G' => 24,  // 邮箱
            'H' => 14,  // 手机号
            'I' => 10,  // 在职状态
            'J' => 10,  // 工作状态
            'K' => 12,  // 入职日期
            'L' => 12,  // 出生日期
            'M' => 20,  // 身份证号
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = '员工导入模板.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        return $this->file($temp_file, $fileName)->deleteFileAfterSend(true);
    }

    #[Route('/employee/import/upload', name: 'employee_import_upload', methods: ['POST'])]
    public function importUpload(Request $request): Response
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $taskId = uniqid('import_');
        $uploadDir = sys_get_temp_dir();
        $fileName = $taskId . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $fileName);

        return $this->json(['taskId' => $taskId]);
    }

    #[Route('/employee/import/process', name: 'employee_import_process')]
    public function importProcess(Request $request, EntityManagerInterface $em): Response
    {
        $taskId = $request->query->get('taskId');
        if (!$taskId) {
            throw $this->createNotFoundException();
        }

        $filePath = sys_get_temp_dir() . '/' . $taskId . '.xlsx';
        if (!file_exists($filePath)) {
            $filePath = sys_get_temp_dir() . '/' . $taskId . '.xls';
            if (!file_exists($filePath)) {
                $filePath = sys_get_temp_dir() . '/' . $taskId . '.csv';
            }
        }

        if ($request->hasSession()) {
            $request->getSession()->save();
        }

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filePath, $em) {
            set_time_limit(0);
            
            if (!file_exists($filePath)) {
                echo "data: " . json_encode(['error' => 'File not found']) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                return;
            }

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();
                
                if ($highestRow <= 1) {
                    echo "data: " . json_encode(['error' => 'Empty file']) . "\n\n";
                    if (ob_get_level() > 0) ob_flush(); flush();
                    return;
                }

                $total = $highestRow - 1;
                $processed = 0;

                for ($row = 2; $row <= $highestRow; $row++) {
                    $employeeNo = $sheet->getCell('A' . $row)->getValue();
                    $name = $sheet->getCell('B' . $row)->getValue();
                    
                    if (!$name) continue;

                    $employee = new Employee();
                    $employee->setName((string)$name);
                    $employee->setEmployeeNo((string)$employeeNo);
                    $employee->setEnglishName((string)$sheet->getCell('C' . $row)->getValue());
                    $employee->setEmail((string)$sheet->getCell('D' . $row)->getValue());
                    $employee->setMobile((string)$sheet->getCell('E' . $row)->getValue());
                    
                    $gender = $sheet->getCell('F' . $row)->getValue();
                    if ($gender == '男') $employee->setGender('male');
                    elseif ($gender == '女') $employee->setGender('female');
                    
                    $em->persist($employee);
                    $processed++;
                    
                    if ($processed % 10 === 0) {
                        $em->flush();
                        $em->clear();
                        
                        $progress = floor(($processed / $total) * 100);
                        echo "data: " . json_encode(['progress' => $progress, 'processed' => $processed, 'total' => $total]) . "\n\n";
                        if (ob_get_level() > 0) ob_flush(); flush();
                    }
                }
                
                $em->flush();
                
                echo "data: " . json_encode(['progress' => 100, 'processed' => $processed, 'total' => $total, 'complete' => true]) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                
                @unlink($filePath);
                
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/employee/list', name: 'employee_list')]
    public function list(Request $request, EntityManagerInterface $em): Response
    {
        // 1. 获取组织架构树
        $deptRepo = $em->getRepository(Department::class);
        $tree = $deptRepo->childrenHierarchy(null, false, [
            'decorate' => true,
            'rootOpen' => static function (array $tree): ?string {
                if ([] !== $tree && 0 == $tree[0]['lvl']) {
                    return '<ol class="ol-left-tree">';
                }

                if ($tree[0]['type'] === 'department') {
                    return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
                }

                return '<span class="tree-indent"></span><ol class="sub-tree-content">';
            },
            'rootClose' => static function (array $child): ?string {
                return '</ol>';
            },
            'childOpen' => '<li>',
            'childClose' => '</li>',
            'nodeDecorator' => static function (array $node) {
                if ($node['type'] === 'corperations') {
                    return '
                    <div class="item-content scroll-item">
                        <div class="arrow-icon">
                            <i class="fa-solid fa-caret-down"></i>
                        </div>
                        <div class="org-icon">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="org-name">
                            <div class="org-text-content">' .
                            $node['name']
                            . '</div>
                        </div>
                    </div>
                    ';
                }

                if ($node['type'] === 'company') {
                    $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

                    return '
                    <div class="item-content scroll-item">
                        <div class="arrow-icon">' . $arrayIcon . '</div>
                        <div class="org-icon">
                            <i class="fa-solid fa-building-user"></i>
                        </div>
                        <div class="org-name">
                            <div class="org-text-content company" type="company" id="' . $node['id'] . '">' .
                            $node['name']
                            . '</div>
                        </div>
                    </div>
                    ';
                }

                if ($node['type'] === 'department') {
                    $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

                    return '
                    <div class="item-content scroll-item">
                        <div class="arrow-icon">' . $arrayIcon . '</div>
                        <div class="org-icon">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div class="org-name">
                            <div class="org-text-content department" type="department" id="' . $node['id'] . '">' .
                            $node['name']
                            . '</div>
                        </div>
                    </div>
                    ';
                }
            }
        ]);

        // 2. 分页获取员工列表
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $offset = ($page - 1) * $limit;

        // 排序处理
        $sort = $request->query->get('sort');
        $order = $request->query->get('order');

        // 获取用户自定义列和排序设置
        $user = $this->getUser();
        $prefRepo = $em->getRepository(UserPreference::class);
        $userPref = null;
        if ($user) {
            $userId = method_exists($user, 'getId') ? (string)$user->getId() : $user->getUserIdentifier();
            $userPref = $prefRepo->findOneBy(['userId' => $userId, 'prefKey' => 'employee_list_columns']);
        }

        $allColumns = [
            'name' => ['label' => 'employee.field.name', 'sortable' => true],
            'employeeNo' => ['label' => 'employee.field.employee_no', 'sortable' => true],
            'department' => ['label' => 'employee.field.department', 'sortable' => true],
            'position' => ['label' => 'employee.field.position', 'sortable' => true],
            'employmentStatus' => ['label' => 'employee.field.employment_status', 'sortable' => true],
            'workStatus' => ['label' => 'employee.field.work_status', 'sortable' => true],
            'hireDate' => ['label' => 'employee.field.entry_date', 'sortable' => true],
            'email' => ['label' => 'employee.field.email', 'sortable' => true],
            'mobile' => ['label' => 'employee.field.mobile', 'sortable' => true],
            'gender' => ['label' => 'employee.field.gender', 'sortable' => true],
            'birthDate' => ['label' => 'employee.field.birth_date', 'sortable' => true],
            'idCard' => ['label' => 'employee.field.id_card', 'sortable' => true],
            'englishName' => ['label' => 'employee.field.english_name', 'sortable' => true],
        ];

        $columns = [];
        if ($userPref) {
            $prefVal = $userPref->getPrefValue();
            if (is_string($prefVal)) {
                $prefVal = json_decode($prefVal, true);
            }
            
            if (is_array($prefVal) && isset($prefVal['columns'])) {
                // Merge stored columns with definition to ensure they are valid and have labels
                foreach ($prefVal['columns'] as $key => $config) {
                    // Compatibility for old 'status' key
                    if ($key === 'status') {
                        $key = 'employmentStatus';
                    }
                    
                    if (isset($allColumns[$key])) {
                        $columns[$key] = $allColumns[$key];
                    }
                }
            }
        }
        
        if (empty($columns)) {
            // Default columns
            $defaultKeys = ['name', 'employeeNo', 'department', 'position', 'employmentStatus', 'hireDate'];
            foreach ($defaultKeys as $key) {
                if (isset($allColumns[$key])) {
                    $columns[$key] = $allColumns[$key];
                }
            }
        }

        // 如果 URL 参数中没有指定排序，尝试使用用户偏好，否则使用默认值
        if (!$sort) {
            if ($userPref) {
                $prefVal = $userPref->getPrefValue();
                if (is_string($prefVal)) {
                    $prefVal = json_decode($prefVal, true);
                }
                if (is_array($prefVal) && isset($prefVal['sort'])) {
                    $sort = $prefVal['sort'];
                    // Compatibility for old 'status' key in sort
                    if ($sort === 'status') {
                        $sort = 'employmentStatus';
                    }
                    $order = $prefVal['order'] ?? 'desc';
                }
            }
            
            if (!$sort) {
                $sort = 'hireDate';
                $order = 'desc';
            }
        }
        
        // 验证排序字段是否在允许的字段中
        $allowedSortFields = array_keys($allColumns);
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'hireDate';
        }
        
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $empRepo = $em->getRepository(Employee::class);
        $qb = $empRepo->createQueryBuilder('e')
            ->leftJoin('e.department', 'd')
            ->leftJoin('e.position', 'p')
            ->where('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('isSystem', false);

        // 应用排序
        if ($sort === 'department') {
            $qb->orderBy('d.name', $order);
        } elseif ($sort === 'position') {
            $qb->orderBy('p.name', $order);
        } else {
            $qb->orderBy('e.' . $sort, $order);
        }

        // 搜索功能 (可选，支持按姓名或工号搜索)
        $search = $request->query->get('q');
        if ($search) {
            $qb->andWhere('e.name LIKE :search OR e.employeeNo LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // 在职状态筛选
        $employmentStatus = $request->query->get('employment_status', 'active'); // 默认为 active
        if ($employmentStatus && $employmentStatus !== 'all') {
            $qb->andWhere('e.employmentStatus = :employmentStatus')
               ->setParameter('employmentStatus', $employmentStatus);
        }

        // 树状结构筛选
        $departmentId = $request->query->get('department_id');
        $companyId = $request->query->get('company_id');
        $includeSub = $request->query->getBoolean('include_sub', true); // 默认为 true

        if ($departmentId) {
            if ($includeSub) {
                $dept = $deptRepo->find($departmentId);
                if ($dept) {
                    $qb->andWhere('d.lft >= :lft')
                       ->andWhere('d.rgt <= :rgt')
                       ->andWhere('d.root = :root')
                       ->setParameter('lft', $dept->getLft())
                       ->setParameter('rgt', $dept->getRgt())
                       ->setParameter('root', $dept->getRoot());
                } else {
                    // Fallback if department not found (shouldn't happen usually)
                    $qb->andWhere('e.department = :departmentId')
                       ->setParameter('departmentId', $departmentId);
                }
            } else {
                $qb->andWhere('e.department = :departmentId')
                   ->setParameter('departmentId', $departmentId);
            }
        } elseif ($companyId) {
            // $companyId 可能是 Department 表中 company 类型的节点 ID
            // 先尝试从 Department 表中查找
            $companyDept = $deptRepo->find($companyId);
            
            if ($companyDept && $companyDept->getCompany()) {
                // 如果找到了 Department 且它关联了 Company 实体，则使用 Company 实体的 ID
                $qb->andWhere('e.company = :companyId')
                   ->setParameter('companyId', $companyDept->getCompany()->getId());
            } else {
                // 否则，假设它就是 Company ID（回退策略）
                $qb->andWhere('e.company = :companyId')
                   ->setParameter('companyId', $companyId);
            }
        }

        // 决定是否显示统计数据：只有在首页（无搜索、无筛选、第一页）时显示
        $showStats = !($search || $departmentId || $companyId || $page > 1);

        // 计算总数
        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy');
        $countQb->select('count(e.id)');
        $totalItems = $countQb->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalItems / $limit);

        // 获取当前页数据
        $employees = $qb->select('e', 'd', 'p') // 预加载部门和岗位
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // 3. 统计数据 (从数据库获取真实数据)
        $activeCountQb = $empRepo->createQueryBuilder('e')
            ->select('count(e.id)')
            ->where('e.employmentStatus = :status')
            ->andWhere('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('isSystem', false);
        $activeCount = $activeCountQb->getQuery()->getSingleScalarResult();
        
        $deptCount = $deptRepo->count(['type' => 'department']);

        // 简单的统计数据结构
        $stats = [
            'total_employee' => ['value' => $totalItems, 'change' => '+0', 'trend' => 'flat'],
            'active_employee' => ['value' => $activeCount, 'change' => '+0', 'trend' => 'flat'],
            'total_department' => ['value' => $deptCount, 'change' => '+0', 'trend' => 'flat'],
            // 平均工龄暂时使用静态数据或后续实现
            'average_tenure' => ['value' => '3.2', 'unit' => 'years', 'change' => '+0%', 'trend' => 'up'],
        ];

        return $this->render('employee/list.html.twig', [
            'tree' => $tree,
            'entities' => $employees,
            'stats' => $stats,
            'show_stats' => $showStats,
            'columns' => $columns,
            'allColumns' => $allColumns,
            'currentSort' => $sort,
            'currentOrder' => $order,
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

    #[Route('/employee/{id}', name: 'employee_show', requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function show(string $id, EntityManagerInterface $em, Request $request): Response
    {
        $employee = $em->getRepository(Employee::class)->find($id);

        if (!$employee) {
            throw $this->createNotFoundException('Employee not found');
        }

        // Map Entity to View Data (adapting to the template's expectation)
        $viewData = (object)[
            // Basic Info
            'id' => $employee->getId(),
            'name' => $employee->getName(),
            'englishName' => $employee->getEnglishName(),
            'avatar' => $employee->getAvatar(),
            'position' => $employee->getPosition() ? $employee->getPosition()->getName() : '',
            'employeeNo' => $employee->getEmployeeNo(),
            'employmentStatus' => $employee->getEmploymentStatus(), // active, inactive, etc.
            'workStatus' => $employee->getWorkStatus(),
            'hireDate' => $employee->getHireDate() ? $employee->getHireDate()->format('Y-m-d') : '-',
            'email' => $employee->getEmail(),
            'mobile' => $employee->getMobile(),
            
            // Org Info
            'department' => $employee->getDepartment() ? $employee->getDepartment()->getName() : '-',
            'company' => $employee->getCompany() ? $employee->getCompany()->getName() : '-',
            'manager' => (object)[
                'id' => $employee->getManager() ? $employee->getManager()->getId() : '', 
                'name' => $employee->getManager() ? $employee->getManager()->getName() : 'N/A', 
                'avatar' => $employee->getManager() ? $employee->getManager()->getAvatar() : null
            ],
            'subordinates' => [], // Will populate below
            
            // Personal Info
            'gender' => $employee->getGender(),
            'birthDate' => $employee->getBirthDate() ? $employee->getBirthDate()->format('Y-m-d') : '-',
            'idCard' => $employee->getIdCard(),
            'address' => $employee->getAddress(),
            'emergencyContact' => $employee->getEmergencyContact(),
            'emergencyPhone' => $employee->getEmergencyPhone(),
            
            // Education Info
            'education' => $employee->getEducation(),
            'school' => $employee->getSchool(),
            'major' => $employee->getMajor(),
            'graduationDate' => $employee->getGraduationDate() ? $employee->getGraduationDate()->format('Y-m-d') : '-',
            
            // Account Info
            'username' => $employee->getUsername(),
            'lastLoginAt' => $employee->getLastLoginAt() ? $employee->getLastLoginAt()->format('Y-m-d H:i:s') : '-',
            'roles' => $employee->getRoles(),
            'isActive' => $employee->getIsActive(),
            
            // Stats (Mock for Dashboard - as per requirement to use test page implementation)
            'payout' => [
                'total' => 17877,
                'base' => 15000,
                'overtime' => 2877
            ],
            'timeWorked' => '47h 21m',
            'reimbursement' => 235.00,
            
            // Tasks (Mock)
            'activeTasks' => [
                [
                    'title' => 'Develop marketing strategy',
                    'deadline' => 'Feb 10, 2024 at 6:00 pm',
                    'status' => 'In Progress',
                    'color' => '#f97316'
                ],
                [
                    'title' => 'Re-branding oaxel (Logo, Website and Colors)',
                    'deadline' => 'Feb 10, 2024 at 6:00 pm',
                    'status' => 'Pending',
                    'color' => '#22c55e'
                ]
            ],
            
            // Activity Log (Mock)
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
                    'time' => '23:59',
                    'action' => 'Clock-out',
                    'status' => 'GOOD',
                    'statusColor' => '#8b5cf6'
                ]
            ],
            
            // Documents (Mock)
            'documents' => [
                ['name' => 'NDA - ' . $employee->getName() . ' 2025', 'size' => '12mb', 'date' => '12 April', 'type' => 'pdf'],
                ['name' => 'Labor Contract', 'size' => '2mb', 'date' => '12 Jan', 'type' => 'pdf']
            ]
        ];

        // Populate Subordinates
        foreach ($employee->getSubordinates() as $sub) {
            $viewData->subordinates[] = (object)[
                'id' => $sub->getId(),
                'name' => $sub->getName(),
                'avatar' => 'https://i.pravatar.cc/300?u=' . $sub->getId()
            ];
        }

        // Check for Passkey
        // WebauthnCredential stores userHandle as base64url encoded string of the UUID
        $userHandle = rtrim(strtr(base64_encode($employee->getId()), '+/', '-_'), '=');
        $credentialRepo = $em->getRepository(WebauthnCredential::class);
        $credentials = $credentialRepo->findBy(['userHandle' => $userHandle]);
        $viewData->hasPasskey = count($credentials) > 0;
        $viewData->passkeys = [];
        foreach ($credentials as $cred) {
            $viewData->passkeys[] = (object)[
                'id' => $cred->getId(),
                'deviceName' => $cred->getDeviceName() ?? 'Unknown Device',
                'createdAt' => $cred->getCreatedAt(),
                'lastUsedAt' => $cred->getLastUsedAt(),
                'aaguid' => $cred->getAaguid() ? $cred->getAaguid()->toRfc4122() : '00000000-0000-0000-0000-000000000000',
            ];
        }

        return $this->render('employee/show.html.twig', [
            'employee' => $viewData
        ]);
    }

    #[Route('/employee/{id}/clear-passkey', name: 'employee_clear_passkey', methods: ['POST'])]
    public function clearPasskey(string $id, EntityManagerInterface $em): Response
    {
        $employee = $em->getRepository(Employee::class)->find($id);

        if (!$employee) {
            return $this->json(['status' => 'error', 'message' => 'Employee not found'], 404);
        }

        // Only allow admins or the user themselves to clear passkeys
        if (!$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $employee) {
            return $this->json(['status' => 'error', 'message' => 'Access Denied.'], 403);
        }

        // WebauthnCredential stores userHandle as base64url encoded string of the UUID
        $userHandle = rtrim(strtr(base64_encode($employee->getId()), '+/', '-_'), '=');

        $credentialRepo = $em->getRepository(WebauthnCredential::class);
        $credentials = $credentialRepo->findBy(['userHandle' => $userHandle]);

        foreach ($credentials as $credential) {
            $em->remove($credential);
        }
        
        $em->flush();

        return $this->json(['status' => 'success', 'message' => 'Passkeys cleared successfully']);
    }
}


