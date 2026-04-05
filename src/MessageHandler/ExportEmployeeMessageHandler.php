<?php

namespace App\MessageHandler;

use App\Message\ExportEmployeeMessage;
use App\Entity\Organization\Employee;
use App\Entity\Organization\Department;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMessageHandler]
final class ExportEmployeeMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private HubInterface $hub,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    public function __invoke(ExportEmployeeMessage $message): void
    {
        $userId = $message->getUserId();
        $filters = $message->getFilters();

        $qb = $this->em->getRepository(Employee::class)->createQueryBuilder('e')
                 ->where('e.isSystem = :isSystem OR e.isSystem IS NULL')
                 ->setParameter('isSystem', false);

        // Apply filters
        if (!empty($filters['search'])) {
            $qb->andWhere('e.name LIKE :search OR e.employeeNo LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['employmentStatus']) && $filters['employmentStatus'] !== 'all') {
            $qb->andWhere('e.employmentStatus = :employmentStatus')
               ->setParameter('employmentStatus', $filters['employmentStatus']);
        }

        if (!empty($filters['departmentId'])) {
            if (!empty($filters['includeSub'])) {
                $dept = $this->em->getRepository(Department::class)->find($filters['departmentId']);
                if ($dept) {
                    $qb->join('e.department', 'd')
                       ->andWhere('d.lft >= :lft')
                       ->andWhere('d.rgt <= :rgt')
                       ->andWhere('d.root = :root')
                       ->setParameter('lft', $dept->getLft())
                       ->setParameter('rgt', $dept->getRgt())
                       ->setParameter('root', $dept->getRoot());
                }
            } else {
                $qb->andWhere('e.department = :deptId')
                   ->setParameter('deptId', $filters['departmentId']);
            }
        } elseif (!empty($filters['companyId'])) {
            $companyDept = $this->em->getRepository(Department::class)->find($filters['companyId']);
            if ($companyDept && $companyDept->getCompany()) {
                $qb->andWhere('e.company = :companyId')
                   ->setParameter('companyId', $companyDept->getCompany()->getId());
            } else {
                $qb->andWhere('e.company = :companyId')
                   ->setParameter('companyId', $filters['companyId']);
            }
        }

        $employees = $qb->getQuery()->toIterable();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $headers = ['工号', '姓名', '英文名', '部门', '职位', '性别', '邮箱', '手机号', '在职状态', '工作状态', '入职日期', '出生日期', '身份证号'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }

        // Enable autofilter for header row
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
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFE3E5E8'],
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['argb' => 'FFF1F3F4'],
            ],
        ];
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Set Data
        $row = 2;
        foreach ($employees as $emp) {
            $sheet->setCellValue('A' . $row, $emp->getEmployeeNo());
            $sheet->setCellValue('B' . $row, $emp->getName());
            $sheet->setCellValue('C' . $row, $emp->getEnglishName());
            $sheet->setCellValue('D' . $row, $emp->getDepartment() ? $emp->getDepartment()->getName() : '');
            $sheet->setCellValue('E' . $row, $emp->getPosition() ? $emp->getPosition()->getName() : '');
            $sheet->setCellValue('F' . $row, $emp->getGender() === 'male' ? '男' : ($emp->getGender() === 'female' ? '女' : ''));
            $sheet->setCellValue('G' . $row, $emp->getEmail());
            $sheet->setCellValue('H' . $row, $emp->getMobile());
            $sheet->setCellValue('I' . $row, $emp->getEmploymentStatus() === 'active' ? '在职' : '离职');
            
            $workStatusMap = [
                'working' => '工作',
                'vacation' => '休假',
                'business_trip' => '出差',
                'out_of_office' => '外出',
                'in_meeting' => '会议中'
            ];
            $sheet->setCellValue('J' . $row, $workStatusMap[$emp->getWorkStatus()] ?? '');
            
            $sheet->setCellValue('K' . $row, $emp->getHireDate() ? $emp->getHireDate()->format('Y-m-d') : '');
            $sheet->setCellValue('L' . $row, $emp->getBirthDate() ? $emp->getBirthDate()->format('Y-m-d') : '');
            $sheet->setCellValueExplicit('M' . $row, $emp->getIdCard(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            
            if ($row % 200 === 0) {
                $this->em->clear(Employee::class);
            }
            $row++;
        }

        // Apply Feishu/Lark-style Data Styling
        if ($row > 2) {
            $dataStyle = [
                'font' => [
                    'color' => ['argb' => 'FF464952'],
                    'size' => 12,
                    'name' => 'PingFang SC',
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE3E5E8'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A2:' . $lastColumn . ($row - 1))->applyFromArray($dataStyle);

            for ($i = 2; $i < $row; $i++) {
                $sheet->getRowDimension($i)->setRowHeight(32);
                if ($i % 2 === 0) {
                    $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->getFill()
                          ->setFillType(Fill::FILL_SOLID)
                          ->getStartColor()->setARGB('FFFFFFFF');
                } else {
                    $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->getFill()
                          ->setFillType(Fill::FILL_SOLID)
                          ->getStartColor()->setARGB('FFF6F8FA');
                }
            }
        }

        // Set column widths (Feishu-style)
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

        $writer = new Xlsx($spreadsheet);
        
        $exportDir = $this->projectDir . '/var/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }
        
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $now = new \DateTime('now', $timezone);
        $fileName = 'employees_' . $now->format('Ymd_His') . '_' . uniqid() . '.xlsx';
        $filePath = $exportDir . '/' . $fileName;
        
        $writer->save($filePath);

        // Notify user via Mercure
        $update = new Update(
            'https://enterprise.local/user/' . $userId . '/export',
            json_encode([
                'type' => 'export_complete',
                'title' => '文件已生成',
                'message' => '您请求的花名册文件已经准备就绪。',
                'file_name' => $fileName,
                'download_url' => '/employee/export/download/' . $fileName
            ]),
            true // Private message
        );

        $this->hub->publish($update);
    }
}
