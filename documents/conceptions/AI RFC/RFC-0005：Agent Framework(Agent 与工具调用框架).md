# RFC-0005：Agent Framework

## AI Agent 与 Tool Calling 框架设计规范

**Project：DoggyOA**
**Version：1.0**
**Status：Draft**
**Author：Tony Gao**

---

# 1. Overview

传统 OA 系统中的 AI 通常只是：

```
用户提问
    ↓
AI回答
    ↓
结束
```

这种模式本质是：

> AI 是一个知识查询工具。

而 DoggyOA 的目标是：

> AI 成为企业工作执行助手。

例如：

用户： "帮我创建一个采购审批流程。"

AI 不应该只回答 "创建流程需要进入流程设计器。" 而应该：

1. 理解需求；
2. 查询已有流程模板；
3. 生成流程结构；
4. 调用 OA API 创建流程；
5. 返回预览；
6. 等待用户确认发布。

因此 DoggyOA 需要 Agent Framework。

## 基础平台

`symfony/ai-agent`（^0.1.0 已安装）提供了 Agent 基础设施：

- `Agent` — AI Runtime Container
- `Toolbox` — Tool 注册与管理
- `MessageBag` — 多轮对话上下文
- `Processor` — Input/Output 中间件
- `Subagent` — Agent 调用 Agent
- `MockAgent` — 测试模拟

RFC-0005 的 Agent 设计应**基于 `symfony/ai-agent` 实现**，而非从零构建。

---

# 2. Design Goal

Agent Framework 目标：

## 2.1 AI 能理解任务

例如：

```
帮我设计一个请假流程
```

转换为：

```
Task:

CreateWorkflow
```

---

## 2.2 AI 能调用系统能力

例如：

```
查询员工

创建表单

修改字段

启动流程

查询数据
```

---

## 2.3 AI 行为可控

企业环境必须避免：

```
AI 随便修改生产数据
```

因此需要：

* 权限控制
* 审批确认
* 操作审计

---

## 2.4 Agent 可扩展

第三方插件可以增加：

```
HR Agent

Finance Agent

IPD Agent

CRM Agent
```

---
# 3. Overall Architecture

```text
                         User
                           │
                           ▼
                    Agent Runtime (symfony/ai-agent)
                           │
              ┌────────────┼────────────┐
              │            │            │
          Processor    Memory       Toolbox
          (Input/Output)            ([#[AsTool]])
              │                         │
              ▼                         ▼
          LlmRouter               Tool Registry
          (role-based)                 │
                           ┌───────────┼───────────┐
                           │           │           │
                      OA Tools   Knowledge    External/MCP
                      (#[AsTool])  (RAG)        Tools
```

基础组件使用 `symfony/ai-agent`：

```php
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox;
use Symfony\AI\Agent\MessageBag;

$agent = new Agent(
    platform: $platform,
    model: $model,
    toolbox: new Toolbox([$myTool]),
    messageBag: new MessageBag(...),
);
```

`LlmRouter` 作为模型路由层，为 Agent 提供 Chat 能力。

---

# 4. Core Concepts

DoggyOA Agent 包含：

```
Agent

↓

Task

↓

Plan

↓

Tool

↓

Execution

↓

Result
```

---

# 5. Agent Model

Agent Entity：

```php
Agent
```

字段：

```
id

name

description

systemPrompt

modelProvider

tools

memoryStrategy

enabled
```

例如：

```
名称：

OA流程助手


能力：

创建流程

查询流程

修改表单
```

---

# 6. Agent Runtime

核心服务：

```php
AgentRuntime
```

职责：

* 接收任务
* 调用模型
* 解析 Tool Call
* 执行工具
* 返回结果

示例：

```php
$result =
$agentRuntime
->execute(
    $agent,
    $message
);
```

---

# 7. Agent Execution Lifecycle

完整生命周期：

```
User Request

↓

Intent Analysis

↓

Planning

↓

Tool Selection

↓

Tool Execution

↓

Observation

↓

Next Step

↓

Final Answer
```

---

例如：

用户：

```
创建一个采购审批流程
```

---

## Step 1

LLM：

```
识别任务：

CreateWorkflow
```

---

## Step 2

生成计划：

```
Plan:

1.
查询采购流程模板

2.
生成节点

3.
创建流程

4.
返回结果
```

---

## Step 3

调用工具：

```
workflow.searchTemplate()
```

---

## Step 4

执行：

返回：

```
采购申请模板
```

---

## Step 5

继续：

调用：

```
workflow.create()
```

---

# 8. Tool Framework（基于 Symfony AI Agent）

Tool 是 Agent 操作系统能力的接口。

## 方式一：PHP 8 Attribute（推荐）

使用 `symfony/ai-agent` 的 `#[AsTool]` 属性：

```php
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

class WorkflowTools
{
    #[AsTool(name: 'workflow.create', description: '创建OA审批流程')]
    public function createWorkflow(
        #[Parameter(description: '流程标题')]
        string $title,
        #[Parameter(description: '审批节点列表')]
        array $nodes,
    ): ToolResult {
        // ...
    }
}
```

优点：

