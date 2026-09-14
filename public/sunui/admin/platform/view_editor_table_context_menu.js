// 表格右键菜单功能
(function() {
  'use strict';

  
  let contextMenu = null;
  let currentCell = null;
  let hideSelectionBorder, showSelectionBorder;
  
  // 安全获取 window.viewEditor 的函数
  function getViewEditorFunctions() {
    if (window.viewEditor && window.viewEditor.hideSelectionBorder) {
      hideSelectionBorder = window.viewEditor.hideSelectionBorder;
      showSelectionBorder = window.viewEditor.showSelectionBorder;
    } else {
      // 如果 window.viewEditor 不可用，提供默认的空函数
      hideSelectionBorder = function() {};
      showSelectionBorder = function() {};
    }
  }
  
  // 显示选择边框的函数
  // function showSelectionBorder(cells) {
  //   const $cells = $(cells);
  //   if ($cells.length === 0) return;
    
  //   // 获取表格容器
  //   const $tableContainer = $cells.closest('.table-container');
  //   if ($tableContainer.length === 0) return;
    
  //   // 确保容器有相对定位
  //   if ($tableContainer.css('position') !== 'relative') {
  //     $tableContainer.css('position', 'relative');
  //   }
    
  //   // 计算选区最外围的位置
  //   let top = Infinity, left = Infinity, bottom = -Infinity, right = -Infinity;
  //   $cells.each(function () {
  //     const rect = this.getBoundingClientRect();
  //     top = Math.min(top, rect.top);
  //     left = Math.min(left, rect.left);
  //     bottom = Math.max(bottom, rect.bottom);
  //     right = Math.max(right, rect.right);
  //   });
    
  //   // 将边界转为相对于容器的位置
  //   const containerRect = $tableContainer[0].getBoundingClientRect();
    
  //   // 获取或创建选择边框div
  //   let $borderDiv = $tableContainer.find('.selection-border');
  //   if ($borderDiv.length === 0) {
  //     $borderDiv = $('<div class="selection-border"></div>');
  //     $tableContainer.append($borderDiv);
  //   }
    
  //   $borderDiv.css({
  //     position: 'absolute',
  //     top: top - containerRect.top,
  //     left: left - containerRect.left,
  //     width: right - left,
  //     height: bottom - top,
  //     border: '2px solid rgb(0, 123, 255)',
  //     'background-color': 'rgba(0, 123, 255, 0.1)',
  //     'pointer-events': 'none',
  //     'z-index': 1000,
  //     display: 'block'
  //   });
  // }
  
  // 创建右键菜单HTML
  function createContextMenu() {
    const menuHTML = `
      <div id="table-context-menu" class="context-menu" style="display: none;">
        <div class="menu-item" data-action="cut">
            <i class="fa-solid fa-scissors"></i>
            <span>${t('ctxMenu.m1')}</span>
        </div>
        <div class="menu-item" data-action="copy">
            <i class="fa-solid fa-copy"></i>
            <span>${t('ctxMenu.m2')}</span>
        </div>
        <div class="menu-item" data-action="paste">
            <i class="fa-solid fa-paste"></i>
            <span>${t('ctxMenu.m3')}</span>
        </div>
        <div class="menu-separator"></div>
        <div class="menu-item" data-action="insert-row-above">
            <i class="fa-solid fa-arrow-up"></i>
            <span>' + t('ctxMenu.insertAbove') + ' <input type="number" value="1" min="1" max="10" class="row-count">${t('ctxMenu.m4')}</span>
        </div>
        <div class="menu-item" data-action="insert-row-below">
            <i class="fa-solid fa-arrow-down"></i>
            <span>' + t('ctxMenu.insertBelow') + ' <input type="number" value="1" min="1" max="10" class="row-count">${t('ctxMenu.m5')}</span>
        </div>
        <div class="menu-item" data-action="insert-col-left">
            <i class="fa-solid fa-arrow-left"></i>
            <span>' + t('ctxMenu.insertLeft') + ' <input type="number" value="1" min="1" max="10" class="col-count">${t('ctxMenu.m6')}</span>
        </div>
        <div class="menu-item" data-action="insert-col-right">
            <i class="fa-solid fa-arrow-right"></i>
            <span>' + t('ctxMenu.insertRight') + ' <input type="number" value="1" min="1" max="10" class="col-count">${t('ctxMenu.m7')}</span>
        </div>
        <div class="menu-separator"></div>
        <div class="menu-item" data-action="delete-row">
            <i class="fa-solid fa-minus"></i>
            <span>${t('ctxMenu.m8')}</span>
        </div>
        <div class="menu-item" data-action="delete-col">
            <i class="fa-solid fa-minus"></i>
            <span>${t('ctxMenu.m9')}</span>
        </div>
        <div class="menu-separator"></div>
        <div class="menu-item" data-action="clear-content">
            <i class="fa-solid fa-eraser"></i>
            <span>${t('ctxMenu.m10')}</span>
        </div>
        <div class="menu-item" data-action="clear-format">
            <i class="fa-solid fa-broom"></i>
            <span>${t('ctxMenu.m11')}</span>
        </div>
      </div>
    `;
    
    $('body').append(menuHTML);
    contextMenu = $('#table-context-menu');
    
    // 绑定菜单项点击事件
    contextMenu.on('click', '.menu-item', function(e) {
      e.stopPropagation();
      const action = $(this).data('action');
      executeAction(action, $(this));
      hideContextMenu();
    });
    
    // 阻止输入框的点击事件冒泡
    contextMenu.on('click', 'input', function(e) {
      e.stopPropagation();
    });
    
    // 允许输入框接收键盘事件
    contextMenu.on('keydown', 'input', function(e) {
      e.stopPropagation();
      // 允许输入框正常处理键盘事件，包括Backspace
    });
    
    // 输入框获得焦点时，暂时禁用全局键盘事件
    contextMenu.on('focus', 'input', function(e) {
      $(document).off('keydown.contextmenu');
    });
    
    // 输入框失去焦点时，重新启用全局键盘事件
    contextMenu.on('blur', 'input', function(e) {
      $(document).on('keydown.contextmenu', function(e) {
        if (e.key === 'Escape') {
          hideContextMenu();
        }
      });
    });
  }
  
  // 显示右键菜单
  function showContextMenu(e, cell) {
    if (!contextMenu) {
      createContextMenu();
    }
    
    const $clickedCell = $(cell);
    const $table = $clickedCell.closest('table');
    
    // 检查点击的单元格是否在当前选中区域内
    const isInSelection = $clickedCell.attr('data-cell-active') === 'true';
    
    if (!isInSelection) {
      // 如果点击的单元格不在选中区域内，清除之前的选择并选中当前单元格
      $table.find('td, th').removeAttr('data-cell-active').css('outline', 'none');
      hideSelectionBorder();
      $clickedCell.attr('data-cell-active', 'true');
      showSelectionBorder($clickedCell);
    }
    
    currentCell = cell;
    
    // 暂时禁用单元格快捷键
    $clickedCell.attr('data-shortcuts-disabled', 'true');
    
    // 定位菜单
    const menuWidth = 200;
    const menuHeight = 400;
    let left = e.pageX;
    let top = e.pageY;
    
    // 防止菜单超出视窗
    if (left + menuWidth > $(window).width()) {
      left = e.pageX - menuWidth;
    }
    if (top + menuHeight > $(window).height()) {
      top = e.pageY - menuHeight;
    }
    
    contextMenu.css({
      display: 'block',
      left: left + 'px',
      top: top + 'px'
    });
  }
  
  // 隐藏右键菜单
  function hideContextMenu() {
    if (contextMenu) {
      contextMenu.hide();
    }
    
    // 恢复单元格快捷键
    if (currentCell) {
      $(currentCell).removeAttr('data-shortcuts-disabled');
    }
    
    currentCell = null;
  }
  
  // 执行菜单动作
  function executeAction(action, menuItem) {
    if (!currentCell) return;
    
    const $cell = $(currentCell);
    const $table = $cell.closest('table');
    const $row = $cell.parent();
    const cellIndex = $cell.index();
    const rowIndex = $row.index();
    
    // 获取所有选中的单元格
    const $selectedCells = $table.find('td[data-cell-active="true"], th[data-cell-active="true"]');
    const $targetCells = $selectedCells.length > 0 ? $selectedCells : $cell;
    
    switch (action) {
      case 'cut':
        // 对所有选中的单元格进行剪切操作
        let allCutContent = [];
        $targetCells.each(function() {
          const $currentCell = $(this);
          const cutContent = $currentCell.clone();
          cutContent.find('.td-handle').remove();
          allCutContent.push(cutContent.html());
        });
        
        const combinedContent = allCutContent.join('\n');
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(combinedContent).then(() => {
            // 剪切成功后清除所有选中单元格的内容
            $targetCells.each(function() {
              $(this).contents().not('.td-handle').remove();
            });
            if (window.$.alert) {
              window.$.alert.success(t('viewEditorTableCtxJs.js1', {p1: $targetCells.length}));
            }
          }).catch(() => {
            if (window.$.alert) {
              window.$.alert.error(t('viewEditorTableCtxJs.js8'));
            }
          });
        }
        break;
        
      case 'copy':
        // 对所有选中的单元格进行复制操作
        let allCopyContent = [];
        $targetCells.each(function() {
          const $currentCell = $(this);
          const copyContent = $currentCell.clone();
          copyContent.find('.td-handle').remove();
          allCopyContent.push(copyContent.html());
        });
        
        const combinedCopyContent = allCopyContent.join('\n');
        
        navigator.clipboard.writeText(combinedCopyContent).then(() => {
          if (window.$.alert) {
            window.$.alert.success(t('viewEditorTableCtxJs.js2', {p1: $targetCells.length}));
          }
        }).catch(() => {
          if (window.$.alert) {
            window.$.alert.error(t('viewEditorTableCtxJs.js9'));
          }
        });
        break;
        
      case 'paste':
        // 粘贴内容到所有选中的单元格
        navigator.clipboard.readText().then(text => {
          const pasteLines = text.split('\n');
          
          $targetCells.each(function(index) {
            const $currentCell = $(this);
            // 保存现有的td-handle元素
            const $handles = $currentCell.find('.td-handle').detach();
            
            // 如果有多行内容，按顺序分配给不同单元格
            const contentIndex = index < pasteLines.length ? index : 0;
            const cellContent = pasteLines[contentIndex] || '';
            
            // 设置新内容
            $currentCell.html(cellContent);
            
            // 重新添加td-handle元素
            $currentCell.append($handles);
          });
          
          if (window.$.alert) {
            window.$.alert.success(t('viewEditorTableCtxJs.js3', {p1: $targetCells.length}));
          }
        }).catch(() => {
          if (window.$.alert) {
            window.$.alert.error(t('viewEditorTableCtxJs.js10'));
          }
        });
        break;
        
      case 'insert-row-above':
        const rowsAbove = parseInt(menuItem.find('.row-count').val()) || 1;
        insertRows($table, rowIndex, rowsAbove, 'above');
        break;
        
      case 'insert-row-below':
        const rowsBelow = parseInt(menuItem.find('.row-count').val()) || 1;
        insertRows($table, rowIndex, rowsBelow, 'below');
        break;
        
      case 'insert-col-left':
        const colsLeft = parseInt(menuItem.find('.col-count').val()) || 1;
        insertColumns($table, cellIndex, colsLeft, 'left');
        break;
        
      case 'insert-col-right':
        const colsRight = parseInt(menuItem.find('.col-count').val()) || 1;
        insertColumns($table, cellIndex, colsRight, 'right');
        break;
        
      case 'delete-row':
        deleteRow($table, rowIndex);
        break;
        
      case 'delete-col':
        deleteColumn($table, cellIndex);
        break;
        
      case 'clear-content':
        // 清除所有选中单元格的内容
        $targetCells.each(function() {
          const $cell = $(this);
          const $cellContent = $cell.find('.cell-content');
          if ($cellContent.length) {
            // 如果有cell-content，只清除其内容，保留div本身
            $cellContent.empty();
          } else {
            // 如果没有cell-content（向后兼容），清除除td-handle外的所有内容
            $cell.contents().not('.td-handle').remove();
          }
        });
        if (window.$.alert) {
          window.$.alert.success(t('viewEditorTableCtxJs.js4', {p1: $targetCells.length}));
        }
        break;
        
      case 'clear-format':
        // 清除所有选中单元格的格式
        $targetCells.each(function() {
          $(this).removeAttr('style').removeClass();
        });
        if (window.$.alert) {
          window.$.alert.success(t('viewEditorTableCtxJs.js5', {p1: $targetCells.length}));
        }
        break;
    }
  }
  
  // 插入行
  function insertRows($table, targetRowIndex, count, position) {
    const $targetRow = $table.find('tr').eq(targetRowIndex);
    const colCount = $targetRow.find('td, th').length;
    
    for (let i = 0; i < count; i++) {
      const $newRow = $('<tr></tr>');
      
      // 复制模板行的样式
      const templateRowStyle = $targetRow.attr('style') || '';
      if (templateRowStyle) {
        $newRow.attr('style', templateRowStyle);
      }
      
      // 以目标行为模板创建新单元格
        // 获取模板行的高度
        const templateRowHeight = $targetRow.outerHeight();
        
        $targetRow.find('td, th').each(function(j) {
          const $templateCell = $(this);
          const $newCell = $('<td></td>');
          
          // 复制模板单元格的样式和属性
          let style = $templateCell.attr('style') || '';
          const className = $templateCell.attr('class') || '';
          const rowspan = $templateCell.attr('rowspan') || '1';
          const colspan = $templateCell.attr('colspan') || '1';
          
          // 移除选中状态的outline样式
          style = style.replace(/outline:\s*[^;]+;?/g, '');
          
          // 确保样式包含cursor和高度
          let finalStyle = style;
          if (!finalStyle.includes('cursor:')) {
            finalStyle += finalStyle ? '; cursor: text;' : 'cursor: text;';
          }
          // 添加高度样式，确保新行保持模板行的高度
          if (!finalStyle.includes('height:') && templateRowHeight > 0) {
            finalStyle += finalStyle ? `; height: ${templateRowHeight}px;` : `height: ${templateRowHeight}px;`;
          }
          
          $newCell.attr({
            'style': finalStyle,
            'class': className,
            'tabindex': '0',
            'data-cell-active': 'false',
            'rowspan': rowspan,
            'colspan': colspan,
            'key-press': 'enter',
            'key-event': 'dblclick',
            'key-scope': '.ef-table-component'
          });
          
          // 添加内容div
          const $contentDiv = $('<div class="cell-content" contenteditable="false" style="width: 100%; height: 100%; outline: none; border: none; background: transparent; cursor: default; display: flex; justify-content: flex-start; align-items: center;"></div>');
          $newCell.append($contentDiv);
          
          // 添加拖拽手柄
          const $columnHandle = $('<div class="column-resize-handle td-handle"></div>');
          const $rowHandle = $('<div class="row-resize-handle td-handle"></div>');
          const $cornerHandle = $('<div class="resize-handle-corner td-handle"></div>');
          $newCell.append($columnHandle, $rowHandle, $cornerHandle);
          
          // 如果是最左侧单元格，添加left-resize-handle
          if (j === 0) {
            const $leftHandle = $('<div class="left-resize-handle td-handle"></div>');
            $newCell.append($leftHandle);
          }
          
          $newRow.append($newCell);
        });
      
      if (position === 'above') {
        $targetRow.before($newRow);
      } else {
        $targetRow.after($newRow);
      }
    }
    
    // 重新初始化表格调整大小功能，这会重新绑定所有td-handle的事件
    if (window.initTableResize) {
      window.initTableResize();
    }
    
    // 为新插入的单元格绑定多选事件
    if (window.viewEditor && window.viewEditor.bindTableComponentEvents) {
      const $tableComponent = $table.closest('.ef-table-component');
      window.viewEditor.bindTableComponentEvents($tableComponent);
    }
    
    if (window.$.alert) {
      window.$.alert.success(t('viewEditorTableCtxJs.js6', {p1: count}));
    }
  }
  
  // 插入列
  function insertColumns($table, targetColIndex, count, position) {
    const insertIndex = position === 'left' ? targetColIndex : targetColIndex + 1;
    
    // 计算单元格宽度
    const $tableComponent = $table.closest('.ef-table-component');
    const sectionWidth = $tableComponent.width() || 800;
    const currentColCount = $table.find('tr').first().find('td, th').length;
    const newColCount = currentColCount + count;
    const cellWidth = (sectionWidth / newColCount).toFixed(2);
    
    $table.find('tr').each(function() {
      const $row = $(this);
      const $targetCell = $row.find('td, th').eq(targetColIndex);
      
      for (let i = 0; i < count; i++) {
        const $newCell = $('<td></td>');
        
        // 复制目标单元格的样式和属性作为模板
        let templateStyle = $targetCell.attr('style') || '';
        const templateClass = $targetCell.attr('class') || '';
        const templateRowspan = $targetCell.attr('rowspan') || '1';
        const templateColspan = $targetCell.attr('colspan') || '1';
        
        // 移除选中状态的outline样式
        templateStyle = templateStyle.replace(/outline:\s*[^;]+;?/g, '');
        
        // 更新宽度并确保包含cursor样式
        let finalStyle = templateStyle.replace(/width:\s*[^;]+/g, `width: ${cellWidth}px`);
        if (!finalStyle.includes('cursor:')) {
          finalStyle += finalStyle ? '; cursor: text;' : 'cursor: text;';
        }
        
        // 设置新单元格的样式和属性
        $newCell.attr({
          'style': finalStyle,
          'class': templateClass,
          'tabindex': '0',
          'data-cell-active': 'false',
          'rowspan': templateRowspan,
          'colspan': templateColspan,
          'key-press': 'enter',
          'key-event': 'dblclick',
          'key-scope': '.ef-table-component'
        });
        
        // 添加内容div
        const $contentDiv = $('<div class="cell-content" contenteditable="false" style="width: 100%; height: 100%; outline: none; border: none; background: transparent; cursor: default; display: flex; justify-content: flex-start; align-items: center;"></div>');
        $newCell.append($contentDiv);
        
        // 添加拖拽手柄
        const $columnHandle = $('<div class="column-resize-handle td-handle"></div>');
        const $rowHandle = $('<div class="row-resize-handle td-handle"></div>');
        const $cornerHandle = $('<div class="resize-handle-corner td-handle"></div>');
        $newCell.append($columnHandle, $rowHandle, $cornerHandle);
        
        // 如果是向左插入列且新单元格将成为最左侧单元格，添加left-resize-handle
        if (position === 'left' && targetColIndex === 0) {
          const $leftHandle = $('<div class="left-resize-handle td-handle"></div>');
          $newCell.append($leftHandle);
        }
        
        if (position === 'left') {
          $targetCell.before($newCell);
        } else {
          $targetCell.after($newCell);
        }
      }
    });
    
    // 更新colgroup
    let $colgroup = $table.find('colgroup');
    if (!$colgroup.length) {
      // 如果没有colgroup，创建一个
      $colgroup = $('<colgroup></colgroup>');
      $table.prepend($colgroup);
      // 为现有列添加col元素
      const existingCols = $table.find('tr').first().find('td, th').length - count;
      for (let i = 0; i < existingCols; i++) {
        $colgroup.append(`<col style="width: ${cellWidth}px;">`);
      }
    }
    
    // 添加新的col元素
    for (let i = 0; i < count; i++) {
      const $newCol = $(`<col style="width: ${cellWidth}px;">`);
      if (position === 'left') {
        $colgroup.find('col').eq(targetColIndex).before($newCol);
      } else {
        $colgroup.find('col').eq(targetColIndex).after($newCol);
      }
    }
    
    // 重新调整所有列的宽度
    const newTableWidth = $table.width();
    const totalCols = $table.find('tr').first().find('td, th').length;
    const adjustedCellWidth = (newTableWidth / totalCols).toFixed(2);
    
    // 更新colgroup中的col宽度
    $colgroup.find('col').each(function() {
      $(this).css('width', adjustedCellWidth + 'px');
    });
    
    $table.find('tr').each(function() {
      $(this).find('td, th').each(function() {
        $(this).css('width', adjustedCellWidth + 'px');
      });
    });
    
    // 重新初始化表格调整大小功能，这会重新绑定所有td-handle的事件
    if (window.initTableResize) {
      window.initTableResize();
    }
    
    // 为新插入的单元格绑定多选事件
    if (window.viewEditor && window.viewEditor.bindTableComponentEvents) {
      const $tableComponent = $table.closest('.ef-table-component');
      window.viewEditor.bindTableComponentEvents($tableComponent);
    }
    
    if (window.$.alert) {
      window.$.alert.success(t('viewEditorTableCtxJs.js7', {p1: count}));
    }
  }
  
  // 删除行
  function deleteRow($table, rowIndex) {
    const $row = $table.find('tr').eq(rowIndex);
    if ($table.find('tr').length <= 1) {
      if (window.$.alert) {
        window.$.alert.warning(t('viewEditorTableCtxJs.js11'));
      }
      return;
    }
    
    $row.remove();
    
    // 重新初始化表格调整大小功能，这会重新绑定所有td-handle的事件
    if (window.initTableResize) {
      window.initTableResize();
    }
    
    if (window.$.alert) {
      window.$.alert.success(t('viewEditorTableCtxJs.js12'));
    }
  }
  
  // 删除列
  function deleteColumn($table, colIndex) {
    const firstRow = $table.find('tr').first();
    if (firstRow.find('td, th').length <= 1) {
      if (window.$.alert) {
        window.$.alert.warning(t('viewEditorTableCtxJs.js13'));
      }
      return;
    }
    
    $table.find('tr').each(function() {
      $(this).find('td, th').eq(colIndex).remove();
    });
    
    // 重新初始化表格调整大小功能，这会重新绑定所有td-handle的事件
    if (window.initTableResize) {
      window.initTableResize();
    }
    
    if (window.$.alert) {
      window.$.alert.success(t('viewEditorTableCtxJs.js14'));
    }
  }
  
  // 初始化右键菜单
  function initTableContextMenu() {
    // 初始化时获取 viewEditor 函数
    getViewEditorFunctions();
    
    // 为表格单元格绑定右键事件
    $(document).on('contextmenu', '.ef-table-component td, .ef-table-component th', function(e) {
      e.preventDefault();
      showContextMenu(e, this);
    });
    
    // 点击其他地方隐藏菜单
    $(document).on('click', function(e) {
      if (!$(e.target).closest('#table-context-menu').length) {
        hideContextMenu();
      }
    });
    
    // ESC键隐藏菜单
    $(document).on('keydown.contextmenu', function(e) {
      if (e.key === 'Escape') {
        hideContextMenu();
      }
    });
  }
  
  // 页面加载完成后初始化
  $(document).ready(function() {
    initTableContextMenu();
  });
  
  // 导出完整的右键菜单功能供其他模块使用
  const tableContextMenu = {
    show: showContextMenu,
    hide: hideContextMenu,
    init: initTableContextMenu,
    createMenu: createContextMenu,
    executeAction: executeAction
  };
  
  // 兼容性：如果 window.viewEditor 存在，也将右键菜单功能添加到其中
  if (typeof window.viewEditor === 'object') {
    window.viewEditor.tableContextMenu = tableContextMenu;
  }
})();