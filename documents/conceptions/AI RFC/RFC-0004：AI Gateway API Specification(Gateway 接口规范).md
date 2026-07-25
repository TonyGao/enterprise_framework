---

# RFC-0004：AI Gateway API Specification

## AI Gateway 接口规范

**Project：DoggyOA**

**Version：1.0**

**Status：Draft**

**Author：Tony Gao**

---

# 1. Overview

DoggyOA 中存在两种 "AI Gateway"：

## 1.1 PHP Chat Gateway（已实现）

代码位置：`src/Service/Platform/Llm/`

这是当前已实现的 PHP-native Gateway，职责：

* 统一的 Chat 接口抽象（`LlmGatewayInterface`）
* 基于数据库配置的路由（`LlmRouter`）
* 管理后台 UI（`LlmConfigController`）
* API Key 加密存储（`LlmEncryptor`）

详见 RFC-0003。

## 1.2 Python AI Capability Gateway（规划中）

这是一个独立的 AI Capability Runtime（本文剩余部分描述的内容），为 DoggyOA Core 提供：

* 文档解析
* OCR
* Embedding
* Reranking
* Speech
* Vision
* Local Model Access

等 AI 原生能力。**当前尚未实现。**

---

# 2. Design Philosophy

Python AI Gateway 遵循：

> Compute Only，No Business Logic

即：

AI Gateway 负责：

```
输入
 ↓
AI计算
 ↓
输出
```

不负责：

* 用户管理
* 权限
* 工作流
* 文档生命周期
* 知识库
* 业务数据

---

# 3. Architecture

> **注意**：以下是**规划中**的 Python AI Gateway 架构，目前尚未实现。
> 当前已使用的是 PHP-native Gateway（见 RFC-0003）。

```
                 DoggyOA Symfony


                       │

              LlmRouter（PHP 已实现）

                       │

        ┌──────────────┼──────────────┐
        │              │              │

   Chat Gateway  future:  Python AI Gateway
   (已实现)       (文档解析/OCR/Speech)


        │              │              │

    OpenAI API    未来: Docling / Whisper / vLLM
    Anthropic
    Ollama
```

---

# 4. Communication Protocol

## 4.1 Protocol

默认：

```
HTTP REST
```

数据格式：

```
JSON
```

字符编码：

```
UTF-8
```

---

## 4.2 Base URL

示例：

```
http://localhost:8080/api/v1
```

生产：

```
https://ai.example.com/api/v1
```

---

# 5. Authentication

AI Gateway 必须支持认证。

## 5.1 API Key

Header：

```http
Authorization: Bearer {API_KEY}
```

例如：

```http
Authorization: Bearer dg_ai_xxxxx
```

---

## 5.2 Internal Network Mode

企业内网：

可以关闭认证：

```
AUTH_MODE=internal
```

但是必须：

* 网络隔离
* 防火墙限制

---

# 6. Common Response Format

所有接口统一：

成功：

```json
{
    "success": true,
    "data": {}
}
```

失败：

```json
{
    "success": false,
    "error": {
        "code":"MODEL_ERROR",
        "message":"Model unavailable"
    }
}
```

---

# 7. Error Code

统一错误：

| Code            | 描述    |
| --------------- | ----- |
| AUTH_ERROR      | 认证失败  |
| INVALID_REQUEST | 参数错误  |
| MODEL_ERROR     | 模型异常  |
| TIMEOUT         | 超时    |
| RATE_LIMIT      | 限流    |
| RESOURCE_BUSY   | 资源不足  |
| UNSUPPORTED     | 不支持能力 |

---

# 8. Health API

## GET

```
/health
```

返回：

```json
{
 "status":"ok",
 "version":"1.0",
 "models":[
    "bge-m3",
    "reranker-v2"
 ]
}
```

用途：

* Symfony启动检测
* 管理后台状态显示

---

# 9. Capability API

## GET

```
/capabilities
```

返回：

```json
{
"capabilities":[

{
"name":"embedding",
"models":[
"bge-m3"
]
},

{
"name":"reranker",
"models":[
"bge-reranker-v2-m3"
]
}

]
}
```

用途：

让 DoggyOA 自动发现能力。

---

# 10. Document Parser API

## POST

```
/documents/parse
```

---

## Request

```json
{
"file":

"base64",

"filename":
"ipd.pdf",

"options":
{
"ocr":true,
"tables":true
}
}
```

---

## Response

```json
{
"document":{

"title":
"IPD管理制度",

"content":
"# 第一章..."

"sections":[...]

}
}
```

---

# 11. Markdown Export

支持：

```
/documents/parse/markdown
```

返回：

```markdown
# IPD研发管理制度


## 第一章

...
```

---

# 12. Embedding API

## POST

```
/embedding
```

---

Request：

```json
{
"model":"bge-m3",

"texts":[
"项目什么时候立项？",
"IPD流程"
]
}
```

---

Response：

```json
{

"model":
"bge-m3",

"dimensions":
1024,

"vectors":[

[
0.123,
0.345
]

]

}
```

---

# 13. Embedding Batch

支持批量：

```json
{
"texts":[
"...",
"...",
"..."
]
}
```

建议：

单批：

```
100~1000 texts
```

---

# 14. Reranker API

## POST

```
/rerank
```

---

Request：

```json
{

"model":
"bge-reranker-v2-m3",

"query":
"项目什么时候立项？",

"documents":[

{
"id":"1",
"text":"项目审批完成后立项"
},

{
"id":"2",
"text":"采购流程说明"
}

]

}
```

---

Response：

