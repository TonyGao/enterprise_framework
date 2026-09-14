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

0. **禁止使用任何 CSS 类名（class 属性）** —— 所有样式必须用 inline style (`style="..."`) 实现。页面存在未知的类名 hash 机制，任何 class 都会被破坏导致样式丢失。**不要依赖外部 CDN 的 CSS/JS**；但可使用**站内 `/lib/` 下的本地库**（GSAP、three.js 等，见下）。
1. **最外层 div 必须包含 `width:100%`** —— `.section-content` 是 `display:flex`，子元素不加 width 会收缩包裹内容。
2. 输出必须是结构完整、标签闭合、可直接作为视图内容呈现的 HTML。
3. 全部内容完成后**必须调用** `viewfile_renderHtml(viewId)` 生成可执行模板。

## 富媒体与动效（让页面尽善尽美）

设计里可用下列手段，把页面做到极致（脚本会随服务端渲染进页面执行，编辑器画布与服务页都会运行）：

- **内联 SVG**：直接写 `<svg>...</svg>`（图标、插图、分隔、装饰），可加 `stroke-dasharray`/`<animate>` 做矢量动效。
- **位图**：需要真实图片时用 `viewfile_downloadImage(url, alt)` 下载到本站，再以 `/uploads/...` 本地引用（不要外链）。
- **GSAP 动画**：先引入站内库，再写内联脚本（用**唯一 ID** 选择元素，脚本用 IIFE 包裹）：
  ```html
  <script src="/lib/gsap/gsap.min.js"></script>
  <script src="/lib/gsap/ScrollTrigger.min.js"></script>
  <script>(function(){ if(!window.gsap) return;
    gsap.from('#hero-title', {y:24, opacity:0, duration:.8, ease:'power3.out'});
    gsap.utils.toArray('[data-reveal]').forEach(function(el,i){
      gsap.from(el,{y:30,opacity:0,duration:.7,delay:i*.08,
        scrollTrigger:{trigger:el,start:'top 85%'}});
    });
  })();</script>
  ```
  可用入场/滚动/悬停/数字滚动等动效；滚动动效需 `gsap.registerPlugin(ScrollTrigger)`（在新版 gsap 中 `ScrollTrigger` 已随文件全局注册）。
- **three.js 3D**：引入站内库 + 一个 `<canvas>` + 内联脚本初始化（同样用唯一 ID）：
  ```html
  <canvas id="hero-3d" style="width:100%;height:360px;display:block;"></canvas>
  <script src="/lib/three/three.min.js"></script>
  <script>(function(){ if(!window.THREE) return;
    var el=document.getElementById('hero-3d'); if(!el) return;
    var r=new THREE.WebGLRenderer({canvas:el, alpha:true, antialias:true});
    r.setSize(el.clientWidth, el.clientHeight, false);
    var s=new THREE.Scene(), c=new THREE.PerspectiveCamera(60, el.clientWidth/el.clientHeight, .1, 1000);
    c.position.z=4;
    /* ...建几何/材质/灯光... */
    (function loop(){ requestAnimationFrame(loop); /* 旋转/动画 */ r.render(s,c); })();
  })();</script>
  ```
- **通用**：脚本一律 `(function(){ ... })()` 包裹；选择器用**唯一 ID 或 data-* attribute**（不要用 class）；库缺失时安全退出（`if(!window.gsap) return`）。
- **渐进增强**：即使 JS 未执行，布局与内容也必须完整可读（**不要**只靠 JS 渲染正文内容）。

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

