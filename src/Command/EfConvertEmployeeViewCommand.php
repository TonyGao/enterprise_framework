<?php

namespace App\Command;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Entity\Platform\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ef:convert-employee-view',
    description: '将手写 employees/edit.html.twig 转换为 View Designer 视图',
)]
class EfConvertEmployeeViewCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $viewName = 'employee_edit_form';
        $entityFqn = 'App\Entity\Organization\Employee';

        $view = $this->em->getRepository(View::class)
            ->findOneBy(['name' => $viewName, 'builtIn' => true]);

        if (!$view) {
            $io->writeln("创建内置视图: $viewName");

            $view = new View();
            $view->setName($viewName);
            $view->setLabel('人员编辑表单');
            $view->setType('view');
            $view->setBuiltIn(true);
            $view->setTemplate('employee/edit_drawer.html.twig');

            $root = $this->em->getRepository(View::class)->findOneBy(['name' => 'root']);
            if (!$root) {
                $root = new View();
                $root->setName('root');
                $root->setLabel('Root');
                $root->setType('root');
                $this->em->persist($root);
                $this->em->flush();
            }
            $view->setParent($root);
            $this->em->persist($view);
            $this->em->flush();
        }

        $platformEntity = $this->em->getRepository(Entity::class)
            ->findOneBy(['fqn' => $entityFqn]);
        if (!$platformEntity) {
            $io->error("未找到 $entityFqn 平台实体 (请先运行 ef:entity --init)");
            return Command::FAILURE;
        }

        $view->setFormEntity($platformEntity);

        // sectionConfig: 全宽 + 双列网格 + 纵向布局
        $view->setSectionConfig([
            'contentWidth' => 'full-width',
            'columns' => 2,
            'fieldLayout' => 'vertical',
        ]);

        // 删除旧 ViewField
        $oldFields = $this->em->getRepository(ViewField::class)->findBy(['view' => $view]);
        foreach ($oldFields as $old) {
            $this->em->remove($old);
        }
        $this->em->flush();

        // 定义字段：匹配 templates/employee/edit.html.twig
        $fieldDefs = [
            ['name' => 'name',                'type' => 'string', 'label' => 'employee.field.name',             'required' => true],
            ['name' => 'englishName',         'type' => 'string', 'label' => 'employee.field.english_name',      'required' => false],
            ['name' => 'employeeNo',          'type' => 'string', 'label' => 'employee.field.employee_no',       'required' => true],
            ['name' => 'employmentStatus',    'type' => 'select', 'label' => 'employee.field.employment_status', 'required' => true, 'choices' => [
                ['label' => 'employee.employment_status.active',   'value' => 'active'],
                ['label' => 'employee.employment_status.probation','value' => 'probation'],
                ['label' => 'employee.employment_status.inactive', 'value' => 'inactive'],
            ]],
            ['name' => 'workStatus',          'type' => 'select', 'label' => 'employee.field.work_status',       'required' => false, 'choices' => [
                ['label' => 'employee.work_status.working',        'value' => 'working'],
                ['label' => 'employee.work_status.vacation',       'value' => 'vacation'],
                ['label' => 'employee.work_status.business_trip',  'value' => 'business_trip'],
                ['label' => 'employee.work_status.out_of_office',  'value' => 'out_of_office'],
                ['label' => 'employee.work_status.in_meeting',     'value' => 'in_meeting'],
            ]],
            ['name' => 'isActive',            'type' => 'select', 'label' => 'employee.field.account_status',    'required' => true, 'choices' => [
                ['label' => 'employee.account_status.enabled',  'value' => true],
                ['label' => 'employee.account_status.disabled', 'value' => false],
            ]],
            ['name' => 'hireDate',            'type' => 'date',   'label' => 'employee.field.hire_date',         'required' => false],
            ['name' => 'position',            'type' => 'entity', 'label' => 'employee.field.position',          'required' => false, 'class' => 'App\Entity\Organization\Position'],
            ['name' => 'email',               'type' => 'email',  'label' => 'employee.field.email',             'required' => true, 'colSpan' => 2],
            ['name' => 'mobile',              'type' => 'string', 'label' => 'employee.field.mobile',            'required' => false, 'colSpan' => 2],
            ['name' => 'company',             'type' => 'entity', 'label' => 'employee.field.company',           'required' => false, 'class' => 'App\Entity\Organization\Company',
                'colSpan' => 2, 'sectionDivider' => true, 'sectionHeader' => 'employee.detail.info.org.title'],
            ['name' => 'department',          'type' => 'entity', 'label' => 'employee.field.department',        'required' => false, 'class' => 'App\Entity\Organization\Department'],
            ['name' => 'manager',             'type' => 'entity', 'label' => 'employee.field.manager',           'required' => false, 'class' => 'App\Entity\Organization\Employee'],
        ];

        foreach ($fieldDefs as $i => $def) {
            $field = new ViewField();
            $field->setView($view);
            $field->setEntity($platformEntity);
            $field->setFieldName($def['name']);
            $field->setFieldLabel($def['label']);
            $field->setFieldType($def['type']);
            $field->setSortOrder($i);

            $config = [
                'label' => $def['label'],
                'height' => 36,
                'rounded' => true,
                'required' => $def['required'] ?? false,
            ];

            if ($def['type'] === 'entity' && !empty($def['class'])) {
                $config['class'] = $def['class'];
            }

            if ($def['type'] === 'select' && !empty($def['choices'])) {
                $config['choices'] = $def['choices'];
            }

            if (!empty($def['colSpan'])) {
                $config['colSpan'] = $def['colSpan'];
            }

            if (!empty($def['sectionDivider'])) {
                $config['sectionDivider'] = true;
            }

            if (!empty($def['sectionHeader'])) {
                $config['sectionHeader'] = $def['sectionHeader'];
            }

            $field->setConfig($config);
            $this->em->persist($field);
        }

        $this->em->flush();

        $io->success("已为 $viewName 创建 " . count($fieldDefs) . " 个字段");

        return Command::SUCCESS;
    }
}
