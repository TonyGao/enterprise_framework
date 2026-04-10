<?php

namespace App\Controller;

use App\Entity\Organization\Department;
use App\Entity\Organization\Employee;
use App\Entity\Organization\Position;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\Security\PasswordPolicyRepository;

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
    public function importTemplate(EntityManagerInterface $em): Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = ['公司', '部门', '职位', '工号', '姓名', '用户名', '英文名', '直接上级', '性别', '出生日期', '身份证号', '邮箱', '手机号', '联系地址', '入职日期', '在职状态', '工作状态', '学历', '毕业院校', '专业', '毕业时间', '联系人姓名', '联系电话'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }

        // Enable autofilter
        $lastColumn = chr(ord('A') + count($headers) - 1);
        $sheet->setAutoFilter('A1:' . $lastColumn . '1');

        // Freeze panes: first row and columns A-F (up to and including 姓名)
        $sheet->freezePane('G2');

        // Set column widths
        $columnWidths = [
            'A' => 14,  // 公司
            'B' => 14,  // 部门
            'C' => 12,  // 职位
            'D' => 10,  // 工号
            'E' => 10,  // 姓名
            'F' => 14,  // 用户名
            'G' => 14,  // 英文名
            'H' => 10,  // 直接上级
            'I' => 6,   // 性别
            'J' => 12,  // 出生日期
            'K' => 20,  // 身份证号
            'L' => 22,  // 邮箱
            'M' => 14,  // 手机号
            'N' => 26,  // 联系地址
            'O' => 12,  // 入职日期
            'P' => 10,  // 在职状态
            'Q' => 10,  // 工作状态
            'R' => 8,   // 学历
            'S' => 16,  // 毕业院校
            'T' => 14,  // 专业
            'U' => 12,  // 毕业时间
            'V' => 12,  // 联系人姓名
            'W' => 14,  // 联系电话
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

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

        // Dynamically fetch sample data from database
        $department = $em->getRepository(Department::class)->findOneBy([]);
        $position = $em->getRepository(Position::class)->findOneBy([]);
        // Find a subsidiary company (not the root group)
        $company = $em->getRepository(\App\Entity\Organization\Company::class)->createQueryBuilder('c')
            ->where('c.name != :groupName')
            ->setParameter('groupName', '华夏集团')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $manager = $em->getRepository(Employee::class)->findOneBy(['isSystem' => false]);

        $sampleData = [];
        if ($department || $position) {
            $sampleData[] = [
                $company?->getName() ?? '华夏集团',
                $department?->getName() ?? '',
                $position?->getName() ?? '',
                'EMP001',
                '张三',
                'zhangsan',
                'Zhang San',
                $manager?->getName() ?? '',
                '男',
                '1990-01-01',
                '110101199001010000',
                'zhangsan@company.com',
                '13800138000',
                '北京市朝阳区某某路1号',
                date('Y-m-d'),
                '在职',
                '工作',
                '本科',
                '清华大学',
                '计算机科学与技术',
                '2015-06-30',
                '张三父亲',
                '13800138001'
            ];
            $sampleData[] = [
                $company?->getName() ?? '华夏集团',
                $department?->getName() ?? '',
                $position?->getName() ?? '',
                'EMP002',
                '李四',
                'lisi',
                'Li Si',
                $manager?->getName() ?? '',
                '女',
                '1992-01-01',
                '110101199201010000',
                'lisi@company.com',
                '13800138001',
                '上海市浦东新区某某大道2号',
                date('Y-m-d'),
                '在职',
                '休假',
                '硕士',
                '北京大学',
                '工商管理',
                '2018-06-30',
                '李四母亲',
                '13800138002'
            ];
        } else {
            $sampleData[] = [
                '华夏集团', '技术部', '后端工程师', 'EMP001', '张三', 'zhangsan', 'Zhang San', '', '男', '1990-01-01', '110101199001010000', 'zhangsan@company.com', '13800138000', '北京市朝阳区某某路1号', date('Y-m-d'), '在职', '工作', '本科', '清华大学', '计算机科学与技术', '2015-06-30', '张三父亲', '13800138001'
            ];
            $sampleData[] = [
                '华夏集团', '市场部', '产品经理', 'EMP002', '李四', 'lisi', 'Li Si', '', '女', '1992-01-01', '110101199201010000', 'lisi@company.com', '13800138001', '上海市浦东新区某某大道2号', date('Y-m-d'), '在职', '休假', '硕士', '北京大学', '工商管理', '2018-06-30', '李四母亲', '13800138002'
            ];
        }
        $row = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Add data validation dropdowns for fixed-option fields
        $dropdownData = [
            'I' => ['男', '女'],  // 性别
            'P' => ['在职', '离职', '试用期'],  // 在职状态
            'Q' => ['工作', '休假', '出差', '外出', '会议中'],  // 工作状态
            'R' => ['初中', '高中', '中专', '大专', '本科', '硕士', '博士', 'MBA', 'EMBA'],  // 学历
        ];

        // Get all companies for company dropdown
        $companies = $em->getRepository(\App\Entity\Organization\Company::class)->findAll();
        $companyNames = array_column($companies, 'name');
        $dropdownData['A'] = $companyNames;

        // Apply dropdown validations for rows 2-1000
        foreach ($dropdownData as $col => $options) {
            $range = $col . '2:' . $col . '1000';
            $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"' . implode(',', $options) . '"');
            $validation->setSqref($range);
            $sheet->setDataValidation($range, $validation);
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
    public function importProcess(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, PasswordPolicyRepository $policyRepo, LoggerInterface $logger): Response
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

        $policy = $policyRepo->findOneBy([]);
        $defaultPassword = $policy?->getDefaultPassword() ?? 'Welcome@2024';
        $forceResetPassword = $policy?->isForceResetPasswordOnFirstLogin() ?? true;

        $deptRepo = $em->getRepository(Department::class);
        $positionRepo = $em->getRepository(Position::class);

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filePath, $em, $passwordHasher, $defaultPassword, $forceResetPassword, $deptRepo, $positionRepo, $logger) {
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

                // Build column index map from header row
                $headerMap = [];
                $highestColumn = $sheet->getHighestColumn();
                $maxColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                for ($col = 1; $col <= $maxColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $headerValue = $sheet->getCell($colLetter . '1')->getValue();
                    if ($headerValue) {
                        $headerMap[trim($headerValue)] = $col;
                    }
                }

                // Validate required headers
                $requiredHeaders = ['工号', '姓名', '邮箱'];
                $missingHeaders = [];
                foreach ($requiredHeaders as $required) {
                    if (!isset($headerMap[$required])) {
                        $missingHeaders[] = $required;
                    }
                }
                if (!empty($missingHeaders)) {
                    echo "data: " . json_encode(['error' => '缺少必需列: ' . implode(', ', $missingHeaders)]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush(); flush();
                    return;
                }

                $total = $highestRow - 1;
                $processed = 0;
                $importErrors = [];

                for ($row = 2; $row <= $highestRow; $row++) {
                    $getCellValue = function(string $header) use ($sheet, $row, $headerMap): ?string {
                        if (!isset($headerMap[$header])) {
                            return null;
                        }
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap[$header]);
                        $value = $sheet->getCell($colLetter . $row)->getValue();
                        return $value !== null && $value !== '' ? (string)$value : null;
                    };

                    $employeeNo = $getCellValue('工号');
                    $name = $getCellValue('姓名');
                    
                    if (!$name) continue;
                    
                    if ($employeeNo) {
                        $existingEmp = $em->getRepository(Employee::class)->findOneBy(['employeeNo' => $employeeNo]);
                        if ($existingEmp) {
                            $importErrors[] = "第{$row}行: 工号 '{$employeeNo}' 已存在，跳过";
                            continue;
                        }
                    }

                    $employee = new Employee();
                    $employee->setName($name);
                    $employee->setEmployeeNo($employeeNo ?: 'EMP_' . uniqid());
                    
                    $email = $getCellValue('邮箱');
                    $importedUsername = $getCellValue('用户名');
                    if ($importedUsername) {
                        $username = $importedUsername . '_' . $row;
                    } elseif ($email) {
                        $username = strstr($email, '@', true) ?: strtolower(preg_replace('/\s+/', '', $name));
                        $username = $username . '_' . $row;
                    } else {
                        $username = strtolower(preg_replace('/\s+/', '', $name));
                        $username = $username . '_' . $row;
                    }
                    $employee->setUsername($username);
                    
                    $hashedPassword = $passwordHasher->hashPassword($employee, $defaultPassword);
                    $employee->setPassword($hashedPassword);
                    $employee->setForcePasswordReset($forceResetPassword);
                    
                    $employee->setEnglishName($getCellValue('英文名'));
                    $employee->setEmail($email);
                    $employee->setMobile($getCellValue('手机号'));
                    
                    $gender = $getCellValue('性别');
                    if ($gender == '男') $employee->setGender('male');
                    elseif ($gender == '女') $employee->setGender('female');
                    
                    $deptName = $getCellValue('部门');
                    if ($deptName) {
                        $department = $deptRepo->findOneBy(['name' => $deptName]);
                        if ($department) {
                            $employee->setDepartment($department);
                        }
                    }
                    
                    $positionName = $getCellValue('职位');
                    if ($positionName) {
                        $position = $positionRepo->findOneBy(['name' => $positionName]);
                        if ($position) {
                            $employee->setPosition($position);
                        }
                    }
                    
                    $employmentStatus = $getCellValue('在职状态');
                    if ($employmentStatus) {
                        $statusMap = ['在职' => 'active', '离职' => 'inactive', '试用期' => 'probation'];
                        $employee->setEmploymentStatus($statusMap[$employmentStatus] ?? 'active');
                    }
                    
                    $workStatus = $getCellValue('工作状态');
                    if ($workStatus) {
                        $workStatusMap = ['工作' => 'working', '休假' => 'vacation', '出差' => 'business_trip', '外出' => 'out_of_office', '会议中' => 'in_meeting'];
                        $employee->setWorkStatus($workStatusMap[$workStatus] ?? 'working');
                    }
                    
                    $hireDate = $getCellValue('入职日期');
                    if ($hireDate) {
                        try {
                            $employee->setHireDate(new \DateTime($hireDate));
                        } catch (\Exception $e) {
                            $importErrors[] = "第{$row}行: 入职日期格式错误 '{$hireDate}'";
                        }
                    }
                    
                    $birthDate = $getCellValue('出生日期');
                    if ($birthDate) {
                        try {
                            $employee->setBirthDate(new \DateTime($birthDate));
                        } catch (\Exception $e) {
                            $importErrors[] = "第{$row}行: 出生日期格式错误 '{$birthDate}'";
                        }
                    }
                    
                    $idCard = $getCellValue('身份证号');
                    if ($idCard) {
                        $employee->setIdCard($idCard);
                    }
                    
                    $companyName = $getCellValue('公司');
                    if ($companyName) {
                        $companyEntity = $em->getRepository(\App\Entity\Organization\Company::class)->findOneBy(['name' => $companyName]);
                        if ($companyEntity) {
                            $employee->setCompany($companyEntity);
                        }
                    }
                    
                    $managerName = $getCellValue('直接上级');
                    if ($managerName) {
                        $manager = $em->getRepository(Employee::class)->findOneBy(['name' => $managerName]);
                        if ($manager) {
                            $employee->setManager($manager);
                        }
                    }
                    
                    $education = $getCellValue('学历');
                    if ($education) {
                        $eduMap = ['初中' => 'junior_high', '高中' => 'senior_high', '中专' => 'secondary', '大专' => 'associate', '本科' => 'bachelor', '硕士' => 'master', '博士' => 'phd', 'MBA' => 'mba', 'EMBA' => 'emba'];
                        $employee->setEducation($eduMap[$education] ?? $education);
                    }
                    
                    $address = $getCellValue('联系地址');
                    if ($address) {
                        $employee->setAddress($address);
                    }
                    
                    $school = $getCellValue('毕业院校');
                    if ($school) {
                        $employee->setSchool($school);
                    }
                    
                    $major = $getCellValue('专业');
                    if ($major) {
                        $employee->setMajor($major);
                    }
                    
                    $graduationDate = $getCellValue('毕业时间');
                    if ($graduationDate) {
                        try {
                            $employee->setGraduationDate(new \DateTime($graduationDate));
                        } catch (\Exception $e) {
                            $importErrors[] = "第{$row}行: 毕业时间格式错误 '{$graduationDate}'";
                        }
                    }
                    
                    $emergencyContact = $getCellValue('联系人姓名');
                    if ($emergencyContact) {
                        $employee->setEmergencyContact($emergencyContact);
                    }
                    
                    $emergencyPhone = $getCellValue('联系电话');
                    if ($emergencyPhone) {
                        $employee->setEmergencyPhone($emergencyPhone);
                    }
                    
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
                
                $result = ['progress' => 100, 'processed' => $processed, 'total' => $total, 'complete' => true];
                if (!empty($importErrors)) {
                    $result['warnings'] = $importErrors;
                }
                echo "data: " . json_encode($result) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                
                @unlink($filePath);
                
            } catch (\Exception $e) {
                $errorId = sprintf('import_%s_%s', date('Ymd_His'), substr(md5(uniqid()), 0, 6));
                $logger->error('Import failed', [
                    'error_id' => $errorId,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                echo "data: " . json_encode([
                    'error' => '导入过程中发生错误，请联系管理员并提供错误ID: ' . $errorId
                ]) . "\n\n";
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
    public function list(Request $request, EntityManagerInterface $em, \Symfony\Contracts\Cache\CacheInterface $cache): Response
    {
        // 1. 获取组织架构树 (带缓存)
        $tree = $cache->get('employee_list_tree', function () use ($em) {
            $deptRepo = $em->getRepository(Department::class);
            return $deptRepo->childrenHierarchy(null, false, [
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
                        <div class="item-content scroll-item" data-type="corperations" data-id="' . ($node['id'] ?? '') . '">
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
                        <div class="item-content scroll-item" data-type="company" data-id="' . ($node['id'] ?? '') . '">
                            <div class="arrow-icon">' . $arrayIcon . '</div>
                            <div class="org-icon">
                                <i class="fa-solid fa-building-ngo"></i>
                            </div>
                            <div class="org-name">
                                <div class="org-text-content">' .
                                $node['name']
                                . '</div>
                            </div>
                        </div>
                        ';
                    }

                    if ($node['type'] === 'department') {
                        $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

                        return '
                        <div class="item-content scroll-item" data-id="' . $node['id'] . '" data-type="department">
                            <div class="arrow-icon">' . $arrayIcon . '</div>
                            <div class="org-icon">
                                <i class="fa-solid fa-sitemap"></i>
                            </div>
                            <div class="org-name">
                                <div class="org-text-content">' .
                                $node['name']
                                . '</div>
                            </div>
                        </div>
                        ';
                    }

                    return '<div class="item-content scroll-item">' . $node['name'] . '</div>';
                },
            ]);
        });

        $deptRepo = $em->getRepository(Department::class);
        $positionRepo = $em->getRepository(Position::class);

        // 获取所有部门和岗位用于筛选下拉
        $departments = $deptRepo->createQueryBuilder('d')
            ->where('d.type = :type')
            ->setParameter('type', 'department')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
        
        $positions = $positionRepo->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

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
            'username' => ['label' => 'employee.field.username', 'sortable' => true],
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

        // 高级筛选字段
        if ($name = $request->query->get('name')) {
            $qb->andWhere('e.name LIKE :name')->setParameter('name', '%' . $name . '%');
        }
        if ($employeeNo = $request->query->get('employeeNo')) {
            $qb->andWhere('e.employeeNo LIKE :employeeNo')->setParameter('employeeNo', '%' . $employeeNo . '%');
        }
        if ($username = $request->query->get('username')) {
            $qb->andWhere('e.username LIKE :username')->setParameter('username', '%' . $username . '%');
        }
        if ($email = $request->query->get('email')) {
            $qb->andWhere('e.email LIKE :email')->setParameter('email', '%' . $email . '%');
        }
        if ($mobile = $request->query->get('mobile')) {
            $qb->andWhere('e.mobile LIKE :mobile')->setParameter('mobile', '%' . $mobile . '%');
        }
        if ($englishName = $request->query->get('englishName')) {
            $qb->andWhere('e.englishName LIKE :englishName')->setParameter('englishName', '%' . $englishName . '%');
        }
        if ($idCard = $request->query->get('idCard')) {
            $qb->andWhere('e.idCard LIKE :idCard')->setParameter('idCard', '%' . $idCard . '%');
        }
        if ($gender = $request->query->get('gender')) {
            $qb->andWhere('e.gender = :gender')->setParameter('gender', $gender);
        }
        if ($education = $request->query->get('education')) {
            $qb->andWhere('e.education = :education')->setParameter('education', $education);
        }
        if ($workStatus = $request->query->get('workStatus')) {
            $qb->andWhere('e.workStatus = :workStatus')->setParameter('workStatus', $workStatus);
        }

        // 筛选面板：部门和岗位筛选
        if ($filterDeptId = $request->query->get('filter_department_id')) {
            $qb->andWhere('e.department = :filterDeptId')->setParameter('filterDeptId', $filterDeptId);
        }
        if ($filterPosId = $request->query->get('filter_position_id')) {
            $qb->andWhere('e.position = :filterPosId')->setParameter('filterPosId', $filterPosId);
        }

        // 树状结构筛选
        $departmentId = $request->query->get('department_id');
        $companyId = $request->query->get('company_id');
        $includeSub = $request->query->getBoolean('include_sub', true); // 默认为 true
        $departmentPath = null;

        if ($departmentId) {
            $dept = $deptRepo->find($departmentId);
            if ($dept) {
                // Build department path from ancestors
                $pathParts = [];
                $current = $dept;
                while ($current) {
                    array_unshift($pathParts, $current->getName());
                    $current = $current->getParent();
                }
                $departmentPath = implode(' - ', $pathParts);
                
                if ($includeSub) {
                    $qb->andWhere('d.lft >= :lft')
                       ->andWhere('d.rgt <= :rgt')
                       ->andWhere('d.root = :root')
                       ->setParameter('lft', $dept->getLft())
                       ->setParameter('rgt', $dept->getRgt())
                       ->setParameter('root', $dept->getRoot());
                } else {
                    $qb->andWhere('e.department = :departmentId')
                       ->setParameter('departmentId', $departmentId);
                }
            } else {
                // Fallback if department not found (shouldn't happen usually)
                $qb->andWhere('e.department = :departmentId')
                   ->setParameter('departmentId', $departmentId);
            }
        } elseif ($companyId) {
            // $companyId 可能是 Department 表中 company 类型的节点 ID
            // 先尝试从 Department 表中查找
            $companyDept = $deptRepo->find($companyId);
            
            if ($companyDept) {
                $departmentPath = $companyDept->getName();
            }
            
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
        $showStats = !($search || $departmentId || $companyId);

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

        // Get saved column widths
        $columnWidths = [];
        $widthPref = $prefRepo->findOneBy(['userId' => $userId, 'prefKey' => 'employee_list_column_widths']);
        if ($widthPref) {
            $widthVal = $widthPref->getPrefValue();
            if (is_string($widthVal)) {
                $widthVal = json_decode($widthVal, true);
            }
            if (is_array($widthVal)) {
                $columnWidths = $widthVal;
            }
        }

        // Calculate total table width from column widths
        $defaultWidths = [
            'name' => 140, 'employeeNo' => 100, 'department' => 150,
            'position' => 120, 'employmentStatus' => 100, 'workStatus' => 100,
            'hireDate' => 110, 'email' => 200, 'mobile' => 130,
            'gender' => 60, 'birthDate' => 110, 'idCard' => 180, 'englishName' => 120
        ];
        $tableWidth = 40; // checkbox column
        foreach ($columns as $key => $config) {
            if (isset($columnWidths[$key])) {
                $w = str_replace('px', '', $columnWidths[$key]);
                $tableWidth += (int)$w;
            } else {
                $tableWidth += $defaultWidths[$key] ?? 120;
            }
        }
        $tableWidth += 60; // actions column

        // If no user widths saved, use auto layout
        $hasUserWidths = !empty($columnWidths);

// Get stats collapsed from URL parameter (passed from sessionStorage)
        $statsCollapsed = $request->query->get('stats_collapsed') === 'true';

        return $this->render('employee/list.html.twig', [
            'tree' => $tree,
            'entities' => $employees,
            'stats' => $stats,
            'show_stats' => $showStats,
            'statsCollapsed' => $statsCollapsed,
            'columns' => $columns,
            'allColumns' => $allColumns,
            'columnWidths' => $columnWidths,
            'tableWidth' => $hasUserWidths ? ($tableWidth . 'px') : 'auto',
            'hasUserWidths' => $hasUserWidths,
            'currentSort' => $sort,
            'currentOrder' => $order,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'limit' => $limit,
                'start' => $offset + 1,
                'end' => min($offset + $limit, $totalItems)
            ],
            'filterDepartments' => $departments,
            'filterPositions' => $positions,
            'departmentPath' => $departmentPath
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


