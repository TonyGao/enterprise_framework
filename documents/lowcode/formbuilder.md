# 统一视图设计器 — 页面 & 表单融合架构

## 1. 核心理念

**一个设计器，两种模式。** 不再区分"视图设计器"和"表单设计器"两个入口/编辑器/代码路径，而是将 View Editor 设计为通用低代码编辑器，通过 `View.type` 切换模式：

```
View Editor (统一)
  ├── type=view  → 页面设计模式 (HTML 布局 + 组件)
  └── type=form  → 表单设计模式 (字段布局 + 校验 + 条件)
```

| 层面 | type=view | type=form |
|------|-----------|-----------|
| View 树 | 页面根/文件夹/视图 | 表单根/文件夹/模板 |
| 基模板 | 无 (独立 HTML, layout extend) | `ef_form_base.html.twig` |
| 组件库 | 布局/表格/文本/图片/按钮/富文本/视频/地图 | 文本字段/长文本/数字/开关/日期/选择/部门/用户/提交/分区 |
| 属性面板 | CSS 样式 (字体/颜色/边框/背景/对齐) | 字段配置 (标签/校验/条件/列宽) |
| DomManipulator | `processDynamicFields` + `processTableCells` | `processFormFields` + `processVisibility` |
| 运行时 | 渲染 `.html.twig` 直接输出 | 先 `FormFieldRenderer` 构建 Form → 再渲染 `.html.twig` |
| 存储 (文件) | `templates/views/{path}/{name}/{version}/` | 同一目录结构 |
| 版本控制 | 目录版本 `1_0/` `2_0/` `current →` | 相同机制 |

### 1.1 为什么融合？

- **编辑器 UI 完全复用** — 画布、拖拽、选区、属性面板、工具栏、组件面板、结构树，零重复
- **后端管道复用** — 保存/发布/版本/权限/历史，一条代码路径
- **用户视角统一** — 一个入口管理所有"视图"(页面+表单)，降低学习成本
- **区块共享** — 表单字段块(department/user/date)未来也可用于页面显示组件

---

## 2. 数据模型

### 2.1 View 实体 (现有一张表)

```
platform_view (Gedmo NestedTree)
├── id (UUID)
├── view_name (string)
├── view_label (string)
├── type (string: root | folder | view | form)  ← form 是新增类型
├── path (string: 相对文件路径)
├── entity_id (UUID, nullable)  ← 表单模式下关联的 Entity
├── lft, rgt, lvl (Gedmo Tree)
├── parent_id → self
└── tree_root → self
```

`entity_id` 字段需要在 `platform_view` 表中新增（ManyToOne → `platform_entity`），仅 `type='form'` 时有值。

### 2.2 ViewField 实体 (现有)

```
platform_view_field
├── id (UUID)
├── view_id → platform_view
├── entity_id → platform_entity
├── field_name (string)
├── field_label (string)
├── field_type (string: string|boolean|integer|text|date|department|user|entity)
├── label_inserted (bool)
├── value_inserted (bool)
├── label_position (text, nullable)
├── value_position (text, nullable)
├── sort_order (int)
└── config (jsonb, nullable)  ← 新增: 存储表单字段的个性配置
```

`config` 字段存储表单模式下字段的额外配置，如：
```json
{
  "required": true,
  "readonly": false,
  "placeholder": "请输入姓名",
  "helpText": "请填写真实姓名",
  "columnWidth": 12,
  "validation": { "minLength": 2, "maxLength": 50, "regex": null, "email": false },
  "visibility": [
    { "triggerField": "type", "operator": "equals", "value": "manager" }
  ],
  "widgetOptions": { "rows": 3, "autosize": true }
}
```

对于 `type='view'` 的 View，ViewField 的用途不变（记录插入到视图中的字段标签和值位置）。对于 `type='form'` 的 View，ViewField 记录所有引用的表单字段及其配置。

