<?php

namespace App\Controller\Api\Admin\Platform;

use App\Controller\Api\ApiResponse;
use App\Entity\Platform\Entity;
use App\Entity\Platform\EntityProperty;
use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Service\Utils\DomManipulator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ViewEditorApiController extends AbstractController
{
    /**
     * 保存视图
     * 
     * 1. 从 canvas HTML 中提取字段配置（标签文本等），保存到 ViewField.config JSONB
     * 2. 清理 canvas 的编辑器辅助元素后，保存为设计文件
     * 3. 内置视图不覆盖生产模板，由 FormFieldRenderer 从 ViewField 记录再生
     */
    #[Route(
        '/api/admin/platform/view/save',
        name: 'api_platform_view_save',
        methods: ['POST']
    )]
    public function saveView(
        Request $request,
        EntityManagerInterface $em,
        DomManipulator $domManipulator
    ): ApiResponse {
        $payload = $request->toArray();
        $viewId = $payload['viewId'] ?? null;
        $canvasHtml = $payload['canvasHtml'] ?? null;

        if (!$viewId || !$canvasHtml) {
            return ApiResponse::error('视图ID和画布内容不能为空', 400);
        }

        try {
            $view = $em->getRepository(View::class)->find($viewId);
            if (!$view) {
                return ApiResponse::error('视图不存在', 404);
            }

            // 保存 sectionConfig
            if (isset($payload['sectionConfig'])) {
                $view->setSectionConfig($payload['sectionConfig']);
                $em->persist($view);
                $em->flush();
            }

            // 1. 从 canvas HTML 提取字段标签配置（失败不影响保存）
            try {
                $this->extractAndPersistFieldConfig($canvasHtml, $view, $em);
            } catch (\Exception $e) {
                // 字段配置提取失败可忽略，设计文件仍可保存
            }

            $filesystem = new Filesystem();
            $basePath = $this->getParameter('kernel.project_dir') . '/templates/views/';

            if ($view->isBuiltIn() && $view->getTemplate()) {
                // 内置视图：只保存设计文件，不覆盖生产模板
                $viewPath = $view->getPath();
                $viewName = $view->getName();
                $designDir = $basePath . ($viewPath ? $viewPath . '/' : 'builtin/');
                $filesystem->mkdir($designDir, 0755);
                $filesystem->dumpFile($designDir . $viewName . '.design.twig', $canvasHtml);
                return ApiResponse::success(json_encode(['message' => '保存成功（字段配置已更新）']));
            }

            // 2. 非内置视图：保存设计文件 + 清理后可执行文件
            $viewPath = $view->getPath();
            $viewName = $view->getName();

            $designFilePath = $basePath . $viewPath . '/' . $viewName . '.design.twig';
            $executableFilePath = $basePath . $viewPath . '/' . $viewName . '.html.twig';

            $directory = dirname($designFilePath);
            if (!$filesystem->exists($directory)) {
                $filesystem->mkdir($directory, 0755);
            }

            $filesystem->dumpFile($designFilePath, $canvasHtml);

            $domManipulator->load($canvasHtml);
            $domManipulator->remove('.add-section-button');
            $domManipulator->remove('.section-header');
            $domManipulator->removeClass('.section.active', 'active');
            $domManipulator->removeClass('.ui-droppable', 'ui-droppable');
            $domManipulator->removeClass('.ef-component-labels', 'ef-component-labels');
            $domManipulator->processTableCells();
            $domManipulator->processDynamicFields();

            $filesystem->dumpFile($executableFilePath, $domManipulator->getHtml());

            return ApiResponse::success(json_encode(['message' => '视图保存成功']));
        } catch (\Exception $e) {
            return ApiResponse::error('保存视图失败: ' . $e->getMessage(), 500);
        }
    }

    private function extractAndPersistFieldConfig(string $html, View $view, EntityManagerInterface $em): void
    {
        $useErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_use_internal_errors($useErrors);
        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query("//div[contains(@class, 'ef-component') and contains(@class, 'ef-form-label')]");

        $fieldRepo = $em->getRepository(ViewField::class);
        $processedFieldNames = [];
        $orderNum = 0;

        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $fieldName = $node->getAttribute('data-field-name');
                if (!$fieldName) {
                    continue;
                }

                $labelEl = $xpath->query(".//label[contains(@class, 'ef-form-item-label')]", $node)->item(0);
                if (!$labelEl) {
                    continue;
                }

                $labelText = trim($labelEl->textContent);
                if (!$labelText) {
                    continue;
                }

                $processedFieldNames[] = $fieldName;

                $viewField = $fieldRepo->findOneBy(['view' => $view, 'fieldName' => $fieldName]);
                if (!$viewField) {
                    $prop = $em->getRepository(EntityProperty::class)->findOneBy([
                        'entity' => $view->getFormEntity(),
                        'propertyName' => $fieldName,
                    ]);
                    $viewField = new ViewField();
                    $viewField->setView($view);
                    $viewField->setFieldName($fieldName);
                    $viewField->setFieldLabel($labelText);
                    if ($prop) {
                        $viewField->setFieldType($prop->getType());
                        $viewField->setEntity($prop->getEntity());
                    }
                    $em->persist($viewField);
                }

                // 按 canvas 中的出现顺序维护 sortOrder
                $viewField->setSortOrder($orderNum++);

                $config = $viewField->getConfig() ?? [];
                $config['label'] = $labelText;

                // 从 data-* 属性读取布局/样式配置
                $height = $node->getAttribute('data-height');
                if ($height !== '') {
                    $heightVal = (int) $height;
                    $fieldType = $viewField->getFieldType();
                    if ($fieldType === 'text') {
                        // textarea：兼容旧像素值（>20），归一行数
                        if ($heightVal > 20) {
                            $heightVal = max(1, (int) round($heightVal / 14));
                        }
                    } else {
                        // 非 text 字段：高度为像素值，范围 24-60，异常值回退到 36
                        if ($heightVal < 24 || $heightVal > 60) {
                            $heightVal = 36;
                        }
                    }
                    $config['height'] = $heightVal;
                }
                $rounded = $node->getAttribute('data-rounded');
                if ($rounded !== '') {
                    $config['rounded'] = $rounded === 'true';
                }
                $labelCol = $node->getAttribute('data-label-col');
                if ($labelCol !== '') {
                    $config['labelCol'] = (int) $labelCol;
                }
                $placeholder = $node->getAttribute('data-placeholder');
                if ($placeholder !== '') {
                    $config['placeholder'] = $placeholder;
                }
                $required = $node->getAttribute('data-required');
                if ($required !== '') {
                    $config['required'] = $required === 'true';
                }
                $regularBg = $node->getAttribute('data-regular-bg');
                if ($regularBg !== '') {
                    $config['regularBg'] = $regularBg;
                }
                $requiredBg = $node->getAttribute('data-required-bg');
                if ($requiredBg !== '') {
                    $config['requiredBg'] = $requiredBg;
                }
                $colSpan = $node->getAttribute('data-col-span');
                if ($colSpan !== '') {
                    $config['colSpan'] = (int) $colSpan;
                }

                $viewField->setConfig($config);
            }
        }

        // 删除画布中已不存在的字段记录
        $existingFields = $fieldRepo->findBy(['view' => $view]);
        foreach ($existingFields as $existingField) {
            if (!in_array($existingField->getFieldName(), $processedFieldNames)) {
                $em->remove($existingField);
            }
        }

        $em->flush();
    }

    private function getViewEntityMap(): array
    {
        return [
            'company_edit_form' => [
                'fqn' => 'App\Entity\Organization\Company',
                'template' => 'admin/org/companyEdit.html.twig',
            ],
            'company_structure_form' => [
                'fqn' => 'App\Entity\Organization\Corporation',
                'template' => 'admin/org/corporationEdit.html.twig',
            ],
            'department_edit_form' => [
                'fqn' => 'App\Entity\Organization\Department',
                'template' => 'admin/org/departmentEdit.html.twig',
            ],
            'position_edit_form' => [
                'fqn' => 'App\Entity\Organization\Position',
                'template' => 'admin/org/position/edit_drawer.html.twig',
            ],
            'position_level_edit_form' => [
                'fqn' => 'App\Entity\Organization\PositionLevel',
                'template' => 'admin/org/position/level_edit_drawer.html.twig',
            ],
            'employee_edit_form' => [
                'fqn' => 'App\Entity\Organization\Employee',
                'template' => 'employee/edit_drawer.html.twig',
            ],
        ];
    }

    /**
     * 重置 ViewField：删除旧的，按需从 EntityProperty 重新创建
     *
     * @param string[]|null $fields 可选字段白名单（只创建这些字段，按此顺序）
     */
    private function resetViewFields(
        View $view,
        Entity $entity,
        EntityManagerInterface $em,
        ?array $fields = null
    ): void {
        $oldFields = $em->getRepository(ViewField::class)->findBy(['view' => $view]);
        foreach ($oldFields as $old) {
            $em->remove($old);
        }
        $em->flush();

        $properties = $em->getRepository(EntityProperty::class)
            ->findBy(['entity' => $entity], ['orderNum' => 'ASC']);

        if ($fields !== null) {
            $fieldsMap = array_flip($fields);
            $filtered = [];
            foreach ($properties as $prop) {
                if (isset($fieldsMap[$prop->getPropertyName()])) {
                    $filtered[$prop->getPropertyName()] = $prop;
                }
            }
            $ordered = [];
            foreach ($fields as $name) {
                if (isset($filtered[$name])) {
                    $ordered[] = $filtered[$name];
                }
            }
            $properties = $ordered;
        }

        foreach ($properties as $i => $prop) {
            $field = new ViewField();
            $field->setView($view);
            $field->setEntity($entity);
            $field->setFieldName($prop->getPropertyName());
            $field->setFieldLabel($prop->getComment() ?: $prop->getPropertyName());
            $field->setFieldType($prop->getType());
            $field->setSortOrder($i);

            $fieldConfig = [];
            if ($prop->getValidation()) {
                $fieldConfig['validation'] = $prop->getValidation();
            }
            if ($prop->getFormOptions()) {
                $fieldConfig['formOptions'] = $prop->getFormOptions();
            }
            if ($prop->getTargetEntity()) {
                $fieldConfig['class'] = $prop->getTargetEntity();
            }
            if ($prop->getHeight() !== null) {
                $h = $prop->getHeight();
                if ($prop->getType() === 'text' && $h > 20) {
                    $h = max(1, (int) round($h / 14));
                }
                $fieldConfig['height'] = $h;
            } else {
                $fieldConfig['height'] = 36;
            }
            if ($prop->getRounded() !== null) {
                $fieldConfig['rounded'] = $prop->getRounded();
            } else {
                $fieldConfig['rounded'] = true;
            }
            $field->setConfig(!empty($fieldConfig) ? $fieldConfig : null);

            $em->persist($field);
        }
    }

    /**
     * 自动创建视图并绑定实体（不存在则创建，已存在则直接绑定）
     */
    #[Route(
        '/api/admin/platform/view/auto-bind',
        name: 'api_platform_view_auto_bind',
        methods: ['POST']
    )]
    public function autoBindView(
        Request $request,
        EntityManagerInterface $em
    ): ApiResponse {
        $payload = $request->toArray();
        $viewName = $payload['viewName'] ?? '';
        $viewLabel = $payload['viewLabel'] ?? $viewName;
        $entityFqn = $payload['entityFqn'] ?? '';
        $fields = $payload['fields'] ?? null;

        if (!$viewName || !$entityFqn) {
            return ApiResponse::error('viewName 和 entityFqn 不能为空', 400);
        }

        $map = $this->getViewEntityMap();
        $entry = $map[$viewName] ?? null;
        if (!$entry || $entry['fqn'] !== $entityFqn) {
            return ApiResponse::error('不支持的视图绑定', 400);
        }

        // 查找或创建视图
        $view = $em->getRepository(View::class)->findOneBy([
            'name' => $viewName,
            'builtIn' => true,
        ]);

        if (!$view) {
            $view = new View();
            $view->setName($viewName);
            $view->setLabel($viewLabel);
            $view->setType('view');
            $view->setBuiltIn(true);

            $root = $em->getRepository(View::class)->findOneBy(['name' => 'root']);
            if (!$root) {
                $root = new View();
                $root->setName('root');
                $root->setLabel('Root');
                $root->setType('root');
                $em->persist($root);
                $em->flush();
            }
            $view->setParent($root);

            if (!empty($entry['template'])) {
                $view->setTemplate($entry['template']);
            }

            $em->persist($view);
            $em->flush();
        }

        $entity = $em->getRepository(Entity::class)->findOneBy(['fqn' => $entityFqn]);
        if (!$entity) {
            return ApiResponse::error("未找到实体: {$entityFqn}", 404);
        }

        $view->setFormEntity($entity);

        // 视图专属 sectionConfig
        if ($viewName === 'employee_edit_form') {
            $view->setSectionConfig([
                'contentWidth' => 'full-width',
                'columns' => 2,
                'fieldLayout' => 'vertical',
            ]);
        } else {
            $view->setSectionConfig([
                'contentWidth' => 'boxed',
                'width' => 480,
                'unit' => 'px',
            ]);
        }

        $this->resetViewFields($view, $entity, $em, $fields);

        // 视图专属字段配置
        if ($viewName === 'employee_edit_form') {
            $fullWidthFields = ['email', 'mobile', 'company'];
            foreach ($fullWidthFields as $fname) {
                $field = $em->getRepository(ViewField::class)->findOneBy([
                    'view' => $view,
                    'fieldName' => $fname,
                ]);
                if ($field) {
                    $cfg = $field->getConfig() ?? [];
                    $cfg['colSpan'] = 2;
                    $field->setConfig($cfg);
                }
            }
            $companyField = $em->getRepository(ViewField::class)->findOneBy([
                'view' => $view,
                'fieldName' => 'company',
            ]);
            if ($companyField) {
                $cfg = $companyField->getConfig() ?? [];
                $cfg['sectionDivider'] = true;
                $cfg['sectionHeader'] = 'employee.detail.info.org.title';
                $companyField->setConfig($cfg);
                $em->flush();
            }
        }

        $em->flush();

        return ApiResponse::success(json_encode([
            'message' => '视图创建并绑定成功',
            'viewId' => $view->getId(),
            'entityId' => $entity->getId(),
        ]));
    }

    /**
     * 绑定视图到实体
     *
     * 前端可不传 entityId，服务端根据视图名自动匹配对应实体
     * 绑定时会重置 ViewField 配置，从 EntityProperty 重新创建标准字段
     */
    #[Route(
        '/api/admin/platform/view/{id}/bind-entity',
        name: 'api_platform_view_bind_entity',
        methods: ['POST']
    )]
    public function bindEntity(
        string $id,
        Request $request,
        EntityManagerInterface $em
    ): ApiResponse {
        $payload = $request->toArray();
        $entityId = $payload['entityId'] ?? null;

        $view = $em->getRepository(View::class)->find($id);
        if (!$view) {
            return ApiResponse::error('视图不存在', 404);
        }

        if (!$entityId) {
            $map = $this->getViewEntityMap();
            $entry = $map[$view->getName()] ?? null;
            if (!$entry) {
                return ApiResponse::error('无法自动匹配实体，请传入 entityId', 400);
            }
            $entity = $em->getRepository(Entity::class)->findOneBy(['fqn' => $entry['fqn']]);
            if (!$entity) {
                return ApiResponse::error("未找到实体: {$entry['fqn']}", 404);
            }
            if ($entry['template']) {
                $view->setTemplate($entry['template']);
            }
        } else {
            $entity = $em->getRepository(Entity::class)->find($entityId);
            if (!$entity) {
                return ApiResponse::error('实体不存在', 404);
            }
        }

        $view->setFormEntity($entity);

        // 重置 sectionConfig 为 boxed 默认宽度（绑定即刷新布局）
        $view->setSectionConfig([
            'contentWidth' => 'boxed',
            'width' => 480,
            'unit' => 'px',
        ]);

        $fields = $payload['fields'] ?? null;
        $this->resetViewFields($view, $entity, $em, $fields);

        $em->flush();

        return ApiResponse::success(json_encode([
            'message' => '绑定成功（字段已重置为标准配置）',
            'viewId' => $view->getId(),
            'entityId' => $entity->getId(),
        ]));
    }

    /**
     * 解除绑定视图
     */
    #[Route(
        '/api/admin/platform/view/{id}/unbind-entity',
        name: 'api_platform_view_unbind_entity',
        methods: ['POST']
    )]
    public function unbindEntity(
        string $id,
        EntityManagerInterface $em
    ): ApiResponse {
        $view = $em->getRepository(View::class)->find($id);
        if (!$view) {
            return ApiResponse::error('视图不存在', 404);
        }

        $view->setFormEntity(null);
        $view->setTemplate(null);
        $em->flush();

        return ApiResponse::success(json_encode([
            'message' => '已解除绑定',
            'viewId' => $view->getId(),
        ]));
    }

    /**
     * 移动树状节点（视图/文件夹）到目标文件夹下
     */
    #[Route(
        '/api/admin/platform/view/move',
        name: 'api_platform_view_move',
        methods: ['POST']
    )]
    public function moveView(
        Request $request,
        EntityManagerInterface $em
    ): ApiResponse {
        $payload = $request->toArray();
        $nodeId = $payload['nodeId'] ?? null;
        $parentId = $payload['parentId'] ?? null;
        $siblingId = $payload['siblingId'] ?? null;

        if (!$nodeId) {
            return ApiResponse::error('缺少 nodeId', 400);
        }

        $repo = $em->getRepository(View::class);
        $node = $repo->find($nodeId);
        if (!$node) {
            return ApiResponse::error('节点不存在', 404);
        }
        if ($node->getType() === 'root') {
            return ApiResponse::error('根节点不可移动', 400);
        }

        if ($siblingId) {
            $sibling = $repo->find($siblingId);
            if (!$sibling) {
                return ApiResponse::error('目标兄弟节点不存在', 404);
            }
            $repo->persistAsPrevSiblingOf($node, $sibling);
        } elseif ($parentId) {
            $parent = $repo->find($parentId);
            if (!$parent) {
                return ApiResponse::error('目标文件夹不存在', 404);
            }
            if (!in_array($parent->getType(), ['root', 'folder'], true)) {
                return ApiResponse::error('只能移动到文件夹或根节点下', 400);
            }
            $repo->persistAsLastChildOf($node, $parent);
        } else {
            $root = $repo->findOneBy(['type' => 'root']);
            if ($root) {
                $repo->persistAsLastChildOf($node, $root);
            }
        }

        $em->flush();

        return ApiResponse::success(json_encode([
            'message' => '节点已移动',
            'nodeId' => $nodeId,
            'parentId' => $parentId,
            'siblingId' => $siblingId,
        ]));
    }

    #[Route(
        '/api/admin/platform/view/save-general-config',
        name: 'api_platform_view_save_general_config',
        methods: ['POST']
    )]
    public function saveGeneralConfig(
        Request $request,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): ApiResponse {
        $payload = $request->toArray();
        $configDir = $projectDir . '/var/data';
        $configFile = $configDir . '/view_editor_config.json';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($configFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ApiResponse::success(json_encode(['message' => '配置已保存']));
    }

    #[Route(
        '/api/admin/platform/view/get-general-config',
        name: 'api_platform_view_get_general_config',
        methods: ['GET']
    )]
    public function getGeneralConfig(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): ApiResponse {
        $configFile = $projectDir . '/var/data/view_editor_config.json';

        if (!file_exists($configFile)) {
            return ApiResponse::success(json_encode([]));
        }

        $content = file_get_contents($configFile);
        $data = json_decode($content, true) ?: [];

        return ApiResponse::success(json_encode($data));
    }

    /**
     * 删除视图/文件夹（递归删除所有子节点）
     */
    #[Route(
        '/api/admin/platform/view/{id}/delete',
        name: 'api_platform_view_delete',
        methods: ['POST']
    )]
    public function deleteView(
        string $id,
        EntityManagerInterface $em
    ): ApiResponse {
        $view = $em->getRepository(View::class)->find($id);
        if (!$view) {
            return ApiResponse::error('视图不存在', 404);
        }

        if ($view->getType() === 'root') {
            return ApiResponse::error('根节点不可删除', 400);
        }

        try {
            $em->getConnection()->beginTransaction();

            $repo = $em->getRepository(View::class);
            // 获取所有后代节点（由浅到深）
            $descendants = $repo->getChildren($view, false, null, 'ASC', true);
            // 按 level 从深到浅排序
            usort($descendants, fn($a, $b) => $b->getLvl() - $a->getLvl());

            foreach ($descendants as $node) {
                $node->setFormEntity(null);
                $node->setParent(null);
                $em->remove($node);
            }

            $em->flush();
            $em->getConnection()->commit();

            return ApiResponse::success(json_encode([
                'message' => '删除成功',
                'deletedCount' => count($descendants),
            ]));
        } catch (\Exception $e) {
            $em->getConnection()->rollBack();
            return ApiResponse::error('删除失败: ' . $e->getMessage(), 500);
        }
    }
}
