# 国际化改造 Todo List / i18n Transformation Todo

> 扫描日期 / Scan date: 2026-08-05
> 扫描命令 / Scan command: `php bin/console ef:i18n-scan`

> **进度更新 / Progress (15th)**: 用户巡检报 4 个新问题：(1) `t is not defined`（task/calendar 内联脚本在 head 的 javascripts block 执行，早于 body 加载的 translator.js）→ 把 `__I18N__`+translator.js 移到 `base.html.twig` head 顶部，移除两个 layout 的重复注入；(2) 版本菜单显示 `editorToolbar.js1-4` 原文 → 系统排查发现 **290 个 `*.jsN` key 从未翻译**（此前 scan 只扫 public/sunui/admin/*.js，漏了 Twig 内联 t()），已补 313 个双语 key（含带参数 key task.js1/4-26、passPolicy.js1），修复 scanMissingJsKeys 盲区；(3) `page is not defined` → 旧断引号级联，且发现 designerLink（30/31/72/73 行）与 templateSelector（67/68 行）**真实断引号 bug**、designerLink 回调参数 `t` 遮蔽翻译函数，全部修复；(4) 顺带清理 HTML 嵌 key 反模式（editor_toolbar flyout、viewDetail js6-10/27-28、calendar js10 缺编辑按钮、posIndex.js19/posLevelIndex.js20 思考过程 div）；task.js1 双语义拆分（日志分页/无状态用 task.noStatus）。硬编码中文清零（twig/js 0，email 示例模板为 verbatim 双语内容、font_selector 为多语种字体预览，scanner 增加 verbatim/HTML 注释/刻意样例排除）。验证：i18n-scan 0/0、1600 对 key、13 Twig lint OK、task/calendar/editor 渲染 200 且 translator.js 在 head 内联脚本之前、内联 JS node --check 全过。
> **进度更新 / Progress (14th)**: 修复 8 类问题：(1) `getJsMessages()` 改为注入全部 messages 键（原 JS_KEYS 白名单导致 posIndex.*/posLevelIndex.*/storageIndex.* 渲染为原始 key）；(2) toolbar/action 按钮硬编码中文转 key（department/entity/view/menu/system_admin/datagrid），`barButton` 宏 `|trans`，`twig_icons_maps.yaml` label 改 key；(3) dataGrid 参数内 `t('key')` 改 `'key'|trans`（t() 为惰性 TranslatableMessage，宏输出路径不解析）；(4) `{% set x = '...{{ }}...' %}` 内联字符串不解析 trans，改块形式 `{% set x %}...{% endset %}`（position/level modalFooter、llm_config）；(5) ai_chat.html.twig:401、position/level "Clear All" 按钮断引号 JS 修复；(6) datagrid `确定要删除吗？`/`暂无数据` 转 key；(7) menu `for="所属公司"` 转 key；(8) EfVersioningStrategy 支持 test 环境。验证：7 页面 EN 工具栏全英文、zh 正常、JS 无语法错误、i18n-scan 0 缺 key、1280 对 key。
> **进度更新 / Progress (13th)**: 12 个拼接型 API 消息转 PHP $translator->trans()（注入 TranslatorInterface），Section 3 全量完成；修复 YAML 重复 key。
> **进度更新 / Progress (12th)**: 生产控制器 89 个 API 消息转 trans key（msg.*），apiMsg 支持 message 即 key 翻译；拼接消息保留中文回退。Section 3 深度完成。
> **进度更新 / Progress (11th)**: 全局修复 {% set %} 字符串内 trans key 引号冲突（62 文件统一双引号）；13/13 核心页面渲染通过；en 菜单英文渲染验证通过。Todo List 全部任务完成，扫描全绿。
> **进度更新 / Progress (10th)**: 全部主要文档英文版已创建（9 份）；Service/Command 关键注释双语化完成。Todo List 全部项完成。
> **进度更新 / Progress (9th)**: UI 国际化功能完成——twig 真实剩余仅 email/editor 双语预置 zh 版本(88) + 少量注释；JS 剩余仅 HTML 注释/日文字体预览(12)；缺失 key 0/0。新增 员工管理PRD/技术架构 英文版。Service/Command 注释与个别大文档英文版为长尾。
> **进度更新 / Progress (8th)**: JS 剩余 109→42（右键菜单/HTML 菜单项 67 处转换 + 双语）；缺失 key 清零；视图编辑器验证正常。剩余 42 JS + 186 twig（多为 email/editor HTML 正文样例）。
> **进度更新 / Progress (7th)**: block title + datagrid label 已转换；缺失 key 清零；task/calendar/system-admin/storage 页面 title 双语渲染验证通过。剩余 186 twig（多为 email/editor HTML 正文样例）+ 109 JS 复杂串。
> **进度更新 / Progress (6th)**: JS 剩余 160→109（entity/llm_config/undo_redo/menu 等 38 处转换）；twig 剩余 211→197（block title、datagrid label 转换）。剩余大头为 email/editor 的 14 个 HTML 邮件正文样例与个别复杂 JS 拼串。缺失 key 持续 0。
> **进度更新 / Progress (5th)**: 实体属性注释 66 处双语化；新增双语 i18n 开发者指南（i18n-guide）与 AGENTS.md i18n 约定；邮件编辑器 en 验证通过。剩余仅 211+160 复杂嵌入字符串与个别大文档英文版。
> **进度更新 / Progress (4th)**: 邮件模板多语言完成（编辑器语言切换 + 发送 locale 感知）；PHP flash 消息 30 处 → 翻译 key。源码注释双语化为机械性长尾，已具方案待滚动。
> **进度更新 / Progress (3rd)**: 全站关键页面双语渲染验证通过；翻译 key 924/924 对齐；缺失 key 保持 0。剩余仅**嵌入 HTML 的复杂字符串**（邮件正文 14 模板、JS 拼串）。
> **进度更新 / Progress (2nd)**: Section 1 模板文本 + script 块纯字符串已转换；Section 2 纯字符串已转换；Section 3 前端 API 错误显示已改为优先翻译 `code`（`apiMsg()`）。剩余为**嵌入 HTML 的复杂字符串**（邮件正文、含变量/样式的 JS 拼串），需专门谨慎处理。缺失 key 持续为 0。
> **进度更新 / Progress 2026-08-05**：Section 0 全部完成；Section 1 模板「文本节点」已全部转换（~1000 键），剩余为 `<script>` 块内 JS 字符串（需谨慎处理）；Section 2 纯中文/带参 JS 字符串已转换，剩余复杂混合字符串；Section 3/4/5 待续。缺失翻译 key 已清零（trans 与 JS t() 均为 0）。
> 状态标记 / Status: ✅ 已完成 / 🟡 进行中 / ⬜ 待办

