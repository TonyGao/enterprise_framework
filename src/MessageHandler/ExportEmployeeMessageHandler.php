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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMessageHandler]
final class ExportEmployeeMessageHandler
{
    private const BATCH_SIZE = 500;
    private const STYLE_BATCH_SIZE = 100;
    private const PROGRESS_BATCH_SIZE = 100;

    private array $workStatusMap = [
        'working' => '工作',
        'vacation' => '休假',
        'business_trip' => '出差',
        'out_of_office' => '外出',
        'in_meeting' => '会议中'
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private HubInterface $hub,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    public function __invoke(ExportEmployeeMessage $message): void
    {
        try {
            $this->doInvoke($message);
        } catch (\Throwable $e) {
            file_put_contents('/tmp/export_error.log', date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            throw $e;
        }
    }

    private function doInvoke(ExportEmployeeMessage $message): void
    {
        $userId = $message->getUserId();
        $filters = $message->getFilters();

        // First count total for progress calculation
        $countQb = $this->em->getRepository(Employee::class)->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('isSystem', false);

        $this->applyFilters($countQb, $filters);
        $totalCount = (int) $countQb->getQuery()->getSingleScalarResult();

        if ($totalCount === 0) {
            $this->sendNotification($userId, [
                'type' => 'export_complete',
                'title' => '导出完成',
                'message' => '没有找到符合条件的员工数据。',
                'file_name' => null,
                'download_url' => null
            ]);
            return;
        }

        // Build query for data export
        $qb = $this->em->getRepository(Employee::class)->createQueryBuilder('e')
            ->where('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('isSystem', false);

        $this->applyFilters($qb, $filters);
        $employees = $qb->getQuery()->toIterable();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $headers = ['工号', '姓名', '英文名', '部门', '职位', '性别', '邮箱', '手机号', '在职状态', '工作状态', '入职日期', '出生日期', '身份证号'];
        $this->setHeaders($sheet, $headers);

        // Apply header style
        $headerStyle = $this->getHeaderStyle();
        $sheet->getStyle('A1:' . $this->getLastColumn(count($headers)) . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Set column widths
        $this->setColumnWidths($sheet);

        // Write data
        $row = 2;
        $data = [];
        foreach ($employees as $emp) {
            $data[] = [
                $emp->getEmployeeNo() ?? '',
                $emp->getName() ?? '',
                $emp->getEnglishName() ?? '',
                $emp->getDepartment() ? $emp->getDepartment()->getName() : '',
                $emp->getPosition() ? $emp->getPosition()->getName() : '',
                $emp->getGender() === 'male' ? '男' : ($emp->getGender() === 'female' ? '女' : ''),
                $emp->getEmail() ?? '',
                $emp->getMobile() ?? '',
                $emp->getEmploymentStatus() === 'active' ? '在职' : '离职',
                $this->workStatusMap[$emp->getWorkStatus()] ?? '',
                $emp->getHireDate() ? $emp->getHireDate()->format('Y-m-d') : '',
                $emp->getBirthDate() ? $emp->getBirthDate()->format('Y-m-d') : '',
                $emp->getIdCard() ?? '',
            ];
            $row++;
        }

        if (!empty($data)) {
            $sheet->fromArray($data, null, 'A2');
        }

        // Apply alternating row colors
        $lastDataRow = $row - 1;
        if ($lastDataRow >= 2) {
            $dataStyle = $this->getDataStyle();
            $evenRowStyle = $this->getEvenRowStyle();
            $oddRowStyle = $this->getOddRowStyle();

            for ($i = 2; $i <= $lastDataRow; $i++) {
                $sheet->getRowDimension($i)->setRowHeight(32);
            }

            $sheet->getStyle('A2:' . $this->getLastColumn(count($headers)) . $lastDataRow)->applyFromArray($dataStyle);

            for ($i = 2; $i <= $lastDataRow; $i++) {
                $style = ($i % 2 === 0) ? $evenRowStyle : $oddRowStyle;
                $sheet->getStyle('A' . $i . ':' . $this->getLastColumn(count($headers)) . $i)->applyFromArray($style);
            }
        }

        // Save file
        $fileName = $this->saveSpreadsheet($spreadsheet);

        // Send completion notification
        $this->sendNotification($userId, [
            'type' => 'export_complete',
            'title' => '文件已生成',
            'message' => sprintf('已导出 %d 条记录，文件已准备就绪。', $totalCount),
            'file_name' => $fileName,
            'download_url' => '/employee/export/download/' . $fileName,
            'progress' => 100
        ]);
    }

    private function applyFilters($qb, array $filters): void
    {
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
    }

    private function employeeToArray($emp): array
    {
        return [
            'employeeNo' => $emp->getEmployeeNo() ?? '',
            'name' => $emp->getName() ?? '',
            'englishName' => $emp->getEnglishName() ?? '',
            'department' => $emp->getDepartment() ? $emp->getDepartment()->getName() : '',
            'position' => $emp->getPosition() ? $emp->getPosition()->getName() : '',
            'gender' => match($emp->getGender()) {
                'male' => '男',
                'female' => '女',
                default => '',
            },
            'email' => $emp->getEmail() ?? '',
            'mobile' => $emp->getMobile() ?? '',
            'employmentStatus' => $emp->getEmploymentStatus() === 'active' ? '在职' : '离职',
            'workStatus' => $this->workStatusMap[$emp->getWorkStatus()] ?? '',
            'hireDate' => $emp->getHireDate() ? $emp->getHireDate()->format('Y-m-d') : '',
            'birthDate' => $emp->getBirthDate() ? $emp->getBirthDate()->format('Y-m-d') : '',
            'idCard' => $emp->getIdCard() ?? '',
        ];
    }

    private function setHeaders($sheet, array $headers): void
    {
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }
        $sheet->setAutoFilter('A1:' . $this->getLastColumn(count($headers)) . '1');
    }

    private function getLastColumn(int $headerCount): string
    {
        return chr(ord('A') + $headerCount - 1);
    }

    private function getHeaderStyle(): array
    {
        return [
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
    }

    private function getDataStyle(): array
    {
        return [
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
    }

    private function getEvenRowStyle(): array
    {
        return [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
        ];
    }

    private function getOddRowStyle(): array
    {
        return [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['argb' => 'FFF6F8FA'],
            ],
        ];
    }

    private function applyBatchStyles($sheet, int $startRow, int $endRow, array $rowStyles, array $dataStyle): void
    {
        // Apply base data style to entire range once
        $sheet->getStyle('A' . $startRow . ':M' . $endRow)->applyFromArray($dataStyle);

        // Apply row heights and alternating row colors in batches
        for ($i = $startRow; $i <= $endRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(32);
        }

        // Batch apply alternating colors
        $evenRange = [];
        $oddRange = [];

        foreach ($rowStyles as $idx => $style) {
            $currentRow = $startRow + $idx;
            if ($style === 'even') {
                $evenRange[] = 'A' . $currentRow . ':M' . $currentRow;
            } else {
                $oddRange[] = 'A' . $currentRow . ':M' . $currentRow;
            }
        }

        if (!empty($evenRange)) {
            foreach ($evenRange as $range) {
                $sheet->getStyle($range)->applyFromArray($this->getEvenRowStyle());
            }
        }

        if (!empty($oddRange)) {
            foreach ($oddRange as $range) {
                $sheet->getStyle($range)->applyFromArray($this->getOddRowStyle());
            }
        }
    }

    private function setColumnWidths($sheet): void
    {
        $columnWidths = [
            'A' => 12,
            'B' => 10,
            'C' => 14,
            'D' => 18,
            'E' => 12,
            'F' => 6,
            'G' => 24,
            'H' => 14,
            'I' => 10,
            'J' => 10,
            'K' => 12,
            'L' => 12,
            'M' => 20,
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $exportDir = $this->projectDir . '/var/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $timezone = new \DateTimeZone('Asia/Shanghai');
        $now = new \DateTime('now', $timezone);
        $fileName = 'employees_' . $now->format('Ymd_His') . '_' . uniqid() . '.xlsx';
        $filePath = $exportDir . '/' . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $fileName;
    }

    private function sendNotification(string $userId, array $data): void
    {
        $update = new Update(
            'https://enterprise.local/user/' . $userId . '/export',
            json_encode($data),
            true
        );
        $this->hub->publish($update);
    }
}
