---

# DoggyOA AI Architecture Design

## AI 架构设计方案（V1.0）

**作者：** Tony Gao
**版本：** V1.0
**更新时间：** 2026-07

---

# 一、设计目标

DoggyOA 是一套以 **Symfony** 为核心的企业级低代码平台。

AI 能力属于平台增强能力，而不是平台主体。

因此，整个 AI 架构遵循以下原则：

> **Business First，AI Enhanced。**

即：

* Symfony 永远负责业务。
* AI 只负责 AI 能力。
* AI Gateway 是可选组件，而不是必须组件。
* 所有 AI Provider 均可替换。

---

# 二、总体架构

```text
                          DoggyOA

                    Symfony Core
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼

   Workflow           Knowledge          AI Agent

        │                  │                  │
        └──────────────────┼──────────────────┘
                           │
                   LlmRouter (Role-based)
                           │
              ┌────────────┴────────────┐
              │                         │

      LlmGatewayFactory          Legacy AiManager

              │                    (Symfony AI Bundle)
              │
   ┌──────────┼──────────┐
   │          │          │
   ▼          ▼          ▼

  OpenAI  Anthropic  Ollama

  Azure    Custom (OpenAI-compatible, 10+ Chinese providers)

  │         │         │
  ▼         ▼         ▼

 DB: platform_llm_provider + platform_llm_role (Admin UI)
```

**注意：第二个系统 (Legacy AiManager)** 使用的是 Symfony AI Bundle (`symfony/ai-bundle`) 配置的 Generic Platform（Aliyun / LM Studio），与服务商无关的系统 Prompt 注入和 JSON 解析逻辑。新系统 (LlmRouter) 是数据库驱动的角色绑定体系。两者共存，新系统是主要发展方向。

---

# 三、职责划分

## Symfony Core

Symfony 是整个系统核心。

负责：

* 用户
* 权限
* 菜单
* 流程
* 表单
* 工作流
* 插件
* Agent
* RAG
* Prompt
* Knowledge
* 数据库存储

Symfony 不负责：

* OCR
* Embedding
* Reranker
* 文档解析
* 图片理解
* 语音识别

---

## AI Gateway（已实现：PHP Chat Gateway）

代码位置：`src/Service/Platform/Llm/`

实际已实现的 Gateway 是 PHP-native 的 Chat Gateway，职责：

- 统一的 LLM Chat 接口抽象（`LlmGatewayInterface`）
- 5 类 Gateway 实现：OpenAI / Anthropic / Ollama / Azure / Custom
- Custom 覆盖 10 个国产 OpenAI 兼容厂商（DeepSeek、Moonshot、Qwen、GLM、ERNIE、Doubao、Baichuan、Yi、SiliconFlow + 通用 custom）
- 基于数据库配置（`platform_llm_provider` 表），管理后台可 CRUD
- API Key 通过 `LlmEncryptor` 做 AES-256-GCM 加密存储
- Router 按角色（vision/reasoning/general/lightweight/embedding）自动路由

接口：`chat()` / `chatStream()` / `testConnection()` — 详见 RFC-0004

## AI Gateway（规划中：Python 独立服务）

未来可能引入独立的 Python 服务，提供 Symfony 不具备的 AI 原生能力：

* 文档解析（Docling）
* OCR（PaddleOCR / Apple Vision）
* Embedding（BGE-M3，可能会被 Symfony AI Store 替代）
* Reranker
* 语音识别（Whisper）

但当前阶段这些能力**尚未实现**，优先通过以下方式提供：

* Chat：已实现的 PHP Gateway
* Embedding/向量检索：`symfony/ai-store` 已安装，待集成
* 文档解析：业务层处理

---

# 四、为什么 Chunk 放 Symfony

很多 RAG 项目：

```
Python

↓

Parser

↓

Chunk

↓

Embedding
```

DoggyOA 不采用这种方案。

原因：

Chunk 属于业务规则。

例如：

制度文件：

```
章节切分
```

API 文档：

```
接口切分
```

代码：

```
Function 切分
```

流程：

```
节点切分
```

未来：

不同插件都可以实现自己的 Chunk Strategy。

因此：

Chunk 必须属于 Symfony。

---

# 五、Document Pipeline

```
上传文档

↓

Symfony

↓

DocumentParser

↓

Markdown

↓

Chunk

↓

Embedding

↓

pgvector
```

其中：

Document Parser 只负责：

```
PDF

↓

Markdown
```

Symfony 负责：

```
Markdown

↓

Chunk

↓

Database
```

---

# 六、AI Gateway 提供能力

建议实现：

## Document Parser

默认：

```
Docling
```

未来：

```
MinerU

Unstructured
```

---

## Embedding

默认：

```
BAAI bge-m3
```

接口：

```
POST /embedding
```

返回：

```json
{
  "embedding":[...]
}
```

---

## Reranker

默认：

```
bge-reranker-v2-m3
```

接口：

```
POST /rerank
```

返回：

```json
[
  {
    "score":0.98
  }
]
```

---

## OCR

建议：

```
Apple Vision

PaddleOCR
```

---

## Speech

建议：

```
Whisper
```

---

## Vision

未来：

Qwen VL

Gemma Vision

GPT-4o Vision

Claude Vision

---

# 七、Provider 设计

## 已实现：LlmGatewayInterface（Chat 能力）

代码位置：`src/Service/Platform/Llm/`

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

5 个实现：

| Gateway | 厂商类型 |
|---------|---------|
| `OpenAiGateway` | OpenAI |
| `AnthropicGateway` | Anthropic |
| `OllamaGateway` | Ollama |
| `AzureGateway` | Azure |
| `CustomGateway` | 所有国产 OpenAI 兼容厂商 |

