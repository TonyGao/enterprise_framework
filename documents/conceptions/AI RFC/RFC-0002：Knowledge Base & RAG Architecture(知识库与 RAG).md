---

# RFC-0002：Knowledge Base & RAG Architecture

**Status**

Draft

**Author**

Tony Gao

**Version**

1.0

---

# 1. Overview

DoggyOA 提供统一的 Knowledge Base（知识库）能力。

Knowledge Base 并不是 AI 的附属功能。

而是：

> 企业知识管理平台。

AI 只是其中一个消费者（Consumer）。

因此：

Knowledge Base 必须独立于：

* LLM
* Embedding
* AI Gateway

并可被整个平台使用。

---

# 2. Design Goals

设计目标：

✅ 不依赖任何 LLM

✅ 不依赖任何 Embedding Model

✅ 可替换任何 Vector Database

✅ Symfony 完全控制知识生命周期

✅ AI Gateway 仅负责计算

---

# 3. Architecture

```text
                    Knowledge

                        │

            Upload / Import / Sync

                        │

                        ▼

             Document Parser Provider

                        │

                        ▼

            Structured Document

                        │

                        ▼

                Chunk Strategy

                        │

                        ▼

                Chunk Repository

                        │

                        ▼

            Embedding Provider

                        │

                        ▼

             PostgreSQL(pgvector)

                        │

                        ▼

               Similarity Search

                        │

                        ▼

            Reranker Provider

                        │

                        ▼

                  Top Context

                        │

                        ▼

                 Chat Provider
```

---

# 4. Knowledge Domain

一个 Knowledge 代表：

```text
一本文档
```

例如：

```
研发制度.pdf
```

```
API.md
```

```
员工手册.docx
```

Symfony Entity：

```php
Knowledge
```

字段：

```
id

title

type

status

language

owner

parser

chunkStrategy

metadata

createdAt
```

Knowledge 不保存：

```
Embedding
```

---

# 5. Document

Parser 输出：

```
Document
```

不是 PDF。

Document 是统一的数据结构。

例如：

```php
Document

├── title

├── sections

├── tables

├── images

├── metadata
```

以后：

Docling

↓

Document

MinerU

↓

Document

Unstructured

↓

Document

全部统一。

---

# 6. Chunk

Chunk 是：

> Knowledge 的最小检索单位。

Entity：

```php
KnowledgeChunk
```

字段：

```
id

knowledgeId

content

tokenCount

sequence

heading

metadata
```

注意：

Chunk 永远属于 Symfony。

AI Gateway 不参与。

---

# 7. Chunk Strategy

DoggyOA 不采用固定长度切块。

采用：

Strategy Pattern。

接口：

```php
ChunkStrategyInterface
```

例如：

```
DefaultChunkStrategy
```

```
MarkdownChunkStrategy
```

```
CodeChunkStrategy
```

```
ApiChunkStrategy
```

```
WorkflowChunkStrategy
```

以后：

插件可以注册：

```
BPMNChunkStrategy
```

```
IPDChunkStrategy
```

---

# 8. Embedding

建议使用 **Symfony AI Platform** 的已有抽象能力（`symfony/ai-platform` 已安装）：

```yaml
ai:
    platform:
        generic:
            aliyun:
                base_url: '%env(ALIYUN_BASE_URL)%'
                api_key: '%env(ALIYUN_API_KEY)%'
                embeddings_path: '/embeddings'
```

代码调用：

```php
use Symfony\AI\Platform\PlatformInterface;

$response = $platform->invoke(
    'text-embedding-3-small',
    ['input' => $text]
);
```

未来可切换的 Embedding Provider：

- OpenAI `text-embedding-3-small`（已在 ModelRegistry 中注册）
- Ollama `bge-m3`（本地部署）
- Qwen / DeepSeek（国产兼容 API）

注意：`EmbeddingProviderInterface` 在代码库中**尚未实现**。优先使用 Symfony AI Platform 内置能力，后续可按需封装统一接口。

---

# 9. Vector Store

## 基础设施（已完成）

```
PostgreSQL 17

+

pgvector 0.8.5
```

已在 `ef` 数据库中创建 `CREATE EXTENSION vector`。

## 抽象层

建议使用 `symfony/ai-store`（^0.1.0 已安装）：

```php
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Document\VectorDocument;
```

使用 Symfony AI Store 的 `StoreInterface` 替换自定义 `KnowledgeEmbedding` 实体。好处：

- 内置 `TextDocument` / `VectorDocument` 模型
- 支持 13+ 种向量数据库（通过切换 Driver）
- 内置 `Indexer` / `Retriever` 完成 RAG Pipeline
- 原生 Symfony DI / Messenger / Cache 集成

## 自定义 Entity 方案可选

如需业务字段扩展，可定义：

```php
KnowledgeEmbedding
```

字段：

```
id

chunkId

provider

model

dimensions

vector (pgvector 类型)
```

但建议在此基础上封装 `StoreInterface` 兼容层。

## 未来扩展

通过 Symfony AI Store Driver 切换：

```
Qdrant

Milvus

Pinecone

ChromaDB

+ 其他 10+ 种
```

---

# 10. Search

搜索流程：

```
Question

↓

Embedding

↓

Similarity Search

↓

Top50
```

这里：

不涉及：

LLM。

---

# 11. Reranker

接口：

```
RerankerProvider
```

