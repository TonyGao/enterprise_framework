# 视图多版本管理规划

> 状态：规划中（Phase 0）
> 目标：在视图设计器（视图管理）中让一个视图拥有多个版本，每个版本对应一组独立的 twig 文件，并在右侧详情页提供版本管理能力。

---

## 1. 背景与现状

### 1.1 视图的物理存储（已有"版本化"雏形）

所有视图统一存放在 `templates/views/` 下，当前目录结构为：

```
templates/views/
  └── {folderPath}/                 # 父目录链，如 组织架构/
      └── {viewName}_{rand}/        # 视图目录（含随机后缀避免重名）
          └── 1_0/                  # ← 版本目录（v1.0），当前唯一且固定
              ├── {viewName}.design.twig   # 编辑器文件（含编辑器 DOM / 内联样式）
              └── {viewName}.html.twig     # 运行时文件（干净的可执行模板）
```

- 版本目录目前固定为 `1_0`，是"硬编码"在创建逻辑中的（`ViewEditorController::addView` 拼接 `1_0`）。
- `View.path` 字段存的是**含版本目录的完整相对路径**：`组织架构/offer_email_notification_8600f6/1_0`。
- 即：文件层面已有一个固定版本目录，但**系统管理层面没有任何多版本能力**。

### 1.2 相关数据模型（现状）

| 字段 | 现状 | 说明 |
|---|---|---|
| `View.path` | `组织架构/xxx/1_0` | 含版本目录，且版本与视图目录耦合 |
| `View.section_config` | JSON | 布局配置（contentWidth/width/unit/columns），**全局唯一，未按版本区分** |
| `View.template` | 内置视图用 | 系统内置视图走 `template`，不走版本目录 |
| `ViewField` | 视图级 | 字段配置挂在 View 下，未按版本区分 |
| 版本元数据 | 无 | 没有版本号/标签/当前版本指针的存储 |

### 1.3 关键链路（现状）

- **创建视图**：`ViewEditorController::addView` → 建目录 `…/{name}_{rand}/1_0/`，写 `{name}.html.twig` + `{name}.design.twig`，`path` 存 `…/1_0`。
- **打开编辑器**：`ViewEditorController::editor` → 用 `path + '/' + name + '.design.twig'` 定位设计文件。
- **保存**：`ViewEditorApiController::saveView` → 用 `path + '/' + name` 覆写 `.design.twig` 与 `.html.twig`。
- **运行时渲染**：依据 `path` + `name` 定位 `{path}/{name}.html.twig` 渲染（Twig `templates` 默认路径下 `views/…` 相对路径）。
- **删除视图**：`ViewEditorApiController::deleteView` 目前**只删数据库记录，不清理磁盘文件**（已发现孤儿文件问题）。

---

## 2. 目标与范围

### 2.1 目标

1. **逻辑视图与物理版本解耦**：视图设计器树中的一个视图节点 = 一个逻辑视图，可拥有 N 个版本。
2. **每版本 = 一组独立 twig 文件**：`1_0/`、`2_0/`、`1_1/`… 各自拥有独立的 `{name}.design.twig` 与 `{name}.html.twig`。
3. **系统管理层面的版本管理**：在右侧视图详情页提供版本控制：
   - **切换版本**（激活某版本为当前版本）
   - **新增版本**（从当前版本复制出一个新版本，或新建空版本）
   - **复制某版本为新版本**
   - 展示版本列表、标签、激活状态、最后修改时间
4. **编辑器按版本工作**：打开编辑器时处于哪个版本，就读写哪个版本对应的 twig 文件（默认当前激活版本，也可显式指定 `?version=…`）。
5. **误删可恢复**：删除视图 = 移入回收站（可恢复），彻底删除才销毁文件；多版本视图删除时明确提示版本数与文件数（详见 §15）。

### 2.2 非目标（本期不做）

- 版本差异对比 / 回滚到历史版本（可后续基于版本目录做）。
- 版本级 `section_config` / `ViewField` 隔离（本期版本共享视图级配置；如需按版本差异化配置，见 §12 取舍）。
- 发布/灰度/权限级别的版本流程。

### 2.3 架构动机：为什么视图要独立成模块并做版本控制

本系统的定位**不是严格意义的 OA 系统**，而是把经典的 **MVC 架构拆分为可独立演进、高度解耦的模块**分别实现：

```
Model（数据模型 / 实体管理）
View （视图管理：页面模板 + 编辑器）   ← 本规划的主角，独立模块 + 独立版本控制
Controller / 业务流（审批流、表单提交逻辑、报表数据等）   ← 后续模块
```

