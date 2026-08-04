# AI 表单视图美化助手 — 方案设计（RFC）

> 目标视图类型：绑定实体模型的 **Symfony 表单视图**（如 `company_edit_form`）
> 核心诉求：借助 AI 对话能力，快速把"结构单一、页面死板"的表单构建得**漂亮、可用**

---

## 1. 背景与痛点

以 `company_edit_form`（绑定 `Company` 实体，9 个字段）为代表的表单视图，当前存在以下问题：

1. **结构单一**：所有字段都是"一行标签 + 一个输入框"的横向平铺，一列到底，没有分组、没有主次层次。
2. **页面死板**：白底 / 淡色底输入框，无主题、无卡片分区、无视觉焦点，观感粗糙。
3. **手动改造成本高**：要做出漂亮的分组、多列、配色表单，需要逐个在属性面板调整几十个属性（标签、列宽、背景、分组头、必填样式……），效率极低。
4. **现有 AI 重构对表单不安全**：当前 `view_editor` 的"文件重写"重构（`viewfile_writeDesign` 整页重写 HTML）会让 AI **手写整段表单 HTML**——极易破坏：
   - 字段绑定（`data-field-name` / `name="form[name]"` / `for="form_name"`）
   - 复杂控件（`ef-switch` 开关、`ef-select-view` 实体下拉及其 options、`ef-textarea`）
   - 表单提交语义（`type=submit` 按钮、`form[_token]` CSRF、表单 action/method）
   - 一旦字段控件写法与 `FormFieldRenderer` 不一致，**表单能看不能用**。
5. **表单无法独立成"一整页"**：当前表单视图只能以"字段表单区"出现，AI 无法围绕表单生成完整的落地页（Hero、静态内容、栏目布局、氛围样式），因此页面始终"裸表单"。
6. **表单无法复用**：同一表单（如"公司编辑"）无法被多个宿主页面/视图共享嵌入——没有"表单片段"抽象，要么复制、要么只能绑定到单一模板（如 `companyEdit.html.twig`），改一处要到处同步。

---

## 2. 目标

1. 在表单视图编辑器中提供 **AI 对话**（复用现有 `ai_chat` 面板），用户用自然语言即可让 AI 美化表单。
2. **字段保真**：无论 AI 怎么改版式/主题/分组，所有绑定字段的控件、名称、绑定、CSRF、提交语义必须原样保留，表单始终"可用"。
3. **快速变漂亮**：一键分组、多列、主题换肤、视觉层次；支持"彻底重构版式"与"只换主题"两种力度（复用澄清机制）。
4. 兼容现有架构：不破坏属性面板、拖拽、`FormFieldRenderer` 渲染管线、版本管理与回退。
5. **可独立成整页**：表单视图可作为**单页**，AI 能围绕表单生成复杂的页面布局、静态内容与样式（Hero、栏目、氛围、页脚等），产出一张完整可用的业务页面。
6. **可复用为表单片段**：表单视图可作为**独立表单片段**，被任意宿主视图/模板通过一种统一方式引入（`{{ view_form(viewId, data) }}`），实现"一份表单定义、多处复用、改动自动同步"。

---

## 3. 现状分析（关键结论）

### 3.1 表单的"结构真相"其实已具备表达能力

- `ViewField` 记录（字段名/类型/`config`）是表单的**结构化真相**；`config` 已支持：`label`、`required`、`placeholder`、`height`、`colSpan`、`regularBg`、`requiredBg`、`rounded`、`choices`、`validation`、`sectionHeader`、`sectionDivider`。
- `section_config` 已支持：`columns`（多列网格）、`fieldLayout`（horizontal / vertical）、`contentWidth`（boxed / full-width）。
- `_form_fields.html.twig` 已经能渲染：多列网格、纵向布局、分组头（`sectionHeader`）、分隔线（`sectionDivider`）、跨列（`colSpan`）。
- `generalConfig`（`var/data/view_editor_config.json`）已承载主题类配置（如 `requiredBg`）。

> 结论：**"单一死板"不是能力缺失，而是字段/配置没有被用起来。** 只要 AI 能正确地写回 `ViewField.config` 与 `section_config`，并用现有渲染管线重新生成控件，就能做到"漂亮且可用"，而无需 AI 手写控件 HTML。

### 3.2 现有 AI 链路（可直接复用）

```
编辑器页(base 引入 ai_chat) → POST /api/admin/ai/chat (view_editor 上下文)
  → ViewSubAgentChatRouter::classifyTurn (IntentAgent LLM 判定 intent/redesign/澄清)
    → FormViewSubAgent (form intent) → 文件重写 / CDP 微调
```

- 意图判定已支持 `form`（`view_enhance_intent_prompt.md`）。
- `FormViewSubAgent` 已存在（`view_sub_agent_form.md`）。
- 澄清、进度推送（Mercure）、执行日志、检查点回退、"重构后自动刷新"均已具备。

### 3.3 必须新增的能力