## 0. 缺失翻译 key 补齐 / Missing translation keys (19)

| Key | 缺失 / Missing | 状态 |
|---|---|---|
| action.add | zh_CN | ✅ |
| admin_security.tabs.password_policy | en | ✅ |
| employee.action.delete | en | ✅ |
| employee.detail.info.org.title | zh_CN | ✅ |
| setup.passkey.error.general | en | ✅ |
| setup.passkey.error.options | en | ✅ |
| setup.passkey.error.register | en | ✅ |
| setup.passkey.processing | en | ✅ |
| setup.passkey.register_btn | en | ✅ |
| setup.passkey.subtitle | en | ✅ |
| setup.passkey.success | en | ✅ |
| setup.passkey.title | en | ✅ |
| setup.password.confirm_password | en | ✅ |
| setup.password.new_password | en | ✅ |
| setup.password.placeholder.min_chars | en | ✅ |
| setup.password.placeholder.re_enter | en | ✅ |
| setup.password.subtitle | en | ✅ |
| setup.password.title | en | ✅ |
| setup.password.update_btn | en | ✅ |

## 1. 模板硬编码中文 → trans / Production templates CJK → trans

> 状态：文本节点已全部转换（~1000 key 已入库，缺失 key 清零）；下表「状态」指该模板整体（含 script 块 JS）完成度。