### 2.3 文件存储 (不变)

所有视图（页面 + 表单）统一存储在 `templates/views/` 下：

```
templates/views/
  └── {folderPath}/
      └── {viewName}/
          ├── 1_0/
          │   ├── {viewName}.design.twig   ← 编辑器文件 (含编辑器 DOM)
          │   └── {viewName}.html.twig     ← 运行时文件 (干净的模板)
          ├── 2_0/
          │   ├── {viewName}.design.twig
          │   └── {viewName}.html.twig
          └── current → 1_0/
```

- `type='view'`: `.html.twig` 是独立 HTML，可直接渲染
- `type='form'`: `.html.twig` 以 `ef_form_base.html.twig` 为基模板，`form_body` block 中包含 `{{ form_row() }}` 调用

---

## 3. ViewField.config 字段配置

当 `View.type='form'` 时，ViewField.config 存储每个字段在表单中的所有配置。编辑器属性面板直接读写此配置，DomManipulator 在保存时将其转换为 Twig form_row() 参数。

### 3.1 配置字段

```json
{
  "required": true,
  "readonly": false,
  "hidden": false,
  "placeholder": "请输入",
  "helpText": "帮助说明",
  "columnWidth": 24,
  "layoutIndex": null,
  "validation": {
    "minLength": null,
    "maxLength": null,
    "regex": null,
    "email": false,
    "min": null,
    "max": null
  },
  "visibility": {
    "logic": "and",
    "rules": [
      { "triggerField": "department", "operator": "equals", "value": "IT" }
    ]
  },
  "widgetOptions": {}
}
```

### 3.2 编辑器 ↔ .design.twig 的 config 传递

编辑器中的字段容器附带 `data-field-config` 属性保存 JSON config，DomManipulator 读取后转换为 `form_row()` 参数并写入 `.html.twig`，同时将 config 写入 ViewField：

```
编辑器操作 → .design.twig (data-field-config='{...}')
                   │
                   ▼
            DomManipulator.saveForm()
                   │
                   ├─ 读取 data-field-config → 生成 form_row() 参数
                   ├─ 写入 .html.twig (Twig 代码)
                   └─ 写入 ViewField.config (数据库)
```

---

## 4. 模式切换机制

### 4.1 入口

用户通过统一的 View 管理界面管理所有视图。创建新视图时选择类型（页面/表单）：

```
/admin/platform/view/editor/{id}
  └─ ViewEditorController::editor()
       ├─ 读取 View
       ├─ 检测 View.type
       │    ├─ 'view' → 加载页面组件库 + CSS 属性面板
       │    └─ 'form' → 加载表单组件库 + 字段属性面板
       └─ 渲染同一套 view_editor_layout.html.twig
```

### 4.2 前端初始化

```javascript
// view_editor_core.js (统一入口)
$(function () {
    const viewType = $('meta[name="view-type"]').attr('content');

    // 画布初始化 (通用)
    initCanvas();

    if (viewType === 'form') {
        // 表单模式
        loadFormComponents();
        loadFormPropertyPanels();
        initFormFieldDroppable();
        bindFieldPreviewGeneration();
    } else {
        // 页面模式
        loadViewComponents();
        loadViewPropertyPanels();
        initSectionDroppable();
    }
});
```

### 4.3 视图中判断模式

Twig 模板中根据 View.type 切换：

```twig
{# templates/admin/platform/view/editor.html.twig #}
{% if formView.type == 'form' %}
  <meta name="view-type" content="form">
  <meta name="form-entity-id" content="{{ entity.id }}">
{% else %}
  <meta name="view-type" content="view">
{% endif %}
```

### 4.4 属性面板切换

属性面板根据选中元素的类型展示不同的 UI：

