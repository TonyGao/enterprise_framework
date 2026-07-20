# 模型设计历史与版本能力（未来实现方案）

## 一、目标定位

本模块不是传统意义上的“版本控制系统”，而是低代码平台的：

> **模型演进管理系统（Model Evolution System）**

用于解决以下问题：

* 模型字段持续变化带来的不可追溯性
* UI / 表单 / API 对结构变化的兼容问题
* AI / MCP 对业务结构的可理解性
* 支持回溯、对比、恢复设计状态

核心原则：

* 运行时只存在一份“当前模型（Current Schema）”
* 历史不是运行依赖，而是设计记录

---

## 二、核心概念

### 1. Current Schema（当前模型）

系统唯一生效模型：

* 组织架构、岗位、员工等所有实体均只使用该结构
* 所有 CRUD 操作基于该结构
* 不做版本分裂

---

### 2. Migration（模型演进记录）

记录每一次结构变更：

支持操作类型：

* ADD_FIELD（新增字段）
* REMOVE_FIELD（删除字段）
* MODIFY_FIELD_TYPE（修改类型）
* RENAME_FIELD（重命名字段）
* CHANGE_RELATION（关系变更）

示例：

```json
{
  "id": 1001,
  "model": "department",
  "action": "ADD_FIELD",
  "field": "budget_code",
  "type": "string",
  "created_at": "2026-06-29 10:00:00"
}
```

---

### 3. Snapshot（设计快照）

用于记录某一时刻完整模型结构：

用途：

* 历史查看
* UI 回溯
* 差异对比
* 恢复设计

特点：

* 不参与运行
* 可由系统自动生成（发布 / 保存 / 定时）

结构示例：

```json
{
  "id": 501,
  "model": "department",
  "schema": {
    "fields": [
      { "name": "name", "type": "string" },
      { "name": "manager", "type": "relation:user" },
      { "name": "budget_code", "type": "string" }
    ]
  },
  "created_at": "2026-06-29 10:00:00"
}
```

---

### 4. Restore（恢复机制）

恢复不是覆盖历史，而是：

> 基于 Snapshot 生成新的 Migration

流程：

1. 选择 Snapshot
2. 计算差异（diff）
3. 生成 Migration
4. 应用到 Current Schema
5. 生成新的 Snapshot

---

## 三、系统架构设计

```
                ┌────────────────────┐
                │   Model Designer   │
                └─────────┬──────────┘
                          │
        ┌─────────────────┼──────────────────┐
        │                 │                  │
        ▼                 ▼                  ▼

Current Schema     Migration Log      Snapshot Store
        │                 │                  │
        └──────────┬──────┴──────┬──────────┘
                   ▼             ▼
             Diff Engine     Restore Engine
```

---

## 四、数据库设计建议

### 1. model_schema（当前模型）

```sql
id
model_key
schema_json
version_hash
updated_at
```

---

### 2. model_migration（变更记录）

```sql
id
model_key
action
payload_json
created_at
created_by
```

---

### 3. model_snapshot（快照）

```sql
id
model_key
schema_json
diff_from_migration_id
created_at
```

---

## 五、Diff 机制（核心能力）

用于：

* Snapshot 对比
* Restore
* AI 理解变更历史

输出结构：

```json
{
  "added": ["budget_code"],
  "removed": ["sap_code"],
  "modified": [
    {
      "field": "manager",
      "from": "single",
      "to": "multi"
    }
  ]
}
```

---

## 六、与系统模块的关系

### 1. 表单系统

* 使用 Current Schema
* Snapshot 用于历史表单展示

---

### 2. 流程系统

* 流程绑定字段必须基于 Current Schema
* 历史流程可回溯 Snapshot

---

### 3. 权限系统

* 权限字段依赖 Current Schema
* Snapshot 仅用于审计

---

### 4. AI / MCP

AI 可访问：

* 当前模型结构（必需）
* Migration 历史（增强理解）
* Snapshot（用于解释历史行为）

---

## 七、设计原则

### 1. 永远不运行历史模型

历史模型只用于：

* 查看
* 对比
* 恢复

不参与业务执行

---

### 2. 当前模型唯一性

系统所有业务：

* 只依赖一份 Schema

---

### 3. 演进优先，而不是版本优先

不要做：

* Department V1/V2/V3 运行切换

要做：

* Schema 如何一步步演进

---

### 4. 面向 AI 可解释性

所有变更必须可读：

* 为什么新增字段
* 为什么删除字段
* 谁在什么时候改的

---

## 八、实施阶段建议

### Phase 1（当前阶段）

不实现：

* Snapshot
* Diff
* Restore

只保留：

* Current Schema
* 基础字段扩展能力

---

### Phase 2（流程 + 表单完成后）

增加：

* Migration Log
* Schema Hash
* 基础变更记录

---

### Phase 3（平台成熟后）

增加：

* Snapshot
* Diff Engine
* Restore
* 可视化历史

---

### Phase 4（AI 增强阶段）

增加：

* AI 可解释 Schema Evolution
* 自动变更摘要
* 变更影响分析

---

## 九、最终定位

该系统最终不是：

> “版本管理系统”

而是：

> “低代码模型演进引擎 + 可解释历史系统”