| 缺口 | 说明 |
|------|------|
| **字段保真重构机制** | AI 不能手写控件 HTML；需"AI 出布局指令 + 服务器用渲染器重生成控件" |
| **结构化布局工具** | 新增 `form_applyLayout` 等，AI 输出布局 JSON 而非 HTML |
| **字段/配置写回** | `view_updateFieldConfig` 需扩展分组头/分隔线/配色；`view_updateSectionConfig` 已够用 |
| **主题体系** | `generalConfig` 扩展主题色板，AI 换肤有明确抓手 |
| **表单专属提示词** | 强化分组、多列、纵向、配色、层次等设计要点 |
| **视图形态（单页/片段）** | 表单视图可声明为 `page`（独立整页）或 `fragment`（可复用表单片段），渲染管线据此输出 |
| **表单片段嵌入机制** | 新增 Twig 扩展 `view_form(viewId, data, options)`：宿主视图任意位置嵌入该表单，一份定义多处复用 |
| **整页渲染入口** | 新增页面路由/渲染服务：`page` 形态的表单视图可独立访问（含 AI 生成的页面壳） |
| **形态感知的 AI** | `page` 模式让 AI 生成页面壳（Hero/静态内容/布局/样式）；`fragment` 模式让 AI 只打磨表单本身 |

---

## 4. 方案设计

### 4.1 总体思路：字段保真的"版式壳 + 服务器重渲染控件"

> **AI 设计"骨架与皮肤"，服务器生成"器官"。**

```
用户需求（自然语言）
   │
   ▼
IntentAgent（LLM）→ intent=form, redesign?, 澄清?
   │
   ▼
FormViewSubAgent（LLM，表单领域）
   │  输出：结构化布局指令（JSON），不是整段 HTML
   ▼
form_applyLayout(layoutSpec)   ← 新增工具
   │
   ├─ 写回 ViewField.config（分组头/分隔线/列宽/标签/必填/占位/配色）
   ├─ 写回 section_config（columns / fieldLayout / contentWidth / gap）
   ├─ 写回 generalConfig 主题（primary/bg/inputBg/focus 等）
   ▼
FormFieldRenderer::render() + _form_fields.html.twig   ← 现有渲染管线
   │  重新生成每个字段的控件（保证 ef-input/ef-switch/ef-select/CSRF 正确）
   ▼
写入 design 文件 → renderHtml → 编辑器自动刷新
```

**为什么这样做能"漂亮且可用"：**
- **可用**：控件一律由 `FormFieldRenderer` 生成，字段名/绑定/CSRF/提交语义永不丢失。
- **漂亮**：分组、多列、配色、留白、标题区/操作区全部由 AI 布局指令驱动，落在 `section_config` / `ViewField.config` / `generalConfig` 上，现有渲染器即可呈现。
- **可回退**：`ViewField.config` 与 `section_config` 是结构化数据，天然可版本化、可 undo/redo、可检查点回退（复用现有版本体系）。

### 4.2 布局指令协议（`form_applyLayout` 的输入）

AI 输出一个 JSON（服务端严格 schema 校验，非法则回退到"配置级换肤"方案 B）：

```jsonc
{
  "theme": {
    "primary": "#4f46e5",        // 主色（按钮/聚焦边框/强调）
    "pageBg": "#f8fafc",         // 页面背景
    "cardBg": "#ffffff",         // 分组卡片背景
    "labelColor": "#1e293b",
    "inputBg": "#ffffff",
    "requiredBg": "#fde8e8",     // 必填字段底色
    "regularBg": "#ffffff",      // 普通字段底色
    "radius": 8,                 // 圆角
    "fontSize": 14
  },
  "layout": {
    "columns": 2,                // 网格列数（1/2/3）
    "fieldLayout": "horizontal", // 或 vertical
    "contentWidth": "boxed",     // 或 full-width
    "width": 960,
    "gap": 24
  },
  "groups": [
    { "title": "基本信息", "icon": "building", "fields": ["name", "alias", "code"] },
    { "title": "工商信息", "icon": "id-card", "fields": ["gongSiFaRen", "state"], "columns": 2 },
    { "title": "其他", "fields": ["parent", "repetitionNumHandling", "remark"], "colSpan": 2 }
  ],
  "actions": { "submit": "保存", "cancel": "取消", "align": "right" }
}
```

服务端 `form_applyLayout` 的处理：
1. **校验**：`groups` 覆盖的字段名必须全部是视图已绑定的字段，不能新增/删除绑定字段。
2. **写字段**：按 group 顺序更新 `ViewField.sortOrder`；写 `sectionHeader` / `sectionDivider` / `colSpan` / `label` / `required` / `placeholder` / `regularBg` / `requiredBg`。
3. **写 section**：`section_config = {columns, fieldLayout, contentWidth, width, unit, gap}`。
4. **写主题**：合并进 `generalConfig`（视图级）。
5. **重渲染**：调用 `FormFieldRenderer::render()` 生成完整 `<form>` HTML，再套回编辑器 canvas 骨架（`section` / `section-content` / 操作区），写 `design.twig`，再 `renderHtml`。

### 4.3 三种执行力度（复用澄清机制）

| 力度 | 做法 | 工具 |
|------|------|------|
| **A. 彻底重构版式** | 布局指令全量重建：分组/列/主题/宽度全变（对应"重构=换套全新版式"） | `form_applyLayout` |
| **B. 配置级换肤** | 仅改 `section_config` + `ViewField.config` 的配色/必填/占位/列宽，不动分组结构（安全兜底，指令非法时回退到此） | `view_updateSectionConfig` + `view_updateFieldConfig`（扩展） |
| **C. 局部微调** | 选中某字段/分组，改标签、必填、配色、占位、跨列（对应 CDP 就地微调） | CDP + `view_updateFieldConfig` |

