<?php

namespace App\Service\AI\Tool;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Repository\Platform\ViewFieldRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTool(name: 'view_getInfo', description: '获取视图的完整信息，包括绑定实体、字段列表和布局配置', method: 'getViewInfo')]
#[AsTool(name: 'view_updateSectionConfig', description: '更新视图的布局配置（contentWidth, width, unit, columns 等）', method: 'updateSectionConfig')]
#[AsTool(name: 'view_updateFieldConfig', description: '更新视图字段的配置项，如标签文本、占位符、高度等', method: 'updateFieldConfig')]
#[AsTool(name: 'view_listEntityFields', description: '列出视图绑定实体的所有可用字段（含类型和验证规则）', method: 'listEntityFields')]
class ViewEditorToolProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewFieldRepository $fieldRepo,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    private function findView(string $idOrName): ?View
    {
        // Try UUID first
        $view = $this->em->getRepository(View::class)->find($idOrName);
        if ($view) {
            return $view;
        }
        // Fallback to name lookup
        return $this->em->getRepository(View::class)->findOneBy(['name' => $idOrName]);
    }

    #[AsTool(
        name: 'view.getInfo',
        description: '获取视图的完整信息，包括绑定实体、字段列表和布局配置',
    )]
    public function getViewInfo(string $viewId): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $entity = $view->getFormEntity();
        $fields = $this->fieldRepo->findBy(['view' => $view], ['sortOrder' => 'ASC']);

        return [
            'id' => $view->getId(),
            'name' => $view->getName(),
            'label' => $view->getLabel(),
            'type' => $view->getType(),
            'builtIn' => $view->isBuiltIn(),
            'entity' => $entity ? [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'fqn' => $entity->getFqn(),
            ] : null,
            'sectionConfig' => $view->getSectionConfig(),
            'fields' => array_map(fn (ViewField $f) => [
                'id' => $f->getId(),
                'fieldName' => $f->getFieldName(),
                'fieldLabel' => $f->getFieldLabel(),
                'fieldType' => $f->getFieldType(),
                'sortOrder' => $f->getSortOrder(),
                'config' => $f->getConfig(),
            ], $fields),
        ];
    }

    #[AsTool(
        name: 'view.updateSectionConfig',
        description: '更新视图的布局配置（contentWidth, width, unit, columns 等）',
    )]
    public function updateSectionConfig(string $viewId, string $contentWidth, ?int $width = null, ?string $unit = null, ?int $columns = null): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $config = $view->getSectionConfig() ?? [];
        $config['contentWidth'] = $contentWidth;
        if ($width !== null) $config['width'] = $width;
        if ($unit !== null) $config['unit'] = $unit;
        if ($columns !== null) $config['columns'] = $columns;

        $view->setSectionConfig($config);
        $this->em->flush();

        return ['message' => '布局配置已更新', 'sectionConfig' => $config];
    }

    #[AsTool(
        name: 'view.updateFieldConfig',
        description: '更新视图字段的配置项，如标签文本、占位符、高度等',
    )]
    public function updateFieldConfig(string $viewId, string $fieldName, ?string $label = null, ?string $placeholder = null, ?int $height = null, ?bool $required = null, ?bool $rounded = null, ?int $colSpan = null): array
    {
        $field = $this->fieldRepo->findOneBy(['view' => $viewId, 'fieldName' => $fieldName]);
        if (!$field) {
            return ['error' => "字段 $fieldName 不存在于视图中"];
        }

        $config = $field->getConfig() ?? [];

        if ($label !== null) {
            $field->setFieldLabel($label);
        }
        if ($placeholder !== null) {
            $config['placeholder'] = $placeholder;
        }
        if ($height !== null) {
            $config['height'] = $height;
        }
        if ($required !== null) {
            $config['required'] = $required;
        }
        if ($rounded !== null) {
            $config['rounded'] = $rounded;
        }
        if ($colSpan !== null) {
            $config['colSpan'] = $colSpan;
        }

        $field->setConfig($config);
        $this->em->flush();

        return ['message' => "字段 $fieldName 配置已更新", 'config' => $config];
    }

    #[AsTool(
        name: 'view.listEntityFields',
        description: '列出视图绑定实体的所有可用字段（含类型和验证规则）',
    )]
    public function listEntityFields(string $viewId): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $entity = $view->getFormEntity();
        if (!$entity) {
            return ['error' => '视图未绑定实体'];
        }

        $properties = $entity->getProperties();
        $fields = [];

        foreach ($properties as $prop) {
            $fields[] = [
                'propertyName' => $prop->getPropertyName(),
                'type' => $prop->getType(),
                'comment' => $prop->getComment(),
                'nullable' => $prop->getNullable(),
                'length' => $prop->getLength(),
                'validation' => $prop->getValidation(),
            ];
        }

        return ['entity' => $entity->getName(), 'fields' => $fields];
    }

}
