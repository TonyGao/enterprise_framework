# AI-Enhanced View Editor — Tailwind CSS 布局辅助方案

## 1. 动机与目标

### 当前局限
- **属性面板编辑效率低**：字号、颜色、边距等每项需手动选择/输入，频繁切换面板
- **样式组合复杂度高**：要实现一个美观的卡片、按钮、表单布局，需要逐个调整数十个属性
- **布局能力弱**：现有 Section/Column 体系可以满足基础布局，但灵活嵌套、响应式排列需要大量手动拖拽
- **AI 能力缺失**：有 LLM 配置管理体系（`ai-config.md`），但尚未与视图设计器集成

### 目标
1. 引入 **Tailwind CSS utility class** 作为布局辅助工具，弥补属性面板在快速布局上的不足
2. 通过 **AI 接口**（基于 `ai-config.md` 的 LlmRole/LlmProvider 体系）实现自然语言驱动的样式调整和布局生成
3. **不放弃现有架构**：保留属性面板精细调节、拖拽布局、FormFieldRenderer 等现有能力，Tailwind 和 AI 作为快速构建的入口
4. 适配 **传统 MVC + Twig 项目** 的约束：无构建步骤、无前后端分离、利用 Twig 模板继承与变量渲染

---

## 2. 核心设计原则

### 2.1 运行时 Tailwind（无编译方案）

```
视图设计器 HTML → Tailwind class 字符串 → Twind 运行时解析 → 最终 CSS
```

