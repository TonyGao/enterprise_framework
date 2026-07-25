下面继续按照 DoggyOA 架构规范编写 **RFC-0003：AI Provider Framework**。

这一篇非常关键，因为它决定 DoggyOA 未来能否支持：

* DeepSeek
* OpenAI
* Claude
* Gemini
* Qwen
* 本地 Ollama
* 企业私有模型
* 自研模型

而不需要修改业务代码。

核心思想：

> **业务代码永远面向能力（Capability），而不是面向模型（Model）。**

例如：

错误：

```php
DeepSeekClient->chat()
```

正确：

```php
ChatProvider->chat()
```

业务只知道：

> 我要一次 AI 对话。

至于：

* 用 DeepSeek
* 用 GPT
* 用 Qwen
* 用本地模型

由 Provider 层决定。

---

# RFC-0003：AI Provider Framework

**AI Provider 抽象与实现规范**

---

## 1. Overview

DoggyOA AI Provider Framework 提供统一的 AI 能力抽象层。

目标：

解决不同 AI 服务之间：

* API 不统一
* 参数不统一
* 返回格式不统一
* 部署方式不同

的问题。

---

# 2. Design Goal

Provider Framework 必须满足：

## 2.1 模型无关

业务：

```php
$ai->chat();
```

不关心：

```text
DeepSeek

GPT

Claude

Qwen
```

---

## 2.2 部署无关

同一个能力支持：

```
Cloud

↓

API


Local

↓

Ollama


Private

↓

Enterprise Model
```

---

## 2.3 可扩展

第三方插件可以增加：

```
ClaudeProvider

QwenProvider

LocalLLMProvider

CustomProvider
```

---

## 2.4 可配置

管理员可以选择：

```
默认聊天模型：

DeepSeek V4 Flash


Embedding：

BGE-M3


Reranker：

BGE-Reranker
```

---

# 3. Overall Architecture

```text
                 Symfony Application


                       │


         ┌─────────────┴─────────────┐
         │                           │

    LlmRouter (role-based)    Legacy AiManager
         │                    (Symfony AI Bundle)
         │
    LlmGatewayFactory
         │
         │
  ┌──────┼──────┬──────┬──────┬──────────────────┐
  │      │      │      │      │                  │

OpenAI Ollama Anthropic Azure Custom (10+ Chinese)

  │      │      │      │      │                  │
  ▼      ▼      ▼      ▼      ▼                  ▼

 DB: platform_llm_provider  +  platform_llm_role
```

---

# 4. Gateway Architecture

实际实现使用 `LlmGatewayInterface` 而非分层 Capability+Provider 接口：

```
LlmGatewayInterface

        ↓

OpenAiGateway | AnthropicGateway | OllamaGateway | AzureGateway | CustomGateway
```

所有 Gateway 都通过 `LlmGatewayFactory` 基于数据库中的 `LlmProvider` 实体动态创建。

---

# 5. LlmGatewayInterface

实际接口：

```php
interface LlmGatewayInterface
{
    public function chat(array $messages, array $options = []): ChatResponse;

    public function chatStream(array $messages, array $options = []): \Generator;

    public function testConnection(): array;

    public function getProviderName(): string;

    public function getModelName(): string;
}
```

所有 Gateway 必须实现。

相比 RFC 原始设计中 `AIProviderInterface` 的 `getCapabilities()` 和 `healthCheck()`：

- `healthCheck()` 由 `testConnection()` 替代（返回更详细的连通性信息，包括延迟、模型版本等）
- `getCapabilities()` 未实现——能力的区分通过 `LlmRole` 系统隐式表达（每个 Role 关联一个 Provider，能力由 Role Code 定义）

---

# 6. 已实现的能力

## 6.1 Chat（已实现）

基于 `LlmGatewayInterface`，提供：

```
chat()       — 完整对话
chatStream() — SSE 流式对话
testConnection() — 连接测试
```

DTO：

```php
ChatResponse { content, finishReason, inputTokens, outputTokens, elapsedMs, toolCalls }

Message { role, content, toolCalls, toolCallId }
```

## 6.2 角色路由（已实现）

基于 5 个 capability role code：

| Role Code | 用途 |
|-----------|------|
| `vision` | 多模态视觉（默认 system prompt + 高能力模型） |
| `reasoning` | 深度推理（适合 R1/QwQ 等 reasoning 模型） |
| `general` | 通用对话 |
| `lightweight` | 轻量快速（适合小额/高频场景） |
| `embedding` | 嵌入向量（预留，待集成） |

## 6.3 未实现的 AI 能力

