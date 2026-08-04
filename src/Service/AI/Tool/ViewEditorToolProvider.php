<?php

namespace App\Service\AI\Tool;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Repository\Platform\ViewFieldRepository;
use App\Service\Form\FormLayoutService;
use App\Service\Platform\View\ViewPathResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsTool(name: 'view_getInfo', description: '获取视图的完整信息，包括绑定实体、字段列表和布局配置', method: 'getViewInfo')]
#[AsTool(name: 'view_updateSectionConfig', description: '更新视图的布局配置（contentWidth, width, unit, columns, gap, labelWidth, renderMode 等）', method: 'updateSectionConfig')]
#[AsTool(name: 'view_updateFieldConfig', description: '更新视图字段的配置项，如标签文本、占位符、高度、必填、配色、分组头等', method: 'updateFieldConfig')]
#[AsTool(name: 'view_listEntityFields', description: '列出视图绑定实体的所有可用字段（含类型和验证规则）', method: 'listEntityFields')]
#[AsTool(name: 'view_getFormStructure', description: '获取表单视图的结构化真相：绑定字段（含可配置项）、section_config、主题、渲染形态', method: 'getFormStructure')]
#[AsTool(name: 'form_applyLayout', description: '将结构化布局指令应用到表单视图（分组/列/主题/字段配置），控件由系统重渲染保证字段保真。layout 示例: {theme:{primary,pageBg,cardBg,requiredBg,...}, layout:{columns,fieldLayout,contentWidth,width,gap}, groups:[{title,fields:[...],colSpan}], fields:{name:{label,required,placeholder,requiredBg,regularBg,colSpan}}}', method: 'applyFormLayout')]
#[AsTool(name: 'page_applyShell', description: '将整页壳 HTML 写入表单视图的设计文件（page 形态；可含静态内容/布局/样式与 data-view-form 占位，禁止手写表单控件）', method: 'applyPageShell')]
class ViewEditorToolProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewFieldRepository $fieldRepo,
        private readonly FormLayoutService $formLayoutService,
        private readonly RequestStack $requestStack,
        private readonly ViewPathResolver $pathResolver,
        private readonly ViewFileToolProvider $fileToolProvider,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    private function activeVersion(View $view): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $v = $request?->attributes->get('ai_view_version');
        return ($v && \App\Service\Platform\View\VersionNumber::isValid((string) $v))
            ? (string) $v
            : $this->pathResolver->currentVersion($view);
    }

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
    public function updateSectionConfig(string $viewId, string $contentWidth, ?int $width = null, ?string $unit = null, ?int $columns = null, ?int $gap = null, ?int $labelWidth = null, ?string $renderMode = null): array
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
        if ($gap !== null) $config['gap'] = $gap;
        if ($labelWidth !== null) $config['labelWidth'] = $labelWidth;
        if ($renderMode !== null) $config['render_mode'] = $renderMode;

        $view->setSectionConfig($config);
        $this->em->flush();

        return ['message' => '布局配置已更新', 'sectionConfig' => $config];
    }

    public function updateFieldConfig(
        string $viewId,
        string $fieldName,
        ?string $label = null,
        ?string $placeholder = null,
        ?int $height = null,
        ?bool $required = null,
        ?bool $rounded = null,
        ?int $colSpan = null,
        ?string $sectionHeader = null,
        ?bool $sectionDivider = null,
        ?string $regularBg = null,
        ?string $requiredBg = null,
        ?array $choices = null,
    ): array {
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
        if ($sectionHeader !== null) {
            if ($sectionHeader === '') {
                unset($config['sectionHeader']);
            } else {
                $config['sectionHeader'] = $sectionHeader;
            }
        }
        if ($sectionDivider !== null) {
            $config['sectionDivider'] = $sectionDivider;
        }
        if ($regularBg !== null) {
            $config['regularBg'] = $regularBg;
        }
        if ($requiredBg !== null) {
            $config['requiredBg'] = $requiredBg;
        }
        if ($choices !== null) {
            $config['choices'] = is_array($choices) ? $choices : [];
        }

        $field->setConfig($config);
        $this->em->flush();

        return ['message' => "字段 $fieldName 配置已更新", 'config' => $config];
    }

    #[AsTool(
        name: 'view.getFormStructure',
        description: '获取表单视图的结构化真相：绑定字段（含可配置项）、section_config、主题、渲染形态',
    )]
    public function getFormStructure(string $viewId): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $fields = $this->fieldRepo->findBy(['view' => $view], ['sortOrder' => 'ASC']);
        $sectionConfig = $view->getSectionConfig() ?? [];

        return [
            'viewId' => (string) $view->getId(),
            'viewName' => $view->getName(),
            'viewLabel' => $view->getLabel(),
            'entity' => $view->getFormEntity() ? $view->getFormEntity()->getName() : null,
            'render_mode' => $sectionConfig['render_mode'] ?? 'page',
            'sectionConfig' => $sectionConfig,
            'theme' => $sectionConfig['theme'] ?? null,
            'fields' => array_map(fn (ViewField $f) => [
                'name' => $f->getFieldName(),
                'label' => $f->getFieldLabel(),
                'type' => $f->getFieldType(),
                'sortOrder' => $f->getSortOrder(),
                'config' => $f->getConfig(),
            ], $fields),
        ];
    }

    #[AsTool(
        name: 'form.applyLayout',
        description: '将结构化布局指令应用到表单视图（分组/列/主题/字段配置），控件由系统重渲染保证字段保真',
    )]
    public function applyFormLayout(string $viewId, array $layout): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }
        if (!$view->getFormEntity()) {
            return ['error' => '视图未绑定实体，不能应用表单布局'];
        }
        if (!is_array($layout) || empty($layout['groups']) || !is_array($layout['groups'])) {
            return ['error' => 'layout 必须包含 groups（分组字段列表）'];
        }

        try {
            $result = $this->formLayoutService->applyLayout($view, $this->activeVersion($view), $layout);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        return ['message' => '表单布局已应用', 'result' => $result];
    }

    #[AsTool(
        name: 'page.applyShell',
        description: '将整页壳 HTML 写入表单视图的设计文件（page 形态；可含静态内容/布局/样式与 data-view-form 占位，禁止手写表单控件）',
    )]
    public function applyPageShell(string $viewId, string $html): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }
        if (trim($html) === '') {
            return ['error' => '页面壳 HTML 不能为空'];
        }

        $version = $this->activeVersion($view);
        $designFile = $this->pathResolver->designFile($view, $version);
        if (!$designFile) {
            return ['error' => '视图无设计文件路径'];
        }

        $sectionConfig = $view->getSectionConfig() ?? [];
        $sectionConfig['render_mode'] = 'page';
        $view->setSectionConfig($sectionConfig);
        $this->em->flush();

        $dir = dirname($designFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $randomId = 'sec' . substr(bin2hex(random_bytes(4)), 0, 8);
        $design = '<div class="add-section-button" id="add-section-button" style="display:flex;justify-content:center;align-items:center;"><i class="fa fa-plus"></i></div>'
            . '<div class="section active" id="' . $randomId . '" data-section-type="default">'
            . '<div class="section-header" style="display:none;">'
            . '<button class="btn-add"><i class="fa fa-plus" style="font-size:1em;"></i></button>'
            . '<button class="btn-layout"><i class="fa fa-grip" style="font-size:1em;"></i></button>'
            . '<button class="btn-close"><i class="fa fa-times" style="font-size:1em;"></i></button>'
            . '</div>'
            . '<div class="section-content ui-droppable" style="width:100%;">'
            . $html
            . '</div>'
            . '</div>';

        if (file_put_contents($designFile, $design) === false) {
            return ['error' => '写入设计文件失败'];
        }

        // 同步可执行模板（与保存管线一致）
        try {
            $this->fileToolProvider->renderHtml($viewId);
        } catch (\Throwable) {
        }

        return ['message' => '页面壳已写入', 'designFile' => str_replace($this->projectDir . '/templates/', 'views/', $designFile)];
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