选择 **Twind** (https://github.com/tw-in-js/twind) 而非 PostCSS 编译方案：

| 方案 | 是否适合本项目 | 原因 |
|------|:---:|------|
| PostCSS + Tailwind CLI | ❌ | 视图 HTML 运行时动态生成，构建时无法扫描 class |
| Tailwind CDN (Play CDN) | ⚠️ | 简单但依赖网络，管理界面可用但生产前端不建议 |
| **Twind** | ✅ | 纯 JS 运行时，按需解析，嵌入项目 JS 中，无网络依赖 |

Twind 仅在 **管理后台**（视图编辑器页面）中加载，生产前端的 `.html.twig` 页面如何消费 Tailwind class 见 §2.3。

### 2.2 样式共存策略

**现有样式体系** 完全保留，Tailwind 作为补充：

```
单个元素可能同时包含：
  class="ef-input ef-input-rounded text-base font-medium"   ← Tailwind 只负责布局/尺寸
  style="background-color: #ffe599;"                         ← 现有 inline style 负责视觉精确控制
```

| 类别 | 用什么控制 | 理由 |
|------|-----------|------|
| 布局（flex/grid/间距/宽高/对齐） | Tailwind class | utility 书写快、组合灵活，AI 易生成 |
| 颜色/字体/圆角/阴影 | inline style 或 Tailwind class（按需） | 颜色精确值需属性面板微调，Tailwind 色板作快捷选择 |
| 组件专属样式（ef-form-* 等） | 现有 CSS 框架 | 不可替代，保持现有组件行为和样式 |

### 2.3 生产前端渲染策略

视图编辑器的产物是 `.design.twig`（编辑态）和 `.html.twig`（生产态）。生产前端渲染时有三个选项：

| 选项 | 做法 | 适用场景 |
|------|------|---------|
| A. 只存不用 | 编辑器中使用 Tailwind 辅助快速布局，保存时将 Tailwind class **转换为 inline style** 再写入 `.design.twig` | 生产前端完全不引入 Tailwind，最轻量 |
| B. 运行时解析 | `base.html.twig` 中加载 `<script src="https://cdn.tailwindcss.com">` 或 Twind | 生产前端也受益于 Tailwind utility |
| C. 混合 | `.html.twig` 保留 Tailwind class，后台管理布局用 CDN，前端的 `.html.twig` 路径下选择性加载 CDN | 灵活控制 |

**推荐方案**：**选项 C**。管理后台（视图编辑器）始终加载 Twind 确保编辑预览正确；生产前端根据视图实际使用场景决定是否加载 Tailwind CDN（可通过 `generalConfig` 配置控制）。这样：
- 编辑体验始终一致（Twind 解析所有 Tailwind class）
- 生产前端最小化加载（不需要 Tailwind 的页面不加载 CDN）
- 无构建步骤，不改变部署流程

### 2.4 存储格式

`.design.twig` 中的元素数据模型扩展：

```html
<!-- 当前格式：纯 inline style -->
<span style="font-size: 16px; color: #333; padding: 8px;">文本</span>

<!-- 扩展格式：同时支持 class + inline style -->
<span class="text-base p-2" style="color: #333;">文本</span>

<!-- 保存时视图的元数据附加字段（存于 View.sectionConfig 同级或 ViewField.config） -->
{
  "tailwindEnabled": true,
  "tailwindTheme": "default"
}
```

**转换规则（选项 A 时需要）**：
- 保存时，如果该项目设置为「不引入 Tailwind」，`DomManipulator` 负责将 Tailwind class 推导为 inline style
- 如果设置为「引入 Tailwind」，则保留 class 属性，`DomManipulator` 不移除

---

## 3. AI 接口集成方案

### 3.1 与 ai-config.md 的关系

AI 调用不自行管理 API Key 和模型配置，而是复用 `ai-config.md` 中设计的 **LlmRole / LlmProvider 体系**：

```
前端 AJAX → POST /api/admin/ai/chat
  └─ role: "view_editor"    ← 对应 ai-config.md §3 LlmRole.code
  └─ messages: [...]        ← 用户对话 + 上下文
      ↓
后端 LlmRouter.chatByRole("view_editor", messages)
  ├─ 查询 LlmRole(code=view_editor) → 获取绑定的 LlmProvider
  ├─ LlmGatewayFactory.create(provider) → 实例化对应适配器
  ├─ 调用 LLM API（API Key 由 LlmEncryptor 解密，仅存于服务端）
  └─ 返回结构化的操作指令或文本响应
```

**要点**：
- API Key **仅存于服务端**（数据库加密存储），前端不接触
- 模型切换在管理界面完成（`/admin/platform/llm-config`），无需改代码
- 多厂商支持（OpenAI / Anthropic / Ollama / 自定义）由 `LlmGatewayFactory` 自动路由
- 角色级 System Prompt 由 `LlmRole.systemPrompt` 管理，编辑器中可自定义

### 3.2 AI 能力范围

| 能力 | 说明 | 输入 | 输出格式 |
|------|------|------|---------|
| **样式调整** | 修改选中元素样式 | "把这个按钮改成蓝色圆角大号" | Tailwind class + inline style 更新指令 |
| **组件生成** | 生成完整组件 HTML | "一个带图标和副标题的卡片" | 可插入画布的 HTML 片段（含 Tailwind class） |
| **布局生成** | 生成多区布局 | "三列布局，左侧导航，中间内容" | Section + Column HTML 结构 |
| **样式统一** | 批量修改 | "把所有按钮改成 primary 风格" | 批量 class 替换指令 |
| **解释/优化** | 分析并建议 | "这个卡片怎么改更好看" | 建议文本 + 可选修改后的 HTML |

### 3.3 交互入口

在视图编辑器的右侧面板中增加 **AI 标签页**，与现有属性面板同级切换：

```
┌──────────────────────────────┐
│  Canvas                      │
│                              │
│  ┌──────────────────────┐    │
│  │  [属性] [AI] ← 新增  │    │  ← 面板选项卡
│  └──────────────────────┘    │
│                              │
│   AI 助手面板：              │
│   ┌──────────────────────┐   │
│   │ 💬 "把这段文字改成    │   │
│   │ 标题样式，大号蓝色"   │   │
│   │ [发送]                │   │
│   ├──────────────────────┤   │
│   │ 快捷模板：            │   │
│   │ [卡片] [表单] [表格]  │   │
│   │ [导航] [页脚]         │   │
│   ├──────────────────────┤   │
│   │ 最近操作：            │   │
│   │ • 14:23 修改卡片样式  │   │
│   │ • 14:20 生成三列表格  │   │
│   │ [回滚]                │   │
│   └──────────────────────┘   │
└──────────────────────────────┘
```

交互方式（按优先级）：
1. **AI 助手面板** — 右侧面板 AI 标签页，自然语言输入 + 上下文自动附加
2. **选中元素 + AI 操作** — 选中元素后，AI 理解其结构和现有 class，增量修改
3. **预设 Prompt 快捷按钮** — 快速插入常见组件模板

### 3.4 上下文传递

每次 AI 调用时，前端自动附加当前选中元素的上下文：

```javascript
function buildContext() {
  const $el = getSelectedElement();
  return {
    tagName: $el.prop('tagName').toLowerCase(),
    currentClasses: $el.attr('class') || '',
    currentStyles: $el.attr('style') || '',
    innerText: $el.text().substring(0, 100),
    childCount: $el.children().length,
    // 视图整体摘要（非完整 HTML，避免泄露和数据过大）
    viewSummary: getViewStructureSummary(),
  };
}
```

### 3.5 System Prompt 设计

System Prompt 存储在 `LlmRole` 实体的 `systemPrompt` 字段中，管理界面可编辑。默认值：

```
你是一个视图设计器 AI 助手。当前视图使用以下技术栈：
- Twig 模板（服务端渲染）
- 自定义 CSS 框架（ef-form-*, ef-input*, ef-btn* 等组件）
- Tailwind CSS utility class（用于布局辅助）

可用操作：
1. updateStyles(tailwindClasses, inlineStyles)
   - 修改选中元素的样式，优先返回 Tailwind class
   - class 无法精确表达时（如特定色值），使用 inline style
   - 布局类（flex/grid/p/m/w/h/gap）优先用 Tailwind

2. insertHTML(position, html)
   - 在选中元素附近插入新 HTML，使用 Tailwind class 描述布局
   - position: "before" | "after" | "append" | "prepend"

3. replaceHTML(html)
   - 用新的 HTML 替换选中元素

注意事项：
- 保留现有的 ef-form-* 组件 class，只修改布局/视觉 class
- 不要移除 data-* 属性，它们是视图编辑器工作所必需的
- 字体映射：text-xs(12px) text-sm(14px) text-base(16px) text-lg(18px) text-xl(20px) text-2xl(24px)
- 间距映射：p/m-1(4px) p/m-2(8px) p/m-3(12px) p/m-4(16px) p/m-5(20px) p/m-6(24px)
- 颜色优先使用 inline style（保持与属性面板颜色选择器兼容）
```

### 3.6 Tool Use 格式（OpenAI Function Calling）

```json
{
  "tools": [
    {
      "type": "function",
      "function": {
        "name": "updateStyles",
        "description": "修改选中元素的 style class 或 inline style",
        "parameters": {
          "type": "object",
          "properties": {
            "tailwindClasses": {
              "type": "string",
              "description": "Tailwind utility classes，仅布局/尺寸/间距类"
            },
            "inlineStyles": {
              "type": "object",
              "description": "CSS 属性键值对，用于颜色等精确值"
            }
          }
        }
      }
    },
    {
      "type": "function",
      "function": {
        "name": "insertHTML",
        "description": "在指定位置插入新组件 HTML",
        "parameters": {
          "type": "object",
          "properties": {
            "position": { "type": "string", "enum": ["before", "after", "append", "prepend"] },
            "html": { "type": "string", "description": "完整 HTML 片段，使用 Tailwind class 描述布局" }
          }
        }
      }
    },
    {
      "type": "function",
      "function": {
        "name": "replaceHTML",
        "description": "替换选中元素为新的 HTML",
        "parameters": {
          "type": "object",
          "properties": {
            "html": { "type": "string", "description": "新的 HTML" }
          }
        }
      }
    }
  ]
}
```

### 3.7 调用流程详图

```
用户在 AI 面板输入 Prompt
    │
    ▼
前端收集上下文（选中元素结构、现有 class/style、视图摘要）
    │
    ▼
POST /api/admin/ai/chat  { role: "view_editor", messages: [...] }
    │
    ▼
LlmRouter.chatByRole("view_editor", messages)
    ├─ 从 DB 读取 LlmRole(code=view_editor)
    ├─ 获取绑定的 LlmProvider → 解密 API Key
    ├─ LlmGatewayFactory.create(provider) → 对应适配器
    ├─ 前置 System Prompt（来自 LlmRole.systemPrompt）
    └─ 调用 LLM API（OpenAI / Anthropic / Ollama 等）
    │
    ▼
LLM 返回 Tool Call（updateStyles / insertHTML / replaceHTML）
    │
    ▼
后端透传 Tool Call 给前端
    │
    ▼
前端 JS 执行 Tool Call：
  - updateStyles → 修改选中元素 class/style 属性
  - insertHTML → 在指定位置插入 HTML
  - replaceHTML → 替换元素
    │
    ▼
UndoRedoManager 记录本次 AI 操作（可 Ctrl+Z 回退）
```

---

## 4. 与现有架构的集成

### 4.1 编辑器改动范围

| 模块 | 改动 |
|------|------|
| `view_editor_core.js` | 加载 Twind（`<script src="twind.umd.min.js">`），初始化 `twind.setup()` |
| `editor_toolbar.js` | 新增 `aiPanel.js` 作为 AI 助手面板，Tool Call 执行函数 |
| 属性面板 (`text_component_properties.js`) | 各字段修改时优先尝试映射 Tailwind class |
| 保存流程 (`editor_toolbar.js saveView`) | 保存时根据配置决定是否保留 Tailwind class |
| 加载流程 (`ViewEditorController.php`) | 加载 `.design.twig` 后，Twind 自动解析其中的 class |

### 4.2 Twig 模板侧的考虑

由于 `.design.twig` 存储的是富 HTML（含 Tailwind class），渲染到生产前端时：

**当前渲染流程**：
```
FormFieldRenderer → HTML（inline style）→ 存入 .design.twig
                                            ↓
                                      DomManipulator 清理
                                            ↓
                                      .html.twig（生产态）
```

**加入 Tailwind 后的渲染流程**：
```
FormFieldRenderer → HTML（inline style + Tailwind class）→ 存入 .design.twig
                                                              ↓
                                                        DomManipulator 清理
                                                              ↓
                                                    ┌─ Tailwind 模式：保留 class → .html.twig
                                                    └─ 非 Tailwind 模式：class → inline style → .html.twig
```

**Twig 模板中加载 Tailwind**（按需）：
```twig
{# base.html.twig 或特定视图模板 #}
{% if tailwind_enabled %}
  <script src="https://cdn.tailwindcss.com"></script>
{% endif %}
```

或通过 `generalConfig` 全局配置控制：
```twig
{# 在管理后台编辑器中始终加载 #}
{% if is_granted('ROLE_ADMIN') %}
  <script src="https://cdn.tailwindcss.com"></script>
{% endif %}
```

### 4.3 FormFieldRenderer 兼容性

FormFieldRenderer 生成的组件 HTML 保持现有结构不变，仅扩展 class 属性：

```php
// 当前输出
$html = '<span class="ef-input-wrapper ef-input-rounded" style="height: 36px;">';

// Tailwind 模式输出（class 中追加布局 utility）
$html = '<span class="ef-input-wrapper ef-input-rounded flex items-center w-full" style="height: 36px;">';
```

`FormFieldRenderer` 可增加一个 `$tailwindClasses` 参数，由视图配置决定是否追加：

```php
public function render(View $view, $data, bool $isEditor = false, array $extra = [], ?array $generalConfig = null): array
{
    $tailwindEnabled = $generalConfig['tailwindEnabled'] ?? false;
    // ... 渲染过程中根据 $tailwindEnabled 追加布局 class
}
```

### 4.4 AI 操作与 UndoRedoManager 集成

每个 AI 操作对应一个 UndoRedo AtomicAction：

```javascript
// AI 操作封装
function executeAIAction(toolCalls) {
  const snapshot = $('#canvas').html();  // 操作前快照
  
  toolCalls.forEach(call => {
    switch (call.function.name) {
      case 'updateStyles': applyToolUpdateStyles(call.function.arguments); break;
      case 'insertHTML': applyToolInsertHTML(call.function.arguments); break;
      case 'replaceHTML': applyToolReplaceHTML(call.function.arguments); break;
    }
  });
  
  // 注册到 UndoRedoManager
  undoRedoManager.pushAction({
    type: 'ai',
    description: 'AI 操作',
    undo: () => { $('#canvas').html(snapshot); reinitControls(); },
    redo: () => { /* 重新执行 */ },
  });
}
```

---

## 5. 实现路径

### Phase 1: Tailwind 运行时集成（2-3 天）

1. 在视图编辑器页面加载 Twind（`<script src="/sunui/lib/twind.umd.min.js">`）
2. 初始化 Twind：`twind.setup({ preflight: false })`（关闭 preflight 防止与现有 CSS 冲突）
3. 属性面板中增加 **"布局辅助"** 快捷面板（flex/grid/间距/宽高 的快速按钮组）
4. 属性面板中 CSS class 输入框（直接编辑 class 字符串，Twind 实时解析）
5. 保存流程：`DomManipulator` 增加 tailwind → inline style 转换能力（选项 A）
6. `generalConfig` 增加 `tailwindEnabled` 开关

### Phase 2: AI 助手面板（3-5 天）

1. 确认 `ai-config.md` Phase 1 已完成（LlmProvider/LlmRole CRUD + 加密）
2. 实现 `ai-config.md` Phase 2（LlmGatewayInterface + OpenAI/Anthropic/Ollama 适配器 + LlmRouter）
3. 实现 `ai-config.md` Phase 3 的 `/api/admin/ai/chat` 端点（LlmChatController）
4. AI 助手面板 UI（右侧面板新增 AI 标签页）
5. 上下文自动收集 + Tool Call 执行函数
6. UndoRedoManager AI 操作集成

### Phase 3: 高级能力（5-7 天）

1. 组件生成（insertHTML）— AI 生成含 Tailwind 布局 class 的组件
2. 布局生成 — 多列/多区自动布局
3. 批量样式统一
4. 预设 Prompt 模板库
5. inline style ↔ Tailwind class 双向映射表完善

### Phase 4: 生产前端适配（持续）

1. 根据 `generalConfig.tailwindEnabled` 决定生产前端是否加载 Tailwind CDN
2. `DomManipulator` tailwind → inline style 转换完善
3. 自定义 Tailwind theme 匹配企业品牌色（通过 `twind.setup({ theme: {...} })`）
4. 性能优化：Twind 按需解析 vs 预生成 CSS

---

## 6. 与 ai-config.md 的分工

| 层面 | ai-config.md 负责 | ai-view.md 负责 |
|------|------------------|----------------|
| LLM 厂商管理 | LlmProvider 实体 + CRUD + 加密存储 | — |
| LLM 角色/用途 | LlmRole 实体 + 默认角色数据 | view_editor 角色的 System Prompt 设计 |
| 后端调用链 | LlmGatewayInterface → 适配器 → LlmRouter | /api/admin/ai/chat 端点的前端调用约定 |
| API Key | 加密 + 解密 + 掩码回显 | 仅使用已解密的 API Key（不接触原始 key） |
| Failover | LlmRouter.chatByRoleWithFallback | 前端无感，由后端自动降级 |
| 审计日志 | LlmAuditLog 实体 | 触发时机（每次 AI 调用） |
| 限流 | Rate Limiter 配置 | — |
| 前端调用 | — | AI 助手面板 UI + Tool Call 执行 + UndoRedo |

---

## 7. 关键设计决策

### 7.1 为什么不直接用 Tailwind CLI / PostCSS 方案

- 视图 HTML 是 **运行时动态生成** 的，Tailwind JIT 需要在构建时扫描 class 字符串，无法覆盖编辑器生成的动态内容
- 本项目是 **传统 MVC**，没有前端构建流程，引入 Webpack/Vite 等工具会增加不必要的复杂度
- Twind 运行时方案在编辑器场景下更自然：class 字符串写入 DOM → Twind 即时解析 → 样式生效

### 7.2 为什么 Tailwind 只辅助布局，不接管全栈样式

- 现有 CSS 框架（`ef-form-*` 组件体系）已经成熟且经过测试，替换成本高
- 颜色、字体等视觉属性在现有属性面板中有精确的颜色选择器、字号下拉等控件，Tailwind 的有限色板/字号列表无法满足
- Tailwind 的 **flex/grid/间距/宽高/对齐** 等布局 utility 正是当前属性面板的短板，两者互补

### 7.3 安全性考虑

- 所有 LLM 调用经后端代理（`POST /api/admin/ai/chat`），API Key 不暴露给前端
- AI 返回的 HTML 片段需经过 DOMPurify 清洗后插入画布
- AI 仅接收视图结构摘要（非完整 HTML），避免敏感数据泄露
- Tool Call 执行范围限制：不允许 JS 执行、不允许修改非视图元素

---

## 8. 参考资源

- [Twind — 运行时 Tailwind](https://github.com/tw-in-js/twind)
- [Tailwind CSS Play CDN](https://tailwindcss.com/docs/installation/play-cdn)
- `documents/conceptions/viewEditor/ai-config.md` — LLM 配置管理体系
- [OpenAI Function Calling](https://platform.openai.com/docs/guides/function-calling)
- [Anthropic Tool Use](https://docs.anthropic.com/en/docs/build-with-claude/tool-use)
