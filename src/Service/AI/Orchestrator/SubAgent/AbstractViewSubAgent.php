<?php

namespace App\Service\AI\Orchestrator\SubAgent;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sub Agent 抽象基类：
 * 持有所有场景共用的"硬性技术约束"与工具说明，领域提示词由各子类通过 promptPath() 提供。
 * 这样新增场景只需新建一个类 + 一个 .md，即可获得全部技术约束与执行能力。
 */
abstract class AbstractViewSubAgent implements ViewSubAgentInterface
{
    protected const SHARED_CONSTRAINTS = <<<MD
## 工作方式

没有打开浏览器页面，所有操作通过"文件直写"工具完成，直接读写视图的 `.design.twig` 和 `.html.twig` 文件。

## 视图内容结构

视图内容本质是 `.section > .section-content > 自由 HTML`。你写入的 `html` 参数只包含页面内容本身（`.section-content` 内部），工具会自动包裹外层 section 结构。

## 可用工具

- `viewfile_getDesign(viewId)` — 读取当前设计内容（即 `.section-content` 内部 HTML）和文件路径
- `viewfile_writeDesign(viewId, html)` — **核心工具**：将完整页面内容写入设计文件
- `viewfile_renderHtml(viewId)` — **必须调用**：根据设计文件重新生成可执行模板（.html.twig）
- `view_getInfo(viewId)` — 获取视图信息（绑定实体、字段、布局配置）
- `view_listEntityFields(viewId)` — 列出视图绑定实体的字段
- `view_updateSectionConfig(viewId, contentWidth, width, unit, columns)` — 更新布局配置

## 硬性技术要求（必须遵守，否则样式会损坏）

0. **禁止使用任何 CSS 类名（class 属性）** —— 所有样式必须用 inline style (`style="..."`) 实现。页面存在未知的类名 hash 机制，任何 class 都会被破坏导致样式丢失。也不能依赖外部 CSS/JS。
1. **最外层 div 必须包含 `width:100%`** —— `.section-content` 是 `display:flex`，子元素不加 width 会收缩包裹内容。
2. 输出必须是结构完整、标签闭合、可直接作为视图内容呈现的 HTML。
3. 全部内容完成后**必须调用** `viewfile_renderHtml(viewId)` 生成可执行模板。

## 布局宽度（重要）

内容完成后必须调用 `view_updateSectionConfig(viewId, contentWidth, width, unit, columns)` 让 section 宽度适配你的设计：

- 内容最外层带 `max-width` 居中留白（如 860px 邮件、固定内容盒）→ 用 `view_updateSectionConfig(viewId, 'boxed', <maxWidth>, 'px', 1)`，其中 px 取内容设计的 max-width 值（如 860），让蓝色 section 紧贴内容宽度
- 仅当设计确实需要整页通栏（最外层无 `max-width`、内容铺满整行）→ 才用 `view_updateSectionConfig(viewId, 'full-width', 100, '%', 1)`

## 工作流程

1. `viewfile_getDesign(viewId)` 查看当前设计内容（若为空则是新建的空视图）
2. `view_getInfo(viewId)` 获取视图信息，必要时 `view_listEntityFields(viewId)` 查看绑定字段
3. 依据加工需求，用 `viewfile_writeDesign(viewId, html)` 写入完整页面内容
4. 用 `view_updateSectionConfig(...)` 调整布局宽度
5. `viewfile_renderHtml(viewId)` 生成可执行模板
MD;

