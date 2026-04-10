/**
 * 筛选器下拉选择组件辅助函数
 *
 * 用于同步 URL 参数到筛选器下拉选择组件
 *
 * 使用方法：
 *
 * // 在页面加载时初始化所有筛选器下拉组件
 * document.addEventListener('DOMContentLoaded', function() {
 *     initFilterSelectsFromUrl({
 *         'department_id': 'filterDepartment',
 *         'position_id': 'filterPosition',
 *         'employment_status': 'filterEmploymentStatus'
 *     });
 * });
 *
 * // 或者手动初始化单个组件
 * initFilterSelect('filterDepartment', 'department_id', '123');
 */

/**
 * 初始化单个筛选器下拉组件
 * @param {string} inputId - 输入框ID（隐藏input的ID或可见select的ID）
 * @param {string} paramKey - URL参数键名
 * @param {string} url - 可选，默认为当前窗口URL
 */
function initFilterSelectFromUrl(inputId, paramKey, url) {
  url = url || window.location.href;
  const urlObj = new URL(url);
  const value = urlObj.searchParams.get(paramKey) || '';

  let input = document.getElementById(inputId);
  if (!input) return;

  // 对于隐藏输入（ui.select 或 ui.filterSelect 生成的），需要找到对应的包装器
  if (input.type === 'hidden') {
    // 隐藏 input 是 wrapper 的前一个兄弟元素
    const wrapper = input.nextElementSibling;
    if (wrapper && wrapper.classList.contains('ef-select-view-single')) {
      initSelectDisplay(wrapper, input, value);
    }
  } else if (input.classList.contains('ef-select-view-input')) {
    // 直接找到的可见输入框，需要找到包装器
    const wrapper = input.closest('.ef-select-view-single');
    if (wrapper) {
      initSelectDisplay(wrapper, input, value);
    }
  }
}

/**
 * 初始化选择框的显示状态
 */
function initSelectDisplay(wrapper, input, value) {
  const valueSpan = wrapper.querySelector('.ef-select-view-value');
  const inputEl = wrapper.querySelector('.ef-select-view-input');
  const popup = document.getElementById(wrapper.getAttribute('contentid'));

  let label = '';

  // 从下拉弹窗中找到对应的选项标签
  if (popup) {
    const optionItems = popup.querySelectorAll('.ef-select-option');
    optionItems.forEach((item) => {
      if (item.getAttribute('value') === value) {
        const contentSpan = item.querySelector('.ef-select-option-content');
        if (contentSpan) {
          label = contentSpan.textContent;
        }
        // 同时标记该选项为选中状态
        item.classList.add('ef-select-option-active');
      } else {
        item.classList.remove('ef-select-option-active');
      }
    });
  }

  if (value && label) {
    if (valueSpan) {
      valueSpan.textContent = label;
      valueSpan.classList.remove('ef-select-view-value-hidden');
    }
    if (inputEl) {
      inputEl.classList.add('ef-select-view-input-hidden');
    }
    wrapper.setAttribute('chosen', 'true');
    // 更新图标为关闭按钮
    const iconWrapper = wrapper.querySelector('.ef-select-view-icon');
    if (iconWrapper) {
      iconWrapper.innerHTML =
        '<i class="fa-regular fa-circle-xmark ef-select-clear-btn"></i>';
    }
  } else {
    if (valueSpan) {
      valueSpan.textContent = '';
      valueSpan.classList.add('ef-select-view-value-hidden');
    }
    if (inputEl) {
      inputEl.classList.remove('ef-select-view-input-hidden');
    }
    wrapper.setAttribute('chosen', 'false');
    // 重置图标为下拉箭头
    const iconWrapper = wrapper.querySelector('.ef-select-view-icon');
    if (iconWrapper) {
      iconWrapper.innerHTML = `<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-expand" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter" style="transform: rotate(-45deg);"><path d="M7 26v14c0 .552.444 1 .996 1H22m19-19V8c0-.552-.444-1-.996-1H26"></path></svg>`;
    }
  }
}

/**
 * 从 URL 参数初始化多个筛选器下拉组件
 * @param {Object} mappings - 映射对象，格式为 { urlParamKey: inputId }
 * @param {string} url - 可选，默认为当前窗口URL
 */
function initFilterSelectsFromUrl(mappings, url) {
  url = url || window.location.href;

  Object.entries(mappings).forEach(([paramKey, inputId]) => {
    initFilterSelectFromUrl(inputId, paramKey, url);
  });
}

/**
 * 获取筛选器下拉组件的值
 * @param {string} inputId - 输入框ID
 * @returns {string} 选中的值
 */
function getFilterSelectValue(inputId) {
  let input = document.getElementById(inputId);
  if (!input) return '';

  // 对于隐藏输入，需要获取值
  if (input.type === 'hidden') {
    return input.value;
  }

  return input.value;
}

/**
 * 设置筛选器下拉组件的值
 * @param {string} inputId - 输入框ID
 * @param {string} value - 要设置的值
 */
function setFilterSelectValue(inputId, value) {
  let input = document.getElementById(inputId);
  if (!input) return;

  if (input.type === 'hidden') {
    input.value = value;
    const wrapper = input.nextElementSibling;
    if (wrapper && wrapper.classList.contains('ef-select-view-single')) {
      initSelectDisplay(wrapper, input, value);
    }
  } else {
    input.value = value;
  }
}
