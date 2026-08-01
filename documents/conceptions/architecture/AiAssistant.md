# AI Assistant Framework 实现文档

## 概述

本文档描述低代码平台 AI 助手的实际实现架构，基于 `symfony/ai-agent` + `symfony/ai-platform` 技术栈，对接平台自有 `LlmRouter` 网关体系。

---

## 1. 全局入口

AI 助手以浮动按钮 + 侧滑面板形式存在于所有页面。

### 渲染位置

`templates/base.html.twig` — 所有模板的基模板，在 `<body>` 末尾无条件 include：

```twig
{# templates/base.html.twig #}
<body>
    {% block body %}{% endblock %}
    {% include 'admin/ai_chat.html.twig' %}
</body>
```

### 静态资源

| 资源 | 路径 | 说明 |
|---|---|---|
| CSS | `public/sunui/admin/ai_chat.css` | 面板样式，浮动按钮、消息气泡、输入区 |
| 模板 | `templates/admin/ai_chat.html.twig` | 面板 HTML + 内联 JS |

CSS 通过 `base.html.twig` 的 `{% block css %}` 注入：

```twig
<link rel="stylesheet" type="text/css" href="{{ asset('ai_chat.css', 'admin') }}">
```

### 前端交互

JS 逻辑位于 `templates/admin/ai_chat.html.twig` 尾部 `<script>` 中：

- `toggle-ai-chat` 按钮（右下角浮动）打开面板
- `ai-chat-close` 关闭面板
- 输入框支持 Enter 发送，Shift+Enter 换行
- 请求期间禁用输入，显示"思考中..."加载态

---

## 2. 页面上下文注册

每页通过 `window.__AI_CONTEXT__` 向助手注册上下文。未注册时助手面板提示"当前页面未注册 AI 上下文"。

### 上下文格式

```js
window.__AI_CONTEXT__ = {
    name: 'view_editor',                  // 后端 AiContextProviderInterface::getName()
    buildPrompt: function(msg) {          // 可选，构造发送给后端的消息
        return '[当前视图ID: xxx]\n\n' + msg;
    },
};
```

### 视图编辑器注册示例

`templates/admin/platform/view/editor.html.twig`：

```twig
<script>
  window.__VIEW_ID__ = {{ id|json_encode|raw }};
  window.__AI_CONTEXT__ = {
      name: 'view_editor',
      buildPrompt: function(msg) {
          var vid = window.__VIEW_ID__;
          return vid ? '[当前视图ID: ' + vid + ']\n\n' + msg : msg;
      },
  };
</script>
```

---

## 3. 后端架构

### 数据流

```
Client POST /api/admin/ai/chat  { context, message }
  → AiChatController
    → AiContextRegistry::get(context)
      → AiContextProviderInterface (e.g. ViewEditorAiContext)
        → AiAssistant::chat(roleCode, systemPrompt, toolProviders, message)
          → Agent::call(MessageBag, options)
            → SystemPromptInputProcessor: inject system prompt
            → AgentProcessor::processInput: inject Tool[] into options
            → LlmRouterPlatform::invoke(model, MessageBag, options)
              → LlmRouter::chatByRole(roleCode, messages, opts)
                → LlmGatewayFactory::create(provider)
                  → OpenAiGateway::chat(messages, opts)
                    → HTTP POST to LLM API
            ← ToolCallResult or TextResult
            → AgentProcessor::processOutput
              → if ToolCallResult: execute tools, loop (ReAct)
              → else: return TextResult
          ← string response
    ← JSON { code: 200, data: { reply: "..." } }
```

### 文件清单

| 文件 | 职责 |
|---|---|
| `src/Controller/Api/Admin/AiChatController.php` | 接收 `{context, message}`，委派对应 Provider |
| `src/Service/AI/Runtime/AiContextRegistry.php` | 收集所有 `app.ai_context` 标签的服务，按 name 索引 |
| `src/Service/AI/Runtime/AiContextProviderInterface.php` | 上下文提供者接口 |
| `src/Service/AI/Runtime/AiAssistant.php` | 泛化 Agent 工厂，每次 chat() 创建新 Agent |
| `src/Service/AI/Runtime/LlmRouterPlatform.php` | `PlatformInterface` 桥接，MessageBag ↔ 数组，Tool ↔ OpenAI tools |
| `src/Service/AI/Runtime/ChatResultConverter.php` | `ResultConverterInterface`，ChatResponse 数据 → TextResult/ToolCallResult |
| `src/Service/AI/Runtime/view_editor_prompt.md` | 视图编辑器系统提示词 |
| `src/Service/AI/Context/ViewEditorAiContext.php` | 视图编辑器上下文提供者 |
| `src/Service/AI/Tool/ViewEditorToolProvider.php` | 视图编辑器 Tool 方法（`#[AsTool]`） |