```javascript
// 统一属性面板管理器
function showProperties(element) {
    if (viewType === 'form') {
        if (element.hasAttribute('data-field')) {
            showFormFieldProperties(element);    // 字段属性
        } else if (element.hasAttribute('data-section')) {
            showFormSectionProperties(element);  // 分区属性
        }
    } else {
        if (element.classList.contains('section')) {
            showSectionProperties(element);      // 容器属性
        } else {
            showComponentProperties(element);    // 组件 CSS 属性
        }
    }
}
```

---

## 5. ViewField.config 在不同模式下的用途

### 5.1 页面设计模式 (type='view')

ViewField 保持现有用途 — 记录哪些字段的标签和值已插入到视图中：

```
labelInserted  → bool: 标签是否已渲染到 DOM
valueInserted  → bool: 值是否已渲染到 DOM
labelPosition  → string: 插入位置 (DOM 路径)
valuePosition  → string: 插入位置 (DOM 路径)
```

`config` 字段在此模式下为 `null`。

### 5.2 表单设计模式 (type='form')

ViewField 记录字段引用和完整配置：

```
config → JSON: 字段的完整配置 (校验、显隐、列宽等)
sortOrder → int: 字段在表单中的顺序
```

`labelInserted`/`valueInserted`/`*Position` 在此模式下无意义 (`null`)。

### 5.3 保存时的同步逻辑

```php
class ViewEditorApiController
{
    public function save(Request $req): ApiResponse {
        $view = ...;
        $canvasHtml = ...;

        if ($view->getType() === 'form') {
            $this->saveFormView($view, $canvasHtml);
        } else {
            $this->savePageView($view, $canvasHtml);
        }
    }

    private function saveFormView(View $view, string $canvasHtml): void
    {
        $dom->load($canvasHtml);

        // 1. 提取所有 data-field-config
        $fieldConfigs = $dom->extractFieldConfigs();

        // 2. 移除编辑器辅助元素
        $dom->remove('.field-actions');
        $dom->remove('.section-header-buttons');
        $dom->removeClass('.selected', 'selected');

        // 3. 转换 data-field → form_row()
        $dom->processFormFields();

        // 4. 写入 .design.twig (编辑器文件)
        // 5. 写入 .html.twig (运行时文件)
        // 6. 同步 ViewField.config 到数据库
        $this->syncFieldConfigs($view, $fieldConfigs);
    }

    private function savePageView(View $view, string $canvasHtml): void
    {
        $dom->load($canvasHtml);
        $dom->remove('.add-section-button');
        $dom->remove('.section-header');
        $dom->removeClass('.active', 'active');
        $dom->removeClass('.ui-droppable', 'ui-droppable');
        $dom->removeClass('.ef-component-labels', 'ef-component-labels');
        $dom->processTableCells();
        $dom->processDynamicFields();

        // 写入文件
    }
}
```

---

## 6. 表单组件库

表单设计模式下，组件面板展示以下组件：

| 组件 | componentType | icon | 说明 |
|------|---------------|------|------|
| 文本字段 | `form_string` | `fa-solid fa-font` | 短文本输入 |
| 长文本 | `form_text` | `fa-solid fa-paragraph` | 多行文本 |
| 数字 | `form_integer` | `fa-solid fa-hashtag` | 整数输入 |
| 开关 | `form_boolean` | `fa-solid fa-toggle-on` | 开关切换 |
| 日期 | `form_date` | `fa-solid fa-calendar` | 日期选择 |
| 日期时间 | `form_datetime` | `fa-solid fa-clock` | 日期+时间 |
| 选择 | `form_select` | `fa-solid fa-check` | 下拉/单选/多选 |
| 部门 | `form_department` | `fa-solid fa-sitemap` | 部门选择器 |
| 用户 | `form_user` | `fa-solid fa-user` | 用户选择器 |
| 附件 | `form_file` | `fa-solid fa-paperclip` | 文件上传 |
| 图片 | `form_image` | `fa-solid fa-image` | 图片上传 |
| 分区容器 | `form_section` | `fa-solid fa-layer-group` | 字段分组容器 |
| 区块引用 | `form_block_ref` | `fa-solid fa-cubes` | 引用可复用区块 |
| 提交按钮 | `form_submit` | `fa-solid fa-paper-plane` | 表单提交按钮 |
| 重置按钮 | `form_reset` | `fa-solid fa-rotate-left` | 表单重置 |

