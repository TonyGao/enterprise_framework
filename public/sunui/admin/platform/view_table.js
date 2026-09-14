/**
 * 表格行高和列宽拖拽调整功能
 */
$(document).ready(function() {
  // 初始化表格调整大小功能
  initTableResize();

  // 启动观察器，监听 ef-table 的插入
  const observer = new MutationObserver(function(mutationsList) {
    for (const mutation of mutationsList) {
      for (const node of mutation.addedNodes) {
        const $node = $(node);
        if ($node.hasClass('ef-table') || $node.find('.ef-table').length > 0) {
          setTimeout(() => {
            initTableResize();
          }, 100);
        }
      }
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

  const minCellWidth = 30;
  const minCellHeight = 10;

  /**
   * 初始化表格调整大小功能
   */
  function initTableResize() {
    // 移除现有的调整手柄，避免重复
    $('.column-resize-handle, .row-resize-handle, .resize-handle-corner, .left-resize-handle').remove();
    
    // 为每个表格添加调整大小功能
    $('.ef-table').each(function() {
      const $table = $(this);
      const $tableComponent = $table.closest('.ef-table-component');
      
      // 确保表格组件有相对定位，以便正确放置调整手柄
      $tableComponent.css('position', 'relative');
      
      // 为每个单元格添加调整手柄
      $table.find('td, th').each(function() {
        const $cell = $(this);
        
        // 跳过隐藏的单元格（合并单元格中被隐藏的部分）
        if ($cell.is(':hidden') || $cell.css('display') === 'none') {
          return;
        }
        
        const columnIndex = $cell.index();
        
        // 为最左侧的单元格添加左侧拖拽手柄
        if (columnIndex === 0) {
          const $leftHandle = $('<div class="left-resize-handle td-handle"></div>');
          $cell.append($leftHandle);
          
          // 绑定左侧拖拽事件
          $leftHandle.on('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const startX = e.pageX;
            const startWidth = $cell.outerWidth();
            let firstTdWitdh = $cell.outerWidth();
            
            // 添加拖拽状态类
            $tableComponent.addClass('resizing');
            $(this).addClass('dragging');
            
            // 创建辅助线
            const $guide = $('<div class="resize-guide vertical"></div>');
            const cellOffset = $cell.offset();
            
            $guide.css({
              left: cellOffset.left,
            });
            
            $('body').append($guide);
            
            // 鼠标移动事件
            $(document).on('mousemove.leftColumnResize', function(e) {
              const diffX = e.pageX - startX;
              const newWidth = Math.max(minCellWidth, startWidth - diffX);
              $guide.css('left', cellOffset.left + diffX);
            });
            
            // 鼠标释放事件
            $(document).on('mouseup.leftColumnResize', function(e) {
              $(document).off('mousemove.leftColumnResize mouseup.leftColumnResize');
              
              const diffX = e.pageX - startX;
              const newWidth = Math.max(minCellWidth, startWidth - diffX);
              
              // 检查当前单元格是否为合并单元格
              const colspan = parseInt($cell.attr('colspan')) || 1;
              let targetColumnIndex = columnIndex;
              
              // 对于left-resize-handle，始终调整最左侧的列（当前列）
              // 如果是合并单元格，仍然调整当前列，因为left-resize-handle调整的是左边界
              
              // 应用新宽度到目标列的所有单元格
              $table.find('tr').each(function() {
                const $targetCell = $(this).children('td, th').eq(targetColumnIndex);
                if ($targetCell.length) {
                  // 对于left-resize-handle，无论是否为合并单元格，都调整可见的td
                  // 因为left-resize-handle调整的是左边界，影响的是可见的第一个td
                  if (!$targetCell.is(':hidden')) {
                    $targetCell.css({
                      'width': newWidth + 'px',
                      'white-space': 'nowrap'
                    });
                  }
                }
              });
              
              // 同时更新colgroup中对应列的宽度
              const $colgroup = $table.find('colgroup');
              if ($colgroup.length) {
                const $col = $colgroup.find('col').eq(targetColumnIndex);
                if ($col.length) {
                  $col.css('width', newWidth + 'px');
                }
              }
              
              // 移除拖拽状态和辅助线
              $tableComponent.removeClass('resizing');
              $leftHandle.removeClass('dragging');
              $guide.remove();
            });
          });
        }
        
        // 添加列宽调整手柄（所有单元格）
        // 移除条件限制，使所有单元格都有列宽调整手柄
        const $colHandle = $('<div class="column-resize-handle td-handle"></div>');
        $cell.append($colHandle);
        
        // 绑定列宽调整事件
        $colHandle.on('mousedown', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const startX = e.pageX;
          const columnIndex = $cell.index();
          
          // 检查当前单元格是否为合并单元格
          const colspan = parseInt($cell.attr('colspan')) || 1;
          let startWidth;
          let firstTdWitdh = $cell.outerWidth();
          
          if (colspan > 1) {
            // 合并单元格：获取被隐藏的第二个<td>的宽度
            const targetColumnIndex = columnIndex + colspan - 1;
            const $targetCell = $cell.closest('tr').children('td, th').eq(targetColumnIndex);
            if ($targetCell.length && ($targetCell.is(':hidden') || $targetCell.css('display') === 'none')) {
              startWidth = $targetCell.outerWidth() || 100; // 如果获取不到宽度，使用默认值
            } else {
              startWidth = $cell.outerWidth() / colspan; // 平均分配宽度作为备选方案
            }
          } else {
            // 普通单元格：获取当前<td>的宽度
            startWidth = $cell.outerWidth();
          }
          
          // 添加拖拽状态类
          $tableComponent.addClass('resizing');
          $(this).addClass('dragging');
          
          // 创建辅助线
          const $guide = $('<div class="resize-guide vertical"></div>');
          const cellOffset = $cell.offset();
          
          $guide.css({
            left: cellOffset.left + firstTdWitdh,
          });
          
          // 创建实时显示宽高的提示框
          const $sizeTooltip = $('<div class="resize-size-tooltip"></div>');
          const cellHeight = $cell.outerHeight();
          $sizeTooltip.css({
            position: 'fixed',
            left: cellOffset.left + firstTdWitdh + 10,
            top: cellOffset.top - 40,
            background: 'rgba(0, 123, 255, 0.9)',
            color: 'white',
            padding: '6px 12px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: 'bold',
            zIndex: 10000,
            boxShadow: '0 2px 8px rgba(0, 123, 255, 0.3)',
            whiteSpace: 'nowrap',
            pointerEvents: 'none'
          });
          $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(firstTdWitdh), h: Math.round(cellHeight)}));
          
          $('body').append($guide, $sizeTooltip);
          
          // 鼠标移动事件
          $(document).on('mousemove.columnResize', function(e) {
            const diffX = e.pageX - startX;
            const newWidth = Math.max(minCellWidth, firstTdWitdh + diffX);
            $guide.css('left', cellOffset.left + newWidth);
            
            // 计算实际调整的宽度（用于合并单元格）
            let actualNewWidth;
            if (colspan > 1) {
              // 合并单元格：计算目标列的新宽度
              actualNewWidth = Math.max(minCellWidth, startWidth + diffX);
            } else {
              // 普通单元格：使用可见宽度
              actualNewWidth = newWidth;
            }
            
            // 更新实时显示的宽高
            $sizeTooltip.css({
              left: cellOffset.left + newWidth + 10
            });
            $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(actualNewWidth), h: Math.round(cellHeight)}));
          });
          
          // 鼠标释放事件
          $(document).on('mouseup.columnResize', function(e) {
            $(document).off('mousemove.columnResize mouseup.columnResize');
            
            const diffX = e.pageX - startX;
            const newWidth = Math.max(minCellWidth, startWidth + diffX);
            
            // 检查当前单元格是否为合并单元格
            const colspan = parseInt($cell.attr('colspan')) || 1;
            let targetColumnIndex = columnIndex;
            
            // 如果是合并单元格，调整最右侧的列
            if (colspan > 1) {
              targetColumnIndex = columnIndex + colspan - 1;
            }
            
            // 应用新宽度到目标列的所有单元格
            $table.find('tr').each(function() {
              if (colspan > 1) {
                // 合并单元格：只调整被隐藏的td（最右侧的td），绝不调整第一个可见的td
                const $targetCell = $(this).children('td, th').eq(targetColumnIndex);
                if ($targetCell.length && ($targetCell.is(':hidden') || $targetCell.css('display') === 'none')) {
                  $targetCell.css({
                    'width': newWidth + 'px',
                    'white-space': 'nowrap'
                  });
                }
              } else {
                // 普通单元格：调整可见的td
                const $targetCell = $(this).children('td, th').eq(targetColumnIndex);
                if ($targetCell.length && !$targetCell.is(':hidden')) {
                  $targetCell.css({
                    'width': newWidth + 'px',
                    'white-space': 'nowrap'
                  });
                }
              }
            });
            
            // 同时更新colgroup中对应列的宽度
            const $colgroup = $table.find('colgroup');
            if ($colgroup.length) {
              const $col = $colgroup.find('col').eq(targetColumnIndex);
              if ($col.length) {
                $col.css('width', newWidth + 'px');
              }
            }
            
            // 移除拖拽状态和辅助线
            $tableComponent.removeClass('resizing');
            $colHandle.removeClass('dragging');
            $guide.remove();
            $sizeTooltip.remove();
          });
        });
        
        // 添加行高调整手柄（所有单元格）
        // 移除条件限制，使所有单元格都有行高调整手柄
        const $rowHandle = $('<div class="row-resize-handle td-handle"></div>');
        $cell.append($rowHandle);
        
        // 获取 $cell 的rowspan
        let $lastRow = $cell.parent();
        const rowspan = parseInt($cell.attr('rowspan')) || 1;
        if (rowspan > 1) {
          // 通过 rowspan 的数量获取最底部的<tr></tr>
          $lastRow = $table.find('tr').eq($cell.parent().index() + rowspan - 1);
        }
        
        // 绑定行高调整事件
        $rowHandle.on('mousedown', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const $row = $cell.parent();
          const startY = e.pageY;
          const startHeight = $row.outerHeight();
          const firstTdHeight = $cell.outerHeight();
          
          // 添加拖拽状态类
          $tableComponent.addClass('resizing');
          $(this).addClass('dragging');
          
          // 创建辅助线
          const $guide = $('<div class="resize-guide horizontal"></div>');
          const rowOffset = $row.offset();

          $guide.css({
            top: rowOffset.top + firstTdHeight,
          });
          
          // 创建实时显示宽高的提示框
          const $sizeTooltip = $('<div class="resize-size-tooltip"></div>');
          const cellWidth = $cell.outerWidth();
          $sizeTooltip.css({
            position: 'fixed',
            left: rowOffset.left + cellWidth + 10,
            top: rowOffset.top + firstTdHeight - 20,
            background: 'rgba(0, 123, 255, 0.9)',
            color: 'white',
            padding: '6px 12px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: 'bold',
            zIndex: 10000,
            boxShadow: '0 2px 8px rgba(0, 123, 255, 0.3)',
            whiteSpace: 'nowrap',
            pointerEvents: 'none'
          });
          $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(cellWidth), h: Math.round(firstTdHeight)}));
          
          // 将辅助线添加到body，使其能够跨越整个视口
          $('body').append($guide, $sizeTooltip);
          
          // 鼠标移动事件
          $(document).on('mousemove.rowResize', function(e) {
            const diffY = e.pageY - startY;
            const newHeight = Math.max(minCellHeight, firstTdHeight + diffY);
            $guide.css('top', rowOffset.top + newHeight);
            
            // 更新实时显示的宽高
            $sizeTooltip.css({
              top: rowOffset.top + newHeight - 20
            });
            $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(cellWidth), h: Math.round(newHeight)}));
          });
          
          // 鼠标释放事件
          $(document).on('mouseup.rowResize', function(e) {
            $(document).off('mousemove.rowResize mouseup.rowResize');
            // 获取 lastRow 的高度
            let lastRowHeight = $lastRow.outerHeight();
            
            const diffY = e.pageY - startY;
            const newHeight = Math.max(minCellHeight, lastRowHeight + diffY);
            
            // 应用新高度到目标行，同时设置该行所有单元格的最小高度
            $lastRow.css('height', newHeight + 'px');
            // 为该行的所有单元格设置最小高度
            $lastRow.find('td, th').css('min-height', minCellHeight + 'px');
            
            // 移除拖拽状态和辅助线
            $tableComponent.removeClass('resizing');
            $rowHandle.removeClass('dragging');
            $guide.remove();
            $sizeTooltip.remove();
          });
        });
        
        // 添加角落调整手柄（所有单元格）
        // 移除条件限制，使所有单元格都有角落调整手柄
        const $cornerHandle = $('<div class="resize-handle-corner td-handle"></div>');
        $cell.append($cornerHandle);
        
        // 绑定角落调整事件
        $cornerHandle.on('mousedown', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const $row = $cell.parent();
          const startX = e.pageX;
          const startY = e.pageY;
          const startHeight = $row.outerHeight();
          const firstTdHeight = $cell.outerHeight();
          const columnIndex = $cell.index();
          let $lastRow = $cell.parent();

          if (rowspan > 1) {
            // 通过 rowspan 的数量获取最底部的<tr></tr>
            $lastRow = $table.find('tr').eq($cell.parent().index() + rowspan - 1);
          }
          
          // 检查当前单元格是否为合并单元格
          const colspan = parseInt($cell.attr('colspan')) || 1;
          let startWidth;
          let firstTdWitdh = $cell.outerWidth();
          
          if (colspan > 1) {
            // 合并单元格：获取被隐藏的第二个<td>的宽度
            const targetColumnIndex = columnIndex + colspan - 1;
            const $targetCell = $cell.closest('tr').children('td, th').eq(targetColumnIndex);
            if ($targetCell.length && ($targetCell.is(':hidden') || $targetCell.css('display') === 'none')) {
              startWidth = $targetCell.outerWidth() || 100; // 如果获取不到宽度，使用默认值
            } else {
              startWidth = $cell.outerWidth() / colspan; // 平均分配宽度作为备选方案
            }
          } else {
            // 普通单元格：获取当前<td>的宽度
            startWidth = $cell.outerWidth();
          }
          
          // 添加拖拽状态类
          $tableComponent.addClass('resizing');
          $(this).addClass('dragging');
          
          // 创建水平辅助线
          const $hGuide = $('<div class="resize-guide horizontal"></div>');
          const rowOffset = $row.offset();
          
          $hGuide.css({
            top: rowOffset.top + firstTdHeight,
          });
          $('body').append($hGuide);
          
          // 创建垂直辅助线
          const $vGuide = $('<div class="resize-guide vertical"></div>');
          const cellOffset = $cell.offset();
          
          $vGuide.css({
            left: cellOffset.left + firstTdWitdh,
          });
          
          // 创建实时显示宽高的提示框
          const $sizeTooltip = $('<div class="resize-size-tooltip"></div>');
          $sizeTooltip.css({
            position: 'fixed',
            left: cellOffset.left + firstTdWitdh + 10,
            top: rowOffset.top + firstTdHeight - 40,
            background: 'rgba(0, 123, 255, 0.9)',
            color: 'white',
            padding: '6px 12px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: 'bold',
            zIndex: 10000,
            boxShadow: '0 2px 8px rgba(0, 123, 255, 0.3)',
            whiteSpace: 'nowrap',
            pointerEvents: 'none'
          });
          $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(firstTdWitdh), h: Math.round(firstTdHeight)}));
          
          $('body').append($vGuide, $sizeTooltip);
          
          // 鼠标移动事件
          $(document).on('mousemove.cornerResize', function(e) {
            const diffX = e.pageX - startX;
            const diffY = e.pageY - startY;
            const newWidth = Math.max(minCellWidth, firstTdWitdh + diffX);
            const newHeight = Math.max(minCellHeight, firstTdHeight + diffY);
            
            $hGuide.css('top', rowOffset.top + newHeight);
            $vGuide.css('left', cellOffset.left + newWidth);
            
            // 计算实际调整的宽度（用于合并单元格）
            let actualNewWidth;
            if (colspan > 1) {
              // 合并单元格：计算目标列的新宽度
              actualNewWidth = Math.max(minCellWidth, startWidth + diffX);
            } else {
              // 普通单元格：使用可见宽度
              actualNewWidth = newWidth;
            }
            
            // 更新实时显示的宽高
            $sizeTooltip.css({
              left: cellOffset.left + newWidth + 10,
              top: rowOffset.top + newHeight - 40
            });
            $sizeTooltip.text(t('viewTableJs.wh', {w: Math.round(actualNewWidth), h: Math.round(newHeight)}));
          });
          
          // 鼠标释放事件
          $(document).on('mouseup.cornerResize', function(e) {
            $(document).off('mousemove.cornerResize mouseup.cornerResize');
            let lastRowHeight = $lastRow.outerHeight();
            
            const diffX = e.pageX - startX;
            const diffY = e.pageY - startY;
            const newWidth = Math.max(minCellWidth, startWidth + diffX);
            const newHeight = Math.max(minCellHeight, lastRowHeight + diffY);
            
            // 检查当前单元格是否为合并单元格
            const colspan = parseInt($cell.attr('colspan')) || 1;
            let targetColumnIndex = columnIndex;
            
            // 如果是合并单元格，调整最右侧的列
            if (colspan > 1) {
              targetColumnIndex = columnIndex + colspan - 1;
            }
            
            // 应用新宽度到目标列的所有单元格
            $table.find('tr').each(function() {
              if (colspan > 1) {
                // 合并单元格：只调整被隐藏的td（最右侧的td），绝不调整第一个可见的td
                const $targetCell = $(this).children('td, th').eq(targetColumnIndex);
                if ($targetCell.length && ($targetCell.is(':hidden') || $targetCell.css('display') === 'none')) {
                  $targetCell.css({
                    'width': newWidth + 'px',
                    'white-space': 'nowrap'
                  });
                }
              } else {
                // 普通单元格：调整可见的td
                const $targetCell = $(this).children('td, th').eq(targetColumnIndex);
                if ($targetCell.length && !$targetCell.is(':hidden')) {
                  $targetCell.css({
                    'width': newWidth + 'px',
                    'white-space': 'nowrap'
                  });
                }
              }
            });
            
            // 同时更新colgroup中对应列的宽度
            const $colgroup = $table.find('colgroup');
            if ($colgroup.length) {
              const $col = $colgroup.find('col').eq(targetColumnIndex);
              if ($col.length) {
                $col.css('width', newWidth + 'px');
              }
            }
            
            // 应用新高度到行，同时设置该行所有单元格的最小高度
            $lastRow.css('height', newHeight + 'px');
            // 为该行的所有单元格设置最小高度
            $lastRow.find('td, th').css('min-height', minCellHeight + 'px');
            
            // 移除拖拽状态和辅助线
            $tableComponent.removeClass('resizing');
            $cornerHandle.removeClass('dragging');
            $hGuide.remove();
            $vGuide.remove();
            $sizeTooltip.remove();
          });
        });
      });
    });
  }

  // 监听窗口大小变化，更新调整手柄位置
  $(window).on('resize', function() {
    initTableResize();
  });
  
  // 将initTableResize函数暴露到全局，供其他模块使用
  window.initTableResize = initTableResize;
});