---

## 4. 上下文提供者系统

### AiContextProviderInterface

```php
namespace App\Service\AI\Runtime;

interface AiContextProviderInterface
{
    public function getName(): string;                    // 上下文标识，对应 JS __AI_CONTEXT__.name
    public function getRoleCode(): string;                // LlmRouter 角色代码（如 'general'）
    public function getSystemPrompt(): string;            // Agent 系统提示词
    public function getToolProviders(): array;            // Tool 对象数组（含 #[AsTool] 方法）
}
```

### 注册

`config/services.yaml`：

```yaml
App\Service\AI\Runtime\AiContextRegistry:
    arguments:
        $contextProviders: !tagged_iterator app.ai_context

App\Service\AI\Context\ViewEditorAiContext:
    tags:
        - { name: 'app.ai_context' }
```

### 新增上下文步骤

1. 创建类实现 `AiContextProviderInterface`
2. 在 `services.yaml` 中添加 `tags: [{ name: 'app.ai_context' }]`
3. 在页面模板中设置 `window.__AI_CONTEXT__ = { name: 'your_name' }`

---

## 5. Agent 运行时

### AiAssistant 泛化服务

```php
class AiAssistant
{
    /**
     * @param array[] $history  [{role: string, content: string}, ...]
     * @return array{reply: string, history: array[]}
     */
    public function chat(
        string $roleCode,
        string $systemPrompt,
        array $toolProviders,
        string $userMessage,
        ?array $toolNames = null,
        array $history = [],       // 历史对话，跨请求保持上下文
    ): array;
}
```

每次 `chat()` 创建独立 `Agent` 实例，确保无状态污染：

```php
$platform = new LlmRouterPlatform($this->router, $this->converter);
$toolbox = new Toolbox($toolProviders);

$agent = new Agent(
    platform: $platform,
    model: $roleCode,
    inputProcessors: [
        new SystemPromptInputProcessor($systemPrompt),
        new AgentProcessor($toolbox),
    ],
    outputProcessors: [
        new AgentProcessor($toolbox),
    ],
    name: 'ai-assistant',
);
```

### ReAct 循环

`AgentProcessor` 同时作为输入/输出处理器：

- **processInput**：读取 `$options['tools']`，注入当前 Toolbox 的 Tool 元数据（或根据名称过滤）
- **processOutput**：检测结果为 `ToolCallResult` 时：
  1. 将 `ToolCall` 数组以 `AssistantMessage` 加入 `MessageBag`
  2. 执行每个 `ToolCall` → `ToolResult`，以 `ToolCallMessage` 加入 `MessageBag`
  3. 递归调用 `$this->agent->call($messages, $options)`
  4. 循环直到返回 `TextResult`

---

## 6. LLM 平台桥接

### LlmRouterPlatform

`PlatformInterface` 实现，桥接 `symfony/ai-agent` 与平台自有 `LlmRouter`：

```php
class LlmRouterPlatform implements PlatformInterface
{
    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        // 1. MessageBag → 消息数组
        // 2. Tool 对象 → OpenAI tools 格式
        // 3. 调 LlmRouter::chatByRole()
        // 4. ChatResponse → DeferredResult(ChatResultConverter, InMemoryRawResult)
    }
}
```

消息类型映射：

| Symfony AI Message | API role |
|---|---|
| `SystemMessage` | `system`（拼接为单条） |
| `UserMessage` | `user` |
| `AssistantMessage` | `assistant`（含 tool_calls 时序列化） |
| `ToolCallMessage` | `tool`（tool_call_id + content） |

### ChatResultConverter

`ResultConverterInterface` 实现，转换 API 返回数据：

- `tool_calls` 存在 → `ToolCallResult(ToolCall...)`
- 否则 → `TextResult(content)`

### OpenAiGateway 工具支持

`src/Service/Platform/Llm/Gateway/OpenAiGateway.php` 新增 `$payload['tools']` 传递：

```php
if (isset($opts['tools'])) {
    $payload['tools'] = $opts['tools'];
}
```

---

## 7. 工具体系

### #[AsTool] 注解

基于 `symfony/ai-agent` 的 `#[AsTool]` 属性标记工具方法：

