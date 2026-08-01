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

- 内容最外层是 `width:100%` 且自带 `max-width` 居中留白 → 用 `view_updateSectionConfig(viewId, 'full-width', 100, '%', 1)`
- 设计为固定宽度内容（如 800px 邮件、固定内容盒）→ 用 `view_updateSectionConfig(viewId, 'fixed-width', 800, 'px', 1)`（px 值取内容设计宽度）

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

- 内容最外层是 `width:100%` 且自带 `max-width` 居中留白 → 用 `view_updateSectionConfig(viewId, 'full-width', 100, '%', 1)`
- 设计为固定宽度内容（如 800px 邮件、固定内容盒）→ 用 `view_updateSectionConfig(viewId, 'fixed-width', 800, 'px', 1)`（px 值取内容设计宽度）

## 工作流程

1. `cdp_getCurrentUrl()` 确认页面
2. `cdp_getPageHTML(".section-content")` 看当前结构
3. 如需要整体替换：`cdp_removeElement` 删除旧元素，`cdp_injectHTML(".section-content", "afterbegin", "完整HTML")` 注入新布局
4. 如只需局部调整：用 `cdp_setText` / `cdp_setStyle` 微调，避免整页重建
5. 用 `view_updateSectionConfig(...)` 调整布局宽度
6. `cdp_save()` 保存
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
     * 编辑器（CDP 实时画布）模式下的系统提示词：CDP 技术约束 + 领域设计指南。
     */
    public function systemPromptForCdp(): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CDP_CONSTRAINTS . "\n\n" . $domain;
    }
}