    protected const SHARED_CDP_CONSTRAINTS = <<<MD
## 工作方式

你通过 Chrome DevTools CDP 操作视图编辑器画布（实时浏览器），直接向画布注入/修改 HTML，完成后必须保存。

## 画布结构

画布本质是一个空壳：`.section > .section-content > 自由 HTML`。你可以在 `.section-content` 内自由注入任意 HTML。不建议使用 ef-row/ef-col 网格系统，直接用纯 HTML div + inline style 自由排版，效果更可控。

## 可用工具

- `cdp_getCurrentUrl()` — 确认当前页面
- `cdp_getPageHTML(selector?)` — 获取页面 HTML
- `cdp_injectHTML(selector, position, html)` — **核心工具**：向元素注入任意 HTML
- `cdp_setContent(html)` — **整体重构核心工具**：一次性整体替换 `.section-content` 的完整内容（整页重写，等价于写文件）
- `cdp_save()` — 点击保存按钮持久化
- `cdp_setStyleByText(text, cssText)` — 按文本查元素改样式
- `cdp_setStyle(selector, cssText)` — 按选择器改样式
- `cdp_setText(selector, text)` — 改文本内容
- `cdp_addClass(selector, className)` — 加 CSS class（支持 Twind）
- `cdp_removeElement(selector)` — 删元素
- `cdp_click(selector)` — 点击元素
- `cdp_injectCSS(cssText)` — 注入全局 CSS

## 硬性技术要求（必须遵守，否则样式会损坏）

0. **禁止使用任何 CSS 类名（class 属性）** —— 所有样式必须用 inline style (`style="..."`) 实现。页面存在未知的类名 hash 机制，任何 class 都会被破坏导致样式丢失。也不能依赖外部 CSS/JS。
1. **最外层 div 必须包含 `width:100%`** —— `.section-content` 是 `display:flex`，子元素不加 width 会收缩包裹内容。
2. 输出必须是结构完整、标签闭合、可直接作为视图内容呈现的 HTML。
3. 修改完成后必须 `cdp_save()` 保存，让修改持久化。

## 布局宽度（重要）

内容完成后必须调用 `view_updateSectionConfig(viewId, contentWidth, width, unit, columns)` 让 section 宽度适配你的设计：

- 内容最外层带 `max-width` 居中留白（如 860px 邮件、固定内容盒）→ 用 `view_updateSectionConfig(viewId, 'boxed', <maxWidth>, 'px', 1)`，其中 px 取内容设计的 max-width 值（如 860），让蓝色 section 紧贴内容宽度
- 仅当设计确实需要整页通栏（最外层无 `max-width`、内容铺满整行）→ 才用 `view_updateSectionConfig(viewId, 'full-width', 100, '%', 1)`
MD;

    protected const CDP_WORKFLOW_REDESIGN = <<<MD
## 工作流程（整体重构模式——本段是最终命令，优先级最高）

1. `cdp_getCurrentUrl()` 确认页面——**必须与用户消息中的 [目标编辑器URL] 完全一致（尤其要包含 version 参数）**；不一致（如缺少 version、落在其他版本）时，先用 `cdp_navigate` 导航到 [目标编辑器URL]，再继续
2. `cdp_getPageHTML(".section-content")` 看当前结构——识别旧页面的整体版式与视觉语言，记住它
3. **必须整体替换布局，不只是换主题色**：
   - **即便用户只说"按 XX 风格重构 / 换个风格 / 做成 XX 风"**，这里的"重构"也指**重新构造布局与构成**，绝不只是把配色/背景/文字颜色换成新主题。
   - **用 `cdp_setContent(完整的新布局HTML)` 一次性整体替换 `.section-content` 的内容**（等价于整页重写文件，避免增量编辑导致"描述但不执行"）——**不要用 `cdp_setStyle` 就地改色**来完成重构。
   - **禁止"白底圆角卡片盒"（`background:#fff` + `border-radius:8px` 之类）** 呈现信息区；信息区块改用色块分区 + 分隔线、左右双栏流动排版、通栏色带、印章式独立区块等方式。
   - **整体构成必须换一种**：通栏 Hero、左右分栏、非对称构图、时间线、杂志式、沉浸式长页、大幅留白极简中选一种，与旧版式明显不同。
   - 内容沿用指**信息/语义**沿用，不是版式沿用。
   - **禁止页面重复**：`.section-content` 内最终**只能有一个页面容器**（一个 max-width 外层 div）。
   - **主题必须真实落地，而不是"只换色"**：依据你对该节日/主题的**常识**，把它的典型视觉元素、符号、配色、意象真正实现到 `cdp_setContent` 传入的 HTML 里（用 Unicode 图标、SVG、渐变、装饰 div 等方式），并**自检页面是否真的体现该主题**——如果只是换了配色而缺少该主题的典型元素，需补充后再保存。
 4. 用 `view_updateSectionConfig(...)` 调整布局宽度
 5. **保存前强制验证**：用 `cdp_getPageHTML(".section-content")` 检查你宣称要做的改动**是否真实出现在页面 HTML 中**（例如你回复里说要加灯笼/印章/编号，就必须在 HTML 里能看到对应元素）。**只改变颜色不算完成**；缺的元素必须补上，再 `cdp_save()` 保存。
MD;