**视图独立成模块的动因：**

1. **解耦视图与"表单/OA 业务"的强绑定**。传统 OA 设计器把视图视为表单的附属品（跟着某个审批流/实体走），视图无法脱离具体业务独立存在。本系统反其道而行：视图是一等公民，只依赖「模板文件层 + 可选的数据模型绑定」，与审批流、表单提交逻辑无关。

2. **为 OA 表单之外的场景创造可能性**。高度解耦后，同一个视图模块可以承载各种形态的页面，例如：
   - **公司官网的页面视图**（品牌落地页、产品介绍页）
   - **公司制度的页面视图**（制度条款、规范文档页）
   - **人力部门发给待入职员工的 Offer 邮件视图**（正式商务信函页面）
   - 报表看板、信息展示页、落地推广页……
   - 只要是"一个渲染出来的页面/模板"，都能收敛到这个视图模块统一管理。

3. **MVC 分而治之**。模型、视图、业务流各自独立版本化、独立演进：
   - 模型只关心数据结构；
   - 视图只关心"长什么样、展示/录入什么"；
   - 审批流/报表只关心业务编排与数据。
   彼此通过**约定而非硬编码**衔接（如视图可选绑定一个实体），修改一方不影响另一方。

**版本控制对独立视图模块的意义：**

- 每个视图可独立演进（内容/样式按场景迭代），**版本化让视图自身具备可管理、可回退、可发布的一致性**，而不依赖任何外层业务流程。
- 因为视图独立存在、跨场景复用，版本管理是支撑它长期独立演化的基础设施：同一视图的不同版本对应不同内容快照，随时切换/复制，满足不同时段、不同场景的投放。
- 这也为 §14 的应用级整体版本预留了衔接：届时视图版本以 `asset_type='view'` 的身份，作为应用整体版本的一部分参与打包发布，而视图模块本身仍保持独立。

> 一句话总结：**视图独立成模块 + 独立版本控制，是"把系统做成高度解耦的 MVC 平台、而非单一 OA"这一整体定位的基础设施**——它让同一套页面/模板能力覆盖 OA 表单、官网、制度页、Offer 邮件等多种场景，且每个场景的视图都能被可靠地版本化管理。

---

## 3. 版本概念与目录约定

### 3.1 版本号约定

沿用现有 `1_0` 格式，采用语义化 `{major}_{minor}`（下划线分隔，与目录兼容）：

- `1_0`（v1.0）、`1_1`（v1.1）、`2_0`（v2.0）…
- **新增版本默认递增 minor**：`1_0 → 1_1`；也允许用户显式指定 `{major}_{minor}`（如发布大改版 `2_0`）。
- 校验：`/^\d+_\d+$/`，唯一性按视图内校验。

### 3.2 目标目录结构

```
templates/views/
  └── {folderPath}/
      └── {viewName}_{rand}/          # 视图目录（不再含版本）
          ├── 1_0/                    # v1.0
          │   ├── {viewName}.design.twig
          │   └── {viewName}.html.twig
          ├── 2_0/                    # v2.0
          │   ├── {viewName}.design.twig
          │   └── {viewName}.html.twig
          └── …（更多版本目录）
```

- **`View.path` 语义变更**：从「含版本目录」改为「视图目录基路径」，例如 `组织架构/offer_email_notification_8600f6`。
- **实际文件定位**统一由 `path + '/' + currentVersion + '/' + name.{design|html}.twig` 解析。
- 文件系统是 twig 文件**的事实来源**；数据库记录版本元数据与当前指针（见 §5）。

### 3.3 与内置视图的关系

- 系统内置视图（`built_in=true` 且走 `template`）不参与版本管理，维持现状。
- 自定义视图（用户视图）启用完整多版本能力。

---

## 4. 数据模型设计

### 4.1 `platform_view` 调整

| 字段 | 变更 | 说明 |
|---|---|---|
| `path` | **语义变更 + 存量迁移** | 改为视图目录基路径（去掉末尾 `/{version}`） |
| `current_version` | **新增** | 字符串，如 `1_0`；当前激活版本；默认 `1_0` |

### 4.2 新增表 `platform_view_version`

每版本一行，记录版本元数据：

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | uuid | 主键 |
| `view_id` | uuid FK | 关联视图（`platform_view`） |
| `version` | string | 版本号 `1_0`/`2_0`… |
| `label` | string? | 版本说明，如「初版」「春节活动版」 |
| `is_current` | bool | 是否当前版本（与 `view.current_version` 保持一致） |
| `created_by` / `created_at` / `updated_at` | CommonTrait | 审计 |