以下能力尚未实现，属于未来规划：

| 能力 | 建议方案 |
|------|---------|
| Embedding | Symfony AI Platform + `symfony/ai-store` |
| Reranker | 独立服务或 Python 网关 |
| Document Parser | 业务层处理，未来可接入 Docling |
| OCR | Apple Vision / PaddleOCR |
| Speech | Whisper |
| Vision | 通过 Chat Role 的 vision 模型实现 |

**核心原则**：当前优先实现 Chat，其他能力按需逐步接入。Embedding 和 Vector Store 优先使用 `symfony/ai-store`。

---

# 7. LlmGatewayInterface（实际实现）

```php
interface LlmGatewayInterface
{
    public function chat(array $messages, array $options = []): ChatResponse;

    public function chatStream(array $messages, array $options = []): \Generator;

    public function testConnection(): array;

    public function getProviderName(): string;

    public function getModelName(): string;
}
```

---

`chat()` 参数：

- `$messages` — 消息数组（`['role' => 'system'|'user'|'assistant', 'content' => '...']`）
- `$options` — 透传给 Gateway 的额外参数（不含 temperature/max_tokens，已在前端表单中移除）

---

`ChatResponse`：

```php
class ChatResponse
{
    public readonly string $content;

    public readonly string $finishReason;

    public readonly int $inputTokens;

    public readonly int $outputTokens;

    public readonly float $elapsedMs;

    public readonly ?array $toolCalls = null;
}
```

---

`Message`：

```php
class Message
{
    public readonly string $role;

    public readonly string $content;

    public readonly ?array $toolCalls = null;

    public readonly ?string $toolCallId = null;
}
```

注意：`temperature` 和 `max_tokens` 已从表单和 Controller 中移除。原因是：

1. 5 个 Role（vision/reasoning/general/lightweight/embedding）各有固定用途，不需要用户调整这些参数
2. 减少前端复杂度
3. 如需自定义，可通过 `LlmRole` 的 `options` JSON 字段配置

---

# 8. Gateway 实现示例

所有国产厂商（DeepSeek、Moonshot、Qwen、GLM、ERNIE、Doubao、Baichuan、Yi、SiliconFlow）统一使用 `CustomGateway`（继承 `OpenAiGateway`），只需覆盖 provider name：

```php
class CustomGateway extends OpenAiGateway
{
    public function getProviderName(): string
    {
        return $this->provider->getProvider();
    }
}
```

因为所有国产厂商均提供 OpenAI 兼容 API。

---

## OpenAI Gateway（完整示例）

```php
class OpenAiGateway implements LlmGatewayInterface
{

public function chat(array $messages, array $options = []): ChatResponse
{
    $response = $this->httpClient->request('POST', $endpoint, [
        'json' => [
            'model' => $this->provider->getModel(),
            'messages' => $messages,
        ],
    ]);

    $data = $response->toArray();

    return new ChatResponse(
        content: $data['choices'][0]['message']['content'] ?? '',
        finishReason: $data['choices'][0]['finish_reason'] ?? 'stop',
        inputTokens: $data['usage']['prompt_tokens'] ?? 0,
        outputTokens: $data['usage']['completion_tokens'] ?? 0,
        elapsedMs: $elapsed,
    );
}

}
```

业务层完全不知道用的是哪个厂商。

---

# 9. Ollama Gateway 实现

```php
class OllamaGateway implements LlmGatewayInterface
{
    public function chat(array $messages, array $options = []): ChatResponse
    {
        // Ollama /api/chat endpoint
        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'model' => $this->provider->getModel(),
                'messages' => $messages,
                'stream' => false,
            ],
        ]);

        $data = $response->toArray();
        // ...
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        // Ollama SSE streaming
        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'model' => $this->provider->getModel(),
                'messages' => $messages,
                'stream' => true,
            ],
        ]);

        foreach ($this->streamLines($response) as $line) {
            yield $line;
        }
    }
}
```

调用地址：`http://localhost:11434`（可通过 `apiEndpoint` 配置）

---
# 10. LlmGatewayFactory（实际实现）

所有 Gateway 通过工厂动态创建：