0. **禁止使用任何 CSS 类名（class 属性）** —— 所有样式必须用 inline style (`style="..."`) 实现。页面存在未知的类名 hash 机制，任何 class 都会被破坏导致样式丢失。**不要依赖外部 CDN 的 CSS/JS**；可用站内 `/lib/` 本地库（GSAP `/lib/gsap/gsap.min.js`、three.js `/lib/three/three.min.js`）与内联 `<svg>`/`<script>`（IIFE + 唯一 ID）实现动画与 3D。
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
   - **表单视图尤其如此**：不仅换配色/背景/卡片皮肤，**字段的排列构成、分组方式、视觉记忆点都必须换**——禁止把上一版的"表格行式 50% 双列 + 浅灰表头"换个背景就算重构（见下方表单领域指南第四节）
   - **主题/节日的真实元素必须落地到 HTML**（用 Unicode 图标、SVG、渐变、装饰 div），不能只在文字里提
   - **需要真实图片时**（Hero 大图、背景图、产品图、头像、示意图等）：用 `viewfile_downloadImage(url, alt)` 从公网下载到本站（返回 `/uploads/...` 本地地址），再以 `<img src="/uploads/...">` 或 `background-image:url(/uploads/...)` 引用——**不要直接外链第三方图片**（易失效/防盗链）。可下载参考网页里出现的图片，或用稳定的图源（如 `https://images.unsplash.com/...`）
   - 禁止"白底圆角卡片盒"（`background:#fff` + `border-radius:8px`）呈现信息区
   - `.section-content` 内只能有一个页面容器（一个 max-width 外层 div），禁止重复
3. 用 `view_updateSectionConfig(...)` 调整布局宽度
4. `viewfile_renderHtml(viewId)` 生成可执行模板
MD;

    protected const STYLE_MIMIC_WORKFLOW = <<<MD
## 工作流程（网页风格模仿——文件重写模式）

用户给了一个喜欢的网页链接，希望当前视图**模仿该网页的视觉风格**重构。内容（文字/字段/数据）保持不变，只换风格。

1. `viewfile_getDesign(viewId)` 读取当前视图设计内容（`.section-content` 内部 HTML），提取要保留的文字与信息（标题、称谓、字段、正文、文案、数据）——这些内容原样保留，只换风格
2. **优先用 `cdp_analyzeUrlStyle(URL)`** 分析参考网页（会打开该网页**整体截图 + 视觉模型识别**，返回结构化设计规格 JSON：布局构成/风格/配色/字段排列/视觉细节）。这是最准的方式，直接给出 style 规格；若 CDP 不可用（返回 error 需 Chrome 远程调试），则退回 `cdp_fetchWebPage(URL)` / `viewfile_fetchWebPage(URL)` 抓 HTML/CSS，再手动提炼风格
3. **结合风格规格/分析结果**，明确参考网页的具体取值：主色/辅色/背景/渐变、字体、按钮样式、卡片样式（圆角/阴影/边框）、间距、布局构成
4. 用 `viewfile_writeDesign(viewId, html)` 全新生成设计：**布局沿用当前视图的信息层次，视觉风格严格模仿参考网页**——配色、字体、按钮、卡片、间距、圆角、阴影、装饰元素都要体现参考页的风格，让用户一眼看出"就是这个风格"
   - 参考页里的**图片/配图**（Hero、背景、产品图等）：用 `viewfile_downloadImage(url, alt)` 下载到本站后以本地 `/uploads/...` 引用，**不要直接外链**
   - 禁止用"白底圆角卡片盒"（`background:#fff` + `border-radius:8px`）敷衍呈现
   - `.section-content` 内只能有一个页面容器（一个 max-width 外层 div）
   - 表单视图必须用 `{{ form_widget(form.x) }}` 等标准控件输出字段（见下方领域指南），字段保真，不得手写 `<input>`
5. 用 `view_updateSectionConfig(...)` 调整布局宽度适配新设计
6. `viewfile_renderHtml(viewId)` 生成可执行模板
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

    protected const FILE_WORKFLOW_REFINE = <<<MD
## 工作流程（就地修改——文件重写模式）

当前视图是**自定义 Twig 表单设计**，画布只是渲染预览，**不能靠画布保存**（会被保护拦截）。所有修改都必须改设计源码。

1. `viewfile_getDesign(viewId)` 读取当前设计**源码**（Twig），看清现有结构与字段
2. **保持其余部分不变**，只按用户需求就地修改设计源码：
   - 表单视图必须**原样保留** `{{ form_start }}`/`{{ form_widget(form.x) }}`/`{{ form_label }}`/`{{ form_rest }}`/`{{ form_end }}` 等指令与全部绑定字段，不得新增/删除/改名字段
   - 风格/配色/布局/文案按需求调整；`.section-content` 内仍只保留一个页面容器
   - **精确替换选中元素（重要）**：若消息以 `[Element]: …/[Selector]: …/[Style]: …` 开头（用户已在画布选中某元素），且要求"替换/修改这块/把这里改成…"，你**必须用其中的 inline style / 结构特征在源码里定位到该元素，并替换它本身**——**不要**在它旁边/下方新增一个新区块。替换后页面其余部分保持不变。
   - **图片的用法（重要）**：
     - 用户要"图片做背景 / 换成图片 / 这里用一张图"→ 用**一张** `<img>` 铺满该容器（`position:absolute;inset:0;width:100%;height:100%;object-fit:cover`）+ 线性渐变遮罩 + 叠加必要文字（hero 横幅式），呈现"整块就是一张图"的观感。
     - **不要**把"图片做背景"做成带标题/英文角标/多张缩略图+说明的**图集/相册**——除非用户明确要"图集/相册/多图展示/团队风采墙"。
     - 需要图片时用 `viewfile_downloadImage(url, alt)` 下载到站内 `/uploads/...` 引用，不要外链。