澄清问题示例（`needs_clarification`）：
> "你希望怎么美化这个表单？" → 选项：「分组成卡片+双列布局」「只换一个清爽主题」「我来描述具体要求」

### 4.4 新增 / 扩展的工具

| 工具 | 类型 | 说明 |
|------|------|------|
| `form_applyLayout(viewId, layoutSpec)` | **新增** | 核心：布局指令 → 写字段/section/主题 → 用渲染器重生成 form HTML |
| `view_getFormStructure(viewId)` | **新增** | 返回绑定字段 + 每个字段可配置项 + 当前 section_config/generalConfig（AI 决策依据；`view_getInfo`/`view_listEntityFields` 的增强封装） |
| `view_updateFieldConfig` | **扩展** | 增加 `sectionHeader`、`sectionDivider`、`regularBg`、`requiredBg`、`choices` 等 |
| `view_updateSectionConfig` | 现有 | 已支持 `columns`；可加 `gap`、`labelWidth` |
| `viewfile_getDesign/writeDesign/renderHtml` | 现有 | 仅用于读/写最终 HTML，表单路径**不建议**走 `writeDesign` 整页重写 |

> 约束：表单视图的 AI 重构**禁止**走"AI 手写完整 HTML 后 writeDesign"的通用文件重写路径（对表单不安全）；一律走 `form_applyLayout` 结构化路径。通用路径仅对 `email/landing/info/report` 等"展示型"视图开放。

### 4.5 提示词设计（`view_sub_agent_form.md` 增强）

在现有"表单结构 / 结合实体字段 / 视觉"基础上补充：

- **字段保真铁律**：不得新增/删除/改名绑定字段；字段控件由系统渲染，AI 只描述"放哪些字段、怎么分组、什么宽度/配色"。
- **分组与层次**：≥5 个字段时建议分 2~3 个业务组（`sectionHeader` + `sectionDivider`），首组放核心字段（名称/必填），次要信息放后面。
- **多列与跨列**：可用 `columns=2` 让短字段并排；长字段（文本域/备注）`colSpan` 跨整行。
- **主题落地**：用 `theme` 给出主色/背景/卡片色/必填底色，且要求"肉眼可见的变化"（自检：换主题后颜色确实改变）。
- **可用性**：必填字段标 `*`（`required`）、给占位提示、保持提交/返回按钮显眼。

### 4.6 前端交互

- **入口**：复用 `ai_chat` 面板（base 已引入，编辑器页已注册 `view_editor` 上下文）。
- **快捷指令**：在聊天输入框上方提供表单专用快捷按钮，如：
  - 「分组成卡片」「改成双列」「清爽主题」「更紧凑」「详情表单」
- **完成反馈**：复用现有机制——回复展示执行效率，`redesignApplied` 后自动刷新编辑器展示新表单；「回退到此」检查点。
- **数据源联动**：左侧「数据源」面板展示绑定字段，AI 引用字段名与之一致。

### 4.7 数据模型扩展

- `platform_view.section_config`：新增 `gap`、`labelWidth`、`theme`（或 `theme` 放 `generalConfig`）。
- `platform_view_field.config`：已支持的字段直接复用；必要时补 `icon`、`hint`。
- `generalConfig`（视图级）：扩展主题色板 `primary/pageBg/cardBg/labelColor/inputBg/requiredBg/regularBg/radius/fontSize`。
- `design.twig`：仍存最终渲染 HTML（含 section 骨架 + form），是"结果"而非"真相"；真相在 `ViewField` + `section_config` + `generalConfig`。

### 4.8 与版本管理 / 回退的配合

- `form_applyLayout` 写的是**结构化数据**（字段 config + section_config），因此：
  - 每次执行后生成 `ViewVersionHistory` 快照（现有 undo/redo 直接可用）。
  - 检查点（`AiChatCheckpoint`）记录消息前状态，「回退到此」可完整还原。
  - 多个版本并存，AI 只在当前查看的版本上生效（沿用已修复的 `?version=` 版本解析）。

### 4.9 与 Symfony MATE / MCP 的关系（决策）

**结论：运行时表单视图 AI 不需要结合 MATE。** MATE 是"开发期" MCP 服务器，不是运行时能力。

- **定位**：MATE（`.mcp.json` → `./vendor/bin/mate serve`）是给**编码 AI / IDE** 在开发本项目时用的 MCP 工具，仅提供 `server-info`、`symfony-services`（DI 容器内省）、`symfony-profiler-*` 三组能力，**没有任何 Twig 生成/校验工具**。
- **本方案不产生"需要容器内省的 Twig"**：
  - 结构化美化走 `form_applyLayout` → 写 `ViewField.config` / `section_config` → 服务器用**固定模板** `_form_fields.html.twig` 渲染控件，AI 不写 Twig；
  - page 形态的"页面壳"是**静态 HTML + 表单占位符**，不引用 Symfony 服务 ID / 路由 / 资产，无需容器内省。