    protected const CDP_WORKFLOW_REFINE = <<<MD
## 工作流程（局部调整模式：用户只要求小幅改动）

1. `cdp_getCurrentUrl()` 确认页面——**必须与用户消息中的 [目标编辑器URL] 完全一致（尤其要包含 version 参数）**；不一致（如缺少 version、落在其他版本）时，先用 `cdp_navigate` 导航到 [目标编辑器URL]，再继续
2. `cdp_getPageHTML(".section-content")` 看当前结构
3. **就地微调，避免整页重建**：用 `cdp_setText` / `cdp_setStyle` / `cdp_setStyleByText` 修改目标元素的文字与样式；仅当某块确实多余时才用 `cdp_removeElement` 删除单个元素
   - **禁止重新注入一个完整页面容器**——否则会在现有页面下方再摞一个页面，产生重复。添加元素、装饰、区块时，用 `cdp_injectHTML` 只往**局部元素**（如某个 div 内部）插入小块内容，而不是注入整页。
   - 换主题/加节日元素时，**依据你自己的常识**把该主题的典型视觉元素、符号、配色真实落地到 HTML（用 Unicode 图标/SVG/渐变/装饰 div），并自检页面是否真的体现该主题——不能只在文字里提、页面上却看不到。
 4. `cdp_save()` 保存；保存前确认 `.section-content` 内**只有一个页面容器**。
MD;

    protected const FILE_WORKFLOW_REDESIGN = <<<MD
## 工作流程（整体重构——文件重写模式）

1. `viewfile_getDesign(viewId)` 读取原视图的设计内容（`.section-content` 内部 HTML），并从中**提取有意义的文字与信息**（标题、称谓、字段、正文、文案、数据等）——这些是要保留的内容
2. **放弃修改原 HTML，全新生成**：根据用户的加工需求 + 第一步提取出的内容，用 `viewfile_writeDesign(viewId, html)` 生成一套**全新的完整页面**（一次性整体替换 design 文件，不做任何增量修改）
   - **整体构成必须与旧版明显不同**：通栏 Hero、左右分栏、非对称构图、时间线、杂志式、沉浸式长页、大幅留白极简中选一种
   - **主题/节日的真实元素必须落地到 HTML**（用 Unicode 图标、SVG、渐变、装饰 div），不能只在文字里提
   - 禁止"白底圆角卡片盒"（`background:#fff` + `border-radius:8px`）呈现信息区
   - `.section-content` 内只能有一个页面容器（一个 max-width 外层 div），禁止重复
3. 用 `view_updateSectionConfig(...)` 调整布局宽度
4. `viewfile_renderHtml(viewId)` 生成可执行模板
MD;

