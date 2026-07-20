/**
 * 视图编辑器工具栏交互功能
 * 包含工具栏按钮交互和视图保存功能
 */
$(document).ready(function() {
  // 初始化Alert组件
  let alert = window.$.alert;
  
  // 获取所有工具栏按钮并添加点击事件
  $('.toolbar-btn').on('click', function() {
    // 对于需要切换状态的按钮（如粗体、斜体等）
    if (['fa-bold', 'fa-italic', 'fa-underline', 'fa-align-left', 'fa-align-center',
         'fa-align-right', 'fa-border-all'].some(cls => $(this).find('i').hasClass(cls))) {
      $(this).toggleClass('active');
    }
    
    // 在这里可以添加按钮的具体功能实现
    const buttonTitle = $(this).attr('title');
    console.log(`点击了 ${buttonTitle} 按钮`);
    
    // 示例：根据按钮类型执行不同操作
    const iconClass = $(this).find('i').attr('class');
    
    if (iconClass.includes('fa-rotate-left')) {
      // 撤销操作
      console.log('执行撤销操作');
    } else if (iconClass.includes('fa-rotate-right')) {
      // 重做操作
      console.log('执行重做操作');
    }
    // 其他按钮功能可以在这里继续实现...
  });
  
  // 处理下拉选择框变化
  $('.toolbar-select[title="字体选择"]').on('change', function() {
    console.log(`选择了字体: ${$(this).val()}`);
    // 实现字体更改逻辑
  });
  
  $('.toolbar-select[title="字号选择"]').on('change', function() {
    console.log(`选择了字号: ${$(this).val()}px`);
    // 实现字号更改逻辑
  });
  
  // 字体选择器按钮点击事件 - Feature 3
  $('#fontSelectorTrigger').on('click', function() {
    if (window.fontSelectorModal) {
      // 获取当前选中单元格的字体信息
      const activeSection = $('#canvas .section.active');
      const activeCells = activeSection.find('td[data-cell-active="true"]');
      let currentFont = null;
      
      if (activeCells.length > 0) {
        const firstCell = activeCells.first();
        const fontFamily = firstCell.css('font-family');
        const fontWeight = firstCell.css('font-weight');
        
        currentFont = {
          family: fontFamily ? fontFamily.split(',')[0].replace(/["']/g, '').trim() : null,
          weight: fontWeight === 'bold' || fontWeight === '700' ? 700 : parseInt(fontWeight) || 400
        };
      }
      
      window.fontSelectorModal.show(function(selectedFont) {
        // 应用选中的字体到当前选中的单元格或文本
        if (activeCells.length > 0) {
          // 应用字体到选中的单元格
          activeCells.css('font-family', selectedFont.family);
          activeCells.css('font-weight', selectedFont.weight);
          
          // 更新按钮显示的字体名称
          $('#fontSelectorTrigger .font-selector-text').text(selectedFont.name);
          
          // 同步工具栏按钮状态
          window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
          
          console.log('应用字体:', selectedFont.name, '到', activeCells.length, '个单元格');
        } else {
          alert.warning('请先选择要应用字体的单元格');
        }
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
      
      // 获取canvas的HTML内容
      const canvasHtml = $('#canvas').html();
      
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

        const currentStyle = targetElement.css(mapping.style);
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
    
    // 处理字体颜色按钮
    const $fontColorBtn = $('.toolbar-btn.font-palette');
    if ($fontColorBtn.length) {
      const currentColor = element.css('color');
    }
    
    // 处理字体选择器
    const $fontSelector = $('#fontSelectorTrigger .font-selector-text');
    if ($fontSelector.length) {
      const currentFontFamily = element.css('font-family');
      if (currentFontFamily) {
        // 从字体族中提取主要字体名称
        const fontName = currentFontFamily.split(',')[0].replace(/["']/g, '').trim();
        $fontSelector.text(fontName);
      }
    }
    
    // 处理字号选择器
    const $fontSizeSelect = $('.font-size-select');
    if ($fontSizeSelect.length) {
      const currentFontSize = element.css('font-size');
      if (currentFontSize) {
        const fontSize = parseInt(currentFontSize);
        // 检查是否有对应的选项
        const $option = $fontSizeSelect.find(`option[value="${fontSize}"]`);
        if ($option.length > 0) {
          $fontSizeSelect.val(fontSize);
        } else {
          // 如果没有对应选项，添加一个自定义选项
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
    // 同步工具栏按钮状态方法
    syncToolbarButtonStates: syncToolbarButtonStates,
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
  
  // 字体颜色按钮点击事件
  $('.toolbar-btn.font-palette').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length === 0) return;
    
    // 获取第一个选中单元格的当前颜色
    const firstCellColor = activeCells.first().css('color');
    let hexColor = rgbToHex(firstCellColor) || '#000000';
    
    // 如果颜色选择器不存在，则创建
    if (!fontColorPicker) {
      fontColorPicker = new ColorPicker({
        container: 'body',
        defaultColor: hexColor,
        onChange: function(color) {
          // 重新获取当前激活的单元格
          const activeSection = $('#canvas .section.active');
          const currentActiveCells = activeSection.find('td[data-cell-active="true"]');
          
          // 为所有选中的单元格应用相同的颜色
          currentActiveCells.each(function() {
            $(this).css('color', color);
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
      fontColorPicker.setColor(hexColor);
    }
    
    // 打开颜色选择器
    fontColorPicker.open(this);
  });
  
  // 水平对齐按钮事件处理
  $('.toolbar-btn.font-align-left').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 移除其他对齐按钮的active状态
      $('.toolbar-btn.font-align-center, .toolbar-btn.font-align-right').removeClass('active');
      $(this).addClass('active');
      
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'justify-content': 'flex-start'
          });
        } else {
          $cell.css('text-align', 'left');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  $('.toolbar-btn.font-align-center').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 移除其他对齐按钮的active状态
      $('.toolbar-btn.font-align-left, .toolbar-btn.font-align-right').removeClass('active');
      $(this).addClass('active');
      
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'justify-content': 'center'
          });
        } else {
          $cell.css('text-align', 'center');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  $('.toolbar-btn.font-align-right').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      // 移除其他对齐按钮的active状态
      $('.toolbar-btn.font-align-left, .toolbar-btn.font-align-center').removeClass('active');
      $(this).addClass('active');
      
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'justify-content': 'flex-end'
          });
        } else {
          $cell.css('text-align', 'right');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  // 垂直对齐按钮事件处理
  $('.toolbar-btn.font-align-vertical-top').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'align-items': 'flex-start'
          });
        } else {
          $cell.css('vertical-align', 'top');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  $('.toolbar-btn.font-align-vertical-center').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'align-items': 'center'
          });
        } else {
          $cell.css('vertical-align', 'middle');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
  });
  
  $('.toolbar-btn.font-align-vertical-bottom').on('click', function() {
    const activeSection = $('#canvas .section.active');
    const activeCells = activeSection.find('td[data-cell-active="true"]');
    
    if (activeCells.length > 0) {
      activeCells.each(function() {
        const $cell = $(this);
        const $cellContent = $cell.find('.cell-content');
        if ($cellContent.length) {
          $cellContent.css({
            'display': 'flex',
            'align-items': 'flex-end'
          });
        } else {
          $cell.css('vertical-align', 'bottom');
        }
      });
      
      window.viewEditor.toolbar.syncToolbarButtonStates(activeCells.first());
    }
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