$(document).ready(function () {
  let alert = window.alert;
  let $canvas = $('.canvas');

  // 清除选择样式的函数
  function clearSelection (container) {
    container.find('th, td').each(function() {
      const $cell = $(this);
      $cell.removeAttr('data-cell-active');
      
      // 只有在没有手动设置边框的情况下才恢复默认边框
      if (!$cell.attr('data-custom-border')) {
        // 检查是否有原始的border样式，如果没有则设置默认样式
        const currentBorder = $cell.css('border');
        if (!currentBorder || currentBorder === 'none' || currentBorder === '0px none rgb(0, 0, 0)') {
          $cell.css({
            'border': '1px dashed #d5d8dc',
            'border-width': '1px',
            'border-style': 'dashed',
            'border-color': '#d5d8dc'
          });
        }
      }
    });
    
    // 隐藏选择边框
    hideSelectionBorder(container);
    
    // 触发单元格选择变化事件 - Feature 5
    $(document).trigger('cell-selection-changed');
  }

  /** 
   * About Seciton
   **/
  const $addSectionButton = $('.add-section-button');

  // 调整添加section按钮位置
  function adjustButtonPosition () {
    const canvasOffset = $canvas.offset();
    const canvasWidth = $canvas.outerWidth();

    // 固定按钮到屏幕底部，调整按钮相对于 .canvas 的水平位置
    $addSectionButton.css({
      right: $(window).width() - (canvasOffset.left + canvasWidth) + 20 + 'px',
    });
  }

  // 检查section-content宽度并调整canvas对齐方式
  function adjustCanvasAlignment () {
    const $activeSection = $('.section.active');
    if ($activeSection.length > 0) {
      const $sectionContent = $activeSection.find('.section-content');
      const sectionContentWidth = $sectionContent.outerWidth();
      const canvasWidth = $canvas.width();

      // 如果section-content宽度超过canvas宽度，将canvas的align-items设置为flex-start
      // 否则保持居中对齐
      if (sectionContentWidth > canvasWidth) {
        $canvas.css('align-items', 'flex-start');
      } else {
        $canvas.css('align-items', 'center');
      }
    }
  }

  // 点击 section 时激活该 section
  function activateSection (section) {
    // 移除所有 section 的 active class
    $('.section').removeClass('active');

    // 给当前点击的 section 添加 active class
    section.addClass('active');

    // 调整canvas对齐方式
    adjustCanvasAlignment();
  }

  // 给现有的 section 添加点击事件
  $canvas.on('click', '.section', function (event) {
    // 阻止事件冒泡到canvas
    event.stopPropagation();
    const $section = $(this);
    const $sectionHeader = $section.find('.section-header');

    // 检查点击的目标元素
    const $target = $(event.target);

    // 如果点击的是section内的组件（表格、文本等）或其子元素，隐藏section-header
    if ($target.closest('.ef-component').length > 0 || $target.closest('.item-block').length > 0) {
      $sectionHeader.hide();
    } else {
      // 如果点击的是section的空白区域，显示section-header
      $sectionHeader.show();
    }

    // 激活当前点击的 section
    activateSection($section);
  });

  // 初始化 draggable 和 droppable 逻辑
  function initDraggableDroppable () {
    // 设置 item-block 可以接收放置组件
    $('.item-block').droppable({
      accept: '.ef-component, .component-item',
      tolerance: 'pointer',
      hoverClass: 'droppable-hover',
      drop: function (event, ui) {
        const $this = $(this);

        // 处理从组件面板拖拽的新组件
        if (ui.draggable.hasClass('component-item')) {
          const componentType = ui.draggable.attr('componenttype');
          if (!componentType) return;

          // 根据组件类型创建新组件
          if (componentType === 'table') {
            // 显示表格行列输入模态框
            $('#tableModal').css('display', 'flex');

            // 保存当前拖放的位置和目标元素
            const dropTarget = $this;
            const dropOffsetX = ui.offset.left - dropTarget.offset().left;
            const dropOffsetY = ui.offset.top - dropTarget.offset().top;

            // 处理确定按钮点击事件
            $('#create-table-btn').off('click').on('click', function () {
              // ... 表格创建逻辑 ...
            });
          } else {
            // 非表格组件的处理逻辑
            const uniqueId = generateUniqueId();
            const templateConfig = componentTemplates[componentType];
            const html = templateConfig.template.replace(/{uniqueId}/g, uniqueId);
            const newComponent = $(html);

            // 计算放置位置
            const offsetX = ui.offset.left - $this.offset().left;
            const offsetY = ui.offset.top - $this.offset().top;

            // 设置位置并添加到目标
            newComponent.css({
              position: 'absolute',
              top: offsetY,
              left: offsetX
            }).appendTo($this);

            // 使新添加的组件可以拖动 TODO: 暂时注释掉
            // makeComponentDraggable(newComponent);

            // 隐藏模态框
            $('#tableModal').hide();

            // 文本组件的特定初始化逻辑
            if (componentType === 'text') {
              // 获取新添加组件中的可编辑元素
              const editableElement = newComponent.find('[contenteditable]');

              // 添加点击事件，用户手动点击后才清空 placeholder
              editableElement.one('click', function () {
                if ($(this).text() === textPlaceHolder) {
                  $(this).empty(); // 清空 placeholder
                  $(this).focus(); // 聚焦在该元素上
                }
              });

              // 监听该组件中 h2 的 input 事件
              newComponent.find('[contenteditable]').on('input', function () {
                if ($(this).html() === '<br>') {
                  $(this).html(''); // 清除多余的 <br> 标签
                }
              });
            }
          }
        } else if (ui.draggable.hasClass('ef-component')) {
          // 处理已存在组件的拖拽
          const offsetX = ui.offset.left - $this.offset().left;
          const offsetY = ui.offset.top - $this.offset().top;

          // 移动组件到新位置
          ui.draggable.detach().appendTo($this).css({
            position: 'absolute',
            top: offsetY,
            left: offsetX
          });
        }
      }
    });

    // 设置 section-content 可以接收放置组件
    $('.section-content').droppable({
      accept: '.ef-component, .component-item',
      tolerance: 'pointer',
      hoverClass: 'droppable-hover',
      drop: function (event, ui) {
        console.log('dropped');
      }
    });

    // 应用CSS规则来覆盖jQuery UI自动添加的样式
    if ($('#ui-draggable-override-style').length === 0) {
      $('<style id="ui-draggable-override-style">')
        .prop('type', 'text/css')
        .html('.ui-draggable:not(.being-dragged) { position: static !important; }')
        .appendTo('head');
    }
  }

  // 当新的 item-block 添加时重新初始化 droppable 区域
  function reinitializeDroppables () {
    // 销毁已经初始化的 droppable
    // 在调用destroy前先检查元素是否已经初始化为droppable
    try {
      const $itemBlocks = $('.item-block');
      $itemBlocks.each(function () {
        if ($(this).hasClass('ui-droppable')) {
          $(this).droppable('destroy');
        }
      });

      const $sectionContents = $('.section-content');
      $sectionContents.each(function () {
        if ($(this).hasClass('ui-droppable')) {
          $(this).droppable('destroy');
        }
      });
    } catch (error) {
      console.warn('Droppable destroy error:', error);
    }

    // 重新初始化
    initDraggableDroppable();

    // 确保所有组件都是可拖动的
    $('.ef-component').each(function() {
      if (!$(this).hasClass('ui-draggable')) {
        makeComponentDraggable($(this));
      }
    });

    // 应用CSS规则来覆盖jQuery UI自动添加的样式
    if ($('#ui-draggable-override-style').length === 0) {
      $('<style id="ui-draggable-override-style">')
        .prop('type', 'text/css')
        .html('.ui-draggable:not(.being-dragged) { position: static !important; }')
        .appendTo('head');
    }

    // 初始化表格快捷键
    // $('table[ef-table-hotkeys]').each(function() {
    //   const $table = $(this);
    //   const keyConfig = $table.attr('data-table-keys');
    //   if (keyConfig && typeof TableHotkeyManager !== 'undefined') {
    //     try {
    //       // 确保表格单元格有正确的属性用于导航
    //       $table.find('td').attr('tabindex', '0');
    //       TableHotkeyManager.initTableHotkeys($table, keyConfig);
    //     } catch (e) {
    //       console.error('初始化表格快捷键失败:', e);
    //     }
    //   }
    // });
  }

  // 处理添加 section 的逻辑
  $('.add-section-button').click(function () {
    const newSectionHtml = `
          <div class="section" id="${Str.generateRandomString(9)}">
              <div class="section-controls">
                  <button class="btn-toggle-header" title=t('viewEditorCoreJs.js1')><i class="fa-solid fa-eye"></i></button>
                  <button class="btn-toggle-collapse" title=t('viewEditorCoreJs.js2')><i class="fa-solid fa-chevron-up"></i></button>
              </div>
              <div class="section-header">
                  <button class="btn-add"><i class="fa-solid fa-plus"></i></button>
                  <button class="btn-layout"><i class="fa-solid fa-grip"></i></button>
                  <button class="btn-close"><i class="fa-solid fa-times"></i></button>
              </div>
              <div class="section-content ui-droppable">
                  <!-- 新的 Section 内容 -->
              </div>
          </div>`;
    const $newSection = $(newSectionHtml);
    $canvas = $('.canvas');
    $canvas.append($newSection);
    activateSection($newSection);
    reinitializeDroppables();

    // 对新section应用默认 Full Width 样式
    const config = window.__SECTION_CONFIG__ || {};
    if (config.contentWidth === 'full-width') {
      const pct = (config.width > 0 && config.width <= 100) ? config.width : 100;
      $newSection.find('.section-content').css('width', '100%');
      $newSection.css({width: pct + '%', left: 0});
      $newSection.find('.section-controls').css('left', 5);
    }

    // 为新section绑定控制按钮事件
    bindSectionControlEvents($newSection);
  });

  // 页面加载时调整按钮位置和canvas对齐方式
  adjustButtonPosition();
  adjustCanvasAlignment();
  /**
   * End About Seciton
   */

  /**
   * Public Functions
   */
  // 封装 draggable 初始化逻辑
  function makeComponentDraggable (element) {
    // 先确保元素初始状态为static
    $(element).css('position', 'static !important');

    $(element).draggable({
      handle: '.ef-component-labels', // 拖拽的句柄
      containment: $canvas,
      helper: 'original', // 修正拼写错误
      cursor: 'move',
      opacity: 0.7,
      zIndex: 1000,
      start: function (event, ui) {
        $(this).addClass('being-dragged');
        $(ui.helper).addClass('dragging-placeholder');
        // 拖拽开始时临时移除!important
        $(this).css('position', '');
      },
      stop: function (event, ui) {
        $(this).removeClass('being-dragged');
        $(ui.helper).removeClass('dragging-placeholder');

        // 拖拽结束后，如果用户确实移动了元素，则设置为absolute
        // 否则恢复为static
        if (ui.position.left !== 0 || ui.position.top !== 0) {
          $(this).css({
            left: ui.position.left,
            top: ui.position.top,
            position: 'absolute',
          });
        } else {
          $(this).css('position', 'static !important');
        }
      }
    });
  }

  // 监听contenteditable为true的元素，粘贴时仅插入纯文本
  $canvas.on('paste', '[contenteditable="true"]', function (event) {
    event.preventDefault(); // 阻止默认粘贴行为
    const text = event.originalEvent.clipboardData.getData('text/plain'); // 获取纯文本
    document.execCommand('insertText', false, text); // 插入纯文本
  });

  // 表格标签点击事件 - Feature 4
  $canvas.on('click', '.ef-component-labels .label-top', function (event) {
    event.stopPropagation();
    
    // 找到表格所在的section
    const $tableComponent = $(this).closest('.ef-table-component');
    const $section = $tableComponent.closest('.section');
    
    if ($section.length > 0) {
      // 触发section的点击事件
      $section.trigger('click');
    }
  });

  /**
   * About Section 
   */
  // 绑定section控制按钮事件的函数
  function bindSectionControlEvents($section) {
    // 显示/隐藏header按钮事件
    $section.find('.btn-toggle-header').off('click').on('click', function(e) {
      e.stopPropagation();
      const $header = $section.find('.section-header');
      const $icon = $(this).find('i');
      
      if ($header.is(':visible')) {
        $header.hide();
        $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        $(this).attr('title', t('viewEditorCoreJs.js3'));
      } else {
        $header.show();
        $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        $(this).attr('title', t('viewEditorCoreJs.js4'));
      }
    });
    
    // 折叠/展开section按钮事件
    $section.find('.btn-toggle-collapse').off('click').on('click', function(e) {
      e.stopPropagation();
      const $content = $section.find('.section-content');
      const $icon = $(this).find('i');
      
      if ($section.hasClass('collapsed')) {
        // 展开：恢复原始高度
        $content.css({
          'height': 'auto',
          'overflow': 'visible'
        });
        $section.removeClass('collapsed');
        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        $(this).attr('title', t('viewEditorCoreJs.js5'));
      } else {
        // 折叠：设置为手风琴效果的高度
        $content.css({
          'height': '80px',
          'overflow': 'hidden'
        });
        $section.addClass('collapsed');
        $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        $(this).attr('title', t('viewEditorCoreJs.js6'));
      }
    });
  }

  // 为顶层section绑定控制按钮事件（排除嵌套在内容区的子 section）
  $('.section').each(function() {
    const $section = $(this);
    // 跳过嵌套在另一个 section-content 内的子 section
    if ($section.closest('.section-content').length) return;
    // 如果section没有控制按钮，添加它们
    if ($section.find('.section-controls').length === 0) {
      const controlsHtml = `
        <div class="section-controls">
            <button class="btn-toggle-header" title=t('viewEditorCoreJs.js1')><i class="fa-solid fa-eye"></i></button>
            <button class="btn-toggle-collapse" title=t('viewEditorCoreJs.js2')><i class="fa-solid fa-chevron-up"></i></button>
        </div>`;
      $section.prepend(controlsHtml);
    }
    bindSectionControlEvents($section);
  });

  // 关闭上边section添加布局的弹窗
  $('.close-icon i').on('click', function (event) {
    $('#structureModal').hide();
  })

  /**
   * End About Section
   */

  // 右侧属性面板收起展开按钮
  $('#toggle-button').on('click', function () {
    const $panel = $('.properties-panel');
    const $button = $(this);

    // 用 :visible 判定实际可见性，兼容 .hidden class 与 display:none 两种隐藏方式
    const isHidden = !$panel.is(':visible');

    if (isHidden) {
      $panel.show().removeClass('hidden');
      $button.removeClass('reverse');
    } else {
      $panel.hide().addClass('hidden');
      $button.addClass('reverse');
    }

    // 切换图标方向
    const icon = $button.find('i');
    icon.removeClass('fa-angle-right fa-angle-left');
    icon.addClass(isHidden ? 'fa-angle-right' : 'fa-angle-left');
  });

  // 点击模态框外部关闭布局弹窗
  $('#structureModal').on('click', function (event) {
    if ($(event.target).is('#structureModal')) {
      $('#structureModal').hide();
    }
  });

  // 窗口大小改变时调整按钮位置
  $(window).resize(function () {
    adjustButtonPosition();
    adjustCanvasAlignment();
  });

  // 使用累加方式而不是覆盖，保留已有属性
  window.viewEditor = window.viewEditor || {};
  Object.assign(window.viewEditor, {
    makeComponentDraggable: makeComponentDraggable,
    reinitializeDroppables: reinitializeDroppables,
    initDraggableDroppable: initDraggableDroppable,
    clearSelection: clearSelection,
  });
})