```json
{

"results":[

{
"id":"1",
"score":0.98
},

{
"id":"2",
"score":0.03
}

]

}
```

---

# 15. OCR API

## POST

```
/ocr
```

---

Request：

```json
{
"image":"base64"
}
```

---

Response：

```json
{

"text":
"扫描文字内容",

"blocks":[...]

}
```

---

# 16. Speech To Text API

## POST

```
/speech/transcribe
```

---

Request：

```json
{
"audio":"file",

"language":"zh"
}
```

---

Response：

```json
{

"text":
"会议内容",

"segments":[...]

}
```

---

# 17. Vision API

## POST

```
/vision/analyze
```

---

Request：

```json
{
"image":"base64",

"prompt":
"描述图片"
}
```

---

Response：

```json
{
"description":
"一张流程图"
}
```

---

# 18. Local Model Management

企业版支持：

## GET

```
/models
```

返回：

```json
{

"models":[

{
"name":"qwen3",

"type":"chat",

"status":"loaded"
}

]

}
```

---

# 19. Streaming Support

对于：

* Chat
* Vision
* Agent

支持 SSE。

接口：

```
POST /chat/stream
```

返回：

```
text/event-stream
```

示例：

```
data:
你好

data:
，我是AI助手
```

---

# 20. Async Task API

大型任务：

例如：

* 1000页PDF解析
* 批量Embedding

采用异步。

提交：

```
POST /tasks
```

返回：

```json
{
"task_id":
"abc123"
}
```

查询：

```
GET /tasks/{id}
```

返回：

```json
{
"status":
"running",

"progress":
60
}
```

---

# 21. File Transfer

支持两种模式。

---

## 小文件

Base64：

适合：

<10MB

---

## 大文件

Multipart：

```
POST /files
```

上传：

```
binary
```

返回：

```json
{
"file_id":
"xxx"
}
```

后续：

```json
{
"file_id":"xxx"
}
```

---

# 22. AI Gateway Configuration

示例：

```yaml
gateway:

models:

 embedding:
   provider:
     ollama
   model:
     bge-m3


 reranker:
   model:
     bge-reranker-v2-m3


 parser:
   provider:
     docling

```

---

# 23. Performance Requirements

## Embedding

目标：

普通服务器：

```
100~500 texts/s
```

---

## Parser

PDF：

```
100页 < 1分钟
```

---

## Reranker

单次：

```
50 documents
<3秒
```

---

# 24. Deployment

## Development

Mac:

```
Symfony

+

Ollama

+

AI Gateway
```

---

## Enterprise

Docker：

```
docker-compose

├── doggyoa

├── postgres

├── ai-gateway

├── ollama

└── nginx
```

---

# 25. Security

必须支持：

## API Key

## HTTPS

## IP Whitelist

## Request Logging

## Resource Limit

---

# 26. Observability

Gateway 输出：

Metrics：

```
request_count

latency

token_usage

model_load

gpu_memory
```

日志：

```
request_id

provider

model

duration

error
```

---

# 27. Versioning

API：

必须版本化：

```
/api/v1
```

未来：

```
/api/v2
```

不破坏已有客户端。

---

# 28. Symfony Integration Example

```php
$response = $llmRouter->chatByRole('general', [
    ['role' => 'user', 'content' => '查询我的审批任务'],
]);
```

内部调用链：

```
chatByRole('general')
    ↓
LlmRoleRepository::find('general')
    ↓
LlmGatewayFactory::create(LlmProvider)
    ↓
OpenAiGateway / CustomGateway / OllamaGateway
    ↓
Remote Model API
```

---

# 29. Final Architecture（含已实现的 PHP Gateway 和规划中的 Python Gateway）

```
                DoggyOA Core


                    │


          AI Provider Framework


                    │


          AI Gateway Client


                    │


       -----------------------------

                    │


             AI Gateway (规划中)

       -----------------------------
        Parser
        OCR
        Embedding
        Reranker
        Speech
        Vision


                    │


          Local AI Runtime


        Ollama / vLLM / CUDA


```

---

# 30. Design Principles

## PHP Chat Gateway（已实现）

| 原则 | 说明 |
|------|------|
| Role-based API | 通过 Role Code 路由，业务不感知具体模型 |
| Database-driven | 服务商配置和角色绑定通过数据库管理 |
| Replaceable | 任意模型可替换 |
| Cloud & Local | 同时支持云端 API 和本地 Ollama |
| Observable | ChatResponse 携带 token 用量 |
| Secure | API Key 通过 AES-256-GCM 加密 |

## Python AI Gateway（规划中）

| 原则 | 说明 |
|------|------|
| Capability API | 接口描述能力，而不是模型 |
| Stateless | Gateway 不保存业务状态 |
| Replaceable | 任何 AI 实现可替换 |
| Local First | 支持企业私有部署 |
| Cloud Compatible | 支持云端模型 |
| Async Ready | 支持大任务处理 |
| Observable | 所有调用可追踪 |
| Secure | 企业安全要求优先 |

---

## RFC-0004 完成后的架构意义

到这里 DoggyOA AI 架构已经形成完整闭环：

```
RFC-0001
AI Architecture
        │
RFC-0002
Knowledge Base & RAG
        │
RFC-0003
AI Provider Framework
        │
RFC-0004
AI Gateway API
```

下一篇最值得继续的是：

**RFC-0005：Agent Framework（AI Agent 与 Tool Calling 架构）**

因为你的目标不是简单知识库，而是让 AI 能操作 OA（创建流程、生成表单、查询数据、修改配置），Agent 架构会成为 DoggyOA 区别于传统 OA 的核心能力。
