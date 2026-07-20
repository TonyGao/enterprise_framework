$(document).ready(function () {
  function syncSelectPopupSize(selectInput, contentId) {
    const panel = $('#' + contentId);
    if (panel.length === 0) {
      return;
    }

    const elementWidth = selectInput.outerWidth();
    panel.css({
      width: elementWidth,
      minWidth: elementWidth,
    });
  }

  function autoFitDropdownWidth(panel) {
    if (!panel || !panel[0]) return;
    panel.css({ width: '', minWidth: '' });
    requestAnimationFrame(function () {
      var panelEl = panel[0];
      var rect = panelEl.getBoundingClientRect();
      var viewportWidth = window.innerWidth;
      var overflowRight = rect.right - viewportWidth;
      var overflowLeft = -rect.left;
      if (overflowRight > 0 || overflowLeft > 0) {
        var currentLeft = parseFloat(panelEl.style.left) || 0;
        var adjust = 0;
        if (overflowRight > 0) adjust = -(overflowRight + 10);
        if (overflowLeft > 0) adjust = overflowLeft + 10;
        panel.css({ left: (currentLeft + adjust) + 'px' });
      }
    });
  }

  let elements = document.getElementsByClassName('ef-select');
  let config = {
    prevent_repeat: true,
  };

  /**
   * 遍历class是ef-select的dom，获取id，存储selectList对象数组中
   *
   * selectList对象格式
   * {
   *  selectEleId: '557919876',
   *  activeEle: '1999887089',
   *  list: [
   *    { id: "1999887089", idx: 1, value: 'Beijing' },
   *    { id: "717768562", idx: 1, value: 'Shanghai' },
   *    { id: "446617704", idx: 1, value: 'Guangzhou' },
   *    { id: "475261113", idx: 1, value: 'Shenzhen' },
   *    { id: "407502384", idx: 1, value: 'Chengdu' },
   *    { id: "622693981", idx: 1, value: 'Wuhan' },
   *  ]
   * }
   */

  $.each($('.ef-select'), function () {
    let selectList = {};
    let selectEleId = $(this).attr('id');
    selectList['selectEleId'] = selectEleId;
  });

  $('body').on('click', '.ef-select-view-search', function () {
    let isSelected = $(this).attr('chosen');
    if (isSelected !== 'true') {
      $(this).toggleClass('ef-select-view-opened');
      $(this).find('input:first').focus();
      $(this).find('svg').remove();
      let searchIcon = `
      <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-search" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter">
        <path d="M33.072 33.071c6.248-6.248 6.248-16.379 0-22.627-6.249-6.249-16.38-6.249-22.628 0-6.248 6.248-6.248 16.379 0 22.627 6.248 6.248 16.38 6.248 22.628 0Zm0 0 8.485 8.485"></path>
      </svg>
    `;
      $(this).find('.ef-select-view-icon').html(searchIcon);
    }

    let id = $(this).attr('id');
    let contentId = $(this).attr('contentid');
    let height = $(this).outerHeight();
    let top = $(this).position().top + height + 6;
    let left = $(this).position().left;

    let elementOffset = $(this).offset(); // 获取元素相对于文档的偏移位置
    let elementWidth = $(this).outerWidth(); // 获取元素的宽度

    let documentWidth = $('#app').width(); // 获取文档的宽度
    let rightDistance = documentWidth - (elementOffset.left + elementWidth);
    const panel = $('#' + contentId);
    const desiredWidth = elementWidth;

    syncSelectPopupSize($(this), contentId);

    if (rightDistance < desiredWidth - elementWidth) {
      // If it overflows the right edge of the document
      const safeLeft = Math.max(20, documentWidth - desiredWidth - 20);
      // Convert absolute document left to relative left
      let relativeLeft = left + (safeLeft - elementOffset.left);

      panel.css({
        left: relativeLeft,
        top: top,
        width: desiredWidth,
        display: 'block',
        right: 'auto',
      });
    } else {
      panel.css({
        left: left,
        top: top,
        width: desiredWidth,
        display: 'block',
        right: 'auto',
      });
    }

    autoFitDropdownWidth(panel);

    $('.ef-trigger-popup.ef-trigger-position-bl')
      .not('#' + contentId)
      .hide();
  });

  let selectIcon = `
  <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-expand" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter" style="transform: rotate(-45deg);">
    <path d="M7 26v14c0 .552.444 1 .996 1H22m19-19V8c0-.552-.444-1-.996-1H26"></path>
  </svg>
`;

  $('body').on('focusout', '.ef-select-view-input', function (event) {
    $(this).parent().find('svg').remove();
    $(this).parent().find('.ef-select-view-icon').html(selectIcon);
  });

  $(document).on('click', function (event) {
    if (
      !$(event.target).closest(
        '.ef-trigger-popup-wrapper, .ef-select-view-single'
      ).length
    ) {
      $('.ef-trigger-popup.ef-trigger-position-bl').hide();
    }
  });

  $('body').on('click', '.ef-select-option', function (event) {
    // 检查事件的目标元素是否是 span 元素
    if ($(event.target).is('span')) {
      $(this).closest('.ef-select-option').trigger('click');
    }

    if ($(event.target).is('li')) {
      $(event.target)
        .siblings('.ef-select-option-active')
        .removeClass('ef-select-option-active');
      $(event.target).addClass('ef-select-option-active');
    }
  });

  // 选取选项
  $('body')
    .not('.ef-select-option-disabled')
    .on('click', '.ef-select-option', function () {
      let val = $(this).children('.ef-select-option-content').html();
      let value = $(this).attr('value');
      let selectContent = $(this).closest(
        '.ef-trigger-popup.ef-trigger-position-bl'
      );
      let id = selectContent.attr('parentId');
      let selectInput = $('#' + id);
      selectInput.prev('input').val(value).attr('value', value);
      selectInput.removeClass('ef-select-error');
      selectInput
        .children('.ef-select-view-input')
        .addClass('ef-select-view-input-hidden');
      selectInput
        .children('.ef-select-view-value')
        .html(val)
        .removeClass('ef-select-view-value-hidden');

      let closeIon = '<i class="fa-regular fa-circle-xmark"></i>';
      selectInput.children().find('.ef-select-view-icon').html(closeIon);
      selectInput.attr('chosen', 'true');

      // Trigger change event on the hidden input
      selectInput.prev("input[component='select']").trigger('change');

      selectContent.hide();
    });

  // 删掉选中的选项
  $('body').on(
    'click',
    '.ef-select-view-icon .fa-regular.fa-circle-xmark, .ef-select-view-icon .ef-select-clear-btn',
    function (event) {
      event.stopPropagation();
      let selectInput = $(this).closest('.ef-select-view-single');
      selectInput.attr('chosen', 'false');
      selectInput
        .children('.ef-select-view-input')
        .removeClass('ef-select-view-input-hidden');
      selectInput
        .children('.ef-select-view-value')
        .addClass('ef-select-view-value-hidden');
      selectInput.children('.ef-select-view-value').html('');

      let selectIcon = `
    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-expand" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter" style="transform: rotate(-45deg);">
      <path d="M7 26v14c0 .552.444 1 .996 1H22m19-19V8c0-.552-.444-1-.996-1H26"></path>
    </svg>
    `;
      selectInput.children().find('.ef-select-view-icon').html(selectIcon);

      // 设置相应的 hidden input 的值为空并触发change事件
      const hiddenInput = selectInput.prev("input[type='hidden']");
      hiddenInput.val('').trigger('change');
    }
  );

  // 当在输入框输入时，将li元素遍历出来值并保存到原始数组中，并通过输入的内容模糊查询
  let dynamic = [];

  /**
   * flag 用来标记是否为中文输入完毕
   */
  let flag = true;
  $('body').on('compositionstart', '.ef-select-view-input', function () {
    flag = false;
  });

  $('body').on('compositionend', '.ef-select-view-input', function () {
    flag = true;
  });

  $('body').on(
    'input change',
    '.ef-select-view-input',
    _.debounce(function () {
      if (flag) {
        let contentId = $(this).parent().attr('contentid');
        let optionList = $('#' + contentId)
          .children()
          .find('.ef-select-option-content');
        let ul = $('#' + contentId)
          .children()
          .find('.ef-select-dropdown-list');
        if (dynamic['selectOrigin' + contentId] === undefined) {
          dynamic['selectOrigin' + contentId] = [];
          optionList.each(function (idx, element) {
            let eleObj = {};
            eleObj['lowerText'] = $(this).text().toLowerCase();
            eleObj['orginText'] = $(this).text();
            eleObj['id'] = $(this).parent().attr('id');
            dynamic['selectOrigin' + contentId].push(eleObj);
          });
        }
        // 除了搜索的li，其他全部隐藏
        let searchResult = Str.searchArr(
          dynamic['selectOrigin' + contentId],
          $(this).val().toLowerCase()
        );
        let hideList = _.difference(
          dynamic['selectOrigin' + contentId],
          searchResult
        );
        if (hideList.length >= 0) {
          $.each(searchResult, function (idx, ele) {
            if (idx === 0) {
              $('#' + ele.id).addClass('ef-select-option-active');
            }
            $('#' + ele.id).show();
          });
          $.each(hideList, function (idx, ele) {
            $('#' + ele.id).hide();
          });
        }
      }
    }, 500)
  );

  $('body').on('focusout', '.ef-select-view-input', function () {
    $(this).val('');
  });

  // ── 键盘导航：上下箭头 + 回车选中（select 下拉）──
  $(document).on('keydown', function (e) {
    const key = e.key;
    if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Enter' && key !== 'Escape') return;

    // 找到当前打开的 select 面板（含 .ef-select-option 的可见面板）
    const panel = $('.ef-trigger-popup.ef-trigger-position-bl:visible').filter(function () {
      return $(this).find('.ef-select-option').length > 0;
    }).first();

    if (!panel.length) return;

    e.preventDefault();

    if (key === 'Escape') {
      panel.hide();
      return;
    }

    const options = panel.find('.ef-select-option:visible').not('.ef-select-option-disabled');
    if (!options.length) return;

    if (key === 'Enter') {
      const active = options.filter('.ef-select-option-active').first();
      if (active.length) active.trigger('click');
      return;
    }

    const currentIdx = options.index(options.filter('.ef-select-option-active'));
    let nextIdx;
    if (key === 'ArrowDown') {
      nextIdx = currentIdx < options.length - 1 ? currentIdx + 1 : 0;
    } else {
      nextIdx = currentIdx > 0 ? currentIdx - 1 : options.length - 1;
    }
    options.removeClass('ef-select-option-active');
    const nextOption = options.eq(nextIdx);
    nextOption.addClass('ef-select-option-active');

    // 滚动到可见区域
    const list = panel.find('.ef-select-dropdown-list');
    if (list.length) {
      const listEl = list[0];
      const optionEl = nextOption[0];
      if (optionEl.offsetTop < listEl.scrollTop) {
        listEl.scrollTop = optionEl.offsetTop;
      } else if (optionEl.offsetTop + optionEl.offsetHeight > listEl.scrollTop + listEl.clientHeight) {
        listEl.scrollTop = optionEl.offsetTop + optionEl.offsetHeight - listEl.clientHeight;
      }
    }
  });
});