- 自动生成 JSON Schema（用于 LLM Tool Calling）
- 支持参数验证（`#[With(minLength: 3)]`）
- 无需手动维护 Tool 注册

## 方式二：ToolInterface（自定义）

```php
interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getSchema(): array;

    public function execute(array $arguments): ToolResult;
}
```

建议优先使用 `#[AsTool]` 属性方式。

---

# 9. Tool Example

## 创建流程 Tool

名称：

```
workflow.create
```

Schema：

```json
{
"name":"workflow.create",

"description":
"创建OA流程",

"parameters":{

"title":{
"type":"string"
},

"nodes":{
"type":"array"
}

}

}
```

---

AI看到：

```
workflow.create
```

就知道：

自己可以调用。

---

# 10. Tool Categories

DoggyOA Tool 分为：

---

# 10.1 System Tools

系统基础能力：

```
user.query

department.query

permission.check
```

---

# 10.2 Workflow Tools

流程：

```
workflow.create

workflow.publish

workflow.start

workflow.query
```

---

# 10.3 Form Tools

低代码：

```
form.create

field.add

view.generate
```

---

# 10.4 Data Tools

数据：

```
table.query

record.create

record.update
```

---

# 10.5 Knowledge Tools

RAG：

```
knowledge.search

document.query
```

---

# 10.6 External Tools

通过 MCP：

```
GitHub

Jira

ERP

MES
```

---

# 11. Tool Registry

所有 Tool 注册：

```php
ToolRegistry
```

例如：

```yaml
tools:

 workflow.create:
    class:
       WorkflowCreateTool


 form.create:
    class:
       FormCreateTool

```

---

Agent 根据：

```
tools
```

自动获得能力。

---

# 12. Permission System

企业 AI 最大问题：

权限。

例如：

普通员工：

不能：

```
deleteWorkflow
```

管理员：

可以。

---

因此 Tool 执行必须经过：

```
Agent

↓

Permission Guard

↓

Tool

↓

Service
```

---

例如：

```php
if(
 !$permission->allow(
   $user,
   $tool
 )
)
{
 throw AccessDenied;
}
```

---

# 13. Human Confirmation

高风险操作：

必须确认。

例如：

```
删除流程

修改组织架构

批量修改数据

发布制度
```

流程：

```
AI

↓

生成Action

↓

等待确认

↓

Execute
```

---

状态：

```php
PendingConfirmation
```

---

# 14. Tool Execution Record

所有操作记录：

Entity：

```
AgentExecutionLog
```

字段：

```
agent

user

tool

arguments

result

status

duration

createdAt
```

---

用于：

* 审计
* 调试
* 合规

---

# 15. Memory System（基于 Symfony AI Agent）

Agent Memory 分三类。

## 15.1 Short Memory

使用 `symfony/ai-agent` 的 `MemoryInputProcessor`：

```php
use Symfony\AI\Agent\Processor\MemoryInputProcessor;

$agent = new Agent(/*...*/);
$agent->addInputProcessor(new MemoryInputProcessor($platform, $model));
```

当前对话上下文通过 `MessageBag` 管理，默认保留最近 20 轮。

## 15.2 Long Memory

使用 `EmbeddingProvider` 语义记忆：

```php
$embeddingsMemory = new EmbeddingProvider($platform, $model);
```

基于向量检索实现语义级长期记忆。

## 15.3 Business Memory

业务事实通过 Tool Calling 实时查询数据库，而非存储在 Agent Memory 中。

---

# 16. Prompt Architecture

不要使用一个巨大 Prompt。

采用：

```
System Prompt

+

Agent Prompt

+

Tool Description

+

Knowledge Context

+

User Message

```

---

组合：

```
PromptBuilder
```

负责。

---

# 17. Planning Strategy

支持多种模式。

---

## 17.1 Simple Tool Calling

适合：

简单查询。

例如：

```
查询我的审批
```

流程：

```
LLM

↓

Tool

↓

Answer
```

---

## 17.2 ReAct

Reason + Action

流程：

```
Thought

↓

Action

↓

Observation

↓

Thought
```

---

## 17.3 Plan Execute

复杂任务：

```
生成计划

↓

执行计划

↓

检查结果
```

---

# 18. Agent Types

DoggyOA 内置：

---

## OA Assistant

能力：

```
查询流程

创建申请

查看任务
```

---

## Low-code Designer Agent

能力：

```
创建表单

设计页面

生成字段
```

---

## Knowledge Assistant

能力：

```
制度查询

文档总结
```

---

## IPD Assistant

能力：

```
项目查询

研发流程

风险分析
```

---

# 19. MCP Integration

未来支持：

Model Context Protocol。

架构：

```
Agent

↓

MCP Client

↓

External MCP Server

```

例如：

```
GitHub MCP

ERP MCP

Jira MCP

```

---

# 20. Agent + Low Code Integration

这是 DoggyOA 的核心差异。

AI 可以直接操作：

```
Entity

Field

Form

Workflow

View
```

例如：

用户：

> 创建一个供应商管理模块

Agent：

```
Create Entity

↓

Add Fields

↓

Generate Form

↓

Generate List View

↓

Create Workflow
```

---

# 21. Security Architecture

Agent 不直接操作数据库。

错误：

```
AI

↓

SQL

↓

Database
```

---

正确：

```
AI

↓

Tool

↓

Domain Service

↓

Database
```

---

原因：

* 权限
* 审计
* 数据安全

---

# 22. Async Execution

长任务：

使用：

Symfony Messenger。

例如：

```
生成完整ERP模块
```

流程：

```
Agent

↓

Message Queue

↓

Worker

↓

Execution Result
```

---

# 23. Database Design

## agent

```
id

name

description

prompt

model

status
```

---

## agent_tool

```
agent_id

tool_id
```

---

## tool

```
name

description

schema

permission
```

---

## agent_execution

```
agent

user

input

output

status

created_at

```

---

# 24. Observability

记录：

```
Agent Trace
```

包括：

```
User Input

LLM Request

Tool Call

Tool Result

Final Answer
```

类似：

OpenTelemetry Trace。

---

# 25. 已安装的 Symfony AI Agent 能力

`symfony/ai-agent` 已安装，以下能力可直接使用：

| 能力 | 说明 |
|------|------|
| `Agent` | AI Runtime Container，管理消息/工具/Processor |
| `Toolbox` | Tool 注册与管理，自动生成 JSON Schema |
| `#[AsTool]` | PHP 8 属性方式定义 Tool |
| `MessageBag` | 多轮对话上下文管理 |
| `Processor` (Input/Output) | Agent 中间件 Pipeline |
| `MemoryInputProcessor` | 注入短期/长期记忆 |
| `MemoryOutputProcessor` | 保存对话到记忆 |
| `Subagent` | Agent 调用 Agent（Multi-Agent） |
| `SimilaritySearch` | RAG 检索（与 symfony/ai-store 集成） |
| `MockAgent` | 测试模拟 Agent |
| `EmbeddingProvider` | 语义长期记忆 |
| `Dynamic Memory Control` | `use_memory` 参数动态控制 |

---

# 26. 现有代码中的 Agent 实现

两个遗留 Agent 使用 Symfony AI Bundle 的 Generic Platform（Aliyun），尚未迁移到 `symfony/ai-agent`：

| Agent | 功能 | 状态 |
|-------|------|------|
| `NaturalLanguageQueryAgent` | NL 转 DataGrid 过滤器 | 已实现 |
| `PasswordPolicyAgent` | NL 转密码策略 DSL | 已实现 |
| `CodingAgent` | 代码生成 | 基础实现 |
| `VisionAgent` | 图片分析 | 占位符 |

这些 Agent 未来应迁移到 `symfony/ai-agent` + `LlmRouter`。

---

# 27. Future Evolution

## Multi Agent（使用 Subagent）

`symfony/ai-agent` 提供 `Subagent`：

```php
$parentAgent = new Agent(/*...*/);
$subagent = new Subagent($parentAgent);
$result = $subagent->execute('生成供应商管理模块');
```

架构：

```
项目经理Agent
    ↓
Subagent → 研发Agent
    ↓
Subagent → 采购Agent
    ↓
Subagent → 财务Agent
```

---

## Agent Collaboration

多个 Agent 协作完成任务，通过 Subagent 和 Processor 编排。

---

## Autonomous Workflow

AI 自动发现异常、风险、机会，主动触发流程。

---

# 28. Design Principles

| 原则                  | 说明                  |
| ------------------- | ------------------- |
| Tool First          | AI 通过工具操作系统         |
| Permission First    | 所有操作经过权限控制          |
| Human In Loop       | 高风险操作需要确认           |
| Domain Driven       | Agent 不直接访问数据库      |
| Observable          | 所有行为可追踪             |
| Extensible          | 插件可以增加 Agent 和 Tool |
| MCP Ready           | 支持外部生态连接            |
| Business Controlled | AI 不替代业务规则          |

---

# 27. Final Architecture

最终 DoggyOA AI 平台：

```
                         User

                          │

                    Agent Runtime

                          │

              ┌───────────┴───────────┐

              │                       │

          Knowledge              Tool System

              │                       │

             RAG              OA Domain Service

                                      │

                         Workflow/Form/Data

                                      │

                             PostgreSQL


```

---

# RFC-0005 总结

RFC-0005 定义了 DoggyOA 从：

> "AI 问答系统"

升级为：

> "AI 驱动的企业操作系统"

的基础。

前四个 RFC：

```
RFC-0001
AI Architecture

RFC-0002
Knowledge Base & RAG

RFC-0003
AI Provider Framework

RFC-0004
AI Gateway

RFC-0005
Agent Framework
```

已经形成一个完整 AI 平台基础。

下一篇建议编写：

**RFC-0006：MCP Integration Architecture（MCP 集成架构规范）**

因为对于 DoggyOA 这种低代码 OA 平台，MCP 会成为连接：

* 企业内部系统
* ERP
* MES
* Git
* Jira
* 飞书
* 钉钉
* 外部 Agent

的重要标准。你之前提到希望让 AI Agent 修改低代码系统，这篇会直接定义实现路径。