```php
class LlmGatewayFactory
{
    public function __construct(
        private LlmEncryptor $encryptor,
        private HttpClientInterface $httpClient,
    ) {}

    private const OPENAI_COMPATIBLE = [
        'deepseek', 'moonshot', 'qwen', 'glm', 'ernie',
        'doubao', 'baichuan', 'yi', 'siliconflow', 'custom',
    ];

    public function create(LlmProvider $provider): LlmGatewayInterface
    {
        $p = $provider->getProvider();
        return match (true) {
            $p === 'openai'    => new OpenAiGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'anthropic' => new AnthropicGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'ollama'    => new OllamaGateway($provider, $this->encryptor, $this->httpClient),
            $p === 'azure'     => new AzureGateway($provider, $this->encryptor, $this->httpClient),
            in_array($p, self::OPENAI_COMPATIBLE, true)
                             => new CustomGateway($provider, $this->encryptor, $this->httpClient),
            default => throw new \InvalidArgumentException("Unsupported provider: {$p}"),
        };
    }
}
```

支持 15 种 provider 类型：

| 类型 | Gateway |
|------|---------|
| `openai` | OpenAiGateway |
| `anthropic` | AnthropicGateway |
| `ollama` | OllamaGateway |
| `azure` | AzureGateway |
| `deepseek`, `moonshot`, `qwen`, `glm`, `ernie`, `doubao`, `baichuan`, `yi`, `siliconflow`, `custom` | CustomGateway |

---

# 11. LlmRouter（Role-based Routing，实际实现）

`LlmRouter` 通过 `LlmRoleRepository` 查找角色绑定的 Provider，自动路由：

```php
class LlmRouter
{
    public function chatByRole(string $roleCode, array $messages, array $options = []): ChatResponse
    {
        $role = $this->roleRepo->find($roleCode);
        // 自动注入 System Prompt
        $fullMessages = [];
        if ($role->getSystemPrompt()) {
            $fullMessages[] = ['role' => 'system', 'content' => $role->getSystemPrompt()];
        }
        // ...

        $gateway = $this->factory->create($role->getProvider());
        return $gateway->chat($fullMessages, $mergedOptions);
    }

    public function chatByRoleWithFallback(string $roleCode, ...): ChatResponse
    {
        // 支持 Provider 级别 fallback
    }
}
```

业务代码：

```php
// 不需要知道用哪个模型
$response = $llmRouter->chatByRole('general', [
    ['role' => 'user', 'content' => '查询我的审批任务'],
]);
```

选择逻辑（通过后台 UI 配置）：

- **全局配置**：每个 Role 绑定一个 LlmProvider（在管理后台选择）
- **Agent 配置**：不同 Agent 使用不同的 Role Code 即可获得不同模型
- **角色绑定**：vision → 高能力多模态模型, reasoning → 推理模型, general → 通用, lightweight → 轻量快速, embedding → 向量嵌入

---

# 12. Model Router（已实现：LlmRouter）

已通过 `LlmRouter` 实现，架构：

```
Request (with roleCode)
    ↓
LlmRouter::chatByRole()
    ↓
LlmRoleRepository::find(roleCode)
    ↓
LlmGatewayFactory::create(LlmProvider)
    ↓
Gateway::chat()
    ↓
Model API
```

路由依据不是自然语言理解，而是**明确的 Role Code**（vision/reasoning/general/lightweight/embedding），在管理后台配置绑定关系。未来可在此之上增加智能路由层。

用户：

```
查询制度
```

↓

DeepSeek

用户：

```
总结会议
```

↓

Qwen

---

架构：

```
Request

↓

Model Router

↓

Provider

↓

Model

```

---

# 13. Streaming

企业 AI 助理必须支持流式输出。

接口：

```php
stream()
```

例如：

```
DeepSeek

↓

SSE

↓

Symfony

↓

Browser

```

---

# 14. Tool Calling

未来 Agent 必须支持。

统一：

```php
ToolCall
```

例如：

模型返回：

```json
{
"name":"createWorkflow",
"arguments":{
"title":"请假流程"
}
}
```

Provider负责：

转换不同厂商格式。

---

# 15. Token Usage

统一：

```php
TokenUsage
```

结构：

```php
class TokenUsage
{

int $input;


int $output;


int $total;


}
```

用于：

* 成本统计
* 用户配额
* 企业计费

---

# 16. Error Handling

统一异常：

```
AIException
```

分类：

```
AuthenticationError

RateLimitError

TimeoutError

ModelUnavailable

ContextLengthError

```

---

业务：

不用处理：

```text
OpenAI错误

DeepSeek错误

Ollama错误

```

---

# 17. Cache Layer

Provider 支持缓存：

例如：

Embedding：

```
text hash

↓

cached vector
```

Chat：

支持：

```
Prompt Cache
```

---

# 18. Logging

所有调用记录：

```
AIRequestLog
```

字段：

```
provider

model

user

tokens

cost

duration

status

```

用于：

* 审计
* 计费
* 优化