- **不接入运行时**：把本地 MCP 进程暴露给运行时 LLM 会引入本地进程依赖、暴露容器/Profiler 的安全面、性能与权限问题。
- **何时才用到 MATE**：
  - **开发本功能时**：用 `symfony-services` 内省 `FormFieldRenderer` / Twig 环境，用 `symfony-profiler` 调试渲染，提升开发效率（这是工具链层面，与运行时无关）；
  - **若未来让 AI 生成引用真实路由/资产/模板的 Twig**：正确做法是给 AI **受限 Twig 上下文**（可用函数/路由白名单），生成后走**现有 `lint:twig`（twigcs）**校验，而不是开放 MCP；页面壳仍保持"静态 HTML + 表单占位符"的职责单一约束。

---

## 5. 两种形态：单页整站式 与 可复用表单片段

表单视图应在"**一个完整业务页面**"与"**一个可复用的表单部分**"之间自由切换。前者让 AI 围绕表单生成整页；后者让表单成为组件被其他视图引入、一份定义多处复用。

### 5.1 形态模型：`render_mode`

`View` 新增/复用形态字段 `render_mode ∈ { page, fragment }`（存于 `section_config` 或 `View` 列，默认 `page`）：

| 形态 | 语义 | 生产渲染输出 | AI 加工范围 |
|------|------|--------------|-------------|
| `page`（单页整站式） | 表单即一个完整独立页面 | 完整页面 HTML（页面壳 + 表单） | AI 生成整页：Hero/静态内容/栏目/氛围/样式 + 表单区 |
| `fragment`（可复用表单片段） | 表单只是"一个 `<form>` 部分"，宿主页面负责放置 | 自包含 `<form>...</form>` 片段 | AI 只打磨表单：分组/列/主题/必填 |

> 切换形态不丢失数据：字段真相（`ViewField` + `section_config`）与片段/页面壳 HTML 分别存储，随时可来回切换、可版本化。

### 5.2 渲染管线（关键）

```
宿主模板 / 页面路由
   │
   ├─ fragment 形态：{{ view_form(viewId, data, options) }}   ← 新增 Twig 扩展
   │      ├─ 读取 ViewField + section_config + generalConfig（表单真相）
   │      └─ FormFieldRenderer::render() → <form> 片段（含字段控件/CSRF/提交）
   │
   └─ page 形态：/views/{id}（或视图内嵌于某页面）
          ├─ 读取视图"页面壳"HTML（AI 生成：Hero/静态内容/布局/样式）
          └─ 壳内的表单槽位由 view_form(viewId, data) 注入（字段控件仍由渲染器生成）
```

- **表单片段永由 `FormFieldRenderer` 生成**：无论 page 还是 fragment，字段控件、绑定、CSRF、提交语义都不经过 AI 手写 → 始终"可用"。
- `page` 形态下 AI 只生成**页面壳**（壳内含一个表单占位标记，如 `<div data-view-form="{viewId}">`），服务器渲染时用表单片段替换占位符 → "AI 负责页面、渲染器负责表单"。

### 5.3 表单复用机制（`view_form` Twig 扩展）

```twig
{# 宿主模板任意位置嵌入一个表单视图 #}
{{ view_form('949f87d3-91b3-4c21-bca4-6924c4a29fe6', company, {
    submit_label: '保存',
    cancel_href: path('org_company'),
    columns: 2,          # 覆盖该视图默认列数
    readonly: false,
}) }}

{# 或在 page 形态的页面壳占位符处 #}
<div data-view-form="{viewId}" data-view-form-data="..."></div>
```

- **签名**：`view_form(viewId, data, options)`；`data` 为绑定的实体对象，`options` 覆盖默认渲染（表单宽度/提交按钮/只读/列数等）。
- **单一真相源**：所有宿主页面共用同一 `ViewField` + `section_config` + `generalConfig`；改一处（如给某个字段改必填/换主题），所有引入该表单的页面**自动同步**。
- **内置模板迁移**：现有 `companyEdit.html.twig` 里 `{{ form_widget(form) }}` / `dynamicFormHtml` 可平滑替换为 `{{ view_form(viewId, entity) }}`，从而吃上 AI 生成的分组/列/主题，且无需在每个页面复制表单。
- **路由/接口**：可选提供一个 REST 端点 `/api/views/{id}/form` 返回片段 HTML，供非 Twig 宿主（如异步加载、其他语言页面）引入。

### 5.4 页面壳存储与生成（page 形态）

- **存储**：页面壳作为该视图的"页面模板"（可复用 `design.twig` / 新增 `page.twig`，含 `{% block form %}` 占位），与表单字段真相分离。
- **AI 生成**：`page` 形态下，`FormViewSubAgent` 输出两份产物：
  1. **页面壳**（新工具 `page_applyShell(shellSpec)`）：AI 描述 Hero/栏目/静态文案/布局/样式，服务器写入页面壳模板（可用通用视图的文件工具，但**页面壳可含静态元素与样式**，无字段绑定风险，允许自由 HTML）。
  2. **表单区**（`form_applyLayout`）：依旧结构化写回，渲染器生成控件。
- **编辑器呈现**：编辑器画布中，页面壳按真实整页预览，表单区为可交互的表单；`fragment` 形态下画布只显示表单区。