### 6.1 组件模板

```javascript
// view_editor_components.js — 追加表单组件
const componentTemplates = {
    // ... 现有页面组件 ...

    form_string: {
        label: '文本字段',
        icon: 'fa-solid fa-font',
        category: 'form',
        template: (fieldName, label) => `
            <div class="item-block ef-component"
                 data-field="${fieldName}"
                 data-field-type="string"
                 data-field-config='${defaultConfig('string')}'>
                <div class="ef-component-labels">
                    <span class="component-label">文本</span>
                    <span class="component-drag-handle"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="ef-field-preview">
                    <label>${label}</label>
                    <input type="text" placeholder="请输入" disabled>
                </div>
            </div>`
    },
    form_section: { ... },
    form_block_ref: { ... },
    form_submit: { ... },
    form_reset: { ... },
};
```

### 6.2 组件面板分组

```
┌──────────────────────────┐
│ 组件库                    │
│                          │
│ ┌── 布局 ──────────────┐ │  ← 页面模式专有(表单模式下隐藏)
│ │ 分区/自由/表格/栅格   │ │
│ └──────────────────────┘ │
│                          │
│ ┌── 展示 ──────────────┐ │  ← 页面模式专有(表单模式下隐藏)
│ │ 文本/图片/按钮/视频   │ │
│ └──────────────────────┘ │
│                          │
│ ┌── 表单字段 ──────────┐ │  ← 表单模式添加(页面模式下隐藏)
│ │ 文本/长文本/数字/开关  │ │
│ │ 日期/选择/部门/用户   │ │
│ │ 附件/图片             │ │
│ └──────────────────────┘ │
│                          │
│ ┌── 操作 ──────────────┐ │  ← 表单模式添加
│ │ 提交/重置/分区/区块   │ │
│ └──────────────────────┘ │
└──────────────────────────┘
```

### 6.3 字段数据源面板

表单模式下，组件面板的第二 tab 切换为"数据源"视图——展示当前实体（表单关联的 Entity）的所有可用字段：

```
┌── 组件库 │ 数据源 ──────┐
│                          │
│ 当前实体: 员工(Employee)  │
│                          │
│ ☐ name         [文本]   │  ← 勾选后字段出现在画布或直接拖入画布
│ ☐ department   [部门]   │
│ ☐ position     [岗位]   │
│ ☐ employeeNo   [文本]   │
│ ☐ phone        [文本]   │
│ ☐ email        [文本]   │
│ ☐ hireDate     [日期]   │
│ ☐ status       [选择]   │
│ ☐ avatar       [图片]   │
│ ...                     │
│                          │
│ [+ 从数据源添加所有字段]  │  ← 一键添加所有字段到画布
└──────────────────────────┘
```

---

## 7. 属性面板 — 表单字段版

### 7.1 基本设置选项卡