> 说明：`is_current` 冗余自 `view.current_version`，便于列表查询；`view.current_version` 作为权威指针。两者在写操作中同步更新。

> **通用化衔接（详见 §14.5）**：本表字段（version / label / is_current / 审计）与未来通用资产版本表 `platform_asset_version` 保持同构，后续可无痛升级为 `asset_type='view'` 的记录，作为应用级整体版本的一环。

---

## 5. 文件系统与数据库的关系

- **事实来源**：`{name}.design.twig` / `{name}.html.twig` 文件（内容）。
- **元数据来源**：`platform_view_version`（版本列表、标签、激活状态）。
- **一致性策略**：
  - 读（版本列表）：以 `platform_view_version` 为主，**与文件系统 reconcile**——扫描视图目录下匹配 `/^\d+_\d+$/` 的子目录，补齐缺失的版本记录（含「文件存在但 DB 无记录」的情况，如手工拷贝或历史遗留）。
  - 写（新增/复制/删除版本）：先写文件，后写 DB；失败时回滚已写文件。
  - **文件清理的唯一时机是回收站「彻底删除」**（§15）：软删/移入回收站保留文件保证可恢复；彻底删除才删除 DB 与磁盘视图目录（修复现状"删 DB 不删文件"的问题，同时提供误删恢复）。

---

## 6. 后端 API 设计

统一挂在 `ViewEditorApiController`（或新增 `ViewVersionApiController`），前缀 `/api/admin/platform/view/{id}/versions`。

### 6.1 版本列表
`GET /api/admin/platform/view/{id}/versions`

```json
{
  "code": 200,
  "data": {
    "currentVersion": "1_0",
    "versions": [
      { "version": "1_0", "label": "初版", "isCurrent": true,
        "designFile": "views/组织架构/xxx/1_0/xxx.design.twig",
        "updatedAt": "…" }
    ]
  }
}
```

### 6.2 新增版本
`POST /api/admin/platform/view/{id}/versions`

请求体（二选一来源）：
```json
{
  "fromVersion": "1_0",      // 复制来源；缺省=当前版本；传 "blank"=空版本
  "version": "1_1",          // 可选；缺省自动 minor 递增
  "label": "春节活动版"
}
```

行为：
1. 计算目标版本号（校验唯一、格式）。
2. 复制来源版本的 `.design.twig` + `.html.twig` 到新版本目录（`blank` 则生成初始模板）。
3. 插入 `platform_view_version` 记录。
4. 返回新版本信息。

### 6.3 切换版本
`POST /api/admin/platform/view/{id}/versions/{version}/activate`

行为：校验版本存在 → `view.current_version = {version}` → 同步 `platform_view_version.is_current`。

### 6.4 复制版本（显式入口）
`POST /api/admin/platform/view/{id}/versions/{version}/copy`

请求体：`{ "version": "1_2", "label": "…" }`（复制指定版本为新的目标版本，不改变当前指针）。

> 6.2 与 6.4 可合并实现（`fromVersion` 语义），保留两个入口便于前端区分「从当前新增」与「从指定版本复制」。

### 6.5 删除版本（可选）
`DELETE /api/admin/platform/view/{id}/versions/{version}`

约束：不允许删除最后一个版本；不允许删除当前版本（需先切换）。删除文件目录 + DB 记录。
### 6.6 编辑器指定版本（供 §7）

编辑器路由支持 `?version={version}` 参数；未传则默认当前激活版本。

### 6.7 版本预览（供 §8.5）

`GET /api/admin/platform/view/{id}/versions/{version}/preview`

- 渲染该版本运行时模板 `{path}/{version}/{name}.html.twig` 为可嵌入 iframe 的页面（去除全局 chrome）。
- 用于「切换确认预览」与版本行预览。
- 可选（P3）：`POST .../versions/{version}/preview-thumbnail` 生成/刷新缩略图缓存。

---

## 7. 编辑器集成

### 7.1 打开编辑器

`ViewEditorController::editor` 变更：
- 读取 `view.current_version`（或 `?version=` 参数覆盖）。
- 解析设计文件：`basePath . view.path . '/' . activeVersion . '/' . view.name . '.design.twig'`。
- 把 `activeVersion`、`viewPath`、`viewDesignPath` 传给前端（编辑器 UI 展示「正在编辑版本 v1_1」）。

### 7.2 保存

`ViewEditorApiController::saveView` 变更：
- 始终写入 `view.path + '/' + view.current_version + '/' + view.name.{design,html}.twig`。
- 保存成功后更新该版本记录的 `updated_at`。