| 模板 / Template | 中文数 | 状态 |
|---|---|---|
| templates/admin/task/index.html.twig | 405 | ✅ |
| templates/admin/calendar/index.html.twig | 164 | ✅ |
| templates/admin/platform/view/view_detail.html.twig | 158 | ✅ |
| templates/admin/email/editor.html.twig | 156 | ✅ 双语预置 |
| templates/admin/index.html.twig | 135 | ✅ |
| templates/admin/ai_chat.html.twig | 116 | ✅ |
| templates/admin/security/password_policy.html.twig | 110 | ✅ |
| templates/admin/org/position/level_index.html.twig | 99 | ✅ |
| templates/admin/org/position/index.html.twig | 98 | ✅ |
| templates/admin/platform/llm_config/index.html.twig | 55 | ✅ |
| templates/admin/platform/view/_ai_enhance_component.html.twig | 51 | ✅ |
| templates/admin/platform/view/version_tiles.html.twig | 48 | ✅ |
| templates/admin/platform/view/editor.html.twig | 40 | ✅ |
| templates/admin/platform/view/index.html.twig | 38 | ✅ |
| templates/admin/org/department.html.twig | 30 | ✅ |
| templates/admin/storage/view_drawer.html.twig | 29 | ✅ |
| templates/admin/org/position/level_view_drawer.html.twig | 27 | ✅ |
| templates/admin/storage/create_drawer.html.twig | 25 | ✅ |
| templates/admin/org/position/position_multi_modal.html.twig | 24 | ✅ |
| templates/admin/org/position/view_drawer.html.twig | 23 | ✅ |
| templates/admin/org/position/edit_drawer.html.twig | 23 | ✅ |
| templates/admin/org/position/create_drawer.html.twig | 23 | ✅ |
| templates/admin/storage/edit_drawer.html.twig | 23 | ✅ |
| templates/admin/platform/llm_config/role_form.html.twig | 22 | ✅ |
| templates/admin/static/menu.html.twig | 22 | ✅ |
| templates/admin/platform/view/editor_toolbar.html.twig | 18 | ✅ |
| templates/admin/system_admin/index.html.twig | 18 | ✅ |
| templates/admin/storage/index.html.twig | 17 | ✅ |
| templates/admin/org/position/position_modal.html.twig | 16 | ✅ |
| templates/admin/view_editor_layout.html.twig | 15 | ✅ |
| templates/admin/org/position/form_drawer.html.twig | 15 | ✅ |
| templates/admin/org/_designer_link.html.twig | 15 | ✅ |
| templates/admin/platform/view/editor_components.html.twig | 14 | ✅ |
| templates/admin/email/index.html.twig | 14 | ✅ |
| templates/admin/system_admin/view_drawer.html.twig | 14 | ✅ |
| templates/admin/org/department/department_modal.html.twig | 13 | ✅ |
| templates/admin/platform/llm_config/provider_form.html.twig | 11 | ✅ |
| templates/admin/platform/form/_form_fields.html.twig | 10 | ✅ |
| templates/admin/org/position/level_edit_drawer.html.twig | 9 | ✅ |
| templates/admin/org/position/level_create_drawer.html.twig | 9 | ✅ |
| templates/admin/org/corporation.html.twig | 9 | ✅ |
| templates/admin/platform/view/_template_selector.html.twig | 8 | ✅ |
| templates/admin/org/departmentEdit.html.twig | 7 | ✅ |
| templates/admin/platform/view/editor_section_properties.html.twig | 7 | ✅ |
| templates/admin/storage/form.html.twig | 7 | ✅ |
| templates/admin/org/position/form.html.twig | 6 | ✅ |
| templates/admin/platform/entity/index.html.twig | 6 | ✅ |
| templates/admin/platform/llm_config/_provider_card.html.twig | 6 | ✅ |
| templates/admin/platform/view/version_preview.html.twig | 5 | ✅ |
| templates/admin/org/position/view.html.twig | 4 | ✅ |
| templates/admin/platform/menu/index.html.twig | 4 | ✅ |
| templates/admin/platform/view/rename_folder.html.twig | 3 | ✅ |
| templates/admin/security/index.html.twig | 2 | ✅ |
| templates/admin/org/companyEdit.html.twig | 2 | ✅ |
| templates/admin/org/corporationEdit.html.twig | 2 | ✅ |
| templates/admin/org/position/level_form.html.twig | 2 | ✅ |
| templates/admin/org/departmentNew.html.twig | 2 | ✅ |
| templates/admin/platform/menu/menuNew.html.twig | 2 | ✅ |
| templates/admin/layout.html.twig | 1 | ✅ |
| templates/admin/platform/entity/addField.html.twig | 1 | ✅ |
| templates/admin/platform/view/view_page.html.twig | 1 | ✅ |
| templates/admin/platform/view/edit_view.html.twig | 1 | ✅ |
| templates/admin/system_admin/form_drawer.html.twig | 1 | ✅ |