### 5.5 形态感知的 AI 交互

- **形态澄清**：首次/切换时澄清"这个表单要**独立成一个页面**，还是**作为可复用的表单片段**被其它页面引入？"（选项 + 自由描述）。
- **page 指令示例**："把公司编辑做成一个独立页面，顶部 Hero 标题『企业管理』，左侧说明区，右侧放表单，底部页脚，整体蓝色商务风。" → 生成页面壳 + 表单布局。
- **fragment 指令示例**："把这段公司表单分成『基础信息』『工商信息』两组，双列，清爽主题。" → 只写 `ViewField`/`section_config`/主题，宿主页面 `view_form()` 引入后立即可见。
- **复用提示**：`view_getFormStructure` 额外返回 `render_mode`、被哪些宿主引用（可选），AI 可提醒"改这里会影响 N 个引入页面"。

### 5.6 数据模型扩展（补充）

- `section_config`：新增 `render_mode`（`page`/`fragment`）、页面级 `pageShell` 标记。
- `generalConfig`：主题色板（`primary/pageBg/cardBg/labelColor/inputBg/requiredBg/regularBg/radius/fontSize`）。
- `ViewField.config`：沿用既有 `label/required/placeholder/height/colSpan/sectionHeader/sectionDivider/regularBg/requiredBg/rounded/choices`。
- 页面壳：作为视图模板（`page.twig`）或 `design.twig` 的一部分；与字段真相分离，可分别版本化。

### 5.7 与现有生产模板的衔接

| 现状 | 迁移后 |
|------|--------|
| `OrgController::editCompany` 手拼 `form_widget(form)` | 改用 `view_form(viewId, company)`（吃 AI 布局/主题），或保持 `dynamicFormHtml` 由渲染器输出 |
| `companyEdit.html.twig` 写死表单结构 | 只保留"放一个 `view_form()`"的宿主外壳，结构完全交给视图 |
| 一个表单绑定一个页面 | 一个 `fragment` 表单可被任意页面/视图嵌入 |

---

## 6. 实施里程碑

> 实现状态（2026-08-04）：✅ 已实现 / 🟡 部分 / ⬜ 待办

| 阶段 | 内容 | 交付 | 状态 |
|------|------|------|:---:|
| **M1 管线** | 新增 `form_applyLayout` + `view_getFormStructure`；服务端"布局指令 → 写配置 → 重渲染控件"管线 | `FormLayoutService::applyLayout` 分组/双列/主题/字段保真；9 字段回归通过 | ✅ |
| **M2 AI 生成** | `FormViewSubAgent` 提示词增强 + schema 校验 + 非法回退 B 方案；接通 `ai_chat` 表单快捷指令 | `systemPromptForFormLayout` + `FORM_LAYOUT_WORKFLOW` + 路由表单工具集白名单 + 快捷指令 | ✅ |
| **M3 主题体系** | `generalConfig` 主题色板 + 换肤走 B 方案 | `section_config.theme` 默认底色 + 模板消费 | ✅ |
| **M4 前端 UX** | 快捷指令按钮、重构后自动刷新、数据源联动 | 4 个表单快捷指令、redesignApplied 自动刷新 | ✅ |
| **M5 校验强化** | 字段完整性校验、复杂控件重渲染、CSRF/提交回归 | 字段集合校验、`<form>`+CSRF+提交、entity select/switch/textarea 保留 | ✅ |
| **M6 形态切换** | `render_mode`（page/fragment）+ `view_form` Twig 扩展 + `/api/views/{id}/form` + 编辑器形态切换 UI | `ViewFormExtension`、`/api/views/{id}/form`、工具栏形态按钮 | ✅ |
| **M7 整页 AI** | `page_applyShell` + `/views/{id}` + 页面壳占位替换 + 形态澄清 | `applyPageShell`、`/views/{id}`、`renderPage` 占位→表单、意图提示词形态澄清 | ✅ |

> ⚠️ 实时 AI 对话端到端依赖 LLM 提供商（DeepSeek 曾长期 503 繁忙）；`LlmRouter` 已加 429/5xx 瞬时重试，提供商恢复后即可完整跑通。

---

## 7. 风险与对策

| 风险 | 对策 |
|------|------|
| AI 输出的布局指令非法 / 字段名错 | 服务端 schema 严格校验；不合法则回退"配置级换肤"（B），不写坏字段 |
| AI 想新增/删除字段 | `form_applyLayout` 强制字段集合 == 绑定字段集合，否则拒绝并提示 |
| 复杂控件（实体下拉 options、开关）重渲染后丢数据 | 一律走 `FormFieldRenderer` 渲染，不手写；回归测试覆盖 `parent`（entity select）、`loginIndependent/state`（switch）、`remark`（textarea） |
| Twind 把 class 打乱 | 沿用既有约束：所有样式走 inline style，布局用 `section_config`/config 驱动，AI 不写 class |
| 通用 `writeDesign` 路径误用于表单 | 路由层限制：`form` 意图强制走 `form_applyLayout` / `page_applyShell`，不走文件整页重写（页面壳除外，但字段控件仍由渲染器注入） |
| 主题只改一个色、视觉无变化 | 提示词要求主题"多维度落地"（主色/背景/卡片/必填底色），并自检 |
| 表单片段嵌入后样式/CSRF 冲突（同一页多个 form） | `view_form` 支持命名空间/前缀；CSRF token 由每个 `FormFieldRenderer` 实例各自生成，互不冲突 |
| 页面壳渲染与真实数据绑定脱节 | 页面壳仅承载静态内容与布局，所有动态/绑定内容走 `view_form` 注入，职责单一 |
| `view_form` 被滥用（循环引用/嵌套） | 解析引用关系，禁止循环嵌套；同一页嵌入同一表单只渲染一次 |

