---

# RFC-0006：MCP Integration Architecture

## MCP 集成架构规范

**Project：DoggyOA**

**Version：1.0**

**Status：Draft**

**Author：Tony Gao**

---

# 1. Overview

MCP（Model Context Protocol）是一套用于连接 AI Model、Agent、Tools、Resources 的开放协议。

## 当前已安装的 MCP 相关包

| 包名 | 版本 | 用途 |
|------|------|------|
| `symfony/ai-mate` | * (dev) | Symfony MCP 开发助手（IDE Agent） |
| `symfony/ai-symfony-mate-extension` | ^0.9.0 (dev) | Symfony 特定 MCP Tool 扩展 |
| `symfony/ai-mate-composer-plugin` | * | Composer 集成 |

配置文件：`.mcp.json` 和 `mcp.json` 已存在项目根目录。

**未安装**：`symfony/mcp-bundle`（Symfony 官方的 MCP Server 框架）——未来集成 MCP Server/Client 时应从此包开始。

## Symfony MCP 标准

Symfony 官方使用 `#[McpTool]`, `#[McpResource]`, `#[McpPrompt]` 属性声明 MCP 能力：

```php
use Symfony\AI\MCP\Attribute\McpTool;

#[McpTool(name: 'workflow.create')]
public function createWorkflow(string $title, array $nodes): array
{
    // ...
}
```

## DoggyOA 的 MCP 路线

1. 当前：仅配置了 `symfony/ai-mate`（开发环境 IDE 助手）
2. 下一步：安装并使用 `symfony/mcp-bundle`，将 OA 能力暴露为 MCP Server
3. 未来：支持 MCP Client 调用外部系统

DoggyOA 通过 MCP 支持：

1. 作为 MCP Server 暴露企业能力；
2. 作为 MCP Client 调用外部能力；
3. Agent 通过 MCP 自动发现和调用能力。

---

# 2. Design Goals

## 2.1 Open Ecosystem

DoggyOA 不应该成为信息孤岛。

支持：

```
Claude Desktop

Cursor

ChatGPT Agent

企业 Agent

自研 Agent
```

访问 DoggyOA。

---

## 2.2 Capability Exposure

把 OA 能力暴露为：

```
Tool

Resource

Prompt
```

---

## 2.3 Security First

MCP 调用必须经过：

```
认证

权限

审计

数据过滤
```

---

## 2.4 Plugin Friendly

第三方模块可以注册 MCP 能力。

例如：

```
IPD Plugin

ERP Plugin

CRM Plugin
```

自动暴露 MCP。

---

# 3. MCP Architecture

整体：

```
                 AI Agent

                    │

                    │ MCP Protocol

                    ▼


              DoggyOA MCP Server


                    │


        ┌───────────┼───────────┐


        │           │           │


     Tools     Resources    Prompts


        │           │           │


 Workflow      Documents    Templates


 Form          Data         Rules


```

---

# 4. MCP Role Definition

DoggyOA 支持两种角色。

---

# 4.1 DoggyOA as MCP Server

场景：

外部 AI 调用 DoggyOA。

例如：

ChatGPT：

> “查询我的审批任务”

调用：

```
doggyoa.workflow.queryTasks
```

---

# 4.2 DoggyOA as MCP Client

场景：

DoggyOA Agent 调用外部系统。

例如：

采购 Agent：

```
DoggyOA

↓

ERP MCP Server

↓

查询库存
```

---

# 5. MCP Server Architecture（建议使用 symfony/mcp-bundle）

使用 `symfony/mcp-bundle` 的标准结构：

```yaml
# config/packages/mcp.yaml（未来添加）
mcp:
    servers:
        doggyoa:
            transport: http
```

Service 通过 `#[McpTool]` 属性暴露：

```php
use Symfony\AI\MCP\Attribute\McpTool;

class WorkflowService
{
    #[McpTool(name: 'workflow.create', description: '创建OA审批流程')]
    public function createWorkflow(string $title, array $nodes): array
    {
        // Domain Service → Database
    }
}
```

---



MCP 不直接访问：

```
AI → MCP → SQL（错误）
```

正确：

```
AI → MCP Tool → Domain Service → Database
```

---

# 6. MCP Transport

支持：

## 6.1 HTTP Streaming

推荐企业环境：

```
HTTPS + SSE
```

---

## 6.2 STDIO

适合：

本地 Agent。

例如：

```
Cursor

↓

stdio

↓

DoggyOA MCP Server
```

---

# 7. MCP Tool Design（使用 Symfony MCP Bundle 属性）

Symfony Tool 通过 `#[McpTool]` 属性自动暴露为 MCP Tool：

```php
#[McpTool(name: 'workflow.create', description: '创建OA审批流程')]
public function createWorkflow(
    #[McpParameter(description: '流程标题')] string $title,
    #[McpParameter(description: '审批节点列表')] array $nodes,
): array {
    // Domain Service
}
```