`ChatResponse` DTO：

```php
class ChatResponse {
    content, finishReason, inputTokens, outputTokens, elapsedMs, toolCalls
}
```

`Message` DTO：

```php
class Message {
    role, content, toolCalls, toolCallId
}
```

## 未实现（属于 Symfony AI Store 能力）

`symfony/ai-store` 已安装但尚未集成。未来可通过 Symfony AI Store 使用：

- Embedding（通过 `PlatformInterface::invoke()` 调用 embedding 模型）
- Vector Store（pgvector，已在 PostgreSQL 17 中安装）
- Indexer / Retriever (RAG Pipeline)

这些不属于 `LlmGatewayInterface` 范畴。

---

# 八、Knowledge Pipeline（规划中，待基于 Symfony AI Store 实现）

`symfony/ai-store` 已安装（^0.1.0），`pgvector` 已在 PostgreSQL 17 中安装可用。

计划 Pipeline：

```
上传

↓

Parser (业务层处理)

↓

Chunk (业务层 ChunkStrategy)

↓

Embedding (Symfony AI Platform / LlmGateway)

↓

PostgreSQL(pgvector) (通过 Symfony AI Store 的 StoreInterface)

↓

Similarity Search (Symfony AI Store Retriever)

↓

Reranker (可选)

↓

LlmRouter->chatByRole('general', ...)

↓

Answer
```

---

# 九、部署模式

## 云模式

```
Symfony

↓

LlmRouter -> LlmGatewayFactory -> OpenAI/DeepSeek/Qwen API
```

适用场景：直接使用云端 API，无需额外 Gateway。

---

## 企业私有部署

```
Symfony

↓

LlmRouter -> LlmGatewayFactory -> Ollama/LM Studio API

↓

未来: Python AI Gateway (Docling/OCR/Whisper)
```

---

## 混合部署

```
Embedding

↓

Local

----------------

LLM

↓

Cloud
```

推荐方案：

```
Embedding：

本地

LLM：

DeepSeek API
```

---

# 十、数据库

## LLM 配置表（已实现）

| 表名 | 用途 |
|------|------|
| `platform_llm_provider` | LLM 服务商配置（UUID id, name, provider 类型, model, apiKeyEncrypted, apiEndpoint, options JSON, isEnabled） |
| `platform_llm_role` | 模型角色分配（code PK: vision/reasoning/general/lightweight/embedding, label, systemPrompt, 关联 LlmProvider, options JSON, isEnabled） |

API Key 通过 `LlmEncryptor` 使用 AES-256-GCM 加密存储。

## 向量数据库（已装待用）

```
PostgreSQL 17

+

pgvector 0.8.5
```

已安装可用，`symfony/ai-store` 已安装但尚未接入。

## 未来可扩展

当知识规模达到 1000 万 Chunk+ 时，可通过 Symfony AI Store 的抽象层切换：

```
Qdrant

Milvus

Chroma

+ 其他 Symfony AI Store 支持的 13+ 种向量数据库
```

---

# 十一、已安装的 Symfony AI 包

| 包名 | 版本 | 状态 |
|------|------|------|
| `symfony/ai-bundle` | ^0.1.0 | 已配置（Aliyun + LM Studio Generic Platform） |
| `symfony/ai-agent` | ^0.1.0 | 已安装，待业务集成 |
| `symfony/ai-generic-platform` | ^0.1.0 | 已配置 |
| `symfony/ai-store` | ^0.1.0 | 已安装，待集成 pgvector |
| `symfony/ai-mate` | * | 已配置（dev，MCP 开发助手） |
| `symfony/ai-symfony-mate-extension` | ^0.9.0 | 已配置（dev） |

注意：`symfony/mcp-bundle` **未安装**，MCP Server 集成是未来方向。

# 十二、未来规划

## 短期优先

- [x] LlmGateway + Role-based routing（已完成）
- [x] 管理后台 UI（已完成）
- [x] PostgreSQL 17 + pgvector（已完成）
- [ ] `symfony/ai-store` 集成 pgvector RAG
- [ ] Agent Tool Calling（使用 `symfony/ai-agent`）
- [ ] 单元测试 / 集成测试

## 中长期

```
MCP Server（使用 symfony/mcp-bundle）

Multi-Agent（使用 symfony/ai-agent Subagent）

Python AI Gateway（Docling / OCR / Whisper 等非 PHP 能力）

A2A

OpenAI Responses API

Image / Video Generation

Code Execution
```

---

# 十二、设计原则

最后，我建议把整个架构浓缩成几条原则，作为 DoggyOA AI 开发的最高设计准则：

| 原则                      | 说明                                                   |
| ----------------------- | ---------------------------------------------------- |
| **Symfony First**       | 所有业务逻辑、权限、流程、知识库、Agent 编排均由 Symfony 管理。              |
| **AI Capability Only**  | AI Gateway 只提供 AI 能力，不承担业务逻辑。                        |
| **Database-driven Config** | 服务商配置和角色绑定通过数据库实体管理，提供管理后台 UI。              |
| **Cloud / Local Equal** | 所有 AI 功能都应支持云端和本地两种 Provider，可自由切换。                  |
| **Provider Pattern**    | Chat 能力采用 LlmGatewayInterface 统一抽象；其他能力（Embedding、Vector Store）通过 Symfony AI Store 提供。 |
| **Stateless AI**        | AI Gateway 无状态，不保存业务数据，便于横向扩展。                       |
| **Business-driven RAG** | Chunk、知识组织、检索策略属于业务层，而不是 AI Gateway。                 |
| **Plugin Ready**        | AI 能力可通过插件扩展，不绑定任何厂商或模型。                             |

---