```
┌── 字段属性 ──────────────────────┐
│                                  │
│ ┌ 基本设置 ────────────────────┐ │
│ │ 标签:      [姓名            ] │ │
│ │ 字段名:    name (只读)        │ │
│ │ 占位文本:  [请输入姓名       ] │ │
│ │ 帮助文字:  [请填写真实姓名    ] │ │
│ │ 默认值:    [                 ] │ │
│ │ 必填 □  只读 □  隐藏 □       │ │
│ │ 列宽: [ 一半(12/24) ▼ ]      │ │
│ └──────────────────────────────┘ │
│                                  │
│ ┌ 校验规则 ────────────────────┐ │
│ │ [+ 添加规则]                  │ │
│ │ 最小长度 ████████████  2      │ │
│ │ 最大长度 ███████████████ 50   │ │
│ │ 正则:    [^[a-zA-Z]+$       ] │ │
│ │ Email □  URL □  数字 □        │ │
│ └──────────────────────────────┘ │
│                                  │
│ ┌ 条件显隐 ────────────────────┐ │
│ │ [+ 添加条件] 逻辑: AND / OR   │ │
│ │ [部门 ▼] [等于 ▼] [IT     ] ✕ │ │
│ └──────────────────────────────┘ │
│                                  │
│ ┌ 控件选项 ────────────────────┐ │
│ │ (根据字段类型动态显示)          │ │
│ │ textarea: 行数 ██ 3           │ │
│ │           自动高度 □           │ │
│ │ select:   选项来源: [自定义 ▼] │ │
│ │           选项: [A,B,C       ] │ │
│ └──────────────────────────────┘ │
└──────────────────────────────────┘
```

### 7.2 分区属性

```
┌── 分区属性 ─────────────────────┐
│                                 │
│ ┌ 分区设置 ───────────────────┐ │
│ │ 标题: [基本信息           ]  │ │
│ │ 列数: [ 2 ▼ ]               │ │
│ │ 可折叠: ☑                   │ │
│ │ 默认展开: ☑                 │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

### 7.3 表单级属性

```
┌── 表单属性 ─────────────────────┐
│                                 │
│ ┌ 表单设置 ───────────────────┐ │
│ │ 布局: [垂直 ▼]               │ │
│ │ 标签位置: [上方 ▼]           │ │
│ │ 提交按钮文本: [提交]         │ │
│ │ 显示重置按钮: ☑              │ │
│ │ CSS 类名: [ef-form-custom]   │ │
│ └─────────────────────────────┘ │
│                                 │
│ ┌ 提交行为 ───────────────────┐ │
│ │ 成功跳转: [                 ] │ │
│ │ 成功消息: [保存成功        ]  │ │
│ │ 确认提交: ☐                  │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

---

## 8. DomManipulator 统一处理

### 8.1 通用管道

```php
class DomManipulator
{
    public function process(View $view): void
    {
        if ($view->getType() === 'form') {
            $this->remove('.field-actions');
            $this->remove('.section-header-buttons');
            $this->removeClass('.selected', 'selected');
            $this->processFormFields();
            $this->processVisibility();
        } else {
            $this->remove('.add-section-button');
            $this->remove('.section-header');
            $this->removeClass('.section.active', 'active');
            $this->removeClass('.ui-droppable', 'ui-droppable');
            $this->removeClass('.ef-component-labels', 'ef-component-labels');
            $this->processTableCells();
            $this->processDynamicFields();
        }

        // 通用清理
        $this->removeEmptyAttributes();
        $this->normalizeWhitespace();
    }
}
```

### 8.2 processFormFields()

```php
public function processFormFields(): void
{
    $xpath = new \DOMXPath($this->dom);

    foreach ($xpath->query('//*[@data-field]') as $node) {
        $fieldName = $node->getAttribute('data-field');
        $configJson = $node->getAttribute('data-field-config');
        $config = $configJson ? json_decode($configJson, true) : [];

        $args = $this->buildFormRowArgs($fieldName, $config);
        $twigCall = "{{ form_row(form.{$fieldName}, {$args}) }}";

        $textNode = $this->dom->createTextNode($twigCall);
        $node->parentNode->replaceChild($textNode, $node);
    }
}

private function buildFormRowArgs(string $fieldName, array $config): string
{
    $parts = [];

    if (!empty($config['required'])) {
        $parts[] = 'required: true';
    }
    if (!empty($config['placeholder'])) {
        $parts[] = "attr: {placeholder: '" . addslashes($config['placeholder']) . "'}";
    }
    if (!empty($config['helpText'])) {
        $parts[] = "help: '" . addslashes($config['helpText']) . "'";
    }
    if (!empty($config['readonly'])) {
        $parts[] = 'attr: {readonly: true}';
    }
    if (!empty($config['label'])) {
        $parts[] = "label: '" . addslashes($config['label']) . "'";
    }

    return '{' . implode(', ', $parts) . '}';
}
```

