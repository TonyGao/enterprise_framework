$(document).ready(function() {
    // 标尺配置
    const RULER_SIZE = 20; // 标尺宽度/高度
    const RULER_UNIT = 10; // 每个刻度单位（像素）
    const MAJOR_TICK_INTERVAL = 50; // 主刻度间隔
    const MINOR_TICK_INTERVAL = 10; // 次刻度间隔
    
    // 初始化标尺
    function initRulers() {
        // 设置标尺容器样式
        $('.ruler-container').css({
            'position': 'relative',
            'display': 'grid',
            'grid-template-columns': `${RULER_SIZE}px 1fr`,
            'grid-template-rows': `${RULER_SIZE}px 1fr`,
            'width': '100%',
            'height': '100%'
        });
        
        // 设置左上角区域
        $('.ruler-corner').css({
            'grid-column': '1',
            'grid-row': '1',
            'background-color': '#f0f0f0',
            'border-right': '1px solid #ccc',
            'border-bottom': '1px solid #ccc',
            'z-index': '1000'
        });
        
        // 设置水平标尺
        $('.ruler-horizontal').css({
            'grid-column': '2',
            'grid-row': '1',
            'background-color': '#f8f8f8',
            'border-bottom': '1px solid #ccc',
            'position': 'relative',
            'overflow': 'hidden',
            'cursor': 'crosshair',
            'z-index': '999'
        });
        
        // 设置垂直标尺
        $('.ruler-vertical').css({
            'grid-column': '1',
            'grid-row': '2',
            'background-color': '#f8f8f8',
            'border-right': '1px solid #ccc',
            'position': 'relative',
            'overflow': 'hidden',
            'cursor': 'crosshair',
            'z-index': '999'
        });
        
        // 设置canvas包装器
        $('.canvas-wrapper').css({
            'grid-column': '2',
            'grid-row': '2',
            'position': 'relative',
            'overflow': 'hidden'
        });
        
        // 设置辅助线容器
        $('#guide-lines').css({
            'position': 'absolute',
            'top': '0',
            'left': '0',
            'right': '0',
            'bottom': '0',
            'pointer-events': 'none',
            'z-index': '500'
        });
        
        // 绘制标尺刻度
        drawRulerTicks();
        
        // 绑定标尺事件
        bindRulerEvents();
    }
    
    // 绘制标尺刻度
    function drawRulerTicks() {
        const $horizontalRuler = $('#ruler-horizontal');
        const $verticalRuler = $('#ruler-vertical');
        
        // 清空现有刻度
        $horizontalRuler.empty();
        $verticalRuler.empty();
        
        // 获取canvas尺寸
        const canvasWidth = $('.canvas-wrapper').width() || 1200;
        const canvasHeight = $('.canvas-wrapper').height() || 800;
        
        // 绘制水平标尺刻度
        for (let i = 0; i <= canvasWidth; i += MINOR_TICK_INTERVAL) {
            const isMajor = i % MAJOR_TICK_INTERVAL === 0;
            const tickHeight = isMajor ? 12 : 6;
            const tickTop = RULER_SIZE - tickHeight;
            
            const $tick = $('<div>').css({
                'position': 'absolute',
                'left': `${i}px`,
                'top': `${tickTop}px`,
                'width': '1px',
                'height': `${tickHeight}px`,
                'background-color': '#666',
                'font-size': '10px'
            });
            
            // 数字标签已移除，只保留刻度线
            
            $horizontalRuler.append($tick);
        }
        
        // 绘制垂直标尺刻度
        for (let i = 0; i <= canvasHeight; i += MINOR_TICK_INTERVAL) {
            const isMajor = i % MAJOR_TICK_INTERVAL === 0;
            const tickWidth = isMajor ? 12 : 6;
            const tickLeft = RULER_SIZE - tickWidth;
            
            const $tick = $('<div>').css({
                'position': 'absolute',
                'left': `${tickLeft}px`,
                'top': `${i}px`,
                'width': `${tickWidth}px`,
                'height': '1px',
                'background-color': '#666'
            });
            
            // 数字标签已移除，只保留刻度线
            
            $verticalRuler.append($tick);
        }
    }
    
    // 绑定标尺事件
    function bindRulerEvents() {
        let isDragging = false;
        let dragType = null; // 'horizontal' 或 'vertical'
        let dragStartPos = null;
        
        // 水平标尺鼠标事件
        $('#ruler-horizontal').on('mousedown', function(e) {
            isDragging = true;
            dragType = 'horizontal';
            dragStartPos = e.clientX;
            
            // 创建临时辅助线
            createTempGuideLine('vertical', e.clientX - $(this).offset().left);
            
            e.preventDefault();
        });
        
        // 垂直标尺鼠标事件
        $('#ruler-vertical').on('mousedown', function(e) {
            isDragging = true;
            dragType = 'vertical';
            dragStartPos = e.clientY;
            
            // 创建临时辅助线
            createTempGuideLine('horizontal', e.clientY - $(this).offset().top);
            
            e.preventDefault();
        });
        
        // 全局鼠标移动事件
        $(document).on('mousemove', function(e) {
            if (!isDragging) return;
            
            const $tempLine = $('.temp-guide-line');
            if ($tempLine.length === 0) return;
            
            if (dragType === 'horizontal') {
                // 更新垂直辅助线位置
                const rulerOffset = $('#ruler-horizontal').offset();
                const newX = e.clientX - rulerOffset.left;
                $tempLine.css('left', `${newX}px`);
            } else if (dragType === 'vertical') {
                // 更新水平辅助线位置
                const rulerOffset = $('#ruler-vertical').offset();
                const newY = e.clientY - rulerOffset.top;
                $tempLine.css('top', `${newY}px`);
            }
        });
        
        // 全局鼠标释放事件
        $(document).on('mouseup', function(e) {
            if (!isDragging) return;
            
            const $tempLine = $('.temp-guide-line');
            if ($tempLine.length > 0) {
                // 将临时辅助线转换为永久辅助线
                $tempLine.removeClass('temp-guide-line').addClass('guide-line');
                
                // 添加删除功能
                $tempLine.on('dblclick', function() {
                    $(this).remove();
                });
                
                // 添加拖拽功能
                makeGuideLineDraggable($tempLine);
                
                // 添加距离测量功能
                addDistanceMeasurement($tempLine);
                
                // 添加提示
                $tempLine.attr('title', '双击删除辅助线，拖拽可移动位置，按住Ctrl/Cmd点击可测量距离');
            }
            
            isDragging = false;
            dragType = null;
            dragStartPos = null;
        });
    }
    
    // 创建临时辅助线
    function createTempGuideLine(type, position) {
        const $guideLine = $('<div>').addClass('temp-guide-line').css({
            'position': 'absolute',
            'background-color': '#007bff',
            'pointer-events': 'auto',
            'z-index': '600',
            'opacity': '0.8'
        });
        
        if (type === 'vertical') {
            // 垂直辅助线（从水平标尺拖出）
            $guideLine.css({
                'left': `${position}px`,
                'top': '0',
                'width': '1px',
                'height': '100%',
                'cursor': 'ew-resize'
            });
        } else if (type === 'horizontal') {
            // 水平辅助线（从垂直标尺拖出）
            $guideLine.css({
                'left': '0',
                'top': `${position}px`,
                'width': '100%',
                'height': '1px',
                'cursor': 'ns-resize'
            });
        }
        
        $('#guide-lines').append($guideLine);
    }
    
    // 使辅助线可拖拽
    function makeGuideLineDraggable($guideLine) {
        let isGuideLineDragging = false;
        let guideLineStartPos = null;
        let guideLineType = null;
        
        $guideLine.on('mousedown', function(e) {
            // 防止与双击删除冲突
            if (e.detail === 2) return;
            
            isGuideLineDragging = true;
            const $line = $(this);
            
            // 判断辅助线类型
            if ($line.css('width') === '1px') {
                guideLineType = 'vertical';
                guideLineStartPos = e.clientX;
            } else {
                guideLineType = 'horizontal';
                guideLineStartPos = e.clientY;
            }
            
            e.preventDefault();
            e.stopPropagation();
        });
        
        $(document).on('mousemove.guideline', function(e) {
            if (!isGuideLineDragging) return;
            
            if (guideLineType === 'vertical') {
                const canvasOffset = $('#canvas-container').offset();
                if (canvasOffset) {
                    const newX = e.clientX - canvasOffset.left;
                    $guideLine.css('left', `${newX}px`);
                }
            } else if (guideLineType === 'horizontal') {
                const canvasOffset = $('#canvas-container').offset();
                if (canvasOffset) {
                    const newY = e.clientY - canvasOffset.top;
                    $guideLine.css('top', `${newY}px`);
                }
            }
        });
        
        $(document).on('mouseup.guideline', function(e) {
            if (isGuideLineDragging) {
                isGuideLineDragging = false;
                guideLineType = null;
                guideLineStartPos = null;
            }
        });
    }
     
     // 添加距离测量功能
     let selectedGuideLine = null;
     let distanceDisplay = null;
     
     function addDistanceMeasurement($guideLine) {
         $guideLine.on('click', function(e) {
             // 只在按住Ctrl或Command键时处理
             if (!(e.ctrlKey || e.metaKey)) return;
             
             const $clickedLine = $(this);
             const clickedType = $clickedLine.css('width') === '1px' ? 'vertical' : 'horizontal';
             
             if (!selectedGuideLine) {
                 // 第一次点击，选择辅助线
                 selectedGuideLine = {
                     element: $clickedLine,
                     type: clickedType,
                     position: clickedType === 'vertical' ? 
                         parseInt($clickedLine.css('left')) : 
                         parseInt($clickedLine.css('top'))
                 };
                 
                 // 高亮显示选中的辅助线
                 $clickedLine.css({
                     'background-color': '#ff6b6b',
                     'box-shadow': '0 0 5px rgba(255, 107, 107, 0.5)'
                 });
                 
                 e.preventDefault();
                 e.stopPropagation();
             } else {
                 // 第二次点击，计算距离
                 if (selectedGuideLine.type === clickedType && selectedGuideLine.element[0] !== $clickedLine[0]) {
                     const currentPosition = clickedType === 'vertical' ? 
                         parseInt($clickedLine.css('left')) : 
                         parseInt($clickedLine.css('top'));
                     
                     const distance = Math.abs(currentPosition - selectedGuideLine.position);
                     
                     // 高亮显示第二条辅助线
                     $clickedLine.css({
                         'background-color': '#ff6b6b',
                         'box-shadow': '0 0 5px rgba(255, 107, 107, 0.5)'
                     });
                     
                     // 显示距离
                     showDistance(selectedGuideLine, {
                         element: $clickedLine,
                         type: clickedType,
                         position: currentPosition
                     }, distance);
                     
                     // 延迟重置选择状态，让两条线都保持红色一段时间
                     setTimeout(() => {
                         resetGuideLineSelection();
                         $clickedLine.css({
                             'background-color': '#007bff',
                             'box-shadow': 'none'
                         });
                     }, 3000);
                 } else {
                     // 重置选择状态
                     resetGuideLineSelection();
                 }
                 e.preventDefault();
                 e.stopPropagation();
             }
         });
     }
     
     function showDistance(line1, line2, distance) {
         // 移除之前的距离显示
         if (distanceDisplay) {
             distanceDisplay.remove();
         }
         
         // 创建距离显示容器
         distanceDisplay = $('<div>').css({
             'position': 'absolute',
             'z-index': '700',
             'pointer-events': 'none'
         });
         
         // 创建距离文本元素
         const distanceText = $('<div>').css({
             'background-color': 'rgba(0, 0, 0, 0.9)',
             'color': 'white',
             'padding': '8px 12px',
             'border-radius': '6px',
             'font-size': '16px',
             'font-weight': 'bold',
             'font-family': 'monospace',
             'white-space': 'nowrap',
             'text-align': 'center',
             'box-shadow': '0 2px 8px rgba(0, 0, 0, 0.3)'
         }).text(`${distance}px`);
         
         // 创建箭头元素
         const createArrow = (direction) => {
             const arrow = $('<div>').css({
                 'position': 'absolute',
                 'width': '0',
                 'height': '0',
                 'border-style': 'solid'
             });
             
             if (direction === 'left') {
                 arrow.css({
                     'border-width': '6px 10px 6px 0',
                     'border-color': 'transparent #ff6b6b transparent transparent',
                     'left': '-10px',
                     'top': '50%',
                     'transform': 'translateY(-50%)'
                 });
             } else if (direction === 'right') {
                 arrow.css({
                     'border-width': '6px 0 6px 10px',
                     'border-color': 'transparent transparent transparent #ff6b6b',
                     'right': '-10px',
                     'top': '50%',
                     'transform': 'translateY(-50%)'
                 });
             } else if (direction === 'up') {
                 arrow.css({
                     'border-width': '0 6px 10px 6px',
                     'border-color': 'transparent transparent #ff6b6b transparent',
                     'top': '-10px',
                     'left': '50%',
                     'transform': 'translateX(-50%)'
                 });
             } else if (direction === 'down') {
                 arrow.css({
                     'border-width': '10px 6px 0 6px',
                     'border-color': '#ff6b6b transparent transparent transparent',
                     'bottom': '-10px',
                     'left': '50%',
                     'transform': 'translateX(-50%)'
                 });
             }
             
             return arrow;
         };
         
         // 添加文本和箭头到容器
         distanceDisplay.append(distanceText);
         
         // 计算显示位置和箭头方向
         let displayX, displayY;
         if (line1.type === 'vertical') {
             // 垂直线：距离显示在两线中间的水平位置，箭头指向左右
             displayX = (line1.position + line2.position) / 2;
             displayY = 100; // 距离顶部一定距离
             
             // 添加指向两条垂直线的左右箭头
             const leftArrow = createArrow('left');
             const rightArrow = createArrow('right');
             distanceText.css('position', 'relative').append(leftArrow, rightArrow);
         } else {
             // 水平线：距离显示在两线中间的垂直位置，箭头指向上下
             displayX = 100; // 距离左侧一定距离
             displayY = (line1.position + line2.position) / 2;
             
             // 添加指向两条水平线的上下箭头
             const upArrow = createArrow('up');
             const downArrow = createArrow('down');
             distanceText.css('position', 'relative').append(upArrow, downArrow);
         }
         
         distanceDisplay.css({
             'left': `${displayX}px`,
             'top': `${displayY}px`
         });
         
         $('#guide-lines').append(distanceDisplay);
         
         // 3秒后自动隐藏
         setTimeout(() => {
             if (distanceDisplay) {
                 distanceDisplay.fadeOut(300, function() {
                     $(this).remove();
                     distanceDisplay = null;
                 });
             }
         }, 3000);
     }
     
     function resetGuideLineSelection() {
         if (selectedGuideLine) {
             // 恢复辅助线原始样式
             selectedGuideLine.element.css({
                 'background-color': '#007bff',
                 'box-shadow': 'none'
             });
             selectedGuideLine = null;
         }
     }
     
     // 点击其他地方时重置选择
     $(document).on('click', function(e) {
         if (!$(e.target).hasClass('guide-line') && !$(e.target).hasClass('temp-guide-line')) {
             resetGuideLineSelection();
         }
     });
     
     // 窗口大小改变时重新绘制标尺
    $(window).on('resize', function() {
        setTimeout(drawRulerTicks, 100);
    });
    
    // 清除所有辅助线的功能
    function clearAllGuideLines() {
        $('.guide-line, .temp-guide-line').remove();
    }
    
    // 暴露清除功能到全局
    window.clearAllGuideLines = clearAllGuideLines;
    
    // 初始化
    initRulers();
    
    // 添加键盘快捷键支持
    $(document).on('keydown', function(e) {
        // Ctrl+Shift+G 清除所有辅助线
        if (e.ctrlKey && e.shiftKey && e.key === 'G') {
            clearAllGuideLines();
            e.preventDefault();
        }
    });
    
    console.log('标尺功能已初始化');
    console.log('使用说明：');
    console.log('- 从顶部标尺拖拽可创建垂直辅助线');
    console.log('- 从左侧标尺拖拽可创建水平辅助线');
    console.log('- 双击辅助线可删除');
    console.log('- 按 Ctrl+Shift+G 清除所有辅助线');
});