### 7.3 编辑器内版本切换（可选增强）

- 编辑器工具栏加版本选择器，保存时若版本被切换，以切换后的版本为写入目标。
- 为避免"编辑中切换版本导致丢失"，本期**编辑器内只读显示当前版本**，切换统一在右侧详情页完成；编辑器打开后锁定版本。

---

## 8. 前端 UI（右侧视图详情页）

在 `view_detail.html.twig` 新增「版本管理」卡片（位于基本信息下方）。

### 8.1 版本列表

- 展示各版本：版本号 + 标签 + 状态标签（「当前」高亮）+ 最后修改时间。
- 每行操作：`设为当前`（切换）、`复制`、`编辑`（打开 `editor/{id}?version=x`）、（可选）`删除`。

### 8.2 操作区

- `新增版本`：弹窗，来源（当前 / 空）+ 版本号（自动递增预填）+ 标签。
- `复制到新版本`：在列表行上操作，指定目标版本号 + 标签。

### 8.3 打开编辑器

- 默认「打开编辑器」按钮 → `editor/{id}`（当前激活版本）。
- 每版本行的「编辑」→ `editor/{id}?version=x`。

### 8.4 交互数据流

```
右侧详情页加载 → GET /versions → 渲染版本卡片
  新增/复制 → POST /versions → 刷新列表
  切换     → POST /versions/{v}/activate → 刷新列表 + 更新「当前」标记
  预览     → GET /versions/{v}/preview → iframe 渲染该版本
```

### 8.5 版本预览（切换前可视化，重点）

**目的**：切换/复制版本前，让用户"看得到"这个版本大概长什么样，避免凭版本号盲切、误切。

**预览内容与机制**：
- 预览使用该版本的**运行时模板** `{path}/{version}/{name}.html.twig`（干净可渲染），**不用** `.design.twig`（含编辑器 DOM，不适合预览）。
- 提供预览路由：`GET /admin/platform/view/{id}/versions/{version}/preview`，返回可嵌入 iframe 的渲染页（去除全局 chrome，仅内容区）。
- iframe 内缩放适配（`transform: scale` + 固定高度）以便在弹窗/浮层中看到整页轮廓。

**预览形态（分级）**：

| 形态 | 触发 | 阶段 |
|---|---|---|
| **切换确认预览** | 点「设为当前」→ 确认弹窗内嵌 iframe 预览 + 版本信息 + 切换影响提示 | **P0 必做** |
| 行内缩略图 | 版本列表每行展示缩略图（保存时异步生成缓存） | P3 可选 |
| 悬停预览 | 鼠标悬停版本行弹出小浮层 iframe 实时预览 | P3 可选 |

### 8.6 版本切换的人性化交互

- **当前版本标识**：版本卡片顶部显示「当前」徽标 + 高亮边框；当前版本行禁用「设为当前」。
- **切换确认流程**：点「设为当前」→ 确认弹窗（内嵌预览 iframe + 版本号/标签/最后修改时间 + 提示"切换后打开编辑器将编辑此版本"）→ 确认后执行。
- **切换反馈**：成功 toast + 列表刷新 + 「当前」标记移动；若编辑器已打开，提示"请刷新编辑器以加载新版本"。
- **防误切**：切换到与当前相同版本时直接忽略并提示"已在当前版本"。
- **版本号可见性**：视图标题处（树节点 tooltip / 详情页标题）显示当前版本号，让用户随时清楚所处版本。

### 8.7 新增/复制版本的引导

- **新增版本弹窗**：
  - 来源：单选「从当前版本复制（推荐）/ 新建空白版本」，默认"从当前复制"。
  - 版本号：默认按当前版本 minor 递增预填（如 `1_0 → 1_1`），可手动修改；**即时唯一性校验**（已存在则标红提示，不提交）。
  - 标签：建议填写（如"春节活动版"）；留空自动生成"版本 1_1"。
  - 提交成功后**默认切换到新版本**（复制通常意味着"接着在新版上改"），并给出明确 toast。
- **复制到新版本**：在版本行操作，弹窗与新增一致（来源锁定为该行版本），目标版本号默认该版本 minor+1。
- **防重复提交**：提交按钮加载态 + 禁用；失败展示具体原因（版本号冲突 / 目录创建失败 / 权限），并保留弹窗输入不丢失。

### 8.8 异常与边界状态

