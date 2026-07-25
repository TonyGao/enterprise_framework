$(document).ready(function () {
  let alert = window.alert;
  let $canvas = $('.canvas');
  makeComponentDraggable = window.viewEditor.makeComponentDraggable;
  showSelectionBorder = window.viewEditor.showSelectionBorder;
  reinitializeDroppables = window.viewEditor.reinitializeDroppables;

  // 生成唯一ID的函数
  function generateUniqueId () {
    return Math.random().toString(36).substr(2, 9);
  }

  function transformSection (section) {
    section.css({ 'background-color': 'white' }); // 移除 background-color 样式
    section.children('.section-content').css({ 'background-color': 'white', 'min-height': '0px' }); // 清除子元素的背景色
  }

  // section 头部添加按钮点击事件，打开添加布局弹窗
  let sectionId;
  $('#canvas').on('click', '.btn-add', function () {
    sectionId = $(this).closest('.section').attr('id');
    $('#structureModal').css('display', 'flex');
  });

  // 处理 section 的关闭逻辑
  // TODO: 这里的历史记录有待完善
  $canvas.on('click', '.btn-close', function () {
    let $section = $(this).closest('.section');
    let sectionId = $section.attr('id');

    // 记录撤销重做状态
    if (window.undoRedoManager) {
      window.undoRedoManager.recordAction('remove_section', {
        sectionId: sectionId,
        sectionHtml: $section[0].outerHTML
      });
    }

    $section.remove();
  });

  let full24 = `
  	<div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-24 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center;border: 1px dashed #d5d8dc">
      </div>
	  </div>
  `;

  let halfAndHalf = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-12 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc">
      </div>
      <div class="ef-col-12 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc">
      </div>
    </div>
  `;

  let trisect = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-8 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc">
      </div>
      <div class="ef-col-8 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc">
      </div>
      <div class="ef-col-8 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc">
      </div>
    </div>
  `;

  let fourEqualParts = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>  
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
    </div>
  `;

  let eightSixteen = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-8 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-16 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  let sixteenEight = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-16 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-8 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
`;

  let sixSixTwelve = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-12 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  let twelveSixSix = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-12 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  let sixTwelveSix = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-12 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-6 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  let fiveEqualParts = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%; --columns: 5;">
      <div class="ef-col ef-col-auto item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col ef-col-auto item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col ef-col-auto item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col ef-col-auto item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col ef-col-auto item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  let sixEqualParts = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%;">
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc"></div>
    </div>
  `;

  let fourSixteenFour = `
    <div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%">
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-16 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
      <div class="ef-col-4 item-block" style="min-height: 68px; line-height: 68px; color: white; text-align: center; border: 1px dashed #d5d8dc""></div>
    </div>
  `;

  // 点击选取添加到section的布局
  $('.ef-col-item').on('click', function (event) {
    $('#structureModal').hide();
    let itemId = $(this).attr('id');
    transformSection($('#' + sectionId));
    if (itemId === 'full-24') {
      $('#' + sectionId + ' .section-content').append(full24);
    }

    if (itemId === 'half-and-half') {
      $('#' + sectionId + ' .section-content').append(halfAndHalf);
    }

    if (itemId === 'trisect') {
      $('#' + sectionId + ' .section-content').append(trisect);
    }

    if (itemId === 'four-equal-parts') {
      $('#' + sectionId + ' .section-content').append(fourEqualParts);
    }

    if (itemId === 'eight-sixteen') {
      $('#' + sectionId + ' .section-content').append(eightSixteen);
    }

    if (itemId === 'sixteen-eight') {
      $('#' + sectionId + ' .section-content').append(sixteenEight);
    }

    if (itemId === 'six-six-twelve') {
      $('#' + sectionId + ' .section-content').append(sixSixTwelve);
    }

    if (itemId === 'twelve-six-six') {
      $('#' + sectionId + ' .section-content').append(twelveSixSix);
    }

    if (itemId === 'six-twelve-six') {
      $('#' + sectionId + ' .section-content').append(sixTwelveSix);
    }

    if (itemId === 'five-equal-parts') {
      $('#' + sectionId + ' .section-content').append(fiveEqualParts);
    }

    if (itemId === 'six-equal-parts') {
      $('#' + sectionId + ' .section-content').append(sixEqualParts);
    }

    if (itemId === 'four-sixteen-four') {
      $('#' + sectionId + ' .section-content').append(fourSixteenFour);
    }

    $canvas = $('.canvas');
    // 确保新添加的 item-block 能被拖拽
    reinitializeDroppables();
  })

  // 定义组件模板（假设已经存在）
  let textPlaceHolder = '在此添加您的文本';
  const componentTemplates = {
    text: {
      template: `
      <div id="ef-text-comp-{uniqueId}" class="ef-component ef-text-component ef-text ef-text-comp-{uniqueId}" style="width:100%">
        <span class="ef-component-labels ef-label-small label-above-line label-top" style="left: 0px">
          <span class="ef-label-comp-type draggable">
            <span>Text</span>
          </span>
        </span>
        <div class="font_2 ef-rich-text" style="font-size:64px;color:#000;" contenteditable="true" data-placeholder="请输入文本">${textPlaceHolder}</div>
      </div>`,
      width: 512, // 模板的预期宽度
      height: 68 // 模板的预期高度
    },
    // 表格组件模板
    table: {
      template: `
      <div id="ef-table-comp-{uniqueId}" class="ef-component ef-table-component ef-table-comp-{uniqueId}" style="position: static; overflow-x: auto; overflow-y: hidden;">
        <span class="ef-component-labels ef-label-small label-above-line label-top table-label" style="left: 0px">
          <span class="ef-label-comp-type">
            <span>Table</span>
          </span>
        </span>
        <div class="table-container" style="position: relative;">
          <table class="ef-table" ef-table-hotkeys style="width: fit-content;">
            <thead>
              <tr style="height: 30px;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">标题 1</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">标题 2</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">标题 3</th>
              </tr>
            </thead>
            <tbody>
              <tr style="height: 30px;">
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 1</td>
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 2</td>
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 3</td>
              </tr>
              <tr style="height: 30px;">
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 4</td>
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 5</td>
                <td style="border: 1px solid #ddd; padding: 8px;" tabindex="0" data-cell-active="false">内容 6</td>
              </tr>
            </tbody>
          </table>
          <div class="selection-border" style="display: none; position: absolute; border: 2px solid #007bff; pointer-events: none; z-index: 1000;"></div>
        </div>
      </div>`,
      width: 600, // 表格的预期宽度
      height: 200 // 表格的预期高度
    }
    // 可以添加更多组件类型
  };

  // 使用事件委托，将组件设为可拖动
  $('.component-grid').on('mousedown', '.component-item', function (e) {
    e.preventDefault();

    // 获取拖拽的组件类型
    const componentType = $(this).attr('componenttype');
    const templateConfig = componentTemplates[componentType];
    if (!templateConfig) return;

    // 创建虚框，宽高与templateConfig相同
    const placeholder = $('<div class="dragging-placeholder"></div>').css({
      width: templateConfig.width,
      height: templateConfig.height,
      position: 'absolute',
      border: '1px dashed #007bff',
      backgroundColor: 'rgba(0, 123, 255, 0.1)',
      pointerEvents: 'none'
    });

    $('body').append(placeholder);

    $(document).on('mousemove.drag', function (moveEvent) {
      placeholder.css({
        top: moveEvent.pageY - placeholder.height() / 2,
        left: moveEvent.pageX - placeholder.width() / 2
      });
    });

    // 创建一个标志变量，用于防止重复处理
    let isProcessed = false;

    $(document).on('mouseup.drag', function () {
      // 如果已经处理过，则直接返回
      if (isProcessed) return;
      isProcessed = true;

      $(document).off('mousemove.drag mouseup.drag');
      placeholder.remove(); // 移除虚框

      // 检查放置位置 - 优先选择item-block，其次是section-content
      // 确保只选择一个最合适的目标容器
      let droppableArea;
      const itemBlock = $('.item-block:hover');
      const sectionContent = $('.section-content:hover');

      // 优先使用item-block作为放置目标
      if (itemBlock.length) {
        droppableArea = itemBlock;
      } else if (sectionContent.length) {
        droppableArea = sectionContent;
      }

      if (droppableArea && droppableArea.length) {
        // 如果是表格组件，显示表格模态窗口让用户输入行列数
        if (componentType === 'table') {
          // 保存当前拖放的位置和目标元素
          const dropTarget = droppableArea;

          // 显示表格行列输入模态框
          $('#tableModal').css('display', 'flex');

          // 处理确定按钮点击事件
          $('#create-table-btn').off('click').on('click', function () {
            const rows = parseInt($('#table-rows').val()) || 3;
            const cols = parseInt($('#table-cols').val()) || 3;

            // 生成表格HTML
            let tableHtml = `
            <table class="ef-table" ef-table-hotkeys 
              data-table-keys='{
                "ctrl+a": "selectAll",
                "cmd+a": "selectAll",
                "ctrl+c": "copy",
                "cmd+c": "copy",
                "ctrl+v": "paste",
                "cmd+v": "paste",
                "tab": "nextCell",
                "shift+tab": "prevCell",
                "up": "moveUp",
                "down": "moveDown",
                "left": "moveLeft",
                "right": "moveRight"
              }' 
              style="width: fit-content; border-collapse: collapse; margin-left: 0px; margin-right: auto;">
              <colgroup>`;

            // 生成colgroup
            const sectionContent = dropTarget.closest('.section-content');
            const sectionWidth = sectionContent.width() - 4;
            const cellWidth = (sectionWidth / cols).toFixed(2);

            for (let i = 0; i < cols; i++) {
              tableHtml += `<col style="width: ${cellWidth}px;">`;
            }

            tableHtml += `</colgroup><tbody>`;

            // 生成表格内容
            for (let i = 0; i < rows; i++) {
              tableHtml += '<tr style="height: 30px;">';
              for (let j = 0; j < cols; j++) {
                tableHtml += `<td style="font-family: 'Microsoft YaHei', Helvetica, Tahoma, Arial, 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', 'Heiti SC', 'WenQuanYi Micro Hei', sans-serif; font-size: 14px; font-weight: normal; font-style: normal; text-decoration: none; text-align: left; vertical-align: middle; background-color: transparent; padding: 1px 2px; border: 1px dashed #d5d8dc; white-space: nowrap; width: ${cellWidth}px;" rowspan="1" colspan="1" tabindex="0" data-cell-active="false"><div class="cell-content" contenteditable="false" style="width: 100%; height: 100%; outline: none; border: none; background: transparent; cursor: default; display: flex; justify-content: flex-start; align-items: center;"></div></td>`;
              }
              tableHtml += '</tr>';
            }
            tableHtml += '</tbody></table>';

            // 创建表格组件
            const uniqueId = generateUniqueId();
            const componentHtml = `
              <div id="ef-table-comp-${uniqueId}" class="ef-component ef-table-component ef-table-comp-${uniqueId}" style="position: static; overflow-x: auto; overflow-y: hidden;">
                <span class="ef-component-labels ef-label-small label-above-line label-top" style="left: 0px">
                  <span class="ef-label-comp-type draggable">
                    <span>Table</span>
                  </span>
                </span>
                <div class="table-container" style="position: relative;">
                  ${tableHtml}
                  <div class="selection-border" style="display: none; position: absolute; border: 2px solid #007bff; pointer-events: none; z-index: 1000;"></div>
                </div>
              </div>`;

            const newComponent = $(componentHtml);

            // 设置组件位置并添加到目标位置
            newComponent.css({
              position: 'static !important',
              // 使用!important确保不被jQuery UI覆盖
              left: '',
              top: ''
            }).appendTo(dropTarget);

            // 使新添加的组件可以拖动
            makeComponentDraggable(newComponent);

            // 初始化表格交互功能
            /**
             * isSelecting: 一个标志变量，用于判断当前是否处于选择状态。
             * startCell: 选择开始时的单元格。
             * selectedCells: 存储已选择的单元格的数组。
             * startRowIndex: 选择开始时的行索引。
             * startColIndex: 选择开始时的列索引
             * 
             * isSelecting: 默认为false，当表格被点击时，将其设置为true。
             */
            let isSelecting = false;
            let startCell = null;
            let selectedCells = [];
            let startRowIndex = -1;
            let startColIndex = -1;

            // 获取单元格的行列索引
            function getCellIndex (cell) {
              const $cell = $(cell);
              const $row = $cell.parent();

              // 计算逻辑列索引，考虑合并单元格
              let logicalColIndex = 0;
              $row.find('td, th').each(function (index) {
                if (this === cell) {
                  return false; // 找到目标单元格，停止循环
                }

                const $currentCell = $(this);
                const colspan = parseInt($currentCell.attr('colspan')) || 1;
                logicalColIndex += colspan;
              });

              return {
                row: $row.index(),
                col: logicalColIndex,
                domIndex: $cell.index() // DOM中的实际索引
              };
            }

            // 选择区域内的所有单元格（为新组件定制）
            function selectCellsInRangeForNewComponent (startCell, endCell, isMultiSelect = false) {
              // 构建逻辑网格
              const logicalGrid = buildLogicalGrid(newComponent);
              
              // 使用逻辑网格获取准确的位置
              const start = getCellLogicalPosition(startCell, logicalGrid);
              const end = getCellLogicalPosition(endCell, logicalGrid);
              
              if (!start || !end) return [];
              
              const minRow = Math.min(start.row, end.row);
              const maxRow = Math.max(start.row, end.row);
              const minCol = Math.min(start.col, end.col);
              const maxCol = Math.max(start.col, end.col);

              // 如果不是多选模式，清除之前的选择
              if (!isMultiSelect) {
                clearSelection(newComponent);
              }

              let selectedCells = [];
              
              // 检查起始单元格是否有rowspan，如果有，需要扩展影响范围
              const $startCell = $(startCell);
              const startRowspan = parseInt($startCell.attr('rowspan')) || 1;
              
              // 计算最终的行范围（考虑rowspan扩展）
              let finalMinRow = minRow;
              let finalMaxRow = maxRow;
              
              if (startRowspan > 1) {
                // 扩展行范围到起始单元格的rowspan影响范围
                finalMaxRow = Math.max(finalMaxRow, start.row + startRowspan - 1);
              }
              
              // 在最终的行列范围内标记所有单元格
              for (let rowIndex = finalMinRow; rowIndex <= finalMaxRow; rowIndex++) {
                if (rowIndex < logicalGrid.length && logicalGrid[rowIndex]) {
                  for (let colIndex = minCol; colIndex <= maxCol; colIndex++) {
                    if (colIndex < logicalGrid[rowIndex].length && logicalGrid[rowIndex][colIndex]) {
                      const cellElement = logicalGrid[rowIndex][colIndex];
                      // 确保cellElement是真正的DOM元素，而不是'occupied'字符串
                      if (cellElement && typeof cellElement === 'object' && cellElement.nodeType === 1) {
                        const $cell = $(cellElement);
                        if (!$cell.attr('data-cell-active')) {
                          if (!$cell.attr('data-custom-border')) {
                            $cell.css({
                              'border': '1px dashed #d5d8dc',
                              'border-width': '1px',
                              'border-style': 'dashed',
                              'border-color': '#d5d8dc'
                            });
                          }
                          $cell.attr('data-cell-active', 'true');
                          selectedCells.push($cell[0]);
                        }
                      }
                    }
                  }
                }
              }

              // 显示选择边框
              if (selectedCells.length > 0) {
                showSelectionBorder(selectedCells);
              }

              return selectedCells;
            }

            // 保持选中状态
            let isSelected = false;
            let selectedRange = null;
            let multiSelectRanges = []; // 存储多选的区域

            // 鼠标按下时开始选择 拖动形成单元格选区 步骤1
            newComponent.find('td, th').on('mousedown', function (e) {
              // 如果单元格处于编辑状态，允许默认的文本选择行为
              const $contentDiv = $(this).find('.cell-content');
              if (($contentDiv.length && $contentDiv.attr('contenteditable') === 'true') ||
                $(this).attr('contenteditable') === 'true') {
                // 在编辑模式下，不阻止默认行为，允许文本选择
                return;
              }

              // 检查是否点击在拖拽手柄上
              if ($(e.target).hasClass('column-resize-handle') ||
                $(e.target).hasClass('left-resize-handle') ||
                $(e.target).hasClass('row-resize-handle') ||
                $(e.target).hasClass('resize-handle-corner')) {
                return;
              }

              e.preventDefault();
              isSelecting = true;
              startCell = this;

              // 检查是否按住了Ctrl(Windows/Linux)或Command(macOS)键
              const isMultiSelect = e.ctrlKey || e.metaKey;

              if (!isMultiSelect) {
                // 单选模式：清除之前的选择
                clearSelection(newComponent);
                multiSelectRanges = [];
              } else {
                // 多选模式：检查当前单元格是否已经在选中区域内
                const clickedCellInSelection = $(this).attr('data-cell-active') === 'true';
                if (clickedCellInSelection) {
                  // 如果点击的是已选中的单元格，不清除选择，允许继续拖拽
                  return;
                }
              }

              const indices = getCellIndex(this);
              startRowIndex = indices.row;
              startColIndex = indices.col;
              isSelected = true;
            });

            // 生成唯一的组件ID用于事件命名空间
            const componentId = 'newComponent_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

            // 鼠标移动时更新选择范围 - 绑定到document以避免合并单元格的事件冲突 步骤2
            // console.log('准备绑定 mousemove 事件，componentId:', componentId);
            $(document).off('mousemove.' + componentId).on('mousemove.' + componentId, function (e) {
              // console.log('mousemove event triggered, isSelecting:', isSelecting, 'startCell:', startCell); // 添加这行调试
              if (isSelecting && startCell) {
                // 使用elementFromPoint获取鼠标位置下的实际元素
                const elementUnderMouse = document.elementFromPoint(e.clientX, e.clientY);
                const $cellUnderMouse = $(elementUnderMouse).closest('td, th');
                
                // 确保鼠标下的单元格属于当前表格组件
                if ($cellUnderMouse.length && $cellUnderMouse.closest(newComponent).length) {
                  const endCell = $cellUnderMouse[0];
                  const isMultiSelect = e.ctrlKey || e.metaKey;
                  
                  if (isMultiSelect) {
                    // 多选模式：重新渲染所有已保存的区域，然后添加当前拖拽区域
                    clearSelection(newComponent);
                    // 重新渲染之前保存的所有区域
                    multiSelectRanges.forEach(range => {
                      selectCellsInRangeForNewComponent(range.startCell, range.endCell, true);
                    });
                    // 添加当前拖拽的区域
                    selectCellsInRangeForNewComponent(startCell, endCell, true);
                  } else {
                    // 单选模式：只显示当前拖拽的区域
                    selectCellsInRangeForNewComponent(startCell, endCell, false);
                  }
                }
              }
            });

            // 构建表格的逻辑网格，考虑合并单元格
            function buildLogicalGrid(component) {
              const $table = component.find('table').first();
              const $rows = $table.find('tr');
              const grid = [];
              
              // 计算最大列数
              let maxCols = 0;
              $rows.each(function() {
                const $row = $(this);
                let colCount = 0;
                $row.find('td, th').each(function() {
                  const colspan = parseInt($(this).attr('colspan')) || 1;
                  colCount += colspan;
                });
                maxCols = Math.max(maxCols, colCount);
              });
              
              // 初始化网格 - 创建二维数组
              for (let i = 0; i < $rows.length; i++) {
                grid[i] = new Array(maxCols).fill(null);
              }
              
              // 按行处理，正确分配单元格到逻辑位置
              $rows.each(function(rowIndex) {
                const $row = $(this);
                const cells = $row.find('td, th').toArray();
                let logicalColIndex = 0;
                
                cells.forEach((cell) => {
                  const $cell = $(cell);
                  const colspan = parseInt($cell.attr('colspan')) || 1;
                  const rowspan = parseInt($cell.attr('rowspan')) || 1;
                  const isHidden = $cell.css('display') === 'none' || $cell.attr('data-merged');
                  
                  // 跳过已被上方rowspan占用的位置
                  while (logicalColIndex < maxCols && grid[rowIndex][logicalColIndex] !== null) {
                    logicalColIndex++;
                  }
                  
                  // 如果是隐藏的合并单元格，不占用逻辑位置
                  if (isHidden) {
                    return;
                  }
                  
                  // 在逻辑网格中标记该单元格占用的所有位置
                  for (let r = 0; r < rowspan && (rowIndex + r) < grid.length; r++) {
                    for (let c = 0; c < colspan && (logicalColIndex + c) < maxCols; c++) {
                      if (r === 0 && c === 0) {
                        // 主位置存储实际单元格
                        grid[rowIndex + r][logicalColIndex + c] = cell;
                      } else {
                        // 其他位置标记为被占用
                        grid[rowIndex + r][logicalColIndex + c] = 'occupied';
                      }
                    }
                  }
                  
                  logicalColIndex += colspan;
                });
              });
              
              // 将'occupied'标记替换为对应的隐藏td元素引用
              for (let row = 0; row < grid.length; row++) {
                for (let col = 0; col < grid[row].length; col++) {
                  if (grid[row][col] === 'occupied') {
                    // 查找对应位置的隐藏td元素
                    const $tableRow = $table.find('tr').eq(row);
                    let hiddenCell = null;
                    
                    // 在当前行中查找隐藏的合并单元格
                    $tableRow.find('td, th').each(function() {
                      const $cell = $(this);
                      if ($cell.attr('data-merged') || $cell.css('display') === 'none') {
                        // 计算这个隐藏单元格的逻辑位置
                        let cellLogicalCol = 0;
                        $cell.prevAll('td, th').each(function() {
                          const $prevCell = $(this);
                          const prevColspan = parseInt($prevCell.attr('colspan')) || 1;
                          cellLogicalCol += prevColspan;
                        });
                        
                        // 如果这个隐藏单元格的逻辑位置匹配当前网格位置
                        if (cellLogicalCol === col) {
                          hiddenCell = this;
                          return false; // 找到了，退出循环
                        }
                      }
                    });
                    
                    // 如果找到了对应的隐藏单元格，使用它；否则查找主单元格
                    if (hiddenCell) {
                      grid[row][col] = hiddenCell;
                    } else {
                      // 向上和向左查找对应的主单元格
                      let foundCell = null;
                      for (let r = row; r >= 0 && !foundCell; r--) {
                        for (let c = col; c >= 0 && !foundCell; c--) {
                          if (grid[r][c] && grid[r][c] !== 'occupied') {
                            const $mainCell = $(grid[r][c]);
                            const mainRowspan = parseInt($mainCell.attr('rowspan')) || 1;
                            const mainColspan = parseInt($mainCell.attr('colspan')) || 1;
                            
                            // 检查当前位置是否在主单元格的范围内
                            if (r + mainRowspan > row && c + mainColspan > col) {
                              foundCell = grid[r][c];
                            }
                          }
                        }
                      }
                      grid[row][col] = foundCell;
                    }
                  }
                }
              }
              
              return grid;
            }
            
            // 获取单元格在逻辑网格中的位置
            function getCellLogicalPosition(cell, logicalGrid) {
              for (let row = 0; row < logicalGrid.length; row++) {
                if (!logicalGrid[row]) continue;
                for (let col = 0; col < logicalGrid[row].length; col++) {
                  if (logicalGrid[row][col] === cell) {
                    return { row: row, col: col };
                  }
                }
              }
              return null;
            }

            // 处理合并单元格的选择逻辑（根据文档要求）
            function applyMergedCellSelectionLogic() {
              // 获取所有当前选中的单元格
              const $activeCells = newComponent.find('td[data-cell-active="true"], th[data-cell-active="true"]');
              if ($activeCells.length === 0) return;

              // 构建逻辑网格
              const logicalGrid = buildLogicalGrid(newComponent);
              const additionalCells = new Set();
              
              // 收集所有选中单元格的位置和属性
              const selectedCellsInfo = [];
              $activeCells.each(function() {
                const $cell = $(this);
                const position = getCellLogicalPosition(this, logicalGrid);
                if (position) {
                  selectedCellsInfo.push({
                    cell: this,
                    position: position,
                    rowspan: parseInt($cell.attr('rowspan')) || 1,
                    colspan: parseInt($cell.attr('colspan')) || 1
                  });
                }
              });
              
              // 找出所有有rowspan>1的单元格（纵向合并单元格）
              const mergedCells = selectedCellsInfo.filter(info => info.rowspan > 1);
              
              if (mergedCells.length > 0) {
                // 计算所有选中单元格覆盖的列范围
                const allSelectedCols = new Set();
                selectedCellsInfo.forEach(info => {
                  for (let c = info.position.col; c < info.position.col + info.colspan; c++) {
                    allSelectedCols.add(c);
                  }
                });
                
                // 计算所有纵向合并单元格影响的行范围
                const allAffectedRows = new Set();
                mergedCells.forEach(info => {
                  for (let r = info.position.row; r < info.position.row + info.rowspan; r++) {
                    allAffectedRows.add(r);
                  }
                });
                
                // 根据文档逻辑：纵向合并单元格影响的行 × 所有选中的列 = 需要激活的单元格
                allAffectedRows.forEach(row => {
                  allSelectedCols.forEach(col => {
                    if (logicalGrid[row] && logicalGrid[row][col]) {
                      additionalCells.add(logicalGrid[row][col]);
                    }
                  });
                });
              }

              // 标记额外需要选中的单元格
              additionalCells.forEach(cell => {
                const $cell = $(cell);
                if (!$cell.attr('data-custom-border')) {
                  $cell.css({
                    'border': '1px dashed #d5d8dc',
                    'border-width': '1px',
                    'border-style': 'dashed',
                    'border-color': '#d5d8dc'
                  });
                }
                $cell.attr('data-cell-active', 'true');
              });

              // 更新选择边框
              const allSelectedCells = newComponent.find('td[data-cell-active="true"], th[data-cell-active="true"]');
              if (allSelectedCells.length > 0) {
                showSelectionBorder(allSelectedCells);
              }
            }

            // 鼠标松开时结束选择 步骤3
            $(document).off('mouseup.' + componentId).on('mouseup.' + componentId, function (e) {
              if (isSelecting && startCell) {
                const isMultiSelect = e.ctrlKey || e.metaKey;
                if (isMultiSelect) {
                  // 多选模式：保存当前选择区域
                  const elementUnderMouse = document.elementFromPoint(e.clientX, e.clientY);
                  const $cellUnderMouse = $(elementUnderMouse).closest('td, th');
                  if ($cellUnderMouse.length && $cellUnderMouse.closest(newComponent).length) {
                    const currentRange = {
                      startCell: startCell,
                      endCell: $cellUnderMouse[0]
                    };
                    multiSelectRanges.push(currentRange);
                    // 重新渲染所有选择区域
                    renderAllSelections();
                  }
                } else {
                  // 单选模式：清除多选区域
                  multiSelectRanges = [];
                }
                
                // 应用合并单元格的选择逻辑
                applyMergedCellSelectionLogic();
                
                if (selectedCells.length > 0) {
                  selectedRange = {
                    startCell: startCell,
                    endCell: selectedCells[selectedCells.length - 1]
                  };
                }
              }
              isSelecting = false;
              startCell = null;
              // 清理事件监听器
              // $(document).off('mousemove.' + componentId);
            });

            // 将事件绑定到组件级别
            newComponent.on('click', function (e) {
              const $target = $(e.target);
              // 只有当点击的不是表格内容且不是在多选模式下时才清空选区
              if (!$target.closest('.ef-table').length && !(e.ctrlKey || e.metaKey)) {
                clearSelection(newComponent);
                isSelected = false;
                selectedRange = null;
                multiSelectRanges = [];
              }
            });

            // 单独处理单元格点击事件
            newComponent.find('td, th').on('click', function (e) {
              const isMultiSelect = e.ctrlKey || e.metaKey;
              if (!isMultiSelect && !isSelecting) {
                // 单选模式且不在拖拽状态：选中当前单元格
                clearSelection(newComponent);
                multiSelectRanges = [];

                const $currentCell = $(this);
                $currentCell.attr('data-cell-active', 'true');

                // 如果是合并单元格，还需要标记被合并隐藏的单元格
                const colspan = parseInt($currentCell.attr('colspan')) || 1;
                const rowspan = parseInt($currentCell.attr('rowspan')) || 1;

                if (colspan > 1 || rowspan > 1) {
                  const $currentRow = $currentCell.parent();
                  const currentRowIndex = $currentRow.index();
                  const $table = $currentCell.closest('table');

                  // 计算当前单元格的逻辑列起始位置
                  let currentLogicalCol = 0;
                  $currentRow.find('td, th').each(function () {
                    if (this === $currentCell[0]) {
                      return false; // 找到当前单元格，停止计算
                    }
                    const cellColspan = parseInt($(this).attr('colspan')) || 1;
                    currentLogicalCol += cellColspan;
                  });

                  // 遍历合并单元格覆盖的所有行
                  for (let r = 0; r < rowspan; r++) {
                    const $targetRow = $table.find('tr').eq(currentRowIndex + r);
                    if ($targetRow.length) {
                      let logicalColIndex = 0;
                      $targetRow.find('td, th').each(function () {
                        const $cell = $(this);
                        const cellColspan = parseInt($cell.attr('colspan')) || 1;
                        const cellRowspan = parseInt($cell.attr('rowspan')) || 1;

                        // 检查当前单元格的逻辑列范围是否与合并单元格重叠
                        const cellStartCol = logicalColIndex;
                        const cellEndCol = logicalColIndex + cellColspan - 1;
                        const mergedStartCol = currentLogicalCol;
                        const mergedEndCol = currentLogicalCol + colspan - 1;

                        // 如果单元格在合并区域内
                        if (cellStartCol <= mergedEndCol && cellEndCol >= mergedStartCol) {
                          // 如果是隐藏的合并单元格或者在合并区域内的单元格，标记为活动状态
                          if ($cell.attr('data-merged') || $cell.css('display') === 'none' ||
                            (cellStartCol >= mergedStartCol && cellEndCol <= mergedEndCol && r > 0)) {
                            $cell.attr('data-cell-active', 'true');
                          }
                        }

                        logicalColIndex += cellColspan;
                      });
                    }
                  }
                }

                showSelectionBorder($currentCell);

                // 触发单元格选择变化事件 - Feature 5
                $(document).trigger('cell-selection-changed');
              }
            });

            // 双击进入编辑模式
            newComponent.find('th, td').on('dblclick', function () {
              clearSelection(newComponent); // 清除已有的选中效果
              const $contentDiv = $(this).find('.cell-content');
              if ($contentDiv.length) {
                $contentDiv.attr('contenteditable', 'true')
                  .css('cursor', 'text')
                  .focus();
              } else {
                $(this).attr('contenteditable', 'true')
                  .css('cursor', 'text')
                  .focus();
              }
            });

            // 失去焦点时退出编辑模式
            newComponent.find('th, td').on('blur', function () {
              const $contentDiv = $(this).find('.cell-content');
              if ($contentDiv.length) {
                $contentDiv.attr('contenteditable', 'false')
                  .css('cursor', 'default')
                  .css('min-height', ''); // 清除min-height样式
              } else {
                $(this).attr('contenteditable', 'false')
                  .css('cursor', 'default');
              }
            });

            // 添加函数来重新渲染所有多选区域
            function renderAllSelections () {
              clearSelection(newComponent);
              multiSelectRanges.forEach(range => {
                if (range.startCell && range.endCell) {
                  selectCellsInRangeForNewComponent(range.startCell, range.endCell, true);
                }
              });
            }

            // 禁用单元格的默认编辑功能
            newComponent.find('th, td').attr({
              'contenteditable': 'false',
              'key-press': 'enter',
              'key-event': 'dblclick',
              'key-scope': '.ef-table-component'
            }).css('cursor', 'default');

            // 双击进入编辑模式
            newComponent.find('th, td').on('dblclick', function () {
              clearSelection(newComponent); // 清除已有的选中效果
              $(this).attr('contenteditable', 'true')
                .css('cursor', 'text')
                .focus();
            });

            // 失去焦点时退出编辑模式
            newComponent.find('th, td').on('blur', function () {
              $(this).attr('contenteditable', 'false')
                .css('cursor', 'default');
            });

            // 鼠标按下开始选择
            newComponent.find('th, td').on('mousedown', function (e) {
              // 检查是否有任何单元格处于编辑状态
              const hasEditingCell = newComponent.find('td[contenteditable="true"], th[contenteditable="true"]').length > 0;

              if ($(this).attr('contenteditable') !== 'true' && !hasEditingCell) {
                isSelecting = true;
                startCell = this;
                clearSelection(newComponent);
                selectedCells = [this];
                $(this).attr('data-cell-active', 'true');
                showSelectionBorder($(this));
                e.preventDefault();
              }
              // 如果当前单元格处于编辑状态，不阻止默认行为，允许光标定位
            });

            // 鼠标移动时选择单元格
            newComponent.find('th, td').on('mouseover', function () {
              // 检查是否有任何单元格处于编辑状态
              const hasEditingCell = newComponent.find('td[contenteditable="true"], th[contenteditable="true"]').length > 0;

              if (isSelecting && $(this).attr('contenteditable') !== 'true' && !hasEditingCell) {
                selectCellsInRangeForNewComponent(startCell, this);
              }
            });

            newComponent.hotkeyManager();

            // 鼠标松开结束选择
            $(document).on('mouseup', function () {
              isSelecting = false;
            });

            // 隐藏模态窗口
            $('#tableModal').hide();

            // 设置section-content的背景色为白色
            dropTarget.css('background-color', 'white');
          });

          // 处理取消按钮点击事件
          $('#cancel-table-btn').off('click').on('click', function () {
            $('#tableModal').hide();
          });
        } else {
          // 非表格组件的处理逻辑
          const uniqueId = generateUniqueId();
          const html = templateConfig.template.replace(/{uniqueId}/g, uniqueId);
          const newComponent = $(html);

          // 检查目标区域是否已包含相同ID的组件，避免重复添加
          const componentId = `ef-${componentType}-comp-${uniqueId}`;
          if (droppableArea.find(`#${componentId}`).length === 0) {
            // 将生成的组件添加到目标位置
            droppableArea.append(newComponent);
          }

          // 使新添加的组件可以拖动
          makeComponentDraggable(newComponent);

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
      }
    });

    // 应用CSS规则来覆盖jQuery UI自动添加的样式
    if ($('#ui-draggable-override-style').length === 0) {
      $('<style id="ui-draggable-override-style">')
        .prop('type', 'text/css')
        .html('.ui-draggable:not(.being-dragged) { position: static !important; }')
        .appendTo('head');
    }
  });
})