```php
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

class ViewEditorToolProvider
{
    #[AsTool(name: 'view.getInfo', description: '获取视图的完整信息')]
    public function getViewInfo(string $viewId): array { ... }

    #[AsTool(name: 'view.updateSectionConfig', description: '更新视图的布局配置')]
    public function updateSectionConfig(string $viewId, string $contentWidth, ...): array { ... }
}
```

`Toolbox` 使用 `ReflectionToolFactory` 自动读取 `#[AsTool]` 生成 `Tool` 元数据。

### 当前视图编辑器工具

| Tool | 方法 | 功能 |
|---|---|---|
| `view.getInfo` | `getViewInfo(viewId)` | 视图信息（实体、字段、布局） |
| `view.updateSectionConfig` | `updateSectionConfig(viewId, contentWidth, ...)` | 更新布局配置 |
| `view.updateFieldConfig` | `updateFieldConfig(viewId, fieldName, ...)` | 更新字段配置 |
| `view.listEntityFields` | `listEntityFields(viewId)` | 列出实体字段 |

### 工具设计原则

- 每个工具是单一职责的方法
- 参数名与 `#[AsTool]` 的 JSON Schema 参数名一致（自动映射）
- 返回 `array`（转为工具结果文本）
- 使用 `#[AsTool(name, description, method)]` 注解，不使用接口

---

## 8. API 端点

### POST /api/admin/ai/chat

请求：

```json
{
    "context": "view_editor",
    "message": "帮我改成三列布局"
}
```

成功响应：

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "reply": "已修改为三列布局。"
    }
}
```

错误响应：

```json
{
    "code": 400,
    "message": "不支持的 AI 上下文: unknown_context",
    "data": null
}
```

---

## 9. 当前实现状态

### Phase 1 已完成

| 项目 | 状态 |
|---|---|
| 浮动聊天框（全局） | ✅ |
| 页面上下文注册机制 | ✅ |
| Agent 运行时（ReAct 循环） | ✅ |
| Tool Calling（OpenAI 格式） | ✅ |
| 视图编辑器上下文 | ✅ |
| 视图查询/配置工具（4个） | ✅ |
| 提示词注入 | ✅ |

### Phase 2 规划中

| 项目 | 计划 |
|---|---|
| 会话历史（跨请求上下文） | 待实现 |
| Streaming 响应 | 待实现 |
| 操作确认/回滚机制 | 待实现 |
| AI 操作日志 | 待实现 |
| 更多页面上下文（数据模型、工作流） | 待实现 |
| MCP Server（RFC-0006） | 待实现 |

---

## 10. 与框架体系的关系

### 已有基础设施

| 组件 | 说明 |
|---|---|
| `LlmRouter` + `LlmRole` | 角色-模型-提供商映射，已有 'general' 角色 |
| `OpenAiGateway` / `AnthropicGateway` / `OllamaGateway` | LLM API 网关，已扩展工具支持 |
| `LlmEncryptor` | API Key 加解密 |
| `Entity` / `EntityProperty` / `View` / `ViewField` | 元数据实体体系 |
| `ViewEditorController` + ViewEditorApiController | 视图编辑器功能 |
| `FormFieldRenderer` | 表单字段渲染引擎 |

### 新增基础设施

| 组件 | 说明 |
|---|---|
| `LlmRouterPlatform` | `symfony/ai-platform` 与 `LlmRouter` 的桥接层 |
| `ChatResultConverter` | 响应格式转换 |
| `AiContextRegistry` | 上下文提供者注册与路由 |
| `AiAssistant` | 泛化 Agent 工厂 |

---

## 11. 新增页面上下文示例

以"数据模型编辑器"为例，新增上下文只需：

```php
// 1. 创建上下文提供者
namespace App\Service\AI\Context;

use App\Service\AI\Runtime\AiContextProviderInterface;

class DataModelAiContext implements AiContextProviderInterface
{
    public function getName(): string { return 'data_model'; }
    public function getRoleCode(): string { return 'general'; }
    public function getSystemPrompt(): string { return '你是一个数据模型设计助手...'; }
    public function getToolProviders(): array { return [/* 数据模型工具 */]; }
}
```

```yaml
# 2. services.yaml
App\Service\AI\Context\DataModelAiContext:
    tags:
        - { name: 'app.ai_context' }
```

```twig
{# 3. 页面模板设置上下文 #}
<script>
window.__AI_CONTEXT__ = { name: 'data_model' };
</script>
```