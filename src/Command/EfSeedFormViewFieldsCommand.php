<?php

namespace App\Command;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Entity\Platform\Entity;
use App\Entity\Platform\EntityProperty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ef:seed-form-fields',
    description: '从 EntityProperty 记录为内置表单视图填充 ViewField 记录',
)]
class EfSeedFormViewFieldsCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this->addArgument('view-name', InputArgument::OPTIONAL, '视图名称 (company_structure_form 或 company_edit_form)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $viewName = $input->getArgument('view-name');

        $views = [
            'company_structure_form' => [
                'fqn' => 'App\Entity\Organization\Corporation',
                'template' => 'admin/org/corporationEdit.html.twig',
                'parentName' => '组织架构',
            ],
            'company_edit_form' => [
                'fqn' => 'App\Entity\Organization\Company',
                'template' => 'admin/org/companyEdit.html.twig',
                'parentName' => '组织架构',
            ],
        ];

        $targets = $viewName ? [$viewName => $views[$viewName] ?? null] : $views;

        foreach ($targets as $name => $config) {
            if (!$config) {
                $io->error("未知视图: $name");
                return Command::FAILURE;
            }

            $io->section("处理: $name");

            $view = $this->em->getRepository(View::class)
                ->findOneBy(['name' => $name, 'builtIn' => true]);

            if (!$view) {
                $io->writeln("  创建内置视图: $name");
                $parent = $this->em->getRepository(View::class)
                    ->findOneBy(['name' => $config['parentName']]);
                if (!$parent) {
                    $io->error("未找到父文件夹: {$config['parentName']}");
                    continue;
                }

                $view = new View();
                $view->setName($name);
                $view->setLabel($name === 'company_structure_form' ? '公司架构表单' : '公司编辑表单');
                $view->setType('view');
                $view->setPath("{$config['parentName']}/$name/1_0");
                $view->setBuiltIn(true);
                $view->setTemplate($config['template']);
                $view->setParent($parent);

                $repo = $this->em->getRepository(View::class);
                $repo->persistAsLastChild($view);
            }

            $platformEntity = $this->em->getRepository(Entity::class)
                ->findOneBy(['fqn' => $config['fqn']]);

            if (!$platformEntity) {
                $io->error("未找到 {$config['fqn']} 平台实体 (请先运行 ef:entity --init)");
                continue;
            }

            $view->setFormEntity($platformEntity);
            $this->em->persist($view);

            $existing = $this->em->getRepository(ViewField::class)
                ->findBy(['view' => $view]);

            if (!empty($existing)) {
                $io->success("  已存在 " . count($existing) . " 条 ViewField 记录，跳过");
                continue;
            }

            $properties = $this->em->getRepository(EntityProperty::class)
                ->findBy(['entity' => $platformEntity], ['orderNum' => 'ASC']);

            if (empty($properties)) {
                $io->warning("  {$config['fqn']} 实体没有 EntityProperty 记录");
                if ($viewName) {
                    $this->em->flush();
                }
                continue;
            }

            foreach ($properties as $prop) {
                $field = new ViewField();
                $field->setView($view);
                $field->setEntity($platformEntity);
                $field->setFieldName($prop->getPropertyName());
                $field->setFieldLabel($prop->getComment() ?: $prop->getPropertyName());
                $field->setFieldType($prop->getType());
                $field->setSortOrder($prop->getOrderNum() ?? 0);

                $configArr = [];
                if ($prop->getValidation()) {
                    $configArr['validation'] = $prop->getValidation();
                }
                if ($prop->getFormOptions()) {
                    $configArr['formOptions'] = $prop->getFormOptions();
                }
                if ($prop->getTargetEntity()) {
                    $configArr['class'] = $prop->getTargetEntity();
                }
                if ($prop->getHeight()) {
                    $configArr['height'] = $prop->getHeight();
                }
                if ($prop->getRounded()) {
                    $configArr['rounded'] = $prop->getRounded();
                }
                $field->setConfig(!empty($configArr) ? $configArr : null);

                $this->em->persist($field);
            }

            $this->em->flush();

            $io->success("  已为 $name 视图创建 " . count($properties) . " 条 ViewField 记录");
        }

        return Command::SUCCESS;
    }
}