- **空状态**：视图无任何版本（P0 约束至少保留一个，理论上不出现）→ 引导"创建第一个版本"。
- **版本目录缺失**（文件被删 / DB 有记录但文件不在）：列表标记「文件缺失」红色标，提供"重建"或"删除该记录"；reconcile 后自动清理。
- **最后一个版本**：不允许删除、不允许设为非当前——相关按钮置灰并提示原因。
- **网络失败**：版本列表加载显示骨架屏 + 失败重试；写操作失败不丢失用户已填内容。

### 8.9 可访问性与舒适度

- 弹窗遵循项目既有 `ui.modal` 约定：焦点陷阱、Esc 关闭、遮罩点击关闭。
- 键盘可达：版本列表项可 Tab 聚焦，Enter 触发「设为当前」。
- 信息密度：版本号与「当前」标识突出；标签、时间、操作按钮用次要层级（12px 灰、图标按钮）。
- 一致性：按钮复用 `btn` 体系，状态色复用 `ef-tag` 语义色（当前=blue、文件缺失=red 等）。

---

## 9. 运行时渲染

- 渲染视图模板时解析**当前激活版本**：
  `{view.path}/{view.current_version}/{view.name}.html.twig`。
- 涉及位置：运行时渲染视图模板的控制器/服务（如基于 `@views/…` 或 `views/{path}/{name}.html.twig` 的渲染点）统一改为上述解析。
- 若 `current_version` 为空或目录缺失：回退到版本列表里最新版本，并自动修正指针。

---

## 10. 数据迁移

### 10.1 结构迁移（Doctrine migration）

1. `platform_view` 新增 `current_version VARCHAR(32) DEFAULT '1_0'`。
2. 新建 `platform_view_version` 表。

### 10.2 存量数据迁移（一次性脚本 / 迁移内 SQL + 命令）

对每条 `type='view'` 且非内置的记录：
1. 从 `path` 末尾解析版本段（匹配 `/^\d+_\d+$/`），拆出 `version` 与 `basePath`。
2. `path = basePath`，`current_version = version`。
3. 为每版本写入一条 `platform_view_version`（`is_current` 对应当前）。
4. 扫描文件系统 `templates/views/`，对 DB 中缺失的版本目录补录（reconcile）。

> 建议独立命令 `app:view:normalize-versions` 执行存量迁移与完整性校验，可重复运行（幂等）。

---

## 11. 分阶段实施计划

| 阶段 | 内容 | 交付 |
|---|---|---|
| **P0（本期）** | 数据模型 + 迁移 + 文件定位重构 + 版本 CRUD API + 右侧详情页版本卡片 + 编辑器按版本读写 + **切换确认预览（iframe）** + **删除改回收站（软删/恢复/彻底删除 + 删除提示版本文件数）** | 可管理多版本，编辑器按当前版本工作，切换前可预览，误删可恢复 |
| P1 | 运行时渲染按当前版本解析 + 回退逻辑 | 线上效果与版本一致 |
| P2 | 回收站自动清理（保留期配置 + 定时任务，可选开启） | 空间回收 |
| P3（可选） | 编辑器内版本切换、版本标签/说明编辑、版本差异对比、**版本缩略图与悬停预览** | 体验增强 |
| **P4（展望）** | 抽取通用 `VersionNumber`，`platform_view_version` 泛化为 `platform_asset_version`（见 §14） | 为一体化版本铺路 |
| P5（展望） | 新增 `App` / `AppVersion` / `AppVersionBinding` 与「新建应用」向导（见 §14） | 应用级整体版本雏形 |
| P6（展望） | 模型/审批流/报表接入通用版本化 + 应用版本发布/回滚（见 §14） | 一体化版本闭环 |

> P0 建议先落核心闭环：**能列版本 → 能新增/复制 → 能切换 → 编辑器/渲染读对应版本**。

---

## 12. 关键决策与取舍

1. **`View.path` 语义变更（去掉版本段）**：核心解耦，代价是需要迁移存量数据。替代方案（保留含版本 path）会让"版本列表/切换"变成改 path 字符串，脆弱且易错。**采用语义变更 + 迁移**。
2. **是否新增 `platform_view_version` 表**：
   - 优点：版本标签、审计、显式列表、后续扩展（差异、发布状态）。
   - 缺点：多一张表、需维护一致性。
   - 备选（文件系统驱动）：不建表，版本即目录，`current_version` 只存 View 上。适合最小实现，但无版本元数据。
   - **本期建表**（更贴近"系统管理层面"诉求），实现时提供 `reconcile` 兜底文件系统差异。
3. **版本级配置隔离**：
   - 本期 `section_config` / `ViewField` 为**视图级共享**（所有版本同一套布局与字段配置，仅 twig 内容不同）。
   - 若后续需要版本级差异化配置，将 `section_config` 迁移到 `platform_view_version`，`ViewField` 增加 `version` 列。