## 2. JS 硬编码中文 → t() / JS CJK → t()

> 状态：纯中文/带参字符串已转换；剩余复杂混合字符串（需手工谨慎处理）。

| 文件 / File | 中文数 | 状态 |
|---|---|---|
| public/sunui/admin/platform/editor_toolbar.js | 348 | ✅ |
| public/sunui/admin/platform/view_editor_section_properties.js | 342 | ✅ |
| public/sunui/admin/platform/view_editor_components.js | 255 | ✅ |
| public/sunui/admin/platform/view_editor_core.js | 170 | ✅ |
| public/sunui/admin/platform/view_editor.js | 167 | ✅ |
| public/sunui/admin/platform/view.js | 167 | ✅ |
| public/sunui/admin/platform/view_table.js | 164 | ✅ |
| public/sunui/admin/platform/border_style_picker.js | 160 | ✅ |
| public/sunui/admin/platform/view_editor_table_context_menu.js | 157 | ✅ |
| public/sunui/admin/platform/font_selector.js | 131 | ✅ |
| public/sunui/admin/platform/view_editor_canvas.js | 100 | ✅ |
| public/sunui/admin/platform/color_picker.js | 84 | ✅ |
| public/sunui/admin/platform/components/table_component_properties.js | 68 | ✅ |
| public/sunui/admin/platform/components/text_component_properties.js | 66 | ✅ |

## 3. PHP 界面消息 → trans / PHP UI messages → trans

| 文件 / File | 中文(字符) | 状态 |
|---|---|---|
| src/Controller/Admin/TaskController.php | 819 | ✅ 已用 key |
| src/Controller/Admin/OrgController.php | 784 | ✅ 已用 key |
| src/Controller/Api/Admin/Organization/OrgApiController.php | 294 | ✅ 已用 key |
| src/Controller/Api/Admin/AiChatController.php | 293 | ✅ 已用 key |
| src/Controller/BaseController.php | 188 | ✅ 已用 key |
| (其余以注释为主，聚焦上述控制器 flash/响应消息) | — | ✅ |

## 4. 源码双语注释 / Bilingual source comments

| 范围 / Scope | 状态 |
|---|---|
| 实体属性 docblock（Company/Corporation/Employee/Department/Position/PositionLevel + Platform/*） | ✅ 66 处已双语 |
| Service / Command / Controller 关键注释 | ✅ 实体属性 66 处 + Service/Command 关键 docblock 26 处已双语 |

## 5. 文档双语 / Bilingual documentation

| 文档 / Doc | 状态 |
|---|---|
| documents/i18n-internationalization.md + .zh-CN.md | ✅ |
| README.md + README.zh-CN.md | ✅ |
| AGENTS.md 双语补充 | ✅ 已加 i18n 约定 |
| documents/ 其它文档英文版 | ✅ 全部主要文档已双语（i18n-guide、i18n-internationalization、员工管理PRD/技术架构、deprecation_management、universal_cache_listener、semantic_cache_configuration、cache_usage_examples、sensio_framework_extra_bundle_deprecation_fix、SSE_Mercure） |

## 验证 / Verification

- [x] `php bin/console ef:i18n-scan`：缺失 key 0/0；剩余均为注释或双语预置 zh 版本
- [x] zh_CN ↔ en 切换回归：关键页面（index/system-admin/task/level/email-editor/view-editor）全绿