### 8.3 processVisibility()

```php
public function processVisibility(): void
{
    $xpath = new \DOMXPath($this->dom);

    foreach ($xpath->query('//*[@data-visibility]') as $node) {
        $rules = json_decode($node->getAttribute('data-visibility'), true);
        $node->removeAttribute('data-visibility');

        if (empty($rules)) continue;

        $condition = $this->buildTwigCondition($rules);
        $ifNode = $this->dom->createTextNode("{% if {$condition} %}");
        $endifNode = $this->dom->createTextNode("{% endif %}");

        $node->parentNode->insertBefore($ifNode, $node);
        $node->parentNode->insertBefore($endifNode, $node->nextSibling);
    }
}

private function buildTwigCondition(array $rules): string
{
    $parts = [];
    foreach ($rules as $rule) {
        $field = "form.{$rule['triggerField']}.vars.value";
        $parts[] = match ($rule['operator']) {
            'equals'    => "{$field} == '{$rule['value']}'",
            'not_equals' => "{$field} != '{$rule['value']}'",
            'not_empty'  => "{$field} is not empty",
            'empty'      => "{$field} is empty",
            'contains'   => "{$field} contains '{$rule['value']}'",
            default     => "{$field} == '{$rule['value']}'",
        };
    }
    return implode(' and ', $parts);
}
```

---

## 9. 运行时表单渲染

### 9.1 FormFieldRenderer (已有服务)

```php
class FormFieldRenderer
{
    public function buildForm(FormBuilderInterface $builder, View $formView): void
    {
        $entity = $formView->getEntity();  // platform_view.entity_id

        $viewFields = $this->em
            ->getRepository(ViewField::class)
            ->findBy(['view' => $formView], ['sortOrder' => 'ASC']);

        foreach ($viewFields as $vf) {
            $property = $this->em
                ->getRepository(EntityProperty::class)
                ->findOneBy([
                    'entity' => $entity,
                    'fieldName' => $vf->getFieldName(),
                ]);

            if (!$property) continue;

            $formType = Str::convertFormType($property->getType());
            $config = $vf->getConfig() ?: [];

            $options = [
                'label' => $config['label'] ?? $vf->getFieldLabel() ?: $property->getComment(),
                'required' => $config['required'] ?? !$property->isNullable(),
                'attr' => [],
            ];

            if (!empty($config['placeholder'])) {
                $options['attr']['placeholder'] = $config['placeholder'];
            }
            if (!empty($config['helpText'])) {
                $options['help'] = $config['helpText'];
            }
            if (!empty($config['readonly'])) {
                $options['attr']['readonly'] = true;
            }

            if ($property->getType() === 'user') {
                $options['block_prefix'] = 'user';
                $options['attr']['data-user-field'] = 'true';
            }

            $builder->add($vf->getFieldName(), $formType, $options);
        }
    }
}
```

### 9.2 控制器使用

```php
// src/Controller/Platform/FormRenderController.php
public function renderForm(string $formCode, $entityId): Response
{
    $formView = $this->em->getRepository(View::class)->findOneBy([
        'type' => 'form',
        'name' => $formCode,
    ]);

    $entityData = $this->em->find($formView->getEntity()->getClassName(), $entityId);

    $form = $this->createFormBuilder($entityData);
    $this->formFieldRenderer->buildForm($form, $formView);
    $symfonyForm = $form->getForm();

    $symfonyForm->handleRequest($this->request);
    if ($symfonyForm->isSubmitted() && $symfonyForm->isValid()) {
        $this->em->flush();
        return $this->redirect(...);
    }

    return $this->render('@views/' . $formView->getPath() . '/' . $formView->getName() . '.html.twig', [
        'form' => $symfonyForm->createView(),
        'entity' => $entityData,
    ]);
}
```

