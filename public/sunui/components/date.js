/**
 * EF Date Component Helper
 * 提供 daterangepicker 等日期组件的公共配置和辅助方法
 */
window.EFDate = window.EFDate || {};

/**
 * 获取 daterangepicker 的 i18n 本地化配置
 * 根据 document.documentElement.lang 自动判断中英文
 * @param {string} format - 日期/时间格式 (例如: 'YYYY-MM-DD')
 * @returns {object} locale config object
 */
window.EFDate.getLocaleConfig = function(format = 'YYYY-MM-DD') {
  const lang = document.documentElement.lang || 'zh_CN';
  const isZh = lang === 'zh_CN';
  
  const base = isZh ? {
    applyLabel: '确定',
    cancelLabel: '清除',
    fromLabel: '从',
    toLabel: '到',
    customRangeLabel: '自定义',
    daysOfWeek: ['日', '一', '二', '三', '四', '五', '六'],
    monthNames: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
    firstDay: 1
  } : {
    applyLabel: 'Apply',
    cancelLabel: 'Clear',
    fromLabel: 'From',
    toLabel: 'To',
    customRangeLabel: 'Custom',
    daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
    monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    firstDay: 1
  };
  
  return Object.assign({}, base, { format: format });
};

/**
 * 获取 daterangepicker 的默认公共配置
 * 包含样式和多语言设置
 * @param {string} format - 日期/时间格式
 * @returns {object} default options for daterangepicker
 */
window.EFDate.getDefaultOptions = function(format = 'YYYY-MM-DD') {
  return {
    locale: window.EFDate.getLocaleConfig(format),
    buttonClasses: 'btn medium',
    applyButtonClasses: 'primary',
    cancelButtonClasses: 'secondary'
  };
};

/**
 * 自动初始化类名为 .date-picker, .datetime-picker, .time-picker 的输入框
 */
$(document).ready(function() {
    if (typeof $.fn.daterangepicker === 'undefined') {
        return;
    }

    // 1. Date Picker
    $('.date-picker').each(function() {
        if (!$(this).data('daterangepicker')) {
            $(this).daterangepicker({
                ...window.EFDate.getDefaultOptions('YYYY-MM-DD'),
                singleDatePicker: true,
                showDropdowns: true,
                autoUpdateInput: false
            }).on('show.daterangepicker', function(ev, picker) {
                const val = $(this).val().trim();
                if (val) {
                    const m = moment(val, 'YYYY-MM-DD', true);
                    if (m.isValid()) {
                        picker.setStartDate(m);
                        picker.setEndDate(m);
                        picker.updateView();
                    }
                }
            }).on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
            }).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }
    });

    // 2. DateTime Picker
    $('.datetime-picker').each(function() {
        if (!$(this).data('daterangepicker')) {
            $(this).daterangepicker({
                ...window.EFDate.getDefaultOptions('YYYY-MM-DD HH:mm'),
                singleDatePicker: true,
                showDropdowns: true,
                timePicker: true,
                timePicker24Hour: true,
                autoUpdateInput: false
            }).on('show.daterangepicker', function(ev, picker) {
                const val = $(this).val().trim();
                if (val) {
                    const m = moment(val, 'YYYY-MM-DD HH:mm', true);
                    if (m.isValid()) {
                        picker.setStartDate(m);
                        picker.setEndDate(m);
                        picker.updateView();
                    }
                }
            }).on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm'));
            }).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }
    });

    // 3. Time Picker (Hack using daterangepicker)
    $('.time-picker').each(function() {
        if (!$(this).data('daterangepicker')) {
            $(this).daterangepicker({
                ...window.EFDate.getDefaultOptions('HH:mm'),
                singleDatePicker: true,
                timePicker: true,
                timePicker24Hour: true,
                autoUpdateInput: false
            }).on('show.daterangepicker', function (ev, picker) {
                picker.container.addClass('time-picker-only');
                const val = $(this).val().trim();
                if (val) {
                    const m = moment(val, 'HH:mm', true);
                    if (m.isValid()) {
                        picker.setStartDate(m);
                        picker.setEndDate(m);
                        picker.updateView();
                    }
                }
            }).on('hide.daterangepicker', function (ev, picker) {
                picker.container.removeClass('time-picker-only');
            }).on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('HH:mm'));
            }).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }
    });
});