---

# 19. Security

Provider 必须支持：

## API Key 加密保存

例如：

```
OpenAI Key

DeepSeek Key
```

数据库：

加密字段。

---

## 权限控制

例如：

普通用户：

只能使用：

```
DeepSeek
```

管理员：

可以：

```
Claude
GPT
```

---

# 20. Plugin Extension

第三方插件：

可以注册：

```php
AIProviderExtension
```

例如：

```
EnterpriseAIPlugin

↓

Huawei Pangu Provider

↓

Alibaba Qwen Provider

```

---

# 21. Database Design（实际实现）

## platform_llm_provider

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | UUID (PK) | 自动生成 |
| `name` | varchar(100) | 服务商名称 |
| `provider` | varchar(50) | 类型: openai \| anthropic \| azure \| ollama \| custom \| deepseek \| moonshot \| qwen \| glm \| ernie \| doubao \| baichuan \| yi \| siliconflow |
| `model` | varchar(100) | 模型名称 |
| `apiKeyEncrypted` | text (nullable) | AES-256-GCM 加密的 API Key |
| `apiEndpoint` | varchar(255) (nullable) | API 地址 |
| `options` | json (nullable) | 额外配置 |
| `isEnabled` | boolean | 是否启用 |

## platform_llm_role

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | varchar(50) (PK) | vision \| reasoning \| general \| lightweight \| embedding |
| `label` | varchar(100) | 显示名称 |
| `provider_id` | UUID (FK) | 关联的 LlmProvider |
| `systemPrompt` | text (nullable) | 系统提示词（Migration 预置默认值） |
| `options` | json (nullable) | 额外参数 |
| `isEnabled` | boolean | 是否启用 |

用量统计在 `ChatResponse` 的 `inputTokens`/`outputTokens` 中返回，未来可扩展 `ai_usage_log` 表。

---

# 22. Example Usage

业务代码：

```php
$response = $llmRouter->chatByRole('general', [
    ['role' => 'user', 'content' => '查询我的审批任务'],
]);
```

---

实际调用链：

```
chatByRole('general')
    ↓
LlmRoleRepository::find('general')
    ↓
LlmGatewayFactory::create(LlmProvider)
    ↓
OpenAiGateway / AnthropicGateway / CustomGateway
    ↓
Remote API (DeepSeek / GPT / Qwen / Ollama)
```

---

业务代码完全不知道具体厂商或模型。

---

# 23. Final Architecture

实际架构：

```
                 DoggyOA

            ┌────┴────┐
            │         │
      LlmRouter  Legacy AiManager
            │
    LlmGatewayFactory
            │
  ┌────┬────┬────┬────┬──────────┐
  │    │    │    │    │          │
OpenAI Anthropic Ollama Azure Custom (10+ 国产)
  │    │    │    │    │          │
  ▼    ▼    ▼    ▼    ▼          ▼
 platform_llm_provider  +  platform_llm_role（DB + Admin UI）

 --- 未实现（未来） ---

 Embedding (via symfony/ai-store)
 Parser / OCR / Speech (via Python AI Gateway 或业务层)
```

---

# 24. Design Principles

| 原则 | 描述 |
|------|------|
| Capability First | 面向 AI 能力（Role Code），而不是模型名称 |
| Gateway Isolation | 不允许业务直接调用厂商 SDK，通过 LlmGatewayInterface 统一调用 |
| Database-driven Config | 服务商和角色绑定通过数据库实体 + 管理后台 UI 管理 |
| Role-based Routing | 通过 LlmRouter 按 role code 自动路由到对应模型 |
| Cloud & Local Equal | 支持云端 API（OpenAI/DeepSeek）和本地部署（Ollama） |
| Replaceable | 任意模型可替换，业务代码无需修改 |
| Observable | ChatResponse 记录 inputTokens / outputTokens |
| Secure | API Key 通过 AES-256-GCM 加密存储 |

---

# 总结

RFC-0003 的核心价值是：

**把 AI 从一个外部 API 调用，提升为 DoggyOA 自己的一层基础设施。**

未来 DoggyOA 不应该宣传：

> "我们接入了 DeepSeek"

而应该宣传：

> "DoggyOA 拥有统一 AI Runtime，可根据企业需求选择云端大模型或本地私有模型。"

这会让你的产品定位从“带 AI 功能的 OA”提升到“企业 AI 工作平台”。下一篇 RFC-0004 建议继续定义 **AI Gateway API Specification（AI Gateway 接口规范）**，把 Python 服务和 Symfony Provider 的边界正式固定下来。