---

## 10. 区块系统

### 10.1 区块定义

区块是可复用的 Twig block，存储在 `templates/views/blocks/` 下：

```
templates/views/blocks/
  ├── employee_base_info.form.twig
  ├── employee_contact.form.twig
  ├── address.form.twig
  ├── section.form.twig       (通用分区容器 embed)
  └── ...
```

`.form.twig` 后缀用于区分表单区块和页面区块（如 header/footer 片段）。

### 10.2 区块注册

编辑器通过扫描 `templates/views/blocks/` 目录自动发现可用的区块文件，并在组件面板的"区块"分组中展示。

区块文件格式示例：

```twig
{# templates/views/blocks/employee_base_info.form.twig #}
{% block employee_base_info %}
<div class="ef-form-section" data-section="base_info">
  <h3 class="ef-form-section-title">基本信息</h3>
  <div class="ef-row" style="--ef-grid-columns: 2">
    <div class="ef-col-12">{{ form_row(form.name) }}</div>
    <div class="ef-col-12">{{ form_row(form.department) }}</div>
    <div class="ef-col-12">{{ form_row(form.position) }}</div>
    <div class="ef-col-12">{{ form_row(form.employeeNo) }}</div>
  </div>
</div>
{% endblock %}
```

### 10.3 区块版本

区块的版本管理与表单模板解耦。区块被编辑后，所有引用它的表单模板无需变动（Twig 动态引用）。区块文件自身的版本通过 `.versions/` 子目录管理（可选）。

### 10.4 区块在编辑器中的表示

区块引用在编辑器画布上显示为 **封闭的区块组件**，不可编辑内部字段，但可以整体拖拽、删除、复制。双击可展开查看区块内部结构（只读）。

```
┌── 区块: 员工基本信息 ──────────┐
│ │ 姓名   部门   岗位   工号    │  ← 只读预览
│ └──────────────────────────────┘
│ [展开查看] [打开区块编辑] [替换] ✕
└──────────────────────────────────
```

---

## 11. Admin 路由

| 路由 | 方法 | 用途 |
|------|------|------|
| `/admin/platform/view/index` | GET | 统一视图管理页面（类型筛选：全部/页面/表单） |
| `/admin/platform/view/editor/{id}` | GET | 统一编辑器 (根据 View.type 切换模式) |
| `/admin/platform/view/save` | POST | 统一保存 API (内部根据 type 走不同处理) |
| `/admin/platform/view/publish/{id}` | POST | 发布新版本 |
| `/admin/platform/view/versions/{id}` | GET | 版本历史 |
| `/admin/platform/view/preview/{id}` | GET | 预览 |
| `/admin/platform/view/add` | POST | 创建视图 (参数包含 type=view/form) |
| `/admin/platform/view/folders` | GET | 文件夹列表 |
| `/admin/platform/block/list` | GET | 列出可用区块 |
| `/admin/platform/block/save` | POST | 保存区块 |

所有路由均在现有 `ViewEditorController` 和 `ViewEditorApiController` 中，不增加新的 Controller 类。

---

## 12. 关键设计权衡

### 12.1 ViewField.config (DB) vs 纯文件解析

| 方案 | 优点 | 缺点 |
|------|------|------|
| ViewField.config (选定) | 编辑器可直接读取字段列表和配置，无需解析文件；可做字段使用分析 | 写文件时需同步 DB |
| 纯文件解析 | 单一数据源 | 每次加载编辑器需解析文件提取字段信息 |

### 12.2 entity_id 在 View 上 vs 从 ViewField 推导