默认：

```
bge-reranker-v2-m3
```

输入：

```
Question

+

Top50
```

输出：

```
Top5
```

---

# 12. Chat

Chat Provider：

例如：

```
DeepSeek

Claude

OpenAI
```

输入：

```
Question

+

Top5
```

输出：

```
Answer
```

Chat Provider：

不知道：

Parser。

不知道：

Chunk。

不知道：

Embedding。

---

# 13. Metadata

Knowledge：

支持：

```
Department

Project

Tags

Security Level

Language

Version

Status
```

以后：

检索：

```
仅搜索：

研发部
```

或者：

```
仅搜索：

V3版本
```

---

# 14. Pipeline

完整流程：

```text
Upload

↓

Parser

↓

Document

↓

Chunk

↓

Embedding

↓

Store

↓

Question

↓

Embedding

↓

Similarity Search

↓

Top50

↓

Reranker

↓

Top5

↓

Prompt Builder

↓

DeepSeek

↓

Answer
```

---

# 15. Prompt Builder

Prompt：

不属于：

LLM。

Prompt Builder：

属于：

Symfony。

接口：

```php
PromptBuilder
```

负责：

```
System Prompt

Knowledge Context

User Question

History
```

拼装：

最终 Prompt。

---

# 16. Cache

建议使用 **Symfony Cache**（已可用）：

```
Embedding Cache
```

避免重复生成 Embedding。

```php
use Symfony\Contracts\Cache\CacheInterface;

$cache->get(hash('sha256', $text), function() use ($text) {
    return $platform->invoke('text-embedding-3-small', ['input' => $text]);
});
```

也可用于缓存 Chat Response（相同 Query 的缓存命中）。

---

# 17. Event

Knowledge：

事件：

```
KnowledgeUploaded

KnowledgeParsed

KnowledgeChunked

KnowledgeEmbedded

KnowledgeIndexed

KnowledgeDeleted
```

Symfony Messenger：

异步处理。

---

# 18. Plugin

插件可以注册：

```
Parser

Chunk Strategy

Embedding (通过 Symfony AI Platform)

Reranker

Vector Store (通过 Symfony AI Store Driver)

Prompt Builder

Retriever Strategy
```

全部通过 Symfony 原生 Bundle/DI 机制扩展，而非自定义插件接口。

# 19. 与现有系统的交互

RAG Pipeline 的 LLM 对话最终通过 `LlmRouter` 完成：

```php
// RAG 检索到上下文后
$response = $llmRouter->chatByRole('general', [
    ['role' => 'user', 'content' => "以下文内容：\n{$context}\n\n问题：{$question}"],
]);
```

这样 RAG 不需要关心用的是哪个模型——由 `LlmRouter` 根据角色配置自动路由。

---

# 20. Future

知识不仅支持文件，还支持：

```
Git

Confluence

Notion

飞书

钉钉

Jira

GitLab Wiki

Database

REST API
```

全部通过 Import 流程接入。

# 21. 检查清单

| 项 | 状态 |
|----|------|
| PostgreSQL 17 + pgvector | ✅ 已完成 |
| `symfony/ai-store` 安装 | ✅ 已完成 |
| `config/packages/ai.yaml store` 配置 | ⬜ 待配置 |
| Embedding 集成 | ⬜ 待实现 |
| Chunk Strategy | ⬜ 待实现 |
| RAG Pipeline (Indexer + Retriever) | ⬜ 待实现 |
| Hybrid Search (SQL + Vector) | ⬜ 待实现 |

---

# 22. Design Principles

整个知识库系统建议遵循以下原则：

| Principle                      | Description                                                  |
| ------------------------------ | ------------------------------------------------------------ |
| **Knowledge First**            | 知识库是平台能力，而不是聊天功能的附属。                                         |
| **Structured Document**        | 所有导入内容都先转换为统一 `Document` 模型。                                 |
| **Business-owned Chunking**    | Chunk 策略由 Symfony 控制，可按业务类型扩展。                               |
| **Provider Everywhere**        | Parser、Embedding、Reranker、LLM、Vector Store 全部采用 Provider 模式。 |
| **Asynchronous Pipeline**      | 导入、解析、Embedding、索引全部采用异步任务。                                  |
| **Metadata-driven Retrieval**  | 检索优先结合元数据（部门、项目、版本、权限）进行过滤。                                  |
| **Permission-aware Retrieval** | 检索结果必须经过权限过滤，AI 不应获取用户无权访问的内容。                               |
| **Traceable Answers**          | 每条 AI 回答都应保留引用的 Chunk、文档、页码，支持用户回溯来源。                        |

---

## 我对 RFC-0002 的一个建议

如果 DoggyOA 的目标是企业级平台，那么我建议**不要把 RAG 理解成"向量检索 + LLM"**。

应该把它定义为一个**Knowledge Retrieval Engine（知识检索引擎）**，其中向量检索只是众多检索器之一。未来完全可以支持：

* **全文检索（PostgreSQL Full Text / Elasticsearch）**
* **关键词检索（BM25）**
* **标签检索（Tag）**
* **元数据检索（部门、版本、项目）**
* **向量检索（Embedding）**
* **混合检索（Hybrid Search）**

这样你的架构从第一天开始就是"面向检索能力"而不是"面向某一种 AI 技术"，后续随着检索技术的发展，几乎不需要修改上层业务逻辑。
