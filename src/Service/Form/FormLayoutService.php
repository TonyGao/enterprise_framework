<?php

namespace App\Service\Form;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use App\Service\AI\Tool\ViewFileToolProvider;
use App\Service\Platform\View\ViewPathResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment as TwigEnvironment;

/**
 * 表单布局应用服务：把 AI 的结构化布局指令（layoutSpec）写回
 * ViewField.config / section_config / theme，并用 FormFieldRenderer 重渲染控件，
 * 保证"漂亮且可用"（字段控件永不经过 AI 手写）。
 */
class FormLayoutService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FormFieldRenderer $renderer,
        private readonly ViewPathResolver $pathResolver,
        private readonly ViewFileToolProvider $fileTool,
        private readonly RequestStack $requestStack,
        private readonly TwigEnvironment $twig,
        #[Autowire(service: 'session.factory')]
        private readonly SessionFactory $sessionFactory,
    ) {}

    /**
     * 表单渲染依赖 CSRF（session）。CLI/队列无 session 时兜底创建一个，
     * 保证 applyLayout 在任意执行上下文可用。
     */
    private function ensureSession(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            $request = new \Symfony\Component\HttpFoundation\Request();
            $this->requestStack->push($request);
        }
        if (!$request->hasSession()) {
            $request->setSession($this->sessionFactory->createSession());
        }
    }

    /**
     * 应用布局指令。
     *
     * @param View   $view    表单视图
     * @param string $version 目标版本（缺省当前激活版本）
     * @param array  $layout  layoutSpec：{theme?, layout?, groups[], fields?, actions?}
     * @throws BadRequestHttpException 校验失败时抛出（AI 工具层会转为错误返回）
     */
    public function applyLayout(View $view, string $version, array $layout): array
    {
        $fields = $this->em->getRepository(ViewField::class)
            ->findBy(['view' => $view], ['sortOrder' => 'ASC']);

        $boundNames = [];
        foreach ($fields as $f) {
            $boundNames[$f->getFieldName()] = $f;
        }

        // 1. 校验：groups 覆盖的字段集合必须与绑定字段集合完全一致
        $layoutFields = [];
        foreach ($layout['groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $name) {
                $layoutFields[] = $name;
            }
        }
        if (count($layoutFields) !== count($boundNames)) {
            throw new BadRequestHttpException(
                '布局字段集合与视图绑定字段不一致：绑定 ' . count($boundNames) . ' 个，布局声明 ' . count($layoutFields) . ' 个。'
            );
        }
        foreach ($layoutFields as $name) {
            if (!isset($boundNames[$name])) {
                throw new BadRequestHttpException("布局包含未绑定字段: $name");
            }
        }

        // 2. 写字段：分组顺序 → sortOrder；分组头/分隔线/列宽 → config
        $fieldOverrides = $layout['fields'] ?? [];
        $hasTheme = !empty($layout['theme']) && is_array($layout['theme']);
        $sortOrder = 0;
        $seen = [];
        foreach ($layout['groups'] as $gi => $group) {
            $isFirstGroup = $gi === 0;
            $groupTitle = $group['title'] ?? '';
            $groupColSpan = $group['colSpan'] ?? 1;
            foreach ($group['fields'] as $fi => $name) {
                $field = $boundNames[$name];
                if (isset($seen[$name])) {
                    throw new BadRequestHttpException("字段重复出现在布局中: $name");
                }
                $seen[$name] = true;

                $field->setSortOrder($sortOrder++);
                $config = $field->getConfig() ?? [];

                if ($fi === 0) {
                    if ($groupTitle !== '') {
                        $config['sectionHeader'] = $groupTitle;
                    } else {
                        unset($config['sectionHeader']);
                    }
                    // 非首组的首字段前加分隔线
                    if (!$isFirstGroup) {
                        $config['sectionDivider'] = true;
                    } else {
                        unset($config['sectionDivider']);
                    }
                    $config['colSpan'] = $groupColSpan;
                } else {
                    // 组内非首字段不重复分组头
                    unset($config['sectionHeader']);
                    unset($config['sectionDivider']);
                }

                // 字段级覆盖 / Field-level overrides
                if (isset($fieldOverrides[$name])) {
                    $ov = $fieldOverrides[$name];
                    if (array_key_exists('label', $ov) && $ov['label'] !== null) {
                        $field->setFieldLabel((string) $ov['label']);
                    }
                    foreach (['placeholder', 'regularBg', 'requiredBg', 'height', 'rounded', 'colSpan', 'choices'] as $k) {
                        if (array_key_exists($k, $ov) && $ov[$k] !== null) {
                            if ($k === 'height') {
                                $config['height'] = (int) $ov[$k];
                            } elseif ($k === 'rounded' || $k === 'colSpan') {
                                $config[$k] = (int) $ov[$k];
                            } elseif ($k === 'choices') {
                                $config['choices'] = is_array($ov[$k]) ? $ov[$k] : [];
                            } else {
                                $config[$k] = (string) $ov[$k];
                            }
                        }
                    }
                    // 底色：仅在显式覆盖时保留；应用主题时未显式指定的字段清除底色，让主题默认色整体生效
                    if ($hasTheme) {
                        if (!array_key_exists('regularBg', $ov) || $ov['regularBg'] === null) {
                            unset($config['regularBg']);
                        }
                        if (!array_key_exists('requiredBg', $ov) || $ov['requiredBg'] === null) {
                            unset($config['requiredBg']);
                        }
                    }
                    if (array_key_exists('required', $ov) && $ov['required'] !== null) {
                        $config['required'] = (bool) $ov['required'];
                    }
                } elseif ($hasTheme) {
                    // 应用主题时：清除未显式覆盖字段的底色，让主题默认色整体生效
                    unset($config['regularBg'], $config['requiredBg']);
                }

                $field->setConfig($config);
                $this->em->persist($field);
            }
        }
        // 防御：布局未覆盖的绑定字段（校验已保证不会发生，但保险起见清空其分组头）
        foreach ($boundNames as $name => $field) {
            if (!isset($seen[$name])) {
                $config = $field->getConfig() ?? [];
                unset($config['sectionHeader'], $config['sectionDivider']);
                $field->setConfig($config);
                $this->em->persist($field);
            }
        }

        // 3. 写 section_config（布局 + 主题 + 形态）
        $sectionConfig = $view->getSectionConfig() ?? [];
        $l = $layout['layout'] ?? [];
        foreach (['columns' => 1, 'fieldLayout' => 'horizontal', 'contentWidth' => 'boxed', 'unit' => 'px', 'gap' => 24, 'labelWidth' => 8, 'render_mode' => 'page'] as $k => $default) {
            $sectionConfig[$k] = $l[$k] ?? $sectionConfig[$k] ?? $default;
        }
        if (isset($l['width'])) {
            $sectionConfig['width'] = (int) $l['width'];
        }
        if (isset($layout['theme']) && is_array($layout['theme'])) {
            $sectionConfig['theme'] = $layout['theme'];
        }
        $view->setSectionConfig($sectionConfig);
        $this->em->persist($view);
        $this->em->flush();

        // 4. 重渲染并写 design.twig
        $designHtml = $this->renderDesignHtml($view, $version, $sectionConfig);

        $designFile = $this->pathResolver->designFile($view, $version);
        if (!$designFile) {
            throw new BadRequestHttpException('视图无设计文件路径');
        }
        $dir = dirname($designFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (file_put_contents($designFile, $designHtml) === false) {
            throw new BadRequestHttpException('写入设计文件失败');
        }

        // 5. 生成可执行模板（与保存管线一致）
        $this->fileTool->renderHtml((string) $view->getId());

        return [
            'version' => $version,
            'fields' => array_values(array_map(fn (ViewField $f) => [
                'name' => $f->getFieldName(),
                'sortOrder' => $f->getSortOrder(),
                'config' => $f->getConfig(),
            ], $fields)),
            'sectionConfig' => $sectionConfig,
        ];
    }

    /**
     * 生成编辑器 canvas 骨架 + 表单字段区（editor_mode 渲染）。
     */
    /**
     * 渲染表单片段（fragment 形态 / view_form 用）：FormFieldRenderer 生产模式输出 <form>。
     */
    public function renderFragment(View $view, object $data, array $options = []): string
    {
        $this->ensureSession();

        $sc = $view->getSectionConfig() ?? [];
        $width = $options['width'] ?? $sc['width'] ?? 800;
        $formAttr = ['style' => 'width: ' . (int) $width . 'px'];
        if (!empty($options['attr']) && is_array($options['attr'])) {
            $formAttr = array_merge($formAttr, $options['attr']);
        }

        $generalConfig = $sc['theme'] ?? [];

        // 自定义 Twig 表单设计优先：直接用 AI 设计渲染（含 form + 实时 CSRF），控件为框架标准控件
        $built = $this->renderer->build($view, $data, $generalConfig);
        $custom = $this->renderCustomDesign($view, $built['formView'], $data);
        if ($custom !== null) {
            return $custom;
        }

        return $this->renderer->render($view, $data, false, $formAttr, $generalConfig)['html'];
    }

    /**
     * 渲染"自定义 Twig 表单设计"：读设计文件（.section-content 内部），若它是 Symfony 表单
     * Twig 模板（含 form_start/form_widget/form_end 等），则用传入的 FormView + 实体渲染成 HTML。
     * 非自定义设计（传统 ef-form 渲染/纯静态页）返回 null，调用方回退原渲染。
     */
    public function renderCustomDesign(View $view, FormView $formView, object $entity): ?string
    {
        try {
            $version = $this->pathResolver->currentVersion($view);
            $designFile = $this->pathResolver->designFile($view, $version);
            if (!$designFile || !file_exists($designFile)) {
                return null;
            }

            $inner = $this->extractSectionContentInnerHtml((string) file_get_contents($designFile));
            if (trim($inner) === '') {
                return null;
            }

            // 仅当设计含 Symfony 表单 Twig 语法时才走自定义渲染
            if (!preg_match('/\{(form_start|form_end|form_rest|form_widget|form_label|form_errors|form_row)\}|\{\{\s*(form\b|form_)|form_start\(|form_end\(|form_widget\(|form_label\(|form_errors\(/', $inner)) {
                return null;
            }

            $html = $this->renderDesignFragment($inner, $formView, $entity);

            // 应用 section_config 的 boxed 宽度（与 FormFieldRenderer::render 的 formAttr 逻辑一致）/
            // apply boxed width from section_config (consistent with FormFieldRenderer::render formAttr logic)
            $sc = $view->getSectionConfig() ?? [];
            if (($sc['contentWidth'] ?? null) === 'boxed' && !empty($sc['width'])) {
                $unit = $sc['unit'] ?? 'px';
                $html = '<div class="ef-view-boxed" style="width:' . ((float) $sc['width']) . $unit . ';max-width:100%;margin:0 auto;box-sizing:border-box;">' . $html . '</div>';
            }

            return $html;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 渲染自定义表单设计片段：控件用框架标准控件（ef-form 主题，保留前端交互功能），
     * AI 只负责布局外壳美化。直接渲染设计 Twig（form_start/form_widget 等由全局 ef-form 主题输出）。
     *
     * @param FormView   $formView   Symfony FormView
     * @param object     $entity     实体数据
     * @param array|null $fields     ViewField 记录（用于编辑器注入 data-field-type）
     * @param bool       $editorMode 编辑器画布模式：注入透明字段标记（editor-field-row/ef-form-label/ef-form-widget），
     *                               使右侧"组件"面板可点击调整，同时不破坏 AI 布局
     */
    public function renderDesignFragment(string $inner, FormView $formView, object $entity, ?array $fields = null, bool $editorMode = false): string
    {
        $html = $this->twig->createTemplate($inner)->render([
            'form' => $formView,
            'entity' => $entity,
        ]);

        if ($editorMode) {
            $html = $this->injectEditorFieldMarkers($html, $formView, $fields ?? []);
        }

        return $html;
    }

    /**
     * 给编辑器画布注入透明的字段编辑标记：每个表单控件外面包一层
     * .editor-field-row > .ef-form-label(data-field-name) + .ef-form-widget(data-field-type)，
     * 全部 display:contents（不改变布局）。这样点击控件时右侧"组件"面板能识别字段并调整。
     */
    private function injectEditorFieldMarkers(string $html, FormView $formView, array $fields): string
    {
        $fieldTypes = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getFieldName') && method_exists($field, 'getFieldType')) {
                $fieldTypes[(string) $field->getFieldName()] = (string) $field->getFieldType();
            }
        }

        $useErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_use_internal_errors($useErrors);
        $xp = new \DOMXPath($dom);

        $containerQuery = "//*[contains(concat(' ', normalize-space(@class), ' '), ' ef-input-wrapper ')
            or contains(concat(' ', normalize-space(@class), ' '), ' ef-switch ')
            or contains(concat(' ', normalize-space(@class), ' '), ' ef-select-view-single ')
            or contains(concat(' ', normalize-space(@class), ' '), ' ef-textarea-wrapper ')]";
        $containers = $xp->query($containerQuery);
        $done = [];

        foreach ($containers as $container) {
            $fieldName = null;
            $nameEls = $xp->query(".//*[@name and starts-with(@name, 'form[')]", $container);
            if ($nameEls->length) {
                $fieldName = preg_replace('/^form\[(.*)\]$/', '$1', $nameEls->item(0)->getAttribute('name'));
            }
            if (!$fieldName) {
                $idEls = $xp->query(".//*[starts-with(@id, 'form_')]", $container);
                if ($idEls->length) {
                    $fieldName = substr($idEls->item(0)->getAttribute('id'), 5);
                }
            }
            if (!$fieldName || $fieldName === '_token' || isset($done[$fieldName])) {
                continue;
            }

            $vars = $formView->children[$fieldName]->vars ?? [];
            $label = $vars['label'] ?? $fieldName;
            $required = !empty($vars['required']);
            $attr = $vars['attr'] ?? [];
            $placeholder = $attr['placeholder'] ?? '';
            $height = $attr['height'] ?? ($attr['rows'] ?? 36);
            $rounded = !empty($attr['rounded']);
            $regularBg = $attr['data-regular-bg'] ?? '';
            $requiredBg = $attr['data-required-bg'] ?? '';
            $fieldType = $fieldTypes[$fieldName] ?? '';

            // 透明包装结构
            $row = $dom->createElement('div');
            $row->setAttribute('class', 'editor-field-row');
            $row->setAttribute('style', 'display:contents');

            $labelWrap = $dom->createElement('div');
            $labelWrap->setAttribute('class', 'ef-component ef-form-label');
            $labelWrap->setAttribute('style', 'display:contents');
            $labelWrap->setAttribute('data-field-name', $fieldName);
            $labelWrap->setAttribute('data-col-span', '1');
            $labelWrap->setAttribute('data-label-col', '8');
            $labelWrap->setAttribute('data-height', (string) $height);
            $labelWrap->setAttribute('data-rounded', $rounded ? 'true' : 'false');
            $labelWrap->setAttribute('data-placeholder', $placeholder);
            $labelWrap->setAttribute('data-required', $required ? 'true' : 'false');
            if ($regularBg !== '') {
                $labelWrap->setAttribute('data-regular-bg', $regularBg);
            }
            if ($requiredBg !== '') {
                $labelWrap->setAttribute('data-required-bg', $requiredBg);
            }
            $innerLabel = $dom->createElement('label');
            $innerLabel->setAttribute('class', 'ef-form-item-label');
            $innerLabel->setAttribute('style', 'display:none');
            $innerLabel->textContent = (string) $label;
            $labelWrap->appendChild($innerLabel);

            $widgetWrap = $dom->createElement('div');
            $widgetWrap->setAttribute('class', 'ef-component ef-form-widget');
            $widgetWrap->setAttribute('style', 'display:contents');
            $widgetWrap->setAttribute('data-field-name', $fieldName);
            if ($fieldType !== '') {
                $widgetWrap->setAttribute('data-field-type', $fieldType);
            }

            $origParent = $container->parentNode;
            $origParent->insertBefore($widgetWrap, $container);
            $widgetWrap->appendChild($container);
            $row->appendChild($labelWrap);
            $origParent->insertBefore($row, $widgetWrap);
            $row->appendChild($widgetWrap);

            $done[$fieldName] = true;
        }

        $body = $xp->query('//body')->item(0);
        if (!$body) {
            return $html;
        }
        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out === '' ? $html : $out;
    }

    /**
     * 渲染整页（page 形态）：读设计文件 → 提取页面壳 → 替换 data-view-form 占位为表单片段。
     */
    public function renderPage(View $view, object $data, array $options = []): string
    {
        $version = $this->pathResolver->currentVersion($view);
        $designFile = $this->pathResolver->designFile($view, $version);
        if (!$designFile || !file_exists($designFile)) {
            return '<div style="padding:24px;color:#94a3b8;">该视图暂无页面内容</div>';
        }

        $design = (string) file_get_contents($designFile);

        // 自定义 Twig 表单设计：设计本身就是完整页面，直接渲染（含 form + CSRF），控件为框架标准控件
        $this->ensureSession();
        $sc = $view->getSectionConfig() ?? [];
        $built = $this->renderer->build($view, $data, $sc['theme'] ?? []);
        $custom = $this->renderCustomDesign($view, $built['formView'], $data);
        if ($custom !== null) {
            return $custom;
        }

        $shell = $this->extractSectionContentInnerHtml($design);
        if ($shell === '') {
            $shell = $design;
        }

        // 页面壳内含表单占位：替换为生产态表单（含 <form> + 实时 CSRF）
        if (str_contains($shell, 'data-view-form')) {
            $fragment = $this->renderFragment($view, $data, $options);
            return (string) preg_replace('/<div\s+data-view-form="[^"]*"[^>]*><\/div>/', $fragment, $shell);
        }

        // 无占位：若设计本质是表单字段区，则渲染生产态完整表单（form_start/CSRF 实时生成）
        $isFormContent = str_contains($shell, 'data-field-name')
            || str_contains($shell, 'ef-form-label')
            || str_contains($shell, 'editor-field-row');
        if ($isFormContent) {
            return $this->renderFragment($view, $data, $options);
        }

        // 纯静态页面壳（无表单）
        return $shell;
    }

    private function extractSectionContentInnerHtml(string $html): string
    {
        $useErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_use_internal_errors($useErrors);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(@class, 'section-content')]");
        if ($nodes && $nodes->length > 0) {
            $inner = '';
            foreach ($nodes->item(0)->childNodes as $child) {
                $inner .= $dom->saveHTML($child);
            }
            return $inner;
        }
        return $html;
    }

    private function renderDesignHtml(View $view, string $version, array $sectionConfig): string
    {
        $this->ensureSession();

        $fqn = $view->getFormEntity()?->getFqn();
        $data = $fqn && class_exists($fqn) ? new $fqn() : (object) [];

        $width = (int) ($sectionConfig['width'] ?? 480);
        $theme = $sectionConfig['theme'] ?? [];
        $generalConfig = $theme; // M3：主题作为 generalConfig 兜底（requiredBg 等）

        $editorHtml = $this->renderer->render(
            $view,
            $data,
            true,
            ['style' => 'width:' . $width . 'px'],
            $generalConfig
        )['html'];

        $randomId = 'sec' . substr(bin2hex(random_bytes(4)), 0, 8);

        return '<div class="add-section-button" id="add-section-button" style="display:flex;justify-content:center;align-items:center;"><i class="fa fa-plus"></i></div>'
            . '<div class="section active" id="' . $randomId . '" data-section-type="default">'
            . '<div class="section-header" style="display:none;">'
            . '<button class="btn-add"><i class="fa fa-plus" style="font-size:1em;"></i></button>'
            . '<button class="btn-layout"><i class="fa fa-grip" style="font-size:1em;"></i></button>'
            . '<button class="btn-close"><i class="fa fa-times" style="font-size:1em;"></i></button>'
            . '</div>'
            . '<div class="section-content ui-droppable" style="width:' . $width . 'px;">'
            . $editorHtml
            . '</div>'
            . '</div>';
    }
}