| 方案 | 优点 | 缺点 |
|------|------|------|
| View.entity_id (选定) | 可直接从 View 得知关联实体，FormFieldRenderer 直接使用 | 新增字段，需要 migration |
| 从 ViewField 推导 | 无需新增字段 | 空表单时无 ViewField 则无法确定实体；表单重关联实体困难 |

### 12.3 统一 Editor API 保存 vs 分两个 API

| 方案 | 优点 | 缺点 |
|------|------|------|
| 统一 API + type 分支 (选定) | 前端只用调一个接口，路径一致 | 后端代码需要分支判断 |
| 分两个 API (saveView / saveForm) | 职责清晰 | 前端需要根据 type 判断调哪个接口 |

### 12.4 `data-field-config` 属性 vs 纯 data-* 散列

| 方案 | 优点 | 缺点 |
|------|------|------|
| JSON config 属性 (选定) | 单一属性传递完整配置，DomManipulator 一次读取 | 属性值较长，读写需 JSON.parse/stringify |
| 散列 data-* 属性 | 每个配置项独立属性，易读写 | 属性项多时 DOM 膨胀，新增配置需改 DomManipulator |

---

## 13. 实现阶段

### Phase 1 — 基础扩展
- `platform_view` 表新增 `entity_id` 字段（ManyToOne → `platform_entity`）
- `platform_view_field` 表新增 `config` 字段（JSONB, nullable）
- View 枚举 type 支持 'form'
- 创建 `templates/views/blocks/` 目录

### Phase 2 — 编辑器模式切换
- 元数据 `meta[name="view-type"]` → 前端根据 type 切换
- `view_editor_components.js` 追加表单组件模板 + 组件分组
- 属性面板动态切换（CSS 属性 ↔ 字段属性）
- 数据源面板（显示实体字段列表）

### Phase 3 — 保存管道
- `DomManipulator::process()` 统一入口，根据 View.type 分支
- `processFormFields()` — data-field-config → form_row()
- `processVisibility()` — data-visibility → {% if %}
- `ViewEditorApiController::save()` 统一入口 + type 分支
- 提取并同步 ViewField.config 到数据库

### Phase 4 — 表单运行时
- `FormFieldRenderer` — 读取 View + ViewField → 构建 Form
- `FormRenderController` — 统一表单渲染入口
- 条件显隐运行时 JS
- `ef_form_base.html.twig` — 表单基模板

### Phase 5 — 区块系统
- 区块文件自动发现（扫描 `templates/views/blocks/*.form.twig`）
- 区块引用组件在编辑器中展示
- 区块保存 API

### Phase 6 — 接入与替换
- 示例表单模板 + 控制器
- 替换现有硬编码 CRUD 表单
- 统一视图管理页面的类型筛选

---

## 14. 与旧方案对比

| 维度 | 旧方案 (分开) | 新方案 (统一) | 差异 |
|------|-------------|-------------|------|
| Controller | FormDesignerController + ViewEditorController | 仅 ViewEditorController | -1 个 Controller |
| API | saveView + saveForm | 统一 save | 减少前端判断 |
| 编辑器 JS | view_editor_*.js + form_editor_init.js | 统一 view_editor_*.js | 减少一个 JS 入口 |
| 存储目录 | views/ + forms/ 分开 | 统一 views/ | 简化路径管理 |
| 组件注册 | 两套独立 | 一套 + type 切换 | 减少重复 |
| ViewField 配置 | data-* 散列属性 | data-field-config JSON | 更结构化 |
| 实体关联 | ViewField 间接关联 | View.entity_id 直连 | 更直接 |

---

## 15. 向后兼容

- 现有 type='view' 的 View 完全不受影响，entity_id 为 null
- 现有 ViewField 的 labelInserted/valueInserted/labelPosition/valuePosition 字段继续使用
- 新增的 entity_id 和 config 字段均为 nullable，不影响现有数据
- 编辑器 JS 在 view-type='view' 时行为完全不变
- DomManipulator 处理 type='view' 时走原有的 processDynamicFields + processTableCells 路径
