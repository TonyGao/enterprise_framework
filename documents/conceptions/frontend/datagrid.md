# DataSource 实体

* 负责描述数据来源（Entity, SQL, API, 外部JSON等）
* 可单独管理和复用
* 类似Delphi的TDataSource，负责数据的获取和缓存。

# Datagrid 将被设计成高度可配置的完成CRUD的组件

可以多个 DataGrid 共用一个 DataSource，不同的角色看到同意哦数据源的不同视图。

它的核心功能是：

* 主体是二维表
  * 通过开关设置，可选中行，可单选、多选。
  * 顶部 toolbar 可以自定义，添加自定义按钮。
  * 顶部过滤条件，可以自定义过滤字段。
  * 左侧可以显示行数，也可以通过开关关闭。
  * 底部 toolbar 可以自定义，添加自定义按钮。
  * Filter Row 功能，将每列自动形成一个过滤器行，里边的控件是通过字段类型自动映射的。只需要一个开关即可启用。
  * 翻页器
    * 每页的行数
    * 第一页
    * 上一页
    * 输入具体的页数
    * 呈现总共的页数
    * 下一页
    * 最后一页
    * 刷新按钮
    * loading 状态呈现
    * Displaying 11 to 20 of 28 items
    * 翻页器toolbar自定义按钮
    * 自定义翻页器的位置(top, bottom)
  * 排序功能
    * 可以自定义哪些列可以排序。
    * 支持多列排序。
  * Column Group 功能
  * Aligning Columns 功能，可以自定义表头和表体的对齐方式。
  * Frozen Columns 功能，可以通过配置冻结某些列。
  * 格式化特定列的内容。可以通过自定义js函数来实现特定的格式化。
  * Frozen Rows，可以通过配置冻结某些行。
  * Group Rows in DataGrid，对表内容进行分组。
  * Row Editing，对行进行编辑，每个列根据字段类型渲染对应表单控件，操作过程记录修改的行，修改后统一提交，这里需要后端实现批量修改的接口。
  * Cell Editing in DataGrid, 对单元格进行编辑, 点击某个单元格后激活该单元格的编辑模式。
  * DataGrid Row Style, 满足特定条件的行，设置特定的样式。
  * DataGrid Cell Style， 满足特定条件的单元格，设置特定的样式。
  * Footer Rows in DataGrid, 在脚尾显示统计信息。
  * Merge Cells for DataGrid, 可以对某些单元格进行合并。
  * Context Menu on DataGrid, 可以对DataGrid进行右键菜单。
  * Expand row in DataGrid to show details, 可以对某一行进行展开，显示更多的信息。
  * Expand row in DataGrid to show subgrid, 可以对某一行进行展开，显示子表。
  * Loading nested subgrid data, 点击展开按钮，加载子表数据，支持多级子表。
  * Display large amount of data in DataGrid without pagination, 可以显示大量数据，不需要分页。
  * DataGrid Card View, 可以将DataGrid显示成卡片视图。
  * DataGrid Buffer View, 可以将DataGrid显示成缓冲区视图，向下滚动时，会加载更多数据。
  * DataGrid Virtual Scrolling, 可以将DataGrid显示成虚拟滚动视图，向下滚动时，会加载更多数据。
  * DataGrid Virtual Scroll View with Detail Rows, 可以将DataGrid显示成虚拟滚动视图，向下滚动时，会加载更多数据，同时可以展开行，显示更多的信息。
  * Fluid DataGrid, 可以给每列设置百分比宽度。
  * 可以拖拽列的宽度并记录下来，下次打开时，使用到记录的宽度。
  * 可以调整列的顺序，并且记录下来，下次打开时，恢复到记录的顺序。

<img src="assets/datagrid.jpg"  width="100%">

搜索过滤弹窗操作逻辑

(部门 = 人力资源部 AND 年龄 >= 35) OR 入职时间 在 2020-01-01 和 2024-01-01 之间

* 部门、年龄、入职时间 为字段名
* 「人力资源部」、「35」、「2020-01-01」、「2024-01-01」是字段值
* 「=」、「>=」是比较表达式
* 「AND」、「OR」是逻辑表达式

当鼠标悬停在「部门 = 人力资源部」、「AND」、「年龄 >= 35」、
「OR」、「入职时间 在 2020-01-01 和 2024-01-01 之间」时，会出现添加、删除按钮。

* 点击添加按钮可以输入字段名
* 选择字段名后，会触发比较表达式（如果有）的编辑和选择，比如「=」
* 选择逻辑表达式后会进入字段值的选择或者输入
* 点击删除按钮可以删除对应的整个字段表达式

# DataGridService 通用缓存机制使用指南

## 概述

DataGridService 现在支持通用的缓存机制，可以通过参数灵活控制是否启用缓存以及缓存时长。这使得开发者可以根据数据的特性来决定缓存策略。

## 方法签名

```php
public function getTableData(
    string $entityClass,
    int $page = 1,
    int $pageSize = 20,
    bool $useCache = false,
    ?int $cacheTtl = null
): array
```