---

## 8. 验收标准

针对 `company_edit_form`（Company 实体，9 字段）：

### 8.1 表单美化（字段保真）

1. 在编辑器 AI 聊天输入：「把表单分组美化，做成两个卡片、双列，换个专业蓝色主题」。
2. 澄清或直接执行后，结果：
   - 表单出现分组卡片 + 分组标题；双列布局，短字段并排、备注跨整行；
   - 主色/背景/必填底色肉眼可见改变；
   - **9 个字段全部保留**，`name`/`id`/`data-field-name` 绑定不变；
   - 实体下拉 `parent` 仍能展开选择、开关字段可用、文本域正常、CSRF token 存在、提交/返回按钮可用；
   - 可「回退到此」恢复美化前状态。
3. 慢指令（如换主题）在合理时间内完成，页面自动刷新展示新表单，执行日志可查。

### 8.2 单页整站式（page 形态）

4. 切换形态为 `page`，AI 指令：「把公司编辑做成一个独立页面，顶部 Hero 标题『企业管理』，左侧说明、右侧表单，蓝色商务风」。
   - 产出完整业务页：Hero/静态说明/表单区/页脚，布局与样式符合指令；
   - 表单区字段控件正确（同上 8.1 全项）；
   - 通过 `/views/{id}` 能独立访问该页面，提交正常。

### 8.3 可复用表单片段（fragment 形态）

5. 切换形态为 `fragment`，AI 指令：「把公司表单分两组、双列、清爽主题」。
   - 表单以 `<form>` 片段产出；
   - 在另一个视图/模板（或 `companyEdit.html.twig`）中写 `{{ view_form(viewId, entity) }}` 即可嵌入，字段/CSRF/提交全部正常；
   - 修改该表单某字段（如把 `code` 设为必填），所有嵌入页面刷新后同步变化；
   - 同一页多个表单各自 CSRF 正常、互不冲突。

---

## 9. 相关文件与组件

| 模块 | 位置 |
|------|------|
| 子代理（表单） | `src/Service/AI/Orchestrator/SubAgent/FormViewSubAgent.php` |
| 领域提示词 | `src/Service/AI/Runtime/view_sub_agent_form.md` |
| 意图判定 | `src/Service/AI/Runtime/view_enhance_intent_prompt.md` + `IntentAgent` |
| 路由/聊天 | `src/Service/AI/Orchestrator/ViewSubAgentChatRouter.php`、`src/Controller/Api/Admin/AiChatController.php` |
| 表单渲染管线 | `src/Service/Form/FormFieldRenderer.php`、`templates/admin/platform/form/_form_fields.html.twig` |
| 工具集 | `src/Service/AI/Tool/ViewEditorToolProvider.php`、`ViewFileToolProvider.php`（新增 `form_applyLayout`、`view_getFormStructure`、`page_applyShell` 等） |
| Twig 表单片段 | 新增 `Twig\Extension`：`view_form(viewId, data, options)` |
| 页面渲染入口 | 新增视图页面路由（`/views/{id}`）与页面壳渲染服务 |
| 前端面板 | `templates/admin/ai_chat.html.twig`、`templates/admin/platform/view/editor_components.html.twig` |
| 版本/回退 | `src/Service/Platform/View/`（ViewVersionHistory、ViewCheckpointService） |
| 生产宿主示例 | `src/Controller/Admin/OrgController.php`、`templates/admin/org/companyEdit.html.twig`（迁移到 `view_form`） |

---

## 10. 开发方案（实现细节与落地步骤）

> 本节给出可直接照做的实现清单：每层的类/方法/接口签名、数据模型变化、迁移、AI 提示词改动、前端接线与测试，并映射到 §6 里程碑。

### 10.1 落地架构分层

```
┌─ 数据层 ──────────────────────────────────────────────┐
│ ViewField.config / section_config(含 theme,render_mode)│  ← 结构化真相
│ var/data/view_editor_config.json(全局 generalConfig)   │  ← 全局兜底主题
└──────────────────────┬────────────────────────────────┘
                       │
┌─ 服务层（新增/扩展）─▼─────────────────────────────────┐
│ FormLayoutService       applyLayout / renderFragment   │
│ ViewFormExtension(Twig) view_form(viewId,data,options) │
│ PageShellService        saveShell / renderPage         │
└──────────────────────┬────────────────────────────────┘
                       │
┌─ AI 工具层 ──────────▼────────────────────────────────┐
│ view_getFormStructure / form_applyLayout              │
│ view_updateFieldConfig(扩展) / view_updateSectionConfig(扩展)│
│ page_applyShell                                       │
└──────────────────────┬────────────────────────────────┘
                       │
┌─ AI 编排层 ──────────▼────────────────────────────────┐
│ IntentAgent(form,redesign,澄清,形态) → ViewSubAgentChatRouter│
│ → FormViewSubAgent(表单专用工具集，禁 writeDesign)      │
└───────────────────────────────────────────────────────┘
```