    protected const FORM_LAYOUT_WORKFLOW = <<<MD
## 工作流程（表单视图结构化布局模式——本段为最终命令，优先级最高）

**字段保真铁律**：你是表单布局设计师，**不是 HTML 手写工**。所有字段控件由系统渲染器生成，你**禁止**新增/删除/改名绑定字段，**禁止**手写 `<input>/<select>/<textarea>` 控件 HTML。

1. 先调用 `view_getFormStructure(viewId)` 读取表单真相（绑定字段 + 可配置项 + section_config + 主题 + 渲染形态），**不修改任何字段**
2. 依据用户需求，用 `form_applyLayout(viewId, layout)` 一次性应用布局指令。layout 结构：
   ```jsonc
   {
     "theme": { "primary": "#4f46e5", "pageBg": "#f8fafc", "cardBg": "#ffffff",
                "labelColor": "#1e293b", "inputBg": "#ffffff",
                "requiredBg": "#fde8e8", "regularBg": "#ffffff", "radius": 8, "fontSize": 14 },
     "layout": { "columns": 1|2|3, "fieldLayout": "horizontal|vertical",
                 "contentWidth": "boxed|full-width", "width": 960, "gap": 24,
                 "labelWidth": 8, "render_mode": "page|fragment" },
     "groups": [
       { "title": "基本信息", "fields": ["name", "alias", "code"] },
       { "title": "工商信息", "fields": ["gongSiFaRen", "state"], "colSpan": 2 }
     ],
     "fields": { "name": { "label": "公司全称", "required": true, "placeholder": "...", "regularBg": "#ffffff", "requiredBg": "#fde8e8", "colSpan": 2 } }
   }
   ```
   - `groups` 的字段集合必须**恰好等于**绑定字段集合，不能多、不能少、不能重复
   - 每个字段只出现一次；字段顺序即表单显示顺序
   - 分组头（sectionHeader）由系统写入；第一组放核心/必填字段
   - ≥5 个字段建议分 2~3 组；长字段（文本域/备注）用 `colSpan: 2` 跨整行
   - `fields` 仅用于**覆盖**具体字段的标签/必填/占位/配色/跨列，其余字段自动套用主题
3. 主题要求"肉眼可见变化"：`primary` 主色、`pageBg`/`cardBg` 背景、`requiredBg`/`regularBg` 底色都应给出且与默认不同
4. `form_applyLayout` 返回后，必要时再用 `view_updateSectionConfig` / `view_updateFieldConfig` 微调
5. **落盘说明**：`form_applyLayout` 已把布局/主题/分组**直接写入服务器设计文件并持久化**，**不需要、也不允许**再调用任何"保存"类工具（如 `cdp_save`）——编辑器刷新后即见效果，保存工具反而会用陈旧画布覆盖刚写入的布局
MD;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] protected readonly string $projectDir,
    ) {}

    public function systemPrompt(): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CONSTRAINTS . "\n\n" . $domain;
    }

    /**
     * 编辑器（CDP 实时画布）模式下的系统提示词。
     * 根据是否"整体重构"动态选择工作流。
     * 关键：重构模式下把「重构工作流」放在**提示词最后**——LLM 对末尾指令权重最高，
     * 避免领域提示词的标准结构（如邮件卡片式）主导输出导致"换皮不换骨"。
     */
    public function systemPromptForCdp(bool $redesign = false): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        if ($redesign) {
            return self::SHARED_CDP_CONSTRAINTS . "\n\n" . $domain . "\n\n" . self::CDP_WORKFLOW_REDESIGN;
        }

        return self::SHARED_CDP_CONSTRAINTS . "\n\n" . self::CDP_WORKFLOW_REFINE . "\n\n" . $domain;
    }

    /**
     * 整体重构使用"文件重写"模式：提取原内容 → 用指令+原内容全新生成整份 design 文件。
     * 文件直写是一次性全量替换，比 CDP 增量编辑可靠（同样的模型用文件工具能产出真正不同的视图）。
     */
    public function systemPromptForFileRedesign(): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CONSTRAINTS . "\n\n" . self::FILE_WORKFLOW_REDESIGN . "\n\n" . $domain;
    }

    /**
     * 表单视图的"结构化布局"模式：AI 输出 layoutSpec（分组/列/主题/字段配置），
     * 服务器用 FormFieldRenderer 重渲染控件，保证字段保真、可用。
     * 禁止手写字段控件 HTML。
     */
    public function systemPromptForFormLayout(): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CONSTRAINTS . "\n\n" . self::FORM_LAYOUT_WORKFLOW . "\n\n" . $domain;
    }
}