4. **版本号策略**：默认 minor 递增；支持显式指定；同一视图内唯一。
5. **文件为先、DB 为准**：内容读写以文件为准，列表/元数据以 DB 为准，二者 reconcile。

6. **面向一体化的通用化边界（§14.6）**：本期就按可泛化方式实现——`platform_view_version` 字段与未来 `platform_asset_version` 同构；版本号解析/递增抽成 `VersionNumber`；文件定位抽成 `ViewPathResolver`；版本目录 `{major}_{minor}` 作为长期约定。这样应用级整体版本（App/AppVersion/Binding）落地时，视图版本只需注册为 `asset_type='view'`，不返工。

---

## 13. 边界情况与风险

- **删除最后一个版本**：禁止，至少保留一个版本。
- **删除当前版本**：需先切换到其他版本，或自动切换到剩余最新版本。
- **文件与 DB 不一致**（手工拷贝/历史遗留）：reconcile 补录；删除时避免误删 DB 有记录但文件缺失的版本。
- **编辑器未保存就切换版本**：编辑器锁定版本（保存写入当前版本），切换必须离开编辑器。
- **运行时版本目录缺失**：回退到最新版本并自动修正指针。
- **并发**：同一视图多版本并发保存，按版本目录隔离，天然无冲突；`current_version` 切换需在事务内更新指针与 `is_current`。
- **恢复时原父目录在回收站**：视图恢复到根目录，并提示用户（§15.4）。
- **彻底删除被应用版本引用（未来）**：P5/P6 后需校验 `app_version_binding`，有引用则阻止删除或同步解除引用（§15.6）。
- **回收站与文件缺失**：彻底删除时文件已不存在则仅删 DB；恢复时文件缺失则标记「文件缺失」提示（§8.8 / §15.5）。
- **误删恢复的一致性**：恢复只清 `deleted_at`，`platform_view_version`、字段配置、当前版本指针均不丢失，版本列表原样返回。

---

## 14. 一体化版本规划（未来展望：应用级整体版本）

> 本章是**远景架构**。目标形态类似 OA 表单设计器：新建一个「应用」，应用内部包含模型、视图、审批流、报表等资产，整个应用拥有**整体版本**概念。届时视图版本只是应用版本中的一环。**本章不阻塞、不推翻本章 1~13 的视图版本设计，而是定义它们的"升级路径"，并要求 P0 落地时避免返工。**

### 14.1 概念分层

```
应用 App（如「请假审批应用」）
 └── App 版本 AppVersion（整体发布快照，如 v1.0.0）
       ├── 模型版本   ModelVersion
       ├── 视图版本   ViewVersion        ← 本章 1~13 所做
       ├── 审批流版本 ApprovalFlowVersion
       └── 报表版本   ReportVersion
```

- **资产（Asset）**：应用下的可版本化组成部分，`asset_type ∈ {model, view, approval_flow, report, form, …}`。
- **资产独立演进**：每个资产可独立编辑、独立新增/复制/切换版本（即本章视图版本的能力，推广到所有资产）。
- **应用版本 = 一致性快照**：把一组资产的**特定版本**绑定为一个整体发布版本（如 `v1.0.0` 绑定 `model#2 + view#3 + flow#1`）。应用版本是"发布/部署/回滚"的最小单位。

### 14.2 通用版本化模型（可复用层）

目标：版本化做成通用层，视图/模型/审批流/报表共用同一套机制。

**通用资产版本表（未来形态）**

```
platform_asset_version
  id            uuid PK
  asset_type    string   -- 'view' | 'model' | 'approval_flow' | 'report' | ...
  asset_id      uuid     -- 指向对应资产实体
  version       string   -- '{major}_{minor}'（视图本期沿用）
  label         string?  -- 版本说明
  is_current    bool     -- 该资产的当前（编辑态）版本
  created_by / created_at / updated_at   -- CommonTrait
  UNIQUE(asset_type, asset_id, version)
```

**资产 current 指针**：沿用本章 §4 的模式——每个资产实体（View / Model / Flow / Report）各自一个 `current_version` 字段，或统一由版本表 `is_current` 决定（二选一，实施时统一）。

**应用版本 + 绑定表（未来形态）**

```
platform_app_version
  id, app_id, version('1.0.0'), label, status(draft|published), published_at, ...
platform_app_version_binding
  id, app_version_id, asset_type, asset_id, asset_version_id, ...
  UNIQUE(app_version_id, asset_type, asset_id)
```

### 14.3 版本生命周期与语义

- **资产版本 = 草稿演进**：随时编辑、新增、复制、切换（视图本期 P0 的能力）。
- **应用版本 = 一致性快照**：发布时锁定各资产版本（绑定表记录 `asset_version_id`）；同一 app_version 下同一资产只能绑定一个版本。
- **回滚/对比**：基于绑定表 + 各资产版本目录实现（后续阶段）。

### 14.4 文件布局的未来映射

```
本期： templates/views/{folderPath}/{viewName}_{rand}/{major}_{minor}/{name}.{design,html}.twig
未来： templates/apps/{appCode}/{assetType}/{assetName}_{rand}/{major}_{minor}/…
```

- 视图版本目录 = 资产版本目录；未来应用版本通过绑定表引用资产的某个版本目录。
- 迁移时目录整体搬迁，**版本目录 `{major}_{minor}` 命名长期保留**，保证资产版本可被应用版本稳定引用。

### 14.5 本期设计对一体化的衔接点（关键）

| 本期决策 | 与一体化衔接方式 |
|---|---|
| `View.current_version` 指针 | 复用到所有资产（各自一个 current 指针），或统一由版本表 `is_current` 决定 |
| `platform_view_version` 表 | 迁移为 `platform_asset_version(asset_type='view')`；本期字段（version/label/is_current/审计）保持同构即可无痛升级 |
| 版本号 `{major}_{minor}` | 抽成通用 `VersionNumber` 值对象/服务（解析/比较/递增），供所有资产与应用版本复用 |
| `View.path` + `current_version` 定位文件 | 抽成 `ViewPathResolver`，未来加 `asset_type` 参数即通用 |
| 文件系统 ↔ DB reconcile | 推广为通用 reconcile：每个 `asset_type` 一个扫描器 |
| 视图级 `section_config`/字段共享 | 如需资产版本级配置，将配置字段迁到资产版本表 |

### 14.6 对本期实施的要求（避免返工）

1. `platform_view_version` 字段与未来 `platform_asset_version` **保持同构**（version/label/is_current/审计），不引入视图特有又不通用的字段。
2. 版本号解析/递增逻辑**抽成独立类**（`VersionNumber`），不要内联在控制器里。
3. 文件定位统一走「`path` + `current_version`」解析器（`ViewPathResolver`），后期仅增加 `asset_type` 维度。
4. 视图目录内版本目录命名 `{major}_{minor}` 作为**长期约定**，供未来应用版本引用。
5. 新增实体/字段命名预留 `asset_type` 扩展空间（枚举集中管理，如 `AssetType` 常量类）。

### 14.7 演进路线（阶段扩展）

| 阶段 | 内容 |
|---|---|
| **P4** | 抽取通用 `VersionNumber` + 迁移 `platform_view_version` → `platform_asset_version`（view 先行验证） |
| **P5** | 新增 `App` / `AppVersion` / `AppVersionBinding` 实体 + 「新建应用」向导 |
| **P6** | 模型 / 审批流 / 报表接入通用版本化 + 应用版本发布/回滚/对比 UI |

> P0（本章 1~13）与 P4~P6 的关系：P0 先落地视图多版本闭环，P4 起把该机制**泛化**成资产版本层，再在其上加应用级整体版本。只要 P0 遵守 §14.6 的 5 条要求，P4~P6 无需推翻 P0 代码。

---

## 15. 删除与回收站机制（误删保护）

### 15.1 现状与问题

- 现状 `POST /view/{id}/delete` = Gedmo 软删除（置 `deleted_at`），**不清理磁盘文件**，且**没有任何恢复入口**（删除即"消失"，只靠 DB 软删残留，日常管理不可见）。
- 多版本之后，一个视图 = N 个版本 × 2 个 twig 文件 + `section_config` + `view_field`，**"一键删除"的误伤面成倍放大**。若用户手误删除一个含多个版本的视图，没有回收站则内容无法找回。

### 15.2 设计原则

1. **删除 ≠ 销毁**：删除按钮的语义统一改为「移入回收站」——DB 软删（置 `deleted_at`），**文件保留**（保证可恢复）。
2. **彻底删除才是销毁**：仅在回收站内显式执行，才真正删除 DB 行（view + `platform_view_version` + `view_field`）与**磁盘视图目录（含全部版本文件）**。这是**唯一清理文件的地方**，同时解决现状的孤儿文件问题。
3. **恢复无成本**：恢复即清除 `deleted_at`，文件原样在盘，立即可用。

### 15.3 数据与文件流转

```
删除视图 ──软删（deleted_at）──> 回收站（文件保留）
   ├─ 恢复   ──> 清除 deleted_at，回原位置；原父目录也在回收站则回根目录
   └─ 彻底删除 ──> 删 DB（view + view_version + view_field）+ 删磁盘 {viewDir} 目录（含全部版本）
```

### 15.4 后端 API

| 端点 | 语义 |
|---|---|
| `POST /view/{id}/delete` | **语义改为移入回收站**：软删该节点及后代；返回「受影响视图数 + 版本数 + 文件数」供前端提示。不删文件 |
| `GET /api/admin/platform/view/trash` | 回收站列表（软删节点，按 `deleted_at` 倒序；含类型 / 原路径 / 版本数 / 文件数 / 删除时间） |
| `POST /api/admin/platform/view/trash/{id}/restore` | 恢复（递归恢复后代）；原父目录仍可用则回原位，否则回根目录 |
| `POST /api/admin/platform/view/trash/{id}/purge` | 彻底删除（DB + 文件目录），需二次确认 |
| `POST /api/admin/platform/view/trash/purge-all`（可选） | 清空回收站 |
| （可选）定时任务 | 按保留期自动彻底删除超期项（复用现有 task_scheduler） |

### 15.5 前端 UI（回收站）

- **入口**：视图管理页工具栏加「回收站」→ 打开回收站面板（抽屉或独立页），扁平列表展示：图标（文件夹/视图）+ 名称 + 原路径 + 版本数 + 删除时间 + 「恢复」「彻底删除」；文件夹行可展开查看包含的视图。
- **删除确认弹窗增强**（移入回收站时）：
  - 明确文案："删除后将移入回收站，可在回收站恢复"。
  - 多版本视图显示**版本数与文件数**："该视图含 N 个版本、共 M 个文件"。
  - 文件夹删除提示包含的子节点数量。
- **彻底删除二次确认**：红色警告 + "将永久删除 N 个版本、共 M 个文件，不可恢复"，需勾选确认或输入视图名。
- **恢复反馈**：成功 toast；若原父目录在回收站，提示"将恢复到根目录"。
- **空状态**：回收站为空时展示空状态引导文案。

### 15.6 与多版本 / 一体化版本的衔接

- 彻底删除会连带删除 `platform_view_version` 记录与所有版本目录；**回收站不影响版本概念**——恢复后版本列表、当前版本、版本号原样返回。
- 未来应用级版本（§14）若绑定该视图版本，彻底删除前需**校验是否存在应用版本引用**：有引用则提示"被应用版本引用，不可删除"或同步解除引用（见 P5/P6）。
- 复用 §5 的 reconcile：回收站清单可与文件系统核对，标记「文件缺失」项。

### 15.7 保留策略

- 默认：**仅手动彻底删除**（无自动清理，最安全）。
- 可选：配置保留期（如 30 天），由定时任务自动将超期回收站项彻底删除；该策略可在 P3 后再开启。

---

## 附录：涉及的主要代码位置（改动清单）

| 位置 | 改动 |
|---|---|
| `src/Entity/Platform/View.php` | 新增 `currentVersion` 字段 + getter/setter |
| 新 `src/Entity/Platform/ViewVersion.php` | 版本实体 |
| `migrations/` | 结构迁移 + 存量数据迁移命令 |
| `src/Command/EfNormalizeViewVersionsCommand.php`（新） | 存量迁移/reconcile |
| `src/Controller/Api/Admin/Platform/ViewEditorApiController.php` | `saveView` 按当前版本写入；新增 versions 相关端点 |
| `src/Controller/Admin/Platform/ViewEditorController.php` | `addView`（建 `current_version`）、`editor`（按版本定位设计文件）、`viewDetail`（传入版本数据） |
| `templates/admin/platform/view/view_detail.html.twig` | 新增「版本管理」卡片 + 版本操作 |
| `public/sunui/admin/platform/view.js` | 版本操作的 AJAX 与刷新 |
| 运行时渲染点 | 按 `path/current_version/name.html.twig` 解析 |
| `src/Service/AI/…`（ViewFileToolProvider / ViewEditorToolProvider） | 文件定位统一走 `path/current_version` |
| `ViewEditorApiController::deleteView` | 语义改为**移入回收站**（软删 + 返回版本/文件数） |
| 新增回收站端点（trash 列表 / restore / purge） | 见 §15.4，可并入 `ViewEditorApiController` 或新增 `ViewTrashApiController` |
| `templates/admin/platform/view/view_detail.html.twig` + `view.js` | 删除确认弹窗增强（版本/文件数提示）、回收站面板 |
