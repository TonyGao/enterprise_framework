# EF组件库

作为这个企业框架的组件库，力争做到类似Delphi vcl级别的易用、简便、高度自定义和可编程。

## 已实现的

### Checkbox 勾选框

- **文件**: `public/sunui/components/checkbox.js`, `templates/form/ef_ui_form_theme.html.twig` (`checkbox_widget` block)
- **CSS class**: `ef-checkbox`, `ef-checkbox-checked`, `ef-checkbox-icon-hover`, `ef-icon-hover-disabled`
- **初始状态**: 服务端通过 `checked` 变量渲染 `ef-checkbox-checked` class 和 SVG 勾选图标，确保页面加载和 AJAX 加载时均正确显示选中状态
- **交互**: 点击切换 `ef-checkbox-checked` class，动态插入/移除 SVG 图标
- **FormType**: `CheckboxType::class`，需手动在 FormType 中 `$builder->add('field', CheckboxType::class)` 添加

### Switch 开关

- **文件**: `src/Form/Common/SwitchType.php`, `templates/form/ef_ui_form_theme.html.twig` (`switch_widget` block)
- **CSS class**: `ef-switch`, `ef-switch-checked`
- **FormType**: `SwitchType::class`，EntityProperty 中 `type='boolean'` 自动映射为 SwitchType
- **与 Checkbox 的区别**: `boolean` 类型默认渲染为 switch，checkbox 需手动添加

## 计划实现的

## 概念阶段

异构复制功能，即可以通过同类型、甚至不同类型组件的复制，粘贴到目标组件中。

比如人员控件，可以复制到另一个人员控件，甚至单人员选择器可以复制到多人员选择器。甚至直接复制到文本控件，复制结果是可以自定义的，比如部门+人名+工号。