### 10.2 数据层改动

**`section_config`（`platform_view.section_config` JSON，无需迁移表结构）**：

```jsonc
{
  "contentWidth": "boxed",
  "width": 960, "unit": "px", "columns": 2, "gap": 24, "labelWidth": 8,
  "fieldLayout": "horizontal",
  "render_mode": "page",            // page | fragment（新增）
  "theme": {                        // 视图级主题（新增，优先于全局 generalConfig）
    "primary": "#4f46e5", "pageBg": "#f8fafc", "cardBg": "#ffffff",
    "labelColor": "#1e293b", "inputBg": "#ffffff",
    "requiredBg": "#fde8e8", "regularBg": "#ffffff",
    "radius": 8, "fontSize": 14
  }
}
```

- **主题合并规则**：渲染时 `theme = view.section_config.theme ?? globalConfig`（视图级优先、全局 `var/data/view_editor_config.json` 兜底）。`FormFieldRenderer` 已接受 `$generalConfig` 参数，只需把合并后的数组传入。
- **`ViewField.config`**：沿用现有字段；`view_updateFieldConfig` 需能写 `sectionHeader`、`sectionDivider`、`regularBg`、`requiredBg`、`choices`。`sortOrder` 决定字段顺序（分组重排时更新）。
- **页面壳存储**：复用 `design.twig`。`render_mode=page` 时 `.section-content` 内部 = AI 生成的**页面壳**（静态 HTML + `<div data-view-form="{viewId}"></div>` 占位）；`fragment` 时 = 纯 `<form>`。无需新表，天然走现有版本/检查点。

### 10.3 服务层（新增/扩展）

**① `src/Service/Form/FormLayoutService.php`（新增）**

```php
final class FormLayoutService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FormFieldRenderer $renderer,
        private ViewPathResolver $pathResolver,
        private DomManipulator $dom,
    ) {}

    /** 校验并应用布局指令：写字段/分组/section/主题 → 重渲染 design */
    public function applyLayout(View $view, string $version, array $layout): array;
    // 步骤：校验字段集合==绑定集合 → 按 groups 更新 sortOrder/config(sectionHeader/sectionDivider/colSpan/…) 
    //       → 写 section_config(columns/fieldLayout/gap/theme/render_mode) → renderer->render() 生成 <form>
    //       → 套回 canvas 骨架(section/section-content/操作区) → 写 design.twig → renderHtml

    /** 渲染表单片段（fragment 用；供 view_form 与 /api/views/{id}/form 使用） */
    public function renderFragment(View $view, object $data, array $options = []): string;
    // 校验 render_mode 或按调用方指定 → renderer->render(editorMode=false, formAttr, 合并主题)

    /** 渲染完整页面（page 用）：读设计文件 → 替换 data-view-form 占位 → 包页面布局 */
    public function renderPage(View $view, object $data, array $options = []): string;
}
```

**② `src/Twig/Extension/ViewFormExtension.php`（新增）** → 注册为 `twig.extension`：

```php
final class ViewFormExtension extends AbstractExtension
{
    public function getFunctions(): array
    { return [new TwigFunction('view_form', [$this, 'renderForm'], ['is_safe' => ['html']])]; }

    public function renderForm(string $viewId, ?object $data = null, array $options = []): string
    { /* 查 View → FormLayoutService::renderFragment（自动检测 render_mode；page 时仅渲染表单片段） */ }
}
```

**③ `src/Service/Form/PageShellService.php`（新增）**：负责页面壳占位替换、与宿主布局的装配；简单时可并入 `FormLayoutService`，仅作职责拆分。

**④ 控制器/路由（新增）**：
- `GET /views/{id}` → 渲染该视图页面（`FormLayoutService::renderPage` 输出，包在 `base` 页布局内）——供 page 形态独立访问。
- `GET /api/views/{id}/form?data=...` → 返回表单片段 HTML（非 Twig 宿主/异步引入）。
- `POST /api/admin/ai/...`：`form_applyLayout`、`page_applyShell` 走既有 AI 工具执行器，无需新路由。

### 10.4 AI 工具层

**`ViewEditorToolProvider`（扩展 + 新增）**：

| 工具方法 | 说明 |
|---|---|
| `getFormStructure(string $viewId)`（新增） | 返回：绑定字段（name/type/label/config/排序）、`section_config`、`theme`、`render_mode`、被引用宿主（可选）；AI 决策输入 |
| `applyFormLayout(string $viewId, array $layout)`（新增） | 转发 `FormLayoutService::applyLayout`（含 schema 校验），返回结果 + 重渲染后的字段摘要 |
| `applyPageShell(string $viewId, string $html)`（新增） | 写页面壳到 design（允许静态 HTML + `data-view-form` 占位），再 `renderHtml` |
| `updateFieldConfig(...)`（扩展） | 增加 `sectionHeader`、`sectionDivider`、`regularBg`、`requiredBg`、`choices` 参数 |
| `updateSectionConfig(...)`（扩展） | 增加 `gap`、`labelWidth`、`renderMode` 参数 |

