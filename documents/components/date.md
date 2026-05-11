# date 日期时间选择器组件

基于 [daterangepicker](https://www.daterangepicker.com/) 封装的日期/时间选择器，通过框架帮助对象 `EFDate` 统一管理配置与多语言，支持 CSS 类名自动初始化。

---

## 依赖

框架已在 `base.html.twig` 中全局加载，无需额外引入：

| 文件 | 说明 |
|---|---|
| `/lib/daterangepicker/moment.min.js` | moment.js |
| `/lib/daterangepicker/daterangepicker.js` | daterangepicker 插件 |
| `/lib/daterangepicker/daterangepicker.css` | 插件样式 |
| `sunui/components/date.js`（`ui` asset package） | `EFDate` 帮助对象 + 自动初始化 |

---

## EFDate 帮助对象

### `EFDate.getLocaleConfig(format)`

根据 `<html lang="...">` 自动返回中文或英文的 locale 配置，并附加 `format` 字段。

```js
// lang="zh_CN" → 返回中文配置
// 其他 lang → 返回英文配置
const locale = EFDate.getLocaleConfig('YYYY-MM-DD');
```

### `EFDate.getDefaultOptions(format)`

返回 daterangepicker 的公共默认配置（locale + 按钮样式），使用展开运算符合并到具体配置中。

```js
const opts = EFDate.getDefaultOptions('YYYY-MM-DD');
// {
//   locale: { applyLabel, cancelLabel, daysOfWeek, monthNames, firstDay, format, ... },
//   buttonClasses: 'btn medium',
//   applyButtonClasses: 'primary',
//   cancelButtonClasses: 'secondary'
// }
```

---

## 自动初始化

`date.js` 在 `$(document).ready` 时会自动扫描以下 CSS 类名并完成初始化，**无需手动编写 JS**。支持的类名：

| CSS 类名 | 类型 | 输出格式 |
|---|---|---|
| `.date-picker` | 日期 | `YYYY-MM-DD` |
| `.datetime-picker` | 日期时间 | `YYYY-MM-DD HH:mm` |
| `.time-picker` | 时间 | `HH:mm` |

已初始化的实例不会重复初始化（检查 `$(this).data('daterangepicker')`）。

---

## 四种选择器用法

### 1. 日期选择器 `.date-picker`

```html
<span class="ef-input-wrapper ef-input-rounded">
  <input class="ef-input ef-input-size-medium text date-picker"
         type="text" placeholder="请选择日期" autocomplete="off">
</span>
```

输出值格式：`YYYY-MM-DD`，例如 `2026-04-16`。

手动初始化示例（如需自定义）：

```js
$('.date-picker').daterangepicker({
    ...EFDate.getDefaultOptions('YYYY-MM-DD'),
    singleDatePicker: true,
    showDropdowns: true,
    autoUpdateInput: false
}).on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('YYYY-MM-DD'));
}).on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('');
});
```

---

### 2. 日期时间选择器 `.datetime-picker`

```html
<span class="ef-input-wrapper ef-input-rounded">
  <input class="ef-input ef-input-size-medium text datetime-picker"
         type="text" placeholder="请选择日期时间" autocomplete="off">
</span>
```

输出值格式：`YYYY-MM-DD HH:mm`，例如 `2026-04-16 09:00`。

手动初始化示例：

```js
$('.datetime-picker').daterangepicker({
    ...EFDate.getDefaultOptions('YYYY-MM-DD HH:mm'),
    singleDatePicker: true,
    timePicker: true,
    timePicker24Hour: true,
    showDropdowns: true,
    autoUpdateInput: false
}).on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm'));
}).on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('');
});
```

---

### 3. 时间选择器 `.time-picker`

通过 `time-picker-only` CSS class 隐藏 daterangepicker 的日历表格，仅保留时间选择器部分。

需要在页面中声明以下 CSS（或在全局样式中加入）：

```css
.time-picker-only .calendar-table {
    display: none !important;
}
```

```html
<span class="ef-input-wrapper ef-input-rounded">
  <input class="ef-input ef-input-size-medium text time-picker"
         type="text" placeholder="请选择时间" autocomplete="off">
</span>
```

输出值格式：`HH:mm`，例如 `09:00`。

手动初始化示例：

```js
$('.time-picker').daterangepicker({
    ...EFDate.getDefaultOptions('HH:mm'),
    singleDatePicker: true,
    timePicker: true,
    timePicker24Hour: true,
    autoUpdateInput: false
}).on('show.daterangepicker', function(ev, picker) {
    picker.container.addClass('time-picker-only');
}).on('hide.daterangepicker', function(ev, picker) {
    picker.container.removeClass('time-picker-only');
}).on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('HH:mm'));
}).on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('');
});
```

---

### 4. 日期时间范围选择器（手动初始化）

**没有对应的自动初始化类名**，需手动初始化。建议使用 `.datetimerange-picker` 作为类名以保持风格统一。

```html
<span class="ef-input-wrapper ef-input-rounded" style="width: 100%; min-width: 380px;">
  <input class="ef-input ef-input-size-medium text datetimerange-picker"
         type="text" placeholder="请选择日期时间范围" autocomplete="off" style="width: 100%; cursor: pointer;">
</span>
```

输出值格式：`YYYY-MM-DD HH:mm - YYYY-MM-DD HH:mm`，例如 `2026-04-01 09:00 - 2026-04-16 18:00`。

```js
$('.datetimerange-picker').daterangepicker({
    ...EFDate.getDefaultOptions('YYYY-MM-DD HH:mm'),
    timePicker: true,
    timePicker24Hour: true,
    autoUpdateInput: false
}).on('apply.daterangepicker', function(ev, picker) {
    $(this).val(
        picker.startDate.format('YYYY-MM-DD HH:mm') +
        ' - ' +
        picker.endDate.format('YYYY-MM-DD HH:mm')
    );
}).on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('');
});
```

---

## 与 `.ef-input-wrapper` 配合使用

当输入框被 `.ef-input-wrapper` 包裹时，点击 wrapper 区域（非 input 本身）不会自动触发 daterangepicker。可在页面中加入以下代码，将点击事件转发给内部 input：

```js
$('.ef-input-wrapper').on('click', function(e) {
    if ($(e.target).is('input') || $(e.target).closest('.ef-input-clear-btn').length) return;
    var $input = $(this).find('input');
    if ($input.length > 0) {
        $input[0].click();
        $input.focus();
    }
});
```

---

## 动态添加的元素

对于通过 AJAX 或 JS 动态插入 DOM 的输入框，`date.js` 的自动初始化不会覆盖这些元素，需手动调用初始化。已初始化过的元素可通过 `$(el).data('daterangepicker')` 判断，避免重复初始化：

```js
function initDatePicker($input) {
    if ($input.data('daterangepicker')) return;
    $input.daterangepicker({
        ...EFDate.getDefaultOptions('YYYY-MM-DD'),
        singleDatePicker: true,
        showDropdowns: true,
        autoUpdateInput: false
    }).on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD'));
    }).on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });
}
```

---

## 样式说明

- `ef-input-rounded`：圆角输入框外壳
- 不加 `ef-input-rounded`：直角输入框外壳
- `ef-input-size-medium`：中等尺寸高度（36px）

以上样式类均来自 sunui 样式体系，与 daterangepicker 无关。