自动生成 MCP Schema：

```json
{
    "name": "workflow.create",
    "description": "创建OA审批流程",
    "inputSchema": {
        "type": "object",
        "properties": {
            "title": { "type": "string", "description": "流程标题" },
            "nodes": { "type": "array", "description": "审批节点列表" }
        }
    }
}
```

---

# 8. Tool Naming Convention

统一：

```
{domain}.{action}
```

例如：

## Workflow

```
workflow.create

workflow.publish

workflow.query
```

---

## Form

```
form.create

form.addField

form.update
```

---

## User

```
user.search

user.profile
```

---

## Knowledge

```
knowledge.search

knowledge.getDocument
```

---

# 9. MCP Tool Schema

示例：

## 创建流程

```json
{
"name":
"workflow.create",

"description":
"创建新的审批流程",

"inputSchema":

{
"type":"object",

"properties":
{

"name":
{
"type":"string"
},

"nodes":
{
"type":"array"
}

}

}

}
```

---

# 10. Tool Execution Flow

```
AI Agent

↓

MCP Tool Call

↓

Symfony MCP Controller

↓

Permission Check

↓

Tool Registry

↓

Domain Service

↓

Database

↓

Result

```

---

# 11. Permission Model

MCP 必须继承 DoggyOA 权限体系。

例如：

用户：

```
张三
```

调用：

```
workflow.delete
```

流程：

```
MCP

↓

Permission Manager

↓

Role

↓

ACL

↓

Allow/Deny

```

---

# 12. MCP User Identity

必须传递：

```
User Context
```

例如：

```json
{
"userId":1001,

"tenantId":10,

"roles":[
"manager"
]
}
```

---

# 13. Resource Design

MCP Resource 用于提供只读信息。

例如：

企业制度：

```
doggyoa://knowledge/ipd-policy
```

---

组织：

```
doggyoa://organization/tree
```

---

流程模板：

```
doggyoa://workflow/templates
```

---

# 14. Dynamic Resource

资源不固定。

例如：

查询：

```
当前项目
```

动态生成：

```
doggyoa://project/123
```

---

# 15. Prompt Exposure

DoggyOA 可以提供企业 Prompt。

例如：

```
doggyoa.prompt.workflow-review
```

内容：

```
请作为流程专家分析以下审批流程风险。
```

---

# 16. MCP Client Architecture

DoggyOA Agent 调外部系统：

```
Agent Runtime

        │

MCP Client

        │

External MCP Server

```

---

例如：

## GitHub

```
DoggyOA

↓

GitHub MCP

↓

代码仓库
```

---

## Jira

```
项目Agent

↓

Jira MCP

↓

任务
```

---

## ERP

```
采购Agent

↓

ERP MCP

↓

库存
```

---

# 17. MCP Registry

保存 MCP Server：

Entity:

```
McpServer
```

字段：

```
id

name

endpoint

transport

authentication

status

```

---

示例：

```
ERP MCP

https://erp.xxx.com/mcp

OAuth2

Enabled
```

---

# 18. MCP Plugin Extension（基于 Symfony Bundle 机制）

插件通过 Symfony Bundle 注册 MCP 能力。`symfony/mcp-bundle` 支持 Capability System（Tools / Prompts / Resources / Resource Templates），插件只需在 Service 上添加 `#[McpTool]` 属性：

```php
// IPDPlugin Bundle 中的 Service
class IpdProjectService
{
    #[McpTool(name: 'ipd.project.query', description: '查询IPD项目信息')]
    public function queryProject(string $keyword): array
    {
        // ...
    }
}
```

安装插件后自动暴露 MCP Tool，无需额外注册。

---

# 19. Agent + MCP Integration

Agent Runtime：

```
Agent

↓

Tool Registry

↓

Local Tools

+

MCP Tools

```

---

AI 看见：

```
Available Tools:

workflow.create

knowledge.search

erp.queryInventory

github.createIssue

```

自动选择。

---

# 20. Security Architecture

企业环境必须：

## 20.1 Authentication

支持：

* API Key
* OAuth2
* JWT

---

## 20.2 Authorization

支持：

* RBAC
* ABAC
* Tenant Isolation

---

## 20.3 Audit

记录：

```
MCP Request

Tool

User

Arguments

Result

Time

```

---

# 21. Dangerous Operation Protection

高风险：

```
delete

publish

approve

payment
```

必须：

```
AI

↓

Confirmation

↓

Execute

```

---

# 22. MCP Event

事件：

```
McpToolCalled

McpPermissionDenied

McpExecutionFailed

McpResourceAccessed
```

---

# 23. Database Design

`symfony/mcp-bundle` 的 MCP Server 配置通过 `config/packages/mcp.yaml` 管理（推荐），如需数据库持久化可使用以下结构：

