/**
 * 视图编辑器工具栏交互功能
 * 包含工具栏按钮交互和视图保存功能
 */
$(document).ready(function() {
  // 初始化Alert组件
  let alert = window.$.alert;
  
  // 按文字内容恢复选中高亮
  window._restoreByText = function($rich, text) {
    if (!text) return;
    setTimeout(function() {
      if (!$rich.length) return;
      $rich[0].focus();
      var walker = document.createTreeWalker($rich[0], NodeFilter.SHOW_TEXT, null, false);
      var n;
      while (n = walker.nextNode()) {
        var idx = n.textContent.indexOf(text);
        if (idx !== -1) {
          try {
            var r = document.createRange();
            r.setStart(n, idx);
            r.setEnd(n, idx + text.length);
            var s = window.getSelection();
            s.removeAllRanges();
            s.addRange(r);
          } catch(e) {}
          break;
        }
      }
    }, 0);
  };

  // ===== 选区格式化工具函数 =====
  // 用法: applyStyleToSelection('color', '#f00') 或 applyStyleToSelection({color:'#f00','font-weight':'bold'})
  // 通过文本节点分割实现选区包裹，完全避免 surroundContents 的 range 副作用
  // 格式化后自动恢复选区（保持选中高亮）
  window.applyStyleToSelection = function(property, value) {
    const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (!$rich.length) return;
    const isMap = arguments.length === 1 && typeof property === 'object';
    const styles = isMap ? property : (function(o){o[property]=value;return o})({});
    // 获取选区
    var range = window._savedRange || null;
    if (!range) {
      var sel = window.getSelection();
      range = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
    }
    if (!range || !range.toString().trim() || !$rich[0] || !$rich[0].contains(range.commonAncestorContainer)) {
      // 无选区或选区不在 rich text 内 → 全元素应用
      $rich.css(styles);
      $rich.find('span:not([id]):not(.font-format)').each(function() {
        if (!this.style.length || !this.style.cssText) $(this).contents().unwrap();
      });
      window._savedRange = null;
      $(document).trigger('selectionchange');
      return;
    }

    // 展平嵌套
    window._flattenNestedSpans($rich);

    // 如果选区完全覆盖一个现有 span，直接改样式
    var $existing = $(range.commonAncestorContainer).closest('span');
    if ($existing.length && range.toString() === $existing.text()) {
      $existing.css(styles);
      window._savedRange = null;
      // 恢复选中（CSS 改样式不改 DOM，直接 restore）
      window._restoreByText($rich, range.toString());
      $(document).trigger('selectionchange');
      return;
    }

    // 手动包裹：分割文本节点并将选中部分放入 span
    try {
      var startNode = range.startContainer;
      var endNode = range.endContainer;
      var startOff = range.startOffset;
      var endOff = range.endOffset;
      var selectedText = range.toString();

      var $wrap = $('<span style="display:inline">').css(styles);
      var wrapEl = $wrap[0];

      if (startNode === endNode && startNode.nodeType === 3) {
        var text = startNode.textContent;
        var before = text.substring(0, startOff);
        var middle = text.substring(startOff, endOff);
        var after = text.substring(endOff);
        var parent = startNode.parentNode;
        var frag = document.createDocumentFragment();
        if (before) frag.appendChild(document.createTextNode(before));
        wrapEl.textContent = middle;
        frag.appendChild(wrapEl);
        if (after) frag.appendChild(document.createTextNode(after));
        parent.replaceChild(frag, startNode);
      } else {
        range.surroundContents(wrapEl);
        var $p = $wrap.parent();
        while ($p[0] && $p.is('span') && $p[0].childNodes.length === 1 && !$p[0].id) {
          $wrap.insertAfter($p);
          $p.remove();
          $p = $wrap.parent();
        }
      }

      // 清理空 span
      $rich.find('span:not([id]):not(.font-format)').each(function() {
        if (!this.style.length || !this.style.cssText) $(this).contents().unwrap();
      });

      // 恢复选区
      window._restoreByText($rich, selectedText);
    } catch(e) {
      $rich.css(styles);
    }
    window._savedRange = null;
    $(document).trigger('selectionchange');
  };

  // 展平 .ef-rich-text 内深层嵌套的冗余 span（重复执行直到完全展开）
  window._flattenNestedSpans = function($root) {
    var changed = true;
    while (changed) {
      changed = false;
      $root.find('span:not([id]):not(.font-format)').each(function() {
        var $s = $(this);
        if ($s[0].childNodes.length === 1 && $s.children().length === 1 && $s.children().first().is('span')) {
          $s.children().first().insertAfter($s);
          $s.remove();
          changed = true;
        }
      });
    }
  };
  
  // 获取选中文字所在的最内层 span 的某个样式值（优先用 _savedRange）
  function getSelectedStyle(prop) {
    var range = window._savedRange || null;
    if (!range) {
      var sel = window.getSelection();
      range = (sel && sel.rangeCount) ? sel.getRangeAt(0) : null;
    }
    if (!range) return null;
    var $span = $(range.commonAncestorContainer).closest('span, .ef-rich-text').first();
    return $span.length ? $span.css(prop) : null;
  }

  // 全局接口：获取选中文字的某个样式值（给 text_component_properties 等外部使用）
  window.getSelectedStyle = function(prop) {
    return getSelectedStyle(prop);
  };

  // 工具栏按钮 mousedown 时保存选区（click 时焦点已丢失）
  $('.editor-toolbar').on('mousedown', function() {
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (!$rich.length) return;
    var sel = window.getSelection();
    window._savedRange = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
  });

  // 获取所有工具栏按钮并添加点击事件
  $('.toolbar-btn').on('click', function() {
    const iconClass = $(this).find('i').attr('class') || '';
    
    // 粗体/斜体/下划线 → 选区格式化
    if (iconClass.includes('fa-bold')) {
      const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
      if ($rich.length) {
        const currentWeight = getSelectedStyle('font-weight') || $rich.css('font-weight');
        const newWeight = (currentWeight === '700' || currentWeight === 'bold') ? '400' : 'bold';
        window.applyStyleToSelection('font-weight', newWeight);
        $(this).toggleClass('active', newWeight === 'bold');
      }
    } else if (iconClass.includes('fa-italic')) {
      const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
      if ($rich.length) {
        const currentStyle = getSelectedStyle('font-style') || $rich.css('font-style');
        const newStyle = (currentStyle === 'italic') ? 'normal' : 'italic';
        window.applyStyleToSelection('font-style', newStyle);
        $(this).toggleClass('active', newStyle === 'italic');
      }
    } else if (iconClass.includes('fa-underline')) {
      const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
      if ($rich.length) {
        const currentDeco = getSelectedStyle('text-decoration') || $rich.css('text-decoration');
        const isUnderlined = currentDeco && (currentDeco.includes('underline'));
        window.applyStyleToSelection('text-decoration', isUnderlined ? 'none' : 'underline');
        $(this).toggleClass('active', !isUnderlined);
      }
    } else {
      $(this).toggleClass('active');
    }
    
    const buttonTitle = $(this).attr('title');
    console.log(`点击了 ${buttonTitle} 按钮`);
    
    if (iconClass.includes('fa-rotate-left')) {
      console.log('执行撤销操作');
    } else if (iconClass.includes('fa-rotate-right')) {
      console.log('执行重做操作');
    }
  });
  
  // 处理下拉选择框变化
  $('.toolbar-select[title="字体选择"]').on('change', function() {
    console.log(`选择了字体: ${$(this).val()}`);
    // 实现字体更改逻辑 — 使用 applyStyleToSelection
    window.applyStyleToSelection('font-family', $(this).val());
  });
  
  $('.toolbar-select[title="字号选择"]').on('change', function() {
    console.log(`选择了字号: ${$(this).val()}px`);
    window.applyStyleToSelection('font-size', $(this).val() + 'px');
  });
  
  // 自定义字号输入框
  $(document).on('change', '.custom-font-size-input', function() {
    const val = parseInt($(this).val());
    if (val >= 6 && val <= 200) {
      window.applyStyleToSelection('font-size', val + 'px');
    }
  });
  
  // 字体选择器按钮点击事件 - Feature 3
  $('#fontSelectorTrigger').on('click', function() {
    if (window.fontSelectorModal) {
      const activeSection = $('#canvas .section.active');
      const activeCells = activeSection.find('td[data-cell-active="true"]');
      const selectedComponent = window.ComponentProperties?.getSelectedComponent?.();
      const $comp = selectedComponent ? $(selectedComponent) : $();
      let currentFont = null;

      // 保存当前选区（contenteditable 内的文字选中）
      const sel = window.getSelection();
      window._savedRange = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
      
      if (activeCells.length) {
        const firstCell = activeCells.first();
        currentFont = {
          family: (firstCell.css('font-family') || '').split(',')[0].replace(/["']/g, '').trim() || null,
          weight: parseInt(firstCell.css('font-weight')) || 400
        };
      } else if ($comp.length && ($comp.hasClass('ef-text-component') || $comp.find('.ef-rich-text').length)) {
        const $rich = $comp.hasClass('ef-rich-text') ? $comp : $comp.find('.ef-rich-text').first();
        currentFont = {
          family: ($rich.css('font-family') || '').split(',')[0].replace(/["']/g, '').trim() || null,
          weight: parseInt($rich.css('font-weight')) || 400
        };
      }
      
      window.fontSelectorModal.show(function(selectedFont) {
        if (activeCells.length) {
          activeCells.css('font-family', selectedFont.family);
          activeCells.css('font-weight', selectedFont.weight);
          window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
        } else if ($comp.length && ($comp.hasClass('ef-text-component') || $comp.find('.ef-rich-text').length)) {
          const $rich = $comp.hasClass('ef-rich-text') ? $comp : $comp.find('.ef-rich-text').first();
          // 使用共享工具函数，优先选区格式化
          window.applyStyleToSelection({'font-family': selectedFont.family, 'font-weight': selectedFont.weight});
          // 全元素模式下清理冗余 font-format
          const sel = window.getSelection();
          const range = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
          if (!range || !range.toString().trim()) {
            $rich.find('.font-format').each(function() {
              this.style.fontFamily = '';
              this.style.fontWeight = '';
              if (!this.style.length) $(this).contents().unwrap();
            });
          }
          window.viewEditor.toolbar.syncToolbarButtonStates($rich);
        } else {
          alert.warning('请先选择要应用字体的内容');
          return;
        }
        
        $('#fontSelectorTrigger .font-selector-text').text(selectedFont.name);
        console.log('应用字体:', selectedFont.name);
      }, currentFont);
    }
  });

  // 保存按钮点击事件
  $('#save-view-button').on('click', function() {
    saveView();
  });

  /**
   * 保存视图函数
   * 获取视图ID和canvas HTML内容，发送到后端API
   */
  function saveView() {
    // 显示加载状态
    showLoading();
    
    try {
      // 从URL中提取视图ID
      const url = window.location.pathname;
      const viewId = url.substring(url.lastIndexOf('/') + 1);
      
      // 获取canvas的HTML内容（排除动态添加的 section-controls）
      const $canvasClone = $('#canvas').clone();
      $canvasClone.find('.section-controls').remove();
      const canvasHtml = $canvasClone.html();
      
      // 获取 section 配置
      const contentWidth = $('#content-width').val();
      const widthVal = $('#width-value').val();
      const widthUnit = $('#width-value').closest('.input-with-unit').find('.unit-selector span').text();
      const sectionConfig = {
        contentWidth: contentWidth,
        width: parseInt(widthVal) || 480,
        unit: widthUnit || 'px'
      };
      
      // 发送AJAX请求到后端API
      ajax({
        url: '/api/admin/platform/view/save',
        method: 'POST',
        contentType: 'application/json',
        data: {
          viewId: viewId,
          canvasHtml: canvasHtml,
          sectionConfig: sectionConfig
        },
        success: function(response) {
          hideLoading();
          if (response.code === 200) {
            alert.success('视图保存成功', { percent: '280px', title: "保存成功", closable: false });
          } else {
            alert.error('保存失败: ' + response.message, { percent: '40%', title: "保存失败", closable: true });
          }
        },
        error: function(xhr, status, error) {
          hideLoading();
          let errorMsg = '保存视图时发生错误';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMsg = xhr.responseJSON.message;
          }
          console.error('保存视图失败: ' + errorMsg);
          alert.error(errorMsg, { percent: '40%', title: "请求错误", closable: true });
        }
      });
    } catch (e) {
      hideLoading();
      alert.error('保存视图时发生错误: ' + e.message, { percent: '40%', title: "请求错误", closable: true });
      console.error('保存视图错误', e);
    }
  }

  /**
   * 显示加载状态
   */
  function showLoading() {
    // 如果页面中有加载指示器，可以在这里显示
    // 如果没有，可以创建一个简单的加载指示器
    if ($('#loading-indicator').length === 0) {
      $('body').append('<div id="loading-indicator" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center;"><div style="background-color: white; padding: 20px; border-radius: 5px;">正在保存...</div></div>');
    } else {
      $('#loading-indicator').show();
    }
  }

  /**
   * 隐藏加载状态
   */
  function hideLoading() {
    $('#loading-indicator').hide();
  }
  
  /**
   * 获取选中单元格的范围信息
   * @param {jQuery} selectedCells - 选中的单元格集合
   * @returns {Object|null} 包含边界单元格信息的对象
   */
  function getSelectedCellsRange(selectedCells) {
    if (!selectedCells || selectedCells.length === 0) {
      return null;
    }
    
    const cellsInfo = [];
    const tables = new Set();
    
    // 收集所有选中单元格的信息
    selectedCells.each(function() {
      const $cell = $(this);
      const $table = $cell.closest('table');
      const cellIndex = $cell.index();
      const rowIndex = $cell.parent().index();
      
      cellsInfo.push({
        cell: $cell,
        table: $table[0],
        row: rowIndex,
        col: cellIndex
      });
      
      tables.add($table[0]);
    });
    
    // 按表格分组处理
    const result = {
      rightBorderCells: [],
      bottomBorderCells: []
    };
    
    tables.forEach(function(table) {
      const tableCells = cellsInfo.filter(info => info.table === table);
      
      // 找到每行的最右侧单元格（右边界）
      const rowGroups = {};
      tableCells.forEach(function(cellInfo) {
        if (!rowGroups[cellInfo.row]) {
          rowGroups[cellInfo.row] = [];
        }
        rowGroups[cellInfo.row].push(cellInfo);
      });
      
      Object.keys(rowGroups).forEach(function(row) {
        const rowCells = rowGroups[row];
        const maxCol = Math.max(...rowCells.map(c => c.col));
        const rightBorderCell = rowCells.find(c => c.col === maxCol);
        if (rightBorderCell) {
          result.rightBorderCells.push(rightBorderCell);
        }
      });
      
      // 找到每列的最下方单元格（下边界）
      const colGroups = {};
      tableCells.forEach(function(cellInfo) {
        if (!colGroups[cellInfo.col]) {
          colGroups[cellInfo.col] = [];
        }
        colGroups[cellInfo.col].push(cellInfo);
      });
      
      Object.keys(colGroups).forEach(function(col) {
        const colCells = colGroups[col];
        const maxRow = Math.max(...colCells.map(c => c.row));
        const bottomBorderCell = colCells.find(c => c.row === maxRow);
        if (bottomBorderCell) {
          result.bottomBorderCells.push(bottomBorderCell);
        }
      });
    });
    
    return result;
  }

  /**
   * 同步DOM元素的样式状态与工具栏按钮的激活状态
   * @param {jQuery} element - 需要检查样式的DOM元素
   */
  function syncToolbarButtonStates(element) {
    // 定义样式与按钮的映射关系，并按互斥组进行分组
    const styleButtonGroups = {
      // 独立按钮（不需要互斥）
      standalone: [
        {
          style: 'font-weight',
          values: ['700', 'bold'],
          buttonClass: 'font-bold'
        },
        {
          style: 'font-style',
          values: ['italic'],
          buttonClass: 'font-italic'
        },
        {
          style: 'text-decoration',
          values: (value) => value && value.includes('underline'),
          buttonClass: 'font-underline'
        },
        {
          style: 'border',
          values: (value) => value !== 'none' && value !== '' && value !== '1px dashed rgb(213, 216, 220)',
          buttonClass: 'fa-border-all'
        }
      ],
      // 水平对齐按钮组（互斥）
      horizontalAlign: [
        {
          style: 'justify-content',
          values: ['flex-start'],
          buttonClass: 'font-align-left',
          fallbackStyle: 'text-align',
          fallbackValues: ['left']
        },
        {
          style: 'justify-content',
          values: ['center'],
          buttonClass: 'font-align-center',
          fallbackStyle: 'text-align',
          fallbackValues: ['center']
        },
        {
          style: 'justify-content',
          values: ['flex-end'],
          buttonClass: 'font-align-right',
          fallbackStyle: 'text-align',
          fallbackValues: ['right']
        },
        {
          style: 'justify-content',
          values: [''],
          buttonClass: 'font-align-justify',
          fallbackStyle: 'text-align',
          fallbackValues: ['justify']
        }
      ],
      // 垂直对齐按钮组（互斥）
      verticalAlign: [
        {
          style: 'align-items',
          values: ['flex-start'],
          buttonClass: 'font-align-vertical-top',
          fallbackStyle: 'vertical-align',
          fallbackValues: ['top']
        },
        {
          style: 'align-items',
          values: ['center'],
          buttonClass: 'font-align-vertical-center',
          fallbackStyle: 'vertical-align',
          fallbackValues: ['middle']
        },
        {
          style: 'align-items',
          values: ['flex-end'],
          buttonClass: 'font-align-vertical-bottom',
          fallbackStyle: 'vertical-align',
          fallbackValues: ['bottom']
        }
      ]
    };
  
    // 处理每个按钮组
    Object.entries(styleButtonGroups).forEach(([groupName, mappings]) => {
      // 先移除该组所有按钮的激活状态
      mappings.forEach(mapping => {
        $(`.toolbar-btn.${mapping.buttonClass}`).removeClass('active');
      });

      // 对于每个组中的按钮
      mappings.forEach(mapping => {
        const $button = $(`.toolbar-btn.${mapping.buttonClass}`);
        if (!$button.length) return;

        // 对于文本对齐相关的样式，需要检查cell-content div的样式
        let targetElement = element;
        if (groupName === 'horizontalAlign' || groupName === 'verticalAlign') {
          const $cellContent = element.find('.cell-content');
          if ($cellContent.length > 0) {
            targetElement = $cellContent;
          }
        }

        // 对文字样式（font-weight/font-style/text-decoration），优先读取选中文字的 span 样式
        let currentStyle;
        if (groupName === 'standalone' && typeof getSelectedStyle === 'function') {
          currentStyle = getSelectedStyle(mapping.style) || targetElement.css(mapping.style);
        } else {
          currentStyle = targetElement.css(mapping.style);
        }
        let shouldBeActive = false;

        if (typeof mapping.values === 'function') {
          shouldBeActive = mapping.values(currentStyle);
        } else {
          shouldBeActive = mapping.values.some(value => currentStyle === value);
        }

        // 如果主样式没有匹配，检查fallback样式
        if (!shouldBeActive && mapping.fallbackStyle && mapping.fallbackValues) {
          const fallbackStyle = targetElement.css(mapping.fallbackStyle);
          if (typeof mapping.fallbackValues === 'function') {
            shouldBeActive = mapping.fallbackValues(fallbackStyle);
          } else {
            shouldBeActive = mapping.fallbackValues.some(value => fallbackStyle === value);
          }
        }

        // 如果是互斥组（非standalone），先移除组内所有按钮的激活状态
        if (groupName !== 'standalone' && shouldBeActive) {
          // 获取同组的所有按钮
          mappings.forEach(groupMapping => {
            const $groupButton = $(`.toolbar-btn.${groupMapping.buttonClass}`);
            if ($groupButton.length) {
              $groupButton.removeClass('active');
            }
          });
        }

        // 设置当前按钮的状态
        $button.toggleClass('active', shouldBeActive);
      });
    });
    
    // 处理字体颜色按钮（添加颜色指示器，优先选中文字的颜色）
    const $fontColorBtn = $('.toolbar-btn.font-palette');
    if ($fontColorBtn.length) {
      const selColor = typeof getSelectedStyle === 'function' ? getSelectedStyle('color') : null;
      const currentColor = selColor || element.css('color');
      if (currentColor && currentColor !== 'rgba(0, 0, 0, 0)' && currentColor !== 'transparent') {
        const $indicator = $fontColorBtn.find('.color-indicator');
        if ($indicator.length === 0) {
          const $newInd = $('<span class="color-indicator" style="display:block;width:14px;height:3px;margin:2px auto 0;border-radius:1px;"></span>');
          $newInd.css('background-color', currentColor);
          $fontColorBtn.append($newInd);
        } else {
          $indicator.css('background-color', currentColor);
        }
      }
    }
    
    // 处理字体选择器（优先选中文字的 span 字体）
    const $fontSelector = $('#fontSelectorTrigger .font-selector-text');
    if ($fontSelector.length) {
      const selFont = typeof getSelectedStyle === 'function' ? getSelectedStyle('font-family') : null;
      const currentFontFamily = selFont || element.css('font-family');
      if (currentFontFamily) {
        const fontName = currentFontFamily.split(',')[0].replace(/["']/g, '').trim();
        $fontSelector.text(fontName);
      }
    }
    
    // 处理字号选择器（优先选中文字的 span 字号）
    const $fontSizeSelect = $('.font-size-select');
    if ($fontSizeSelect.length) {
      const selSize = typeof getSelectedStyle === 'function' ? getSelectedStyle('font-size') : null;
      const currentFontSize = selSize || element.css('font-size');
      if (currentFontSize) {
        const fontSize = parseInt(currentFontSize);
        const $option = $fontSizeSelect.find(`option[value="${fontSize}"]`);
        if ($option.length > 0) {
          $fontSizeSelect.val(fontSize);
        } else {
          const customOption = `<option value="${fontSize}">${fontSize}px</option>`;
          $fontSizeSelect.find('option[value="custom"]').before(customOption);
          $fontSizeSelect.val(fontSize);
        }
      }
    }
    
    // 处理背景颜色按钮
    const $bgColorBtn = $('.toolbar-btn.bg-palette');
    if ($bgColorBtn.length) {
      const currentBgColor = element.css('background-color');
      if (currentBgColor && currentBgColor !== 'rgba(0, 0, 0, 0)' && currentBgColor !== 'transparent') {
        // 设置按钮的指示器颜色
        const $bgColorIndicator = $bgColorBtn.find('.color-indicator');
        if ($bgColorIndicator.length === 0) {
          // 如果不存在颜色指示器，则创建一个
          const $indicator = $('<span class="color-indicator" style="display: block; width: 14px; height: 3px; margin: 2px auto 0; border-radius: 1px;"></span>');
          $indicator.css('background-color', currentBgColor);
          $bgColorBtn.append($indicator);
        } else {
          // 更新现有指示器的颜色
          $bgColorIndicator.css('background-color', currentBgColor);
        }
      }
    }
  }

  // 确保 viewEditor 对象存在
  window.viewEditor = window.viewEditor || {};
  // 确保 toolbar 对象存在
  window.viewEditor.toolbar = window.viewEditor.toolbar || {};

  // 定义工具栏模块
  Object.assign(window.viewEditor.toolbar, {
    syncToolbarButtonStates: syncToolbarButtonStates,
  });

  // 组件选中时同步工具栏状态（字号、字体、粗体等）
  $(document).on('componentSelected', function(e, component) {
    var $comp = $(component);
    var $el = null;
    if ($comp.hasClass('ef-text-component') || $comp.hasClass('ef-text')) {
      $el = $comp.find('.ef-rich-text').first();
      if (!$el.length && $comp.hasClass('ef-rich-text')) $el = $comp;
    } else if ($comp.is('td') || $comp.closest('td').length) {
      return; // 表格相关由其他逻辑处理
    }
    if ($el && $el.length && typeof syncToolbarButtonStates === 'function') {
      syncToolbarButtonStates($el);
    }
  });

  // 选区变化时同步工具栏状态（选中不同文字时更新工具栏按钮）
  var _syncToolbarTimer = null;
  $(document).on('selectionchange', function() {
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (!$rich.length) return;
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;
    if (!$rich[0].contains(sel.getRangeAt(0).commonAncestorContainer)) return;
    clearTimeout(_syncToolbarTimer);
    _syncToolbarTimer = setTimeout(function() {
      if (typeof syncToolbarButtonStates === 'function') {
        syncToolbarButtonStates($rich);
      }
    }, 60);
  });
  
  // 修改原有的粗体按钮点击事件处理
  $('.toolbar-btn.font-bold').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 获取第一个单元格的当前粗体状态
      const firstCellWeight = activeCells.first().css('font-weight');
      const newWeight = (firstCellWeight === '700' || firstCellWeight === 'bold') ? 'normal' : 'bold';
      
      // 为所有选中的单元格应用相同的粗体状态
      activeCells.each(function() {
        $(this).css('font-weight', newWeight);
      });
      
      // 使用第一个单元格同步按钮状态
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });

  $('.toolbar-btn.font-italic').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 获取第一个单元格的当前斜体状态
      const firstCellStyle = activeCells.first().css('font-style');
      const newStyle = firstCellStyle === 'italic' ? 'normal' : 'italic';
      
      // 为所有选中的单元格应用相同的斜体状态
      activeCells.each(function() {
        $(this).css('font-style', newStyle);
      });
      
      // 使用第一个单元格同步按钮状态
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });

  $('.toolbar-btn.font-underline').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 获取第一个单元格的当前下划线状态
      const firstCellDecoration = activeCells.first().css('text-decoration');
      const newDecoration = firstCellDecoration.includes('underline') ? 'none' : 'underline';
      
      // 为所有选中的单元格应用相同的下划线状态
      activeCells.each(function() {
        $(this).css('text-decoration', newDecoration);
      });
      
      // 使用第一个单元格同步按钮状态
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  // 初始化边框样式选择器
  let borderStylePicker = null;
  
  // 边框样式按钮点击事件
  $('.toolbar-btn.cell-border').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length === 0) return;
    
    // 获取第一个选中单元格的当前边框样式
    const firstCell = activeCells.first();
    const currentBorderWidth = firstCell.css('border-width') || '1px';
    const currentBorderStyle = firstCell.css('border-style') || 'solid';
    const currentBorderColor = firstCell.css('border-color') || '#000000';
    
    // 检查四个方向的边框是否存在
    const hasTopBorder = firstCell.css('border-top-width') !== '0px' && firstCell.css('border-top-style') !== 'none';
    const hasRightBorder = firstCell.css('border-right-width') !== '0px' && firstCell.css('border-right-style') !== 'none';
    const hasBottomBorder = firstCell.css('border-bottom-width') !== '0px' && firstCell.css('border-bottom-style') !== 'none';
    const hasLeftBorder = firstCell.css('border-left-width') !== '0px' && firstCell.css('border-left-style') !== 'none';
    
    // 如果边框样式选择器不存在，则创建
    if (!borderStylePicker) {
      borderStylePicker = new BorderStylePicker({
        container: 'body',
        onChange: function(style) {
          // 重新获取当前激活的单元格
          const activeSection = $('#canvas .section.active');
          const currentActiveCells = activeSection.find('td[data-cell-active="true"]');
          
          // 记录撤销重做状态
          if (window.undoRedoManager) {
            window.undoRedoManager.recordAction('border_style_change', {
              style: style,
              cellCount: currentActiveCells.length
            });
          }
          
          // 获取选中单元格的范围信息
          const selectedCellsInfo = getSelectedCellsRange(currentActiveCells);
          
          // 为所有选中的单元格应用新的边框逻辑
          currentActiveCells.each(function() {
            const $cell = $(this);
            const $table = $cell.closest('table');
            const cellIndex = $cell.index();
            const rowIndex = $cell.parent().index();
            
            // 检查当前单元格是否为合并单元格
            const colspan = parseInt($cell.attr('colspan')) || 1;
            const rowspan = parseInt($cell.attr('rowspan')) || 1;
            const isMergedCell = colspan > 1 || rowspan > 1;
            
            // 重置当前单元格的边框
            $cell.css({
              'border-width': '0',
              'border-style': 'none',
              'border-color': 'transparent'
            });
            
            // 新的边框设置逻辑：只设置右侧和下方边框
            if (style.right) {
              $cell.css({
                'border-right-width': style.width,
                'border-right-style': style.style,
                'border-right-color': style.color
              });
              
              // 合并单元格的右边框处理已在下方统一处理
            }
            
            if (style.bottom) {
              $cell.css({
                'border-bottom-width': style.width,
                'border-bottom-style': style.style,
                'border-bottom-color': style.color
              });
              
              // 合并单元格的下边框处理已在下方统一处理
            }
            
            // 处理上方边框：设置上方单元格的下边框
            if (style.top && rowIndex > 0) {
              // 对于合并单元格，需要为所有跨越的列设置上方边框
              for (let i = 0; i < colspan; i++) {
                const targetColIndex = cellIndex + i;
                const $topCell = $table.find('tr').eq(rowIndex - 1).find('td, th').eq(targetColIndex);
                if ($topCell.length) {
                  $topCell.css({
                    'border-bottom-width': style.width,
                    'border-bottom-style': style.style,
                    'border-bottom-color': style.color
                  });
                  $topCell.attr('data-custom-border', 'true');
                }
              }
            }
            
            // 处理左侧边框：设置左侧单元格的右边框
            if (style.left && cellIndex > 0) {
              // 对于合并单元格，需要为所有跨越的行设置左侧边框
              for (let i = 0; i < rowspan; i++) {
                const targetRowIndex = rowIndex + i;
                const $leftCell = $table.find('tr').eq(targetRowIndex).find('td, th').eq(cellIndex - 1);
                if ($leftCell.length) {
                  $leftCell.css({
                    'border-right-width': style.width,
                    'border-right-style': style.style,
                    'border-right-color': style.color
                  });
                  $leftCell.attr('data-custom-border', 'true');
                }
              }
            }
            
            // 如果是表格的第一行且设置了上边框，直接设置当前单元格的上边框
            if (style.top && rowIndex === 0) {
              $cell.css({
                'border-top-width': style.width,
                'border-top-style': style.style,
                'border-top-color': style.color
              });
            }
            
            // 如果是表格的第一列且设置了左边框，直接设置当前单元格的左边框
            if (style.left && cellIndex === 0) {
              $cell.css({
                'border-left-width': style.width,
                'border-left-style': style.style,
                'border-left-color': style.color
              });
            }

            // 如果至少有一个方向设置了边框，添加data-custom-border属性
            if (style.top || style.right || style.bottom || style.left) {
              $cell.attr('data-custom-border', 'true');
            } else {
              $cell.removeAttr('data-custom-border');
            }
          });
          
          // 处理选取区域边界的相邻单元格边框
          if (selectedCellsInfo && style.right) {
            // 为选取区域右侧边界的右侧单元格设置左边框
            selectedCellsInfo.rightBorderCells.forEach(function(cellInfo) {
              const $rightCell = $(cellInfo.table).find('tr').eq(cellInfo.row).find('td, th').eq(cellInfo.col + 1);
              if ($rightCell.length) {
                $rightCell.css({
                  'border-left-width': style.width,
                  'border-left-style': style.style,
                  'border-left-color': style.color
                });
                $rightCell.attr('data-custom-border', 'true');
              }
            });
          }
          
          if (selectedCellsInfo && style.bottom) {
            // 为选取区域下边界的下侧单元格设置上边框
            selectedCellsInfo.bottomBorderCells.forEach(function(cellInfo) {
              const $bottomCell = $(cellInfo.table).find('tr').eq(cellInfo.row + 1).find('td, th').eq(cellInfo.col);
              if ($bottomCell.length) {
                $bottomCell.css({
                  'border-top-width': style.width,
                  'border-top-style': style.style,
                  'border-top-color': style.color
                });
                $bottomCell.attr('data-custom-border', 'true');
              }
            });
          }
          
          // 更新按钮状态
          if (currentActiveCells.length > 0) {
            window.viewEditor.toolbar.syncToolbarButtonStates(currentActiveCells.first());
          }
        },
        onClose: function() {
          // 可以在这里添加关闭时的处理逻辑
        }
      });
    }
    
    // 设置当前边框样式
    borderStylePicker.setStyle({
      width: currentBorderWidth,
      style: currentBorderStyle,
      color: rgbToHex(currentBorderColor) || '#000000',
      top: hasTopBorder,
      right: hasRightBorder,
      bottom: hasBottomBorder,
      left: hasLeftBorder
    });
    
    // 打开边框样式选择器
    borderStylePicker.open(this);
  });
  
  // 初始化背景颜色选择器
  let bgColorPicker = null;

  // 背景颜色按钮点击事件
  $('.toolbar-btn.cell-background-color').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length === 0) return;
    
    // 获取第一个选中单元格的当前背景颜色
    const firstCellBgColor = activeCells.first().css('background-color');
    let hexColor = rgbToHex(firstCellBgColor) || '#FFFFFF';
    
    // 如果颜色选择器不存在，则创建
    if (!bgColorPicker) {
      bgColorPicker = new ColorPicker({
        container: 'body',
        defaultColor: hexColor,
        onChange: function(color) {
          // 重新获取当前激活的单元格
          const activeSection = $('#canvas .section.active');
          const currentActiveCells = activeSection.find('td[data-cell-active="true"]');
          
          // 记录撤销重做状态
          if (window.undoRedoManager) {
            window.undoRedoManager.recordAction('background_color_change', {
              color: color,
              cellCount: currentActiveCells.length
            });
          }
          
          // 为所有选中的单元格应用相同的背景颜色
          currentActiveCells.each(function() {
            $(this).css('background-color', color);
          });
          
          // 更新按钮状态
          if (currentActiveCells.length > 0) {
            window.viewEditor.toolbar.syncToolbarButtonStates(currentActiveCells.first());
          }
        },
        onClose: function() {
          // 可以在这里添加关闭时的处理逻辑
        }
      });
    } else {
      // 更新颜色选择器的当前颜色
      bgColorPicker.setColor(hexColor);
    }
    
    // 打开颜色选择器
    bgColorPicker.open(this);
  });

  // 初始化字体颜色选择器
  let fontColorPicker = null;
  
  // 字体颜色按钮点击事件（支持表格单元格 + 文本选区）
  $('.toolbar-btn.font-palette').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    
    if (activeCells.length === 0 && !$rich.length) return;
    
    // 保存选区，弹窗后会丢失
    if ($rich.length) {
      const sel = window.getSelection();
      window._savedRange = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
    }
    
    let hexColor = '#000000';
    if (activeCells.length) {
      hexColor = rgbToHex(activeCells.first().css('color')) || '#000000';
    } else if ($rich.length) {
      // 优先选区文字的颜色
      const selColor = getSelectedStyle('color');
      hexColor = rgbToHex(selColor || $rich.css('color')) || '#000000';
    }
    
    if (!fontColorPicker) {
      fontColorPicker = new ColorPicker({
        container: 'body',
        defaultColor: hexColor,
        onChange: function(color) {
          const currentActiveCells = $('#canvas .section.active').find('td[data-cell-active="true"]');
          const $r = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
          
          if (currentActiveCells.length) {
            currentActiveCells.each(function() {
              $(this).css('color', color);
            });
            window.viewEditor.toolbar.syncToolbarButtonStates(currentActiveCells.first());
          } else if ($r.length) {
            window.applyStyleToSelection('color', color);
            window.viewEditor.toolbar.syncToolbarButtonStates($r);
          }
        }
      });
    } else {
      fontColorPicker.setColor(hexColor);
    }
    
    fontColorPicker.open(this);
  });
  
  // 水平对齐按钮事件处理
  // 对齐辅助：设置后触发双向同步
  function _setAlign($el, align, isCell) {
    if (isCell) {
      $('.toolbar-btn.font-align-left, .toolbar-btn.font-align-center, .toolbar-btn.font-align-right, .toolbar-btn.font-align-justify').removeClass('active');
      $('.toolbar-btn.font-align-'+align).addClass('active');
      if (align === 'justify') return;
      $el.each(function() {
        var $c = $(this).find('.cell-content');
        if ($c.length) $c.css({display:'flex','justify-content':align === 'left' ? 'flex-start' : align === 'right' ? 'flex-end' : 'center'});
        else $(this).css('text-align', align);
      });
    } else {
      $el.css('text-align', align);
    }
    window.viewEditor.toolbar.syncToolbarButtonStates(isCell ? $el.first() : $el);
    $(document).trigger('selectionchange');
  }

  $('.toolbar-btn.font-align-left').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setAlign(ac, 'left', true);
    else if ($rich.length) _setAlign($rich, 'left', false);
  });
  
  $('.toolbar-btn.font-align-center').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setAlign(ac, 'center', true);
    else if ($rich.length) _setAlign($rich, 'center', false);
  });
  
  $('.toolbar-btn.font-align-right').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setAlign(ac, 'right', true);
    else if ($rich.length) _setAlign($rich, 'right', false);
  });
  
  $('.toolbar-btn.font-align-justify').on('click', function() {
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if ($rich.length) _setAlign($rich, 'justify', false);
  });
  
  // 垂直对齐按钮事件处理
  function _setVAlign($el, align, isCell) {
    if (isCell) {
      $el.each(function() {
        var $c = $(this).find('.cell-content');
        if ($c.length) $c.css({display:'flex','align-items':align === 'top' ? 'flex-start' : align === 'bottom' ? 'flex-end' : 'center'});
        else $(this).css('vertical-align', align);
      });
    } else {
      $el.css('vertical-align', align);
    }
    window.viewEditor.toolbar.syncToolbarButtonStates(isCell ? $el.first() : $el);
    $(document).trigger('selectionchange');
  }

  $('.toolbar-btn.font-align-vertical-top').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setVAlign(ac, 'top', true);
    else if ($rich.length) _setVAlign($rich, 'top', false);
  });
  
  $('.toolbar-btn.font-align-vertical-center').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setVAlign(ac, 'middle', true);
    else if ($rich.length) _setVAlign($rich, 'middle', false);
  });
  
  $('.toolbar-btn.font-align-vertical-bottom').on('click', function() {
    var ac = $('#canvas .section.active').find('td[data-cell-active="true"]');
    var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
    if (ac.length) _setVAlign(ac, 'bottom', true);
    else if ($rich.length) _setVAlign($rich, 'bottom', false);
  });
  
  // 单元格合并功能
  $('.toolbar-btn.merge-cells').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length < 2) {
      alert.warning('请选择至少两个单元格进行合并');
      return;
    }
    
    // 检查选中的单元格是否连续
    if (!areSelectedCellsContinuous(activeCells)) {
      alert.warning('只能合并连续的单元格区域');
      return;
    }
    
    // 记录撤销重做状态
    if (window.undoRedoManager) {
      window.undoRedoManager.recordAction('merge_cells', {
        cellCount: activeCells.length,
        firstCellIndex: activeCells.first().index()
      });
    }
    
    // 获取合并区域的范围
    const mergeInfo = getMergeInfo(activeCells);
    const firstCell = activeCells.first();
    
    // 计算合并后的总宽度
    let totalWidth = 0;
    const firstCellRow = firstCell.parent().index();
    const firstCellCol = firstCell.index();
    const table = firstCell.closest('table');
    
    // 计算被合并列的原始宽度总和
    for (let col = firstCellCol; col < firstCellCol + mergeInfo.colspan; col++) {
      const cellInFirstRow = table.find('tr').first().find('td, th').eq(col);
      if (cellInFirstRow.length) {
        totalWidth += cellInFirstRow.outerWidth();
      }
    }
    
    // 合并文本内容
    let mergedContent = '';
    activeCells.each(function() {
      const $cell = $(this);
      const $contentDiv = $cell.find('.cell-content');
      const cellContent = $contentDiv.length ? $contentDiv.text().trim() : $cell.text().trim();
      if (cellContent) {
        mergedContent += (mergedContent ? ' ' : '') + cellContent;
      }
    });
    
    // 设置合并属性和宽度
    firstCell.attr({
      'colspan': mergeInfo.colspan,
      'rowspan': mergeInfo.rowspan
    }).css('width', totalWidth + 'px');
    
    // 设置合并后的内容
    const $firstCellContent = firstCell.find('.cell-content');
    if ($firstCellContent.length) {
      $firstCellContent.text(mergedContent);
    } else {
      firstCell.text(mergedContent);
    }
    
    // 注意：合并单元格时不修改colgroup的列宽度
    // colgroup的列宽度只在手动拖拽调整时才会改变
    
    // 确保其他行对应列的宽度保持一致
    // 先记录每列的原始宽度
    const columnWidths = [];
    const firstRow = table.find('tr').first();
    firstRow.find('td, th').each(function(index) {
      columnWidths[index] = $(this).outerWidth();
    });
    
    table.find('tr').each(function(rowIndex) {
      if (rowIndex !== firstCellRow) {
        $(this).find('td, th').each(function(colIndex) {
          const cell = $(this);
          // 只对非合并单元格设置宽度，且不在被合并的列范围内
          if (!cell.attr('colspan') && !cell.attr('data-merged') && 
              (colIndex < firstCellCol || colIndex >= firstCellCol + mergeInfo.colspan)) {
            if (columnWidths[colIndex]) {
              cell.css('width', columnWidths[colIndex] + 'px');
            }
          }
        });
      }
    });
    
    // 隐藏其他被合并的单元格
    activeCells.not(firstCell).hide().attr('data-merged', 'true');
    
    // 为合并后的单元格添加拖拽手柄
    addResizeHandlesToCell(firstCell);
    
    // 清除选择
    activeCells.removeAttr('data-cell-active').css({
      'border-style': '',
      'border-width': '',
      'border-color': '',
      'outline': ''
    });
    
    alert.success('单元格合并成功');
  });
  
  // 检查拆分单元格按钮状态 - Feature 5
  function updateSplitCellsButtonState() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    const splitButton = $('.toolbar-btn.split-cells');
    
    // 检查是否只选中了一个单元格且该单元格已合并
    if (activeCells.length === 1) {
      const cell = activeCells.first();
      const colspan = parseInt(cell.attr('colspan')) || 1;
      const rowspan = parseInt(cell.attr('rowspan')) || 1;
      
      if (colspan > 1 || rowspan > 1) {
        // 单元格已合并，启用按钮
        splitButton.removeClass('disabled').prop('disabled', false);
      } else {
        // 单元格未合并，禁用按钮
        splitButton.addClass('disabled').prop('disabled', true);
      }
    } else {
      // 没有选中单元格或选中多个单元格，禁用按钮
      splitButton.addClass('disabled').prop('disabled', true);
    }
  }
  
  // 监听单元格选择变化，更新拆分按钮状态
  $(document).on('cell-selection-changed', function() {
    updateSplitCellsButtonState();
    updateNewlineButtonIcon();
  });
  
  // 更新换行按钮图标
  function updateNewlineButtonIcon() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    const $newlineBtn = $('.toolbar-btn.newline');
    
    if (activeCells.length === 0) {
      // 没有选中单元格时，显示默认图标（不换行）
      $newlineBtn.find('i').removeClass('fa-align-justify').addClass('fa-align-left');
      $newlineBtn.attr('title', '禁止换行');
      return;
    }
    
    // 检查第一个选中单元格的white-space属性
    const firstCell = activeCells.first();
    const whiteSpace = firstCell.css('white-space');
    
    if (whiteSpace === 'normal') {
      // 当前是换行状态，显示换行图标
      $newlineBtn.find('i').removeClass('fa-align-left').addClass('fa-align-justify');
      $newlineBtn.attr('title', '允许换行');
      $newlineBtn.addClass('active');
    } else {
      // 当前是不换行状态，显示不换行图标
      $newlineBtn.find('i').removeClass('fa-align-justify').addClass('fa-align-left');
      $newlineBtn.attr('title', '禁止换行');
      $newlineBtn.removeClass('active');
    }
  }
  
  // 初始化时更新按钮状态
  updateSplitCellsButtonState();
  updateNewlineButtonIcon();
  
  // 监听单元格点击事件，更新换行按钮状态
  $(document).on('click', '.ef-table-component td, .ef-table-component th', function() {
    // 延迟执行，确保单元格选择状态已更新
    setTimeout(updateNewlineButtonIcon, 10);
  });
  

  // 表格拖拽调整功能已移至 view_table.js 中统一实现
  // 这里保留 addResizeHandlesToCell 函数的空实现以保持兼容性
  function addResizeHandlesToCell($cell) {
    // 功能已迁移到 view_table.js，此处为空实现
    // 实际的拖拽手柄添加和事件绑定由 view_table.js 的 initTableResize() 函数处理
  }

  // 单元格拆分功能
  $('.toolbar-btn.split-cells').on('click', function() {
    // 检查按钮是否被禁用
    if ($(this).hasClass('disabled') || $(this).prop('disabled')) {
      return;
    }
    
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length !== 1) {
      alert.warning('请选择一个已合并的单元格进行拆分');
      return;
    }
    
    const cell = activeCells.first();
    const colspan = parseInt(cell.attr('colspan')) || 1;
    const rowspan = parseInt(cell.attr('rowspan')) || 1;
    
    if (colspan === 1 && rowspan === 1) {
      alert.warning('该单元格未合并，无需拆分');
      return;
    }
    
    // 记录撤销重做状态
    if (window.undoRedoManager) {
      window.undoRedoManager.recordAction('split_cells', {
        cellIndex: cell.index(),
        colspan: colspan,
        rowspan: rowspan
      });
    }
    
    // 移除合并属性
    cell.removeAttr('colspan rowspan');
    
    // 显示被隐藏的单元格
    const table = cell.closest('table');
    table.find('td[data-merged="true"]').show().removeAttr('data-merged');
    
    // 清除选择
    cell.removeAttr('data-cell-active').css({
      'border-style': '',
      'border-width': '',
      'border-color': '',
      'outline': ''
    });
    
    // 更新按钮状态
    updateSplitCellsButtonState();
    
    alert.success('单元格拆分成功');
  });
  
  // 自动换行功能
  $('.toolbar-btn.newline').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length === 0) {
      alert.warning('请先选择表格单元格');
      return;
    }
    
    // 记录撤销重做状态
    if (window.undoRedoManager) {
      window.undoRedoManager.recordAction('toggle_whitespace', {
        cellCount: activeCells.length
      });
    }
    
    // 切换 white-space 属性
    activeCells.each(function() {
      const $cell = $(this);
      const currentWhiteSpace = $cell.css('white-space');
      
      if (currentWhiteSpace === 'nowrap') {
        $cell.css('white-space', 'normal');
      } else {
        $cell.css('white-space', 'nowrap');
      }
    });
    
    // 更新换行按钮图标
    updateNewlineButtonIcon();
    
    // 同步工具栏按钮状态
    window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
  });
  
  // 辅助函数：检查选中的单元格是否连续（考虑合并单元格）
  function areSelectedCellsContinuous(cells) {
    if (cells.length <= 1) return true;
    
    const positions = [];
    const table = cells.first().closest('table');
    
    // 收集所有选中单元格的逻辑位置信息
    cells.each(function() {
      const $cell = $(this);
      const row = $cell.parent().index();
      const col = $cell.index();
      const colspan = parseInt($cell.attr('colspan')) || 1;
      const rowspan = parseInt($cell.attr('rowspan')) || 1;
      
      // 为合并单元格的每个逻辑位置添加记录
      for (let r = row; r < row + rowspan; r++) {
        for (let c = col; c < col + colspan; c++) {
          positions.push({row: r, col: c, element: $cell});
        }
      }
    });
    
    // 按行列排序并去重
    const uniquePositions = [];
    const positionSet = new Set();
    positions.forEach(pos => {
      const key = `${pos.row}-${pos.col}`;
      if (!positionSet.has(key)) {
        positionSet.add(key);
        uniquePositions.push(pos);
      }
    });
    
    uniquePositions.sort((a, b) => a.row - b.row || a.col - b.col);
    
    // 检查是否形成矩形区域
    const minRow = Math.min(...uniquePositions.map(p => p.row));
    const maxRow = Math.max(...uniquePositions.map(p => p.row));
    const minCol = Math.min(...uniquePositions.map(p => p.col));
    const maxCol = Math.max(...uniquePositions.map(p => p.col));
    
    const expectedCount = (maxRow - minRow + 1) * (maxCol - minCol + 1);
    
    // 检查矩形区域内的每个位置是否都被覆盖
    const coveredPositions = new Set();
    uniquePositions.forEach(pos => {
      coveredPositions.add(`${pos.row}-${pos.col}`);
    });
    
    for (let r = minRow; r <= maxRow; r++) {
      for (let c = minCol; c <= maxCol; c++) {
        if (!coveredPositions.has(`${r}-${c}`)) {
          return false;
        }
      }
    }
    
    return true;
  }
  
  // 辅助函数：获取合并信息
  function getMergeInfo(cells) {
    const positions = [];
    cells.each(function() {
      const $cell = $(this);
      const row = $cell.parent().index();
      const col = $cell.index();
      positions.push({row, col});
    });
    
    const minRow = Math.min(...positions.map(p => p.row));
    const maxRow = Math.max(...positions.map(p => p.row));
    const minCol = Math.min(...positions.map(p => p.col));
    const maxCol = Math.max(...positions.map(p => p.col));
    
    return {
      colspan: maxCol - minCol + 1,
      rowspan: maxRow - minRow + 1
    };
  }

  // 字号选择功能
  $('.font-size-select').on('change', function() {
    const selectedValue = $(this).val();
    
    if (selectedValue === 'custom') {
      // 显示自定义输入框
      $('.custom-font-size-container').show();
      $('.custom-font-size-input').focus();
    } else {
      // 隐藏自定义输入框
      $('.custom-font-size-container').hide();
      
      // 应用字号
      applyFontSize(selectedValue + 'px');
    }
  });
  
  // 自定义字号确定按钮
  $('.apply-custom-font-size').on('click', function() {
    const customSize = $('.custom-font-size-input').val();
    
    if (customSize && customSize >= 6 && customSize <= 200) {
      applyFontSize(customSize + 'px');
      
      // 添加到选择框中
      const $select = $('.font-size-select');
      const customOption = `<option value="${customSize}">${customSize}px</option>`;
      
      // 检查是否已存在该选项
      if ($select.find(`option[value="${customSize}"]`).length === 0) {
        $select.find('option[value="custom"]').before(customOption);
      }
      
      // 选中新添加的选项
      $select.val(customSize);
      
      // 隐藏自定义输入框
      $('.custom-font-size-container').hide();
      $('.custom-font-size-input').val('');
    } else {
      alert.warning('请输入6-200之间的有效字号');
    }
  });
  
  // 自定义字号取消按钮
  $('.cancel-custom-font-size').on('click', function() {
    $('.custom-font-size-container').hide();
    $('.custom-font-size-input').val('');
    $('.font-size-select').val('14'); // 恢复默认值
  });
  
  // 自定义字号输入框回车事件
  $('.custom-font-size-input').on('keypress', function(e) {
    if (e.which === 13) {
      $('.apply-custom-font-size').click();
    }
  });
  
  // 应用字号的函数
  function applyFontSize(fontSize) {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 记录撤销重做状态
      if (window.undoRedoManager) {
        window.undoRedoManager.recordAction('font_size_change', {
          fontSize: fontSize,
          cellCount: activeCells.length
        });
      }
      
      activeCells.each(function() {
        $(this).css('font-size', fontSize);
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  }

  /**
   * 将RGB颜色转换为十六进制颜色
   * @param {string} rgb - RGB颜色字符串，如 'rgb(255, 0, 0)'
   * @returns {string} 十六进制颜色字符串，如 '#FF0000'
   */
  function rgbToHex(rgb) {
    if (!rgb || rgb === 'rgba(0, 0, 0, 0)' || rgb === 'transparent') {
      return '#000000';
    }
    
    // 提取RGB值
    const rgbMatch = rgb.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
    if (!rgbMatch) return '#000000';
    
    // 转换为十六进制
    const r = parseInt(rgbMatch[1], 10).toString(16).padStart(2, '0');
    const g = parseInt(rgbMatch[2], 10).toString(16).padStart(2, '0');
    const b = parseInt(rgbMatch[3], 10).toString(16).padStart(2, '0');
    
    
    return `#${r}${g}${b}`.toUpperCase();
  }

});