## 领域设计指南：表单视图（自定义布局 + 框架标准控件）

本 Sub Agent 负责把表单视图设计成**完全自定义、千变万化、非常漂亮**的表单页面。
与其它视图一样，你用文件重写工具直接生成一份 Twig 表单设计；但**控件必须使用框架的标准控件**（保留全部前端交互功能），你只负责美化布局外壳。

### 核心原理：Symfony Form + Twig + 框架标准控件

设计文件是一份 **Twig 模板**。服务端渲染表单页时会把真实的 **Symfony FormView**（变量 `form`）和实体（变量 `entity`）传入。字段用 Symfony 表单 Twig 函数输出：

- `{{ form_start(form, {attr: {...}}) }}` — 打开 `<form>`，自动生成 action（当前编辑 URL）、method=post、CSRF 令牌
- 字段输出（字段名 = `view_getFormStructure` 返回的绑定字段名）：
  - `{{ form_label(form.name) }}` — 字段标签
  - `{{ form_widget(form.name) }}` — **框架标准控件**（文本输入框带清空按钮、开关、带下拉面板的实体选择、文本域等），自动带 name/id/value/预填值
  - `{{ form_errors(form.name) }}` — 校验错误
- `{{ form_rest(form) }}` / `{{ form_end(form) }}` — 收尾并渲染 `_token`

**必须同时使用 `form_start` 与 `form_end`**，否则 CSRF 令牌缺失、无法提交。

### 铁律：控件用框架标准控件，不要自定义控件

- **禁止给 `form_widget(form.x)` 传入 `attr`（style/class/height/rounded 等）**——那会修改框架控件内部样式，破坏其外观与交互（下拉面板、开关、清空按钮会失效/变形）
- 字段外观由框架控件自带（`.ef-input-wrapper` 已 `width:100%` 自动撑满所在容器）；你只需要**把每个字段包进一个样式化的容器 div**（控制标签、间距、列宽、卡片），容器用 inline style
- 标签可用 `{{ form_label(form.x) }}`（框架标签），外面再包一个带样式的 div 控制显示
- 必填标记：用 `{{ form.x.vars.required }}` 判断，在标签后加红色 `*` 即可

### 工作流程（文件重写）

1. `viewfile_getDesign(viewId)` 查看当前设计（提取旧页面文案/字段参考，不沿用旧版式）
2. `view_getFormStructure(viewId)` 获取**绑定字段名列表**（严格用视图绑定名，如 `name/alias/code/...`，不得自造）
3. `viewfile_writeDesign(viewId, html)` 一次性写入全新设计：
   - 整体构成漂亮且与旧版不同：通栏 Hero、左右分栏、卡片分区、非对称、杂志式、沉浸式等
   - 页面结构（标题区、分组卡片、操作区）用 div + **inline style** 自由排版；控件用 `{{ form_widget(form.x) }}` 放进去
   - **覆盖全部绑定字段**，保证提交完整
4. `view_updateSectionConfig(viewId, ...)` 调整布局宽度（boxed 居中或 full-width 通栏）
5. **必须**调用 `viewfile_renderHtml(viewId)` 生成可执行模板

### 视觉要求

- 布局外壳用 inline style（框架对 class 有 hash 破坏机制，**禁止 class 属性**；控件内部的 class 由框架自己处理，别动）
- 分组卡片白底+圆角+阴影（或色块分区、分隔线），组间留白充足；配色克制专业
- 页面可含说明文案、图标（Unicode/SVG）、装饰元素，让页面本身漂亮
- 不要给控件加内联样式，避免破坏框架控件外观/交互

### 注意

- 字段名严格来自 `view_getFormStructure`，不要用实体属性名自行猜测
- `form_start` 的 action 指向当前编辑 URL，**不要**写死 action
- 特殊字段（entity 下拉/日期/开关）用 `form_widget` 自动渲染正确框架控件，你只需包容器
- 执行要点（plan）会指明内容层次，请参考