### 参数说明

* `$entityClass`: 实体类名
* `$page`: 页码（从1开始）
* `$pageSize`: 每页数据量
* `$useCache`: 是否启用缓存（默认false）
* `$cacheTtl`: 缓存时间（秒），null时使用默认值（3600秒）

## 使用场景和示例

### 1. 不经常变更的数据（推荐长时间缓存）

适用于：岗位、部门、公司等组织架构数据

```php
// 岗位数据 - 缓存1小时
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Position',
    $page,
    $pageSize,
    true,  // 启用缓存
    3600   // 缓存1小时
);

// 部门数据 - 缓存30分钟
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Department',
    $page,
    $pageSize,
    true,  // 启用缓存
    1800   // 缓存30分钟
);
```

### 2. 经常变更的数据（短时间缓存或不缓存）

适用于：用户活动记录、订单数据、实时统计等

```php
// 用户活动记录 - 短时间缓存
$result = $dataGridService->getTableData(
    'App\\Entity\\User\\ActivityLog',
    $page,
    $pageSize,
    true,  // 启用缓存
    300    // 缓存5分钟
);

// 实时数据 - 不使用缓存
$result = $dataGridService->getTableData(
    'App\\Entity\\Realtime\\Statistics',
    $page,
    $pageSize,
    false  // 不使用缓存
);
```

### 3. 中等频率变更的数据（中等时间缓存）

适用于：配置数据、字典数据等

```php
// 系统配置 - 缓存15分钟
$result = $dataGridService->getTableData(
    'App\\Entity\\System\\Config',
    $page,
    $pageSize,
    true,  // 启用缓存
    900    // 缓存15分钟
);
```

## 缓存管理

### 清除特定实体的缓存

```php
// 清除岗位相关的所有缓存
$dataGridService->clearEntityCache('App\\Entity\\Organization\\Position');

// 清除部门相关的所有缓存
$dataGridService->clearEntityCache('App\\Entity\\Organization\\Department');
```

### 清除所有缓存

```php
// 清除DataGridService的所有缓存
$dataGridService->clearAllCache();
```

## 性能优化建议

### 1. 缓存时间设置原则

* **静态数据**（如岗位、部门）：1-24小时
* **半静态数据**（如配置、字典）：15分钟-1小时
* **动态数据**（如日志、统计）：1-10分钟或不缓存
* **实时数据**：不使用缓存

### 2. 缓存键的设计

缓存键自动包含以下信息：
* 实体类名（简化后）
* 页码
* 每页数据量
* MD5哈希（避免键名过长）

### 3. 内存使用优化

* 合理设置缓存时间，避免缓存过多数据
* 定期清理不需要的缓存
* 监控缓存命中率

## 实际应用示例

### 在Controller中的使用

```php
#[Route('/api/admin/org/position/list', name: 'api_org_position_list')]
public function positionList(Request $request, DataGridService $dataGridService): JsonResponse
{
    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);

    // 岗位数据不经常变更，使用长时间缓存
    $result = $dataGridService->getTableData(
        'App\\Entity\\Organization\\Position',
        $page,
        $pageSize,
        true,  // 启用缓存
        3600   // 缓存1小时
    );

    return $this->json($result);
}

#[Route('/api/admin/log/activity', name: 'api_log_activity')]
public function activityLog(Request $request, DataGridService $dataGridService): JsonResponse
{
    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);

    // 活动日志经常变更，使用短时间缓存
    $result = $dataGridService->getTableData(
        'App\\Entity\\Log\\ActivityLog',
        $page,
        $pageSize,
        true,  // 启用缓存
        300    // 缓存5分钟
    );

    return $this->json($result);
}
```

## 注意事项

1. **缓存一致性**：当数据发生变更时，记得清除相关缓存
2. **内存管理**：避免设置过长的缓存时间导致内存占用过高
3. **并发安全**：当前实现是线程安全的
4. **错误处理**：缓存失败时会自动降级到直接查询数据库

## 监控和调试

可以通过日志或性能监控工具来观察：
* 缓存命中率
* 查询响应时间
* 内存使用情况
* 缓存清除频率

这些指标可以帮助你优化缓存策略，提升应用性能。

# 一个页面可能存在多个Datagrid，需要考虑支持多个Datagrid

## 每个Datagrid组件都带一个id

## 全选功能

每个Datagrid组件都有一个全选复选框，用于选择所有数据行。当用户点击全选复选框时，会将当前页所有数据行的复选框选中。并且在前端实现以下逻辑来满足全选：

1. 当前DataGrid全部checkbox都被选中。
2. 以 DataGrid id 命名的变量 datagridAllSelected{id} 记录当前页是否全选。
3. 请求体中后台会根据 datagridAllSelected{id} 来判断此DataGrid是否被全选。

## 多选与翻页的实现

在多选的Datagrid内子项的同时，支持翻页进行继续多选的操作，在回到已选定的页面后，之前选中的子项仍然保持选中状态。