## mcp_server

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | UUID (PK) | |
| `name` | varchar(100) | 服务器名称 |
| `endpoint` | varchar(255) | MCP Server 地址 |
| `transport` | varchar(20) | stdio / http |
| `config` | json | 认证配置等 |
| `status` | varchar(20) | enabled / disabled |

## mcp_tool

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | UUID (PK) | |
| `server_id` | UUID (FK) | 关联的 MCP Server |
| `name` | varchar(100) | Tool 名称 |
| `schema` | json | Tool 参数 Schema |
| `permission` | varchar(100) | 所需权限标识 |

## mcp_execution_log

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | UUID (PK) | |
| `user_id` | integer | 调用用户 |
| `tool` | varchar(100) | Tool 名称 |
| `arguments` | json | 调用参数 |
| `result` | json | 执行结果 |
| `status` | varchar(20) | success / error |
| `created_at` | datetime | |

---

# 24. Deployment Modes

## Internal Mode

```
DoggyOA

↓

Internal MCP
```

---

## Enterprise Integration Mode

```
DoggyOA

↓

ERP MCP

MES MCP

CRM MCP

```

---

## Public AI Mode

```
ChatGPT

Claude

Cursor

↓

DoggyOA MCP Server

```

---

# 25. Example Scenario

## AI 创建采购流程

用户：

> 创建一个采购审批流程

Agent：

发现：

```
workflow.create
```

调用：

```
MCP Tool
```

执行：

```
Create Workflow Service
```

生成：

```
采购申请

↓

部门经理审批

↓

财务审批

↓

采购执行

```

返回：

```
流程创建成功
```

---

# 26. MCP 与低代码结合

这是 DoggyOA 最大特色。

AI 可以操作：

```
Entity

Field

Form

View

Workflow

Permission

Report

```

例如：

用户：

> 创建一个供应商管理模块

Agent：

调用：

```
entity.create

field.add

form.generate

workflow.create

permission.assign
```

最终生成：

完整业务模块。

---

# 27. Future Evolution

未来支持：

## MCP Federation

多个 MCP Server：

```
DoggyOA

+

ERP

+

MES

+

PLM

```

统一 Agent 调度。

---

## MCP Marketplace

第三方插件：

```
安装插件

↓

自动增加 MCP 能力
```

---

## Enterprise Agent Platform

最终：

```
企业系统

↓

MCP Layer

↓

AI Agent Ecosystem
```

---

# 28. Design Principles

| 原则 | 说明 |
|------|------|
| Symfony MCP Bundle First | 优先使用 `symfony/mcp-bundle` 和 `#[McpTool]` 属性 |
| Domain Driven | MCP 不绕过业务层，通过 Domain Service 访问数据库 |
| Permission First | 所有调用必须授权 |
| Tool Reuse | DoggyOA Tool (`#[AsTool]`) 可自动映射为 MCP Tool |
| Plugin Ready | 插件通过 Bundle 自动注册 MCP 能力 |
| Mate Compatible | 与 `symfony/ai-mate` 开发助手兼容（已配置） |
| Secure by Default | 默认安全 |
| Observable | 全链路审计 |

---

# RFC-0006 总结

## 当前状态

| 项 | 状态 |
|----|------|
| `symfony/ai-mate` (IDE Agent) | ✅ 已配置，`mcp.json` / `.mcp.json` / `serve` 脚本 |
| `symfony/ai-symfony-mate-extension` (Symfony Tool) | ✅ 已安装 |
| `symfony/mcp-bundle` (MCP Server Framework) | ⬜ 未安装（建议下一步安装） |
| MCP Tool (`#[McpTool]` 属性) | ⬜ 待实现（安装 MCP Bundle 后开始） |
| MCP Client | ⬜ 待实现 |
| MCP Federation | ⬜ 远期规划 |

## 架构全景

至此 DoggyOA AI 架构形成（标 ✅ 为已实现，标 ⬜ 为规划中）：

```
RFC-0001  AI Architecture        ✅ LlmRouter + ✅ Legacy AiManager
RFC-0002  Knowledge Base & RAG   ✅ pgvector + ⬜ symfony/ai-store
RFC-0003  AI Provider Framework  ✅ LlmGatewayInterface + 5 Gateway
RFC-0004  AI Gateway             ✅ PHP Chat Gateway + ⬜ Python AI Gateway
RFC-0005  Agent Framework        ⬜ 基于 symfony/ai-agent
RFC-0006  MCP Integration        ✅ Mate + ⬜ MCP Bundle
```

下一篇最值得继续的是：

**RFC-0007：AI Low-Code Designer Architecture（AI 驱动低代码设计器架构）**

> AI 不只是回答问题，而是通过 Agent + MCP + Low-code Runtime 自动创建企业应用。