**表单工具集白名单**：表单意图的 `tools` 固定为 `[getFormStructure, applyFormLayout, applyPageShell, updateFieldConfig, updateSectionConfig, view_getInfo, view_listEntityFields]`，**不包含** `viewfile_writeDesign`——从工具层根除"AI 手写整段表单 HTML"的路径。

### 10.5 AI 编排层

- **`view_enhance_intent_prompt.md`**：`form` 意图的 `plan` 增加"是否独立成页/是否复用"要点；`redesign=true` 且未说明形态时 `needs_clarification=true`，澄清问题含"独立成页 / 可复用片段"。
- **`view_sub_agent_form.md`（增强）**：加入 §4.5 铁律 + 输出 `layoutSpec` 的格式样例 + 形态说明 + 主题"多维度落地"自检。
- **`ViewSubAgentChatRouter::classifyTurn`**：`intent=form` 时返回表单专用工具集与 `FormViewSubAgent::systemPromptForFormLayout()`（新增，封装 SHARED_CONSTRAINTS + 表单领域 + 布局指令协议）。

### 10.6 渲染层接线

- **编辑器**：`form_applyLayout` 成功后 `FormLayoutService` 把重渲染的 `<form>` 套回 canvas 骨架并写 `design.twig` + `renderHtml`；编辑器自动刷新（复用 `redesignApplied`）。
- **生产 fragment**：宿主模板 `{{ view_form(viewId, entity, {submit_label, cancel_href}) }}` 输出 `<form>` 片段。
- **生产 page**：`/views/{id}` → `renderPage`：读 design → `data-view-form` 占位换成 `renderFragment` → 包页面布局。
- **现有模板迁移**：`OrgController::editCompany` 将 `dynamicFormHtml` 替换为 `view_form(viewId, $company)`；`companyEdit.html.twig` 保留宿主外壳。

### 10.7 前端层

- `templates/admin/ai_chat.html.twig`：表单上下文下显示快捷指令（「分组成卡片」「改成双列」「清爽主题」「独立成页/表单片段」）；`redesignApplied` 自动刷新。
- 编辑器形态切换 UI（`editor_components.html.twig` / 工具栏）：page/fragment 切换按钮，切换只改 `section_config.render_mode`，不动字段真相。
- 数据源面板联动：字段名一致，AI 引用字段名可点击定位。

### 10.8 里程碑 → 开发步骤映射

| 里程碑 | 落地步骤（文件/接口） |
|---|---|
| **M1 管线** | `FormLayoutService` + `getFormStructure` + `applyFormLayout`；用手动 layout JSON 在 `company_edit_form` 验证分组双列可用 |
| **M2 AI 生成** | `view_sub_agent_form.md` 增强 + `systemPromptForFormLayout` + `classifyTurn` 工具集白名单 + schema 校验回退 B |
| **M3 主题** | `section_config.theme` + 合并规则 + `_form_fields.html.twig` 消费主题变量 + `updateFieldConfig` 扩展配色 |
| **M4 前端** | 快捷指令 + 自动刷新 + 数据源联动 |
| **M5 校验** | 字段集合校验、CSRF/提交语义回归、复杂控件（entity select/switch/textarea）回归矩阵 |
| **M6 形态** | `section_config.render_mode` + `ViewFormExtension(view_form)` + `/api/views/{id}/form` + 编辑器形态切换 UI |
| **M7 整页** | `applyPageShell` + `/views/{id}` + `renderPage` 占位替换 + 形态澄清 |

### 10.9 测试与验证

**回归矩阵（`company_edit_form`，9 字段）**：

| 用例 | 断言 |
|---|---|
| `form_applyLayout`（分组+双列+主题） | 9 字段全保留；`name`/`id`/`data-field-name` 不变；可提交 |
| `parent`（entity select） | 下拉仍可展开、选项完整 |
| `loginIndependent`/`state`（switch） | 开关可切换、hidden input 保留 |
| `remark`（textarea） | 文本域正常、rows 生效 |
| CSRF | `form[_token]` 存在且同页多表单互不冲突 |
| 非法 layout（字段名错/缺字段） | 拒绝并回退配置级换肤，不写坏字段 |
| `view_form` 嵌入 + 改必填 | 所有嵌入页刷新后同步 |
| `/views/{id}` page 形态 | 独立访问、Hero/静态区/表单齐全、提交正常 |
| undo/redo、检查点回退 | 美化前后状态可完整还原 |

**命令/工具**：`bin/console lint:twig` 校验生成模板；临时命令手动喂 layout JSON 验证管线；CDP 浏览器端到端。

### 10.10 风险落地（开发期控制点）

- `FormLayoutService::applyLayout` 内**先校验后写入**（字段集合、值类型），写入包事务，异常整体回滚。
- 表单工具集白名单放在 `classifyTurn`，并用工具层 `name` 校验兜底（非白名单工具直接报错）。
- 主题合并函数单测覆盖"视图级优先 / 全局兜底"。
- `view_form` 加循环引用探测与"每页每表单渲染一次"去重（请求作用域缓存）。