3. `viewfile_writeDesign(viewId, 修改后的完整设计)` 写回（一次性整体替换设计文件）
4. 若需调整外层布局宽度 → `view_updateSectionConfig(viewId, contentWidth, width, unit, columns)`
5. `viewfile_renderHtml(viewId)` 生成可执行模板
MD;

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
    public function systemPromptForFileRedesign(?array $designSpec = null, ?string $plan = null): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        $dynamic = self::buildDesignSpecBlock($designSpec, $plan);

        return $dynamic
            . "\n\n" . self::SHARED_CONSTRAINTS
            . "\n\n" . self::FILE_WORKFLOW_REDESIGN
            . "\n\n" . $domain;
    }

    /**
     * 把"参考图片"分析出的设计规格编译成最高优先级指令块，
     * 供控制器在 classifyTurn 之后附加上 / compile image-derived design spec into a directive block.
     */
    public static function imageSpecBlock(array $spec): string
    {
        $layoutMap = [
            'cards' => '卡片分组', 'split' => '左右分栏', 'sidebar' => '左侧导航+主区',
            'steps' => '分步向导', 'hero_flow' => 'Hero+字段区', 'single' => '单列',
            'immersive' => '通栏沉浸', 'table' => '表格行式',
        ];
        $styleMap = [
            'cool' => '酷炫现代', 'enterprise' => '企业稳重', 'minimal' => '极简',
            'dark' => '深色', 'light' => '浅色', 'luxury' => '质感高级',
        ];
        $degreeMap = ['full' => '彻底重构', 'skin' => '保留结构只换皮', 'refine' => '局部微调'];
        $compactMap = ['compact' => '紧凑', 'spacious' => '宽松'];

        $lines = ['## 🖼 参考图片风格（最高优先级，按此还原图片的视觉风格）', ''];
        $d = [];
        if (!empty($spec['layout_mode'])) $d[] = '布局构成：' . ($layoutMap[$spec['layout_mode']] ?? $spec['layout_mode']);
        if (!empty($spec['style'])) $d[] = '视觉风格：' . ($styleMap[$spec['style']] ?? $spec['style']);
        if (!empty($spec['degree'])) $d[] = '重构程度：' . ($degreeMap[$spec['degree']] ?? $spec['degree']);
        if (!empty($spec['compact'])) $d[] = '紧凑度：' . ($compactMap[$spec['compact']] ?? $spec['compact']);
        if (!empty($spec['colors'])) $d[] = '配色：' . $spec['colors'];
        if (!empty($spec['fields_arrangement'])) $d[] = '字段排列：' . $spec['fields_arrangement'];
        foreach ($d as $x) $lines[] = '- ' . $x;
        if (!empty($spec['content_hints'])) { $lines[] = ''; $lines[] = '图片中的内容线索（可还原为卡片/标题/文案）：' . $spec['content_hints']; }
        if (!empty($spec['notes'])) { $lines[] = ''; $lines[] = '其它视觉细节：' . $spec['notes']; }
        $lines[] = '';
        $lines[] = '要求：布局与视觉要明显贴近这张参考图片（配色、卡片、圆角、阴影、装饰、字体层级），但**只保留本视图自身的字段/文案/数据**，不要照抄图片里的无关内容。';

        return implode("\n", $lines);
    }

    /**
     * 把用户的设计规格（layout/style/degree/compact/colors/notes）与 plan 编译成
     * 一段"最高优先级"的执行指令，置于提示词最前，让子代理严格按用户意图执行，
     * 而不是从一句自然语言里猜测。spec 为空的字段忽略。/
     * compile the user's parsed design spec + plan into a top-priority directive block.
     */
    private static function buildDesignSpecBlock(?array $spec, ?string $plan): string
    {
        if (empty($spec) && empty($plan)) {
            return '';
        }

        $lines = ["## 🔒 用户设计规格（最高优先级，严格按此执行）", ""];
        $spec = $spec ?? [];

        $layoutMap = [
            'cards' => '卡片分组（按信息类别拆成多个卡片区块）',
            'split' => '左右分栏（左侧主表单，右侧辅助/操作/信息面板）',
            'sidebar' => '左侧导航 + 右侧主表单区',
            'steps' => '分步向导（顶部步骤条，字段按步骤分区）',
            'hero_flow' => '顶部 Hero 品牌区 + 下方字段区（非对称网格）',
            'single' => '单列垂直、大留白极简',
            'immersive' => '通栏沉浸式（全宽色带 + 场景装饰）',
            'table' => '传统表格行式（标签左、控件右、可双列）',
            'free' => '自由发挥（由你判断最合适的构成）',
        ];
        $styleMap = [
            'cool' => '酷炫现代（强对比、渐变、光晕、图标、装饰几何）',
            'enterprise' => '企业稳重（简洁、克制的配色与边框）',
            'minimal' => '极简（大留白、少装饰、强调留白与字体层级）',
            'dark' => '深色主题',
            'light' => '浅色清爽',
            'luxury' => '质感高级（细腻投影、渐变、精致细节）',
        ];
        $degreeMap = [
            'full' => '彻底重构（改变布局构成与字段排列，与旧版明显不同）',
            'skin' => '保留现有结构，只换配色/皮肤/装饰',
            'refine' => '局部微调优化',
        ];
        $compactMap = [
            'compact' => '布局紧凑（压缩间距、行高、留白）',
            'spacious' => '布局宽松（留白充足）',
        ];

        $directives = [];
        if (!empty($spec['layout_mode'])) {
            $directives[] = '布局构成：' . ($layoutMap[$spec['layout_mode']] ?? $spec['layout_mode']);
        }
        if (!empty($spec['style'])) {
            $directives[] = '视觉风格：' . ($styleMap[$spec['style']] ?? $spec['style']);
        }
        if (!empty($spec['degree'])) {
            $directives[] = '重构程度：' . ($degreeMap[$spec['degree']] ?? $spec['degree']);
        }
        if (!empty($spec['compact'])) {
            $directives[] = '紧凑度：' . ($compactMap[$spec['compact']] ?? $spec['compact']);
        }
        if (!empty($spec['colors'])) {
            $directives[] = '配色倾向：' . $spec['colors'];
        }
        if (!empty($spec['fields_arrangement'])) {
            $directives[] = '字段排列：' . $spec['fields_arrangement'];
        }

        foreach ($directives as $d) {
            $lines[] = '- ' . $d;
        }
        if (!empty($spec['notes'])) {
            $lines[] = '';
            $lines[] = '补充说明：' . $spec['notes'];
        }
        if (!empty($plan)) {
            $lines[] = '';
            $lines[] = '执行要点（plan）：' . $plan;
        }

        return implode("\n", $lines);
    }

    /**
     * 网页风格模仿模式：抓取参考网页并严格模仿其视觉风格重构当前视图 /
     * style-mimic mode: fetch a reference webpage and rewrite the current view to mimic its visual style
     */
    /**
     * 就地修改模式（文件重写）：自定义 Twig 表单设计的画布不可保存，小改动也走设计源码 /
     * in-place edit via design source (canvas save is blocked for custom Twig form designs).
     */
    public function systemPromptForFileRefine(): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CONSTRAINTS . "\n\n" . self::FILE_WORKFLOW_REFINE . "\n\n" . $domain;
    }

    public function systemPromptForStyleMimic(string $url): string
    {
        $domain = (string) file_get_contents($this->projectDir . '/' . $this->promptPath());

        return self::SHARED_CONSTRAINTS
            . "\n\n## 参考网页链接\n\n参考网页：{$url}\n\n"
            . self::STYLE_MIMIC_WORKFLOW . "\n\n" . $domain;
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
