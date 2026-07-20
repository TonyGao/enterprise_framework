/**
 * Section属性面板的JavaScript功能
 */
$(document).ready(function() {
    // 页面加载时应用保存的 sectionConfig
    if (window.__SECTION_CONFIG__) {
        const config = window.__SECTION_CONFIG__;
        const $sectionContent = $('.section.active .section-content');
        if ($sectionContent.length) {
            if (config.contentWidth === 'full-width') {
                $sectionContent.css('width', '100%');
            } else if (config.width && config.unit) {
                $sectionContent.css('width', config.width + config.unit);
            }
        }
        // 同步 UI 控件
        if (config.contentWidth) {
            $('#content-width').val(config.contentWidth);
        }
        if (config.width) {
            const unit = config.unit || 'px';
            $('#width-value').closest('.input-with-unit').find('.unit-selector span').text(unit);
            $('#width-slider').val(config.width);
            $('#width-value').val(config.width);
        }
    }
    // 页面加载时同步已保存的控件样式到预览
    $('.editor-field-row').each(function() {
        const $row = $(this);
        const $label = $row.find('.ef-form-label');
        const $widget = $row.find('.ef-form-widget').children().first();
        // 圆角
        const rounded = $label.attr('data-rounded');
        if (rounded === 'true') {
            if ($widget.is('.ef-input-wrapper, .ef-select-view-single')) {
                $widget.addClass('ef-input-rounded');
            } else if ($widget.is('.ef-textarea-wrapper')) {
                $widget.addClass('ef-textarea-rounded');
            }
        }
        // 底色：编辑器无值概念，始终优先未填底色，降级到常规底色
        const regularBg = $label.attr('data-regular-bg') || '';
        const requiredBg = $label.attr('data-required-bg') || '';
        const bg = requiredBg || regularBg || '';
        if (bg) {
            $widget.css('background-color', bg);
            $widget.find('input, textarea').each(function() { this.style.setProperty('background-color', bg); });
        } else {
            $widget.css('background-color', '');
            $widget.find('input, textarea').each(function() { this.style.removeProperty('background-color'); });
        }
    });

    // 属性组折叠/展开功能
    $('.property-group-header').on('click', function() {
        const $header = $(this);
        const $content = $header.next('.property-group-content');
        
        $header.toggleClass('collapsed');
        if ($header.hasClass('collapsed')) {
            $content.slideUp(300);
        } else {
            $content.slideDown(300);
        }
    });
    
    // 滑块与输入框同步
    function syncSliderAndInput(sliderId, inputId) {
        const $slider = $('#' + sliderId);
        const $input = $('#' + inputId);
        
        $slider.on('input', function() {
            $input.val($slider.val());
            updateSectionProperty(sliderId, $slider.val());
        });
        
        $input.on('input', function() {
            $slider.val($input.val());
            updateSectionProperty(sliderId, $input.val());
        });
    }
    
    // 初始化所有滑块与输入框的同步
    syncSliderAndInput('width-slider', 'width-value');
    syncSliderAndInput('min-height-slider', 'min-height-value');
    syncSliderAndInput('columns-slider', 'columns-value');
    syncSliderAndInput('rows-slider', 'rows-value');
    syncSliderAndInput('page-width-slider', 'page-width-value');
    
    // 初始化时，确保section元素能够适应section-content的宽度
    // $('.section').each(function() {
    //     const $section = $(this);
    //     const $sectionContent = $section.find('.section-content');
    //     // 确保section-content的宽度变化能够正确反映在section元素上
    //     const contentWidth = $sectionContent.width();
    //     if (contentWidth) {
    //         $section.css('min-width', contentWidth + 'px');
    //     }
    // });
    
    // 单位选择器点击事件
    $('.unit-selector').on('click', function(e) {
        e.stopPropagation();
        const $selector = $(this);
        const $span = $selector.find('span');
        const $dropdown = $selector.find('.unit-dropdown');

        // 关闭其他打开的下拉菜单
        $('.unit-selector').not($selector).removeClass('active');
        // 切换当前下拉菜单
        $selector.toggleClass('active');
    });

    // 点击单位选项时更新单位
    $('.unit-dropdown .unit-option').on('click', function(e) {
        e.stopPropagation();
        const $option = $(this);
        const $selector = $option.closest('.unit-selector');
        const $span = $selector.find('span');
        const $widthInput = $('#width-value');
        
        // 更新单位文本
        const newUnit = $option.data('unit');
        $span.text(newUnit);
        $selector.removeClass('active');
        
        // 如果是宽度单位变更，需要处理最大值限制
        if ($selector.closest('.property-item').find('#width-slider').length > 0) {
            const $slider = $('#width-slider');
            const currentValue = parseInt($widthInput.val());
            
            if (newUnit === 'px') {
                // 获取页面最大宽度
                const pageMaxWidth = parseInt($('#page-width-value').val()) || 1200;
                
                // 如果当前值超过最大宽度，则设置为最大宽度
                if (currentValue > pageMaxWidth) {
                    $widthInput.val(pageMaxWidth);
                    $slider.val(pageMaxWidth);
                }
                
                // 更新滑块最大值
                $slider.attr('max', pageMaxWidth);
            } else if (newUnit === '%') {
                // 恢复百分比的最大值
                $slider.attr('max', 100);
                
                // 如果当前值超过100%，则设置为100%
                if (currentValue > 100) {
                    $widthInput.val(100);
                    $slider.val(100);
                }
            }
            
            // 更新section属性
            updateSectionProperty('width-slider', $widthInput.val());
        }
    });

    // 点击页面其他地方时关闭所有下拉菜单
    $(document).on('click', function() {
        $('.unit-selector').removeClass('active');
    });
    
    // 链接图标点击事件（用于同步行列间距）
    $('.link-icon').on('click', function() {
        const $icon = $(this).find('i');
        const $columnGap = $('#column-gap');
        const $rowGap = $('#row-gap');
        
        $icon.toggleClass('fa-link fa-link-slash');
        
        // 如果是链接状态，则同步两个值
        if ($icon.hasClass('fa-link')) {
            $rowGap.val($columnGap.val());
            updateSectionProperty('row-gap', $columnGap.val());
        }
    });
    
    // 对齐按钮点击事件
    $('.align-button').on('click', function() {
        const $button = $(this);
        const $parent = $button.parent();
        
        // 移除同组中其他按钮的active类
        $parent.find('.align-button').removeClass('active');
        // 为当前按钮添加active类
        $button.addClass('active');
        
        // 获取对齐值并更新section属性
        const alignValue = $button.data('value');
        const $label = $parent.prev('label');
        let propertyName = $label.text().toLowerCase().replace(/\s+/g, '-');
        
        // 特殊处理一些属性名称映射
        if (propertyName === 'justify-items') {
            propertyName = 'justify-items';
        } else if (propertyName === 'align-items') {
            propertyName = 'align-items';
        }
        
        updateSectionProperty(propertyName, alignValue);
    });
    
    // 选择框变更事件
    $('.property-select').on('change', function() {
        const $select = $(this);
        const propertyName = $select.attr('id');
        const propertyValue = $select.val();
        
        updateSectionProperty(propertyName, propertyValue);
    });
    
    // 网格轮廓开关事件
    $('#grid-outline').on('change', function() {
        const isChecked = $(this).prop('checked');
        updateSectionProperty('grid-outline', isChecked);
    });
    
    // 更新Section属性的函数
    function updateSectionProperty(property, value) {
        // 获取当前激活的section元素
        const $activeSection = $('.section.active');
        
        if ($activeSection.length === 0) {
            console.warn('没有激活的Section');
            return;
        }
        
        // 根据不同属性应用不同的样式或类
        let $sectionContent;
        switch(property) {                
            case 'content-width':
                // 设置内容宽度作为DOM属性而不是内联样式
                $sectionContent = $activeSection.find('.section-content');
                $sectionContent.attr('data-content-width', value);
                
                // 根据内容宽度类型应用相应的样式
                if (value === 'full-width') {
                    $sectionContent.css('width', '100%');
                } else if (value === 'boxed') {
                    // 使用boxed选项时应用配置宽度或默认480px
                    const boxedWidth = window.__SECTION_CONFIG__ && window.__SECTION_CONFIG__.width
                        ? window.__SECTION_CONFIG__.width + (window.__SECTION_CONFIG__.unit || 'px')
                        : '480px';
                    $sectionContent.css('width', boxedWidth);
                    // 同步滑块值
                    const unit = window.__SECTION_CONFIG__ && window.__SECTION_CONFIG__.unit || 'px';
                    const numWidth = parseInt(boxedWidth);
                    $('#width-value').closest('.input-with-unit').find('.unit-selector span').text(unit);
                    $('#width-slider').val(numWidth);
                    $('#width-value').val(numWidth);
                }
                break;
                
            case 'width-slider':
                // 设置宽度
                $sectionContent = $activeSection.find('.section-content');
                const unit = $('#width-value').closest('.input-with-unit').find('.unit-selector span').text();
                
                // 如果是px单位，确保不超过页面最大宽度
                if (unit === 'px') {
                    const pageMaxWidth = parseInt($('#page-width-value').val()) || 1200;
                    if (parseInt(value) > pageMaxWidth) {
                        value = pageMaxWidth;
                        $('#width-value').val(value);
                        $('#width-slider').val(value);
                    }
                }
                
                // 将宽度应用到section-content元素
                const widthValue = value + unit;
                $sectionContent.css('width', widthValue);
                
                // 确保section元素能够适应section-content的宽度变化
                // 使用setTimeout确保在DOM更新后获取正确的宽度
                setTimeout(function() {
                    const contentWidth = $sectionContent.outerWidth();
                    if (contentWidth) {
                        // 更新section元素的宽度，使其比section-content宽度大10px
                        $activeSection.css('width', 'auto');
                        // $activeSection.css('min-width', (contentWidth + 10) + 'px');
                        
                        // 调整canvas对齐方式
                        // 定义内部函数来调整canvas对齐
                        function adjustCanvasAlignment() {
                            const $canvas = $('.canvas');
                            const sectionContentWidth = $sectionContent.outerWidth();
                            const canvasWidth = $canvas.width();
                            
                            if (sectionContentWidth > canvasWidth) {
                                $canvas.css('align-items', 'flex-start');
                            } else {
                                $canvas.css('align-items', 'center');
                            }
                        }
                        
                        // 调用函数调整canvas对齐
                        adjustCanvasAlignment();
                    }
                }, 0);
                break;
                
            case 'min-height-slider':
                // 设置最小高度
                $activeSection.find('.section-content').css('min-height', value + 'px');
                break;
                
            case 'grid-outline':
                // 显示/隐藏网格轮廓
                if (value) {
                    $activeSection.find('.item-block').css('border', '1px dashed #d5d8dc');
                } else {
                    $activeSection.find('.item-block').css('border', 'none');
                }
                break;
                
            case 'columns-slider':
                // 设置列数
                $activeSection.find('.section-content').css('grid-template-columns', `repeat(${value}, 1fr)`);
                break;
                
            case 'rows-slider':
                // 设置行数
                $activeSection.find('.section-content').css('grid-template-rows', `repeat(${value}, 1fr)`);
                break;
                
            case 'column-gap':
                // 设置列间距
                $activeSection.find('.section-content').css('column-gap', value + 'px');
                break;
                
            case 'row-gap':
                // 设置行间距
                $activeSection.find('.section-content').css('row-gap', value + 'px');
                break;
                
            case 'auto-flow':
                // 设置自动流动方向
                $activeSection.find('.section-content').css('grid-auto-flow', value);
                break;
                
            case 'justify-items':
                // 设置水平对齐方式
                $activeSection.find('.section-content').css('justify-items', value);
                
                // 同时控制表格的margin对齐
                const $tables = $activeSection.find('.ef-table');
                $tables.each(function() {
                    const $table = $(this);
                    switch(value) {
                        case 'start':
                            $table.css({
                                'margin-left': '0',
                                'margin-right': 'auto',
                                'width': ''
                            });
                            break;
                        case 'center':
                            $table.css({
                                'margin-left': 'auto',
                                'margin-right': 'auto',
                                'width': ''
                            });
                            break;
                        case 'end':
                            $table.css({
                                'margin-left': 'auto',
                                'margin-right': '0',
                                'width': ''
                            });
                            break;
                        case 'stretch':
                            $table.css({
                                'margin-left': '0',
                                'margin-right': '0',
                                'width': '100%'
                            });
                            break;
                    }
                });
                break;
                
            case 'align-items':
                // 设置垂直对齐方式
                $activeSection.find('.section-content').css('align-items', value);
                break;
                
            case 'page-width':
                // 设置页面最大宽度
                $('#canvas').css('max-width', value + 'px');
                
                // 更新所有使用px单位的section宽度
                $('.section').each(function() {
                    const $section = $(this);
                    const $content = $section.find('.section-content');
                    const widthStyle = $content.css('width');
                    
                    // 检查是否使用px单位
                    if (widthStyle && widthStyle.endsWith('px')) {
                        const currentWidth = parseInt(widthStyle);
                        if (currentWidth > value) {
                            $content.css('width', value + 'px');
                        }
                    }
                });
                break;
        }
        
        // 保存更改到数据属性，以便后续可以序列化保存
        if (property !== 'content-width') {
            $activeSection.data(property, value);
        }
    }
    
    // 当section被激活时，更新属性面板的值
    $(document).on('click', '.section', function() {
        const $section = $(this);
        
        // 移除其他section的active类
        $('.section').removeClass('active');
        // 为当前section添加active类
        $section.addClass('active');
        
        // 显示section属性面板
        showSectionProperties($section);
    });
    
    // 显示section属性的函数
    function showSectionProperties($section) {
        // 获取section的当前属性值
        const $content = $section.find('.section-content');
        
        // 更新属性面板中的值
        // 容器布局
        let layout = 'normal';
        if ($content.css('display') === 'grid') {
            layout = 'grid';
        } else if ($content.css('display') === 'flex') {
            layout = 'flex';
        }
        $('#container-layout').val(layout);
        
        // 内容宽度 - 从DOM属性中获取
        let contentWidth = $content.attr('data-content-width') || 'custom';
        if (!$content.attr('data-content-width')) {
            // 如果没有设置属性，尝试从样式中推断
            const width = $content.css('width');
            if (width === '100%') {
                contentWidth = 'full-width';
            } else if (width === '1140px') {
                contentWidth = 'fixed-width';
            }
        }
        $('#content-width').val(contentWidth);
        
        // 宽度
        const width = $content.css('width');
        let widthValue = 100;
        let widthUnit = '%';
        
        if (width) {
            if (width.endsWith('px')) {
                widthValue = parseInt(width);
                widthUnit = 'px';
            } else if (width.endsWith('%')) {
                widthValue = parseInt(width);
                widthUnit = '%';
            }
        }
        
        // 先设置单位和最大值
        $('#width-value').closest('.input-with-unit').find('.unit-selector span').text(widthUnit);
        
        // 根据单位设置滑块最大值
        if (widthUnit === 'px') {
            const pageMaxWidth = parseInt($('#page-width-value').val()) || 1200;
            $('#width-slider').attr('max', pageMaxWidth);
        } else {
            $('#width-slider').attr('max', 100);
        }
        
        // 最后设置值
        $('#width-slider').val(widthValue);
        $('#width-value').val(widthValue);
        
        // 最小高度
        const minHeight = parseInt($content.css('min-height')) || 216;
        $('#min-height-slider').val(minHeight);
        $('#min-height-value').val(minHeight);
        
        // 网格轮廓
        const hasOutline = $content.find('.item-block').css('border') !== 'none';
        $('#grid-outline').prop('checked', hasOutline);
        
        // 列数
        const columns = $content.css('grid-template-columns')?.split(' ').length || 3;
        $('#columns-slider').val(columns);
        $('#columns-value').val(columns);
        
        // 行数
        const rows = $content.css('grid-template-rows')?.split(' ').length || 2;
        $('#rows-slider').val(rows);
        $('#rows-value').val(rows);
        
        // 间距
        const columnGap = parseInt($content.css('column-gap')) || 67;
        const rowGap = parseInt($content.css('row-gap')) || 26;
        $('#column-gap').val(columnGap);
        $('#row-gap').val(rowGap);
        
        // 自动流动
        const autoFlow = $content.css('grid-auto-flow') || 'row';
        $('#auto-flow').val(autoFlow);
        
        // 对齐方式
        const justifyItems = $content.css('justify-items') || 'start';
        $('.justify-align-controls').first().find('.align-button').removeClass('active')
            .filter(`[data-value="${justifyItems}"]`).addClass('active');
        
        // 获取真实的align-items值，不使用默认值
        const alignItems = $content.css('align-items');
        $('.justify-align-controls').last().find('.align-button').removeClass('active');
        if (alignItems && alignItems !== 'normal') {
            $('.justify-align-controls').last().find('.align-button')
                .filter(`[data-value="${alignItems}"]`).addClass('active');
        }
        
        // 表单模式检测：如果 Section 包含 .editor-field-row，隐藏 Items 属性组（Grid Controls）
        const hasFormFields = $content.find('.editor-field-row').length > 0;
        const $itemsGroup = $('.section-properties-panel .property-group').filter(function() {
            return $(this).find('.property-group-header span').text().trim() === 'Items';
        });
        if (hasFormFields) {
            $itemsGroup.hide();
        } else {
            $itemsGroup.show();
        }
    }
    
    // 页面宽度变更事件
    $('#page-width-value').on('input', function() {
        const pageWidth = $(this).val();
        updateSectionProperty('page-width', pageWidth);
        
        // 如果当前选中的section宽度单位是px，更新滑块最大值
        const $activeSection = $('.section.active');
        if ($activeSection.length > 0) {
            const widthUnit = $('#width-value').closest('.input-with-unit').find('.unit-selector span').text();
            if (widthUnit === 'px') {
                $('#width-slider').attr('max', pageWidth);
                
                // 如果当前值超过新的最大值，则更新
                const currentWidth = parseInt($('#width-value').val());
                if (currentWidth > pageWidth) {
                    $('#width-value').val(pageWidth);
                    $('#width-slider').val(pageWidth);
                    updateSectionProperty('width-slider', pageWidth);
                }
            }
        }
    });
    
    // 表格属性事件处理
    
    // 边框宽度滑块事件
    $('#table-border-width').on('input', function() {
        const value = $(this).val();
        $('#table-border-width-value').val(value);
        updateTableProperty('border-width', value + 'px');
    });
    
    $('#table-border-width-value').on('input', function() {
        const value = $(this).val();
        $('#table-border-width').val(value);
        updateTableProperty('border-width', value + 'px');
    });
    
    // 边框颜色事件
    $('#table-border-color').on('change', function() {
        const value = $(this).val();
        updateTableProperty('border-color', value);
    });
    
    // 边框样式事件
    $('#table-border-style').on('change', function() {
        const value = $(this).val();
        updateTableProperty('border-style', value);
    });
    
    // 单元格内边距滑块事件
    $('#table-cell-padding').on('input', function() {
        const value = $(this).val();
        $('#table-cell-padding-value').val(value);
        updateTableProperty('cell-padding', value + 'px');
    });
    
    $('#table-cell-padding-value').on('input', function() {
        const value = $(this).val();
        $('#table-cell-padding').val(value);
        updateTableProperty('cell-padding', value + 'px');
    });
    
    // 条纹行开关事件
    $('#table-stripe-rows').on('change', function() {
        const isChecked = $(this).prop('checked');
        updateTableProperty('stripe-rows', isChecked);
    });
    
    // 悬停效果开关事件
    $('#table-hover-effect').on('change', function() {
        const isChecked = $(this).prop('checked');
        updateTableProperty('hover-effect', isChecked);
    });
    
    // 删除组件按钮事件
    $('#delete-component-btn').on('click', function() {
        $('#delete-confirm-modal').show();
    });
    
    // 确认删除事件
    $('#confirm-delete-btn').on('click', function() {
        deleteSelectedComponent();
        $('#delete-confirm-modal').hide();
    });
    
    // 取消删除事件
    $('#cancel-delete-btn, .modal-close').on('click', function() {
        $('#delete-confirm-modal').hide();
    });
    
    // 点击模态框背景关闭
    $('#delete-confirm-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // 更新表格属性的函数
    function updateTableProperty(property, value) {
        // 获取当前选中的表格组件
        const $selectedTable = $('.ef-table.selected');
        
        if ($selectedTable.length === 0) {
            console.warn('没有选中的表格组件');
            return;
        }
        
        switch(property) {
            case 'border-width':
                $selectedTable.css('border-width', value);
                $selectedTable.find('td, th').css('border-width', value);
                break;
                
            case 'border-color':
                $selectedTable.css('border-color', value);
                $selectedTable.find('td, th').css('border-color', value);
                break;
                
            case 'border-style':
                $selectedTable.css('border-style', value);
                $selectedTable.find('td, th').css('border-style', value);
                break;
                
            case 'cell-padding':
                $selectedTable.find('td, th').css('padding', value);
                break;
                
            case 'stripe-rows':
                if (value) {
                    $selectedTable.addClass('table-striped');
                    // 添加条纹样式
                    $selectedTable.find('tbody tr:nth-child(even)').css('background-color', '#f8f9fa');
                } else {
                    $selectedTable.removeClass('table-striped');
                    $selectedTable.find('tbody tr').css('background-color', '');
                }
                break;
                
            case 'hover-effect':
                if (value) {
                    $selectedTable.addClass('table-hover');
                } else {
                    $selectedTable.removeClass('table-hover');
                }
                break;
        }
    }
    
    // 删除选中组件的函数
    function deleteSelectedComponent() {
        const $selectedComponent = $('.ef-table.selected, .ef-text.selected, .ef-image.selected');
        
        if ($selectedComponent.length === 0) {
            console.warn('没有选中的组件');
            return;
        }
        
        // 移除组件
        $selectedComponent.remove();
        
        // 隐藏属性面板
        $('.properties-panel').hide();
        
        console.log('组件已删除');
    }
    
    // 检查是否选中表格组件并显示/隐藏表格属性
    function checkTableSelection() {
        const $selectedTable = $('.ef-table.selected');
        const $tableProperties = $('#table-properties');
        
        if ($selectedTable.length > 0) {
            $tableProperties.show();
            loadTableProperties($selectedTable);
        } else {
            $tableProperties.hide();
        }
    }
    
    // 加载表格属性到面板
    function loadTableProperties($table) {
        // 边框宽度
        const borderWidth = parseInt($table.css('border-width')) || 1;
        $('#table-border-width').val(borderWidth);
        $('#table-border-width-value').val(borderWidth);
        
        // 边框颜色
        const borderColor = $table.css('border-color') || '#dee2e6';
        $('#table-border-color').val(rgbToHex(borderColor));
        
        // 边框样式
        const borderStyle = $table.css('border-style') || 'solid';
        $('#table-border-style').val(borderStyle);
        
        // 单元格内边距
        const cellPadding = parseInt($table.find('td, th').first().css('padding')) || 8;
        $('#table-cell-padding').val(cellPadding);
        $('#table-cell-padding-value').val(cellPadding);
        
        // 条纹行
        const hasStripes = $table.hasClass('table-striped');
        $('#table-stripe-rows').prop('checked', hasStripes);
        
        // 悬停效果
        const hasHover = $table.hasClass('table-hover');
        $('#table-hover-effect').prop('checked', hasHover);
    }
    
    // RGB转十六进制颜色
    function rgbToHex(rgb) {
        if (rgb.startsWith('#')) return rgb;
        
        const result = rgb.match(/\d+/g);
        if (!result || result.length < 3) return '#dee2e6';
        
        return '#' + result.slice(0, 3).map(x => {
            const hex = parseInt(x).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    }
    
    function clearComponentSelection() {
        $('.ef-component').removeClass('selected');
        // 清除 ef-component 子元素的选中样式（display:contents 元素无盒模型，样式需设在子元素上）
        $('.selected-visual').removeClass('selected-visual').css({
            outline: '',
            outlineOffset: '',
            position: ''
        });
        $('.ef-component-close-btn').remove();
        // 清除按钮等非 ef-component 的选中样式
        $('.item-block.selected').removeClass('selected').css({
            outline: '',
            outlineOffset: ''
        });
    }

    function getSelectableChild($el) {
        // display:contents 元素没有盒模型，选取第一个有盒模型且可见的子元素来显示选中样式
        if ($el.css('display') === 'contents') {
            const $first = $el.children().first();
            // entity 类型的第一个子元素是 <input type="hidden">，无盒模型，跳过
            if ($first.is('input[type="hidden"]')) {
                return $first.next().length ? $first.next() : $first;
            }
            return $first;
        }
        return $el;
    }

    function markComponentSelected($el) {
        const $target = getSelectableChild($el);
        $el.addClass('selected');
        $target.addClass('selected-visual').css({
            outline: '3px solid #1890ff',
            outlineOffset: '0'
        });
        // 在组件右上角添加关闭图标
        if ($el.closest('.canvas, #canvas').length && !$el.find('.ef-component-close-btn').length) {
            $target.css('position', 'relative');
            const $btn = $('<div class="ef-component-close-btn" title="删除组件"><i class="fa fa-times"></i></div>');
            $btn.on('mousedown', function(e) {
                e.stopPropagation();
                e.preventDefault();
                const $parent = $(this).closest('.ef-component');
                $parent.remove();
                clearComponentSelection();
                $(document).trigger('componentDeselected');
            });
            // 关闭按钮需要相对于有盒模型的祖先定位，追加到 $target 上
            $target.prepend($btn);
        }
    }

    // 监听组件选择变化
    $(document).on('click', '.ef-table', function() {
        const $this = $(this);
        clearComponentSelection();
        markComponentSelected($this);
        setTimeout(function() {
            checkTableSelection();
            $(document).trigger('componentSelected', [$this[0]]);
        }, 100);
    });
    
    $(document).on('click', '.ef-text, .ef-image', function() {
        const $this = $(this);
        clearComponentSelection();
        markComponentSelected($this);
        setTimeout(function() {
            $('#table-properties').hide();
            $(document).trigger('componentSelected', [$this[0]]);
        }, 100);
    });

    // 双击 label 编辑文案 — 在 <body> 层创建浮动 input，脱离组件层级
    $(document).on('dblclick', '.ef-form-item-label', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $label = $(this);
        if ($label.data('ef-editing')) return;

        const text = $label.text().trim();
        const labelRect = $label[0].getBoundingClientRect();
        const $container = $label.closest('.ef-form-item-label-col');
        const containerRect = $container[0].getBoundingClientRect();

        $label.css('visibility', 'hidden');
        $label.data('ef-editing', true);

        // label 右对齐，input 应固定在右边缘向左扩展
        const minInputWidth = $label.outerWidth() + 10;          // 补偿 input padding+border
        // 容器 content 宽度（去掉 padding+border，border-box 下 label 实际可用宽度）
        const cs = getComputedStyle($container[0]);
        const cntPadL = parseFloat(cs.paddingLeft) || 0;
        const cntPadR = parseFloat(cs.paddingRight) || 0;
        const cntBdrL = parseFloat(cs.borderLeftWidth) || 0;
        const cntBdrR = parseFloat(cs.borderRightWidth) || 0;
        const contentWidth = containerRect.width - cntPadL - cntPadR - cntBdrL - cntBdrR;
        const rightDist = window.innerWidth - labelRect.right;    // label 右边缘离视口右边缘的距离

        const $input = $('<textarea>', {
            'data-ef-label-editor': 'true',
            css: {
                position: 'fixed',
                right: rightDist + 'px',
                top: labelRect.top + 'px',
                width: minInputWidth + 'px',      // 初始 = label 宽度，输入时动态扩展
                maxWidth: contentWidth + 'px',   // 不超过容器 content 宽度
                // 初始高度与单行 label 一致
                height: $label.outerHeight() + 'px',
                zIndex: 99999,
                border: '1px solid #1890ff',
                outline: 'none',
                padding: '0 4px',
                margin: 0,
                overflow: 'hidden',              // 隐藏滚动条，靠 scrollHeight 自动增高
                resize: 'none',                  // 禁止手动拖拽
                fontSize: $label.css('font-size'),
                lineHeight: $label.css('line-height'),
                fontFamily: $label.css('font-family'),
                background: '#fff',
                borderRadius: '2px',
                boxSizing: 'border-box',
                whiteSpace: 'pre-wrap',          // 保留换行、自动换行
                wordBreak: 'break-word'
            }
        }).appendTo('body');

        $input.val(text);
        $label.data('ef-original-text', text);   // 保存原始文本供 Escape 恢复

        // 输入时动态扩展宽度 + 自动增高
        // 使用隐藏 mirror span 测量文本宽度（textarea wrap/scrollWidth 不可靠）
        let $mirror;
        $input.on('input', function() {
            // 按需创建 mirror
            if (!$mirror) {
                $mirror = $('<span>').css({
                    position: 'absolute', top: '-9999px', left: '-9999px',
                    visibility: 'hidden', whiteSpace: 'nowrap',
                    fontSize: this.style.fontSize,
                    fontFamily: this.style.fontFamily,
                    lineHeight: this.style.lineHeight,
                    padding: '0 4px',
                    borderLeft: '1px solid transparent',   // 模拟 border-box 宽度
                    borderRight: '1px solid transparent',
                    boxSizing: 'border-box'
                }).appendTo('body');
            }
            $mirror.text(this.value + ' ');
            const textWidth = $mirror[0].getBoundingClientRect().width;
            this.style.width = Math.min(Math.max(textWidth, minInputWidth), contentWidth) + 'px';
            // 高度：宽度变化后重新计算
            this.style.height = '1px';
            this.style.height = (this.scrollHeight + 2) + 'px';
        });

        $input.focus();
        // 选中全部文字
        $input[0].selectionStart = 0;
        $input[0].selectionEnd = text.length;
        // 初始内容自动适配尺寸
        $input.trigger('input');

        $input.on('blur', function() {
            commitLabelEdit($label, $(this));
        });
        $input.on('keydown', function(ev) {
            // Ctrl+Enter / Cmd+Enter 完成编辑
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                ev.preventDefault();
                $(this).blur();
            }
            // Escape 也完成编辑
            if (ev.key === 'Escape') {
                // 还原文本
                $(this).val($label.data('ef-original-text') || '');
                $(this).blur();
            }
        });
    });

    function commitLabelEdit($label, $input) {
        const newText = $input.val().trim();
        $input.remove();
        $label.text(newText.length ? newText : '\u00A0');
        $label.css({
            visibility: 'visible',
            whiteSpace: 'normal',   // 父级 white-space: nowrap 被 display:contents 阻断后无法继承
            maxWidth: '100%'        // 相应 CSS 选择器 > 无法跨 display:contents 匹配
        });
        $label.data('ef-editing', false);
        // 同步到 properties 面板
        $('#form-label-text').val(newText);
        // 同步到包装器 data-label（供 save 时提取）
        const $wrapper = $label.closest('.ef-form-label');
        if ($wrapper.length) {
            $wrapper.attr('data-label', newText);
        }
    }

    function clearEmptyColSelection() {
        $('.editor-col.selected').removeClass('selected');
        window.__selectedEmptyCol__ = null;
    }

    // 字段行 mousedown — 统一处理标签列和控件列的选中
    $(document).on('mousedown', '.editor-field-row', function(e) {
        if ($(e.target).closest('.drag-handle').length) return;
        e.preventDefault();
        clearEmptyColSelection();
        const $row = $(this);
        const $label = $row.find('.ef-form-label');
        const $widget = $row.find('.ef-form-widget');
        const inLabelCol = $(e.target).closest('.ef-form-item-label-col').length > 0;
        const inWidgetCol = $(e.target).closest('.ef-form-item-wrapper-col').length > 0;
        clearComponentSelection();
        $('#table-properties').hide();
        $('#text-properties').hide();
        if (inWidgetCol && $widget.length) {
            markComponentSelected($widget);
            $(document).trigger('componentSelected', [$widget[0]]);
        } else if (inLabelCol && $label.length) {
            markComponentSelected($label);
            $(document).trigger('componentSelected', [$label[0]]);
        } else if (inWidgetCol) {
            const $col = $row.find('.ef-form-item-wrapper-col.editor-col').first();
            $col.addClass('selected');
            window.__selectedEmptyCol__ = $col[0];
            $(document).trigger('componentDeselected');
        } else if (inLabelCol) {
            const $col = $row.find('.ef-form-item-label-col.editor-col').first();
            $col.addClass('selected');
            window.__selectedEmptyCol__ = $col[0];
            $(document).trigger('componentDeselected');
        }
    });
    // 阻止 label/控件/按钮的 click 默认行为（焦点传递、checkbox toggle、表单提交等），
    // 用 #canvas 而非 document，因 view_editor_core.js 在 .section 上 stopPropagation
    $('#canvas').on('click', '.ef-form-label, .ef-form-widget', function(e) {
        e.preventDefault();
    });
    $('#canvas').on('click', '.item-block button.btn', function(e) {
        e.preventDefault();
    });

    // 表单提交/返回按钮 — 可选中、不可删除
    $(document).on('mousedown', '.item-block button.btn', function(e) {
        e.preventDefault();
        clearComponentSelection();
        const $block = $(this).closest('.item-block');
        $block.addClass('selected');
        $block.css({
            outline: '2px dashed #1890ff',
            outlineOffset: '0'
        });
        $('#table-properties').hide();
        $('#text-properties').hide();
        $(document).trigger('componentSelected', [$block[0]]);
    });
    // 捕获阶段阻止内联 onclick（如 location.href）的执行，委托 click 处理器在冒泡阶段已被 stopPropagation 阻断
    $('#canvas')[0].addEventListener('click', function(e) {
        if ($(e.target).closest('.item-block button.btn').length) {
            e.stopPropagation();
            e.preventDefault();
        }
    }, true);
    
    // 监听画布 mousedown，取消组件选择（click 事件被 .section handler 的 stopPropagation 阻断）
    $(document).on('mousedown', '.canvas, #canvas', function(e) {
        // 浮动 label 编辑器（在 body 层）不属于画布组件，不触发反选
        if ($(e.target).closest('[data-ef-label-editor]').length) return;
        // 字段行的选择/反选由 .editor-field-row 自身 handler 管理
        if (!$(e.target).closest('.ef-table, .ef-text, .ef-image, .ef-form-label, .ef-form-widget, .item-block.selected, .editor-field-row').length) {
            clearComponentSelection();
            $(document).trigger('componentDeselected');
            clearEmptyColSelection();
        }
    });

    // —— 字段行拖动排序 ——
    let _dragSrcRow = null;

    function addDragHandle($row) {
        if ($row.find('> .drag-handle').length) return;
        $row.prepend('<div class="drag-handle" draggable="true"><i class="fa fa-grip-vertical"></i></div>');
    }

    // 给已有行添加把手
    $('.editor-field-row').each(function() { addDragHandle($(this)); });

    $(document).on('dragstart', '.editor-field-row .drag-handle', function(e) {
        _dragSrcRow = $(this).closest('.editor-field-row');
        _dragSrcRow.addClass('dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        e.originalEvent.dataTransfer.setData('text/plain', '');
    });

    $(document).on('dragend', function() {
        if (_dragSrcRow) {
            _dragSrcRow.removeClass('dragging');
            _dragSrcRow = null;
        }
        $('.editor-field-row').removeClass('drop-target');
    });

    $(document).on('dragover', '.editor-field-row', function(e) {
        e.preventDefault();
        if (!_dragSrcRow || this === _dragSrcRow[0]) return;
        e.originalEvent.dataTransfer.dropEffect = 'move';
        // 标记插入位置（取目标的中点，鼠标在上半则插上面，下半插下面）
        const rect = this.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        const after = e.clientY > midY;
        $(this).toggleClass('drop-target', !after);
        // 存一个插入标识供 drop 用
        this._dropAfter = after;
    });

    $(document).on('dragleave', '.editor-field-row', function(e) {
        $(this).removeClass('drop-target');
    });

    $(document).on('drop', '.editor-field-row', function(e) {
        e.preventDefault();
        $(this).removeClass('drop-target');
        if (!_dragSrcRow || this === _dragSrcRow[0]) return;

        const $target = $(this);
        const after = this._dropAfter;

        if (after) {
            $target.after(_dragSrcRow);
        } else {
            $target.before(_dragSrcRow);
        }

        _dragSrcRow.removeClass('dragging');
        _dragSrcRow = null;
    });

    // 初始化 section 属性面板中的颜色选择器
    if (window.ColorPicker && !window.sectionTableBorderColorPicker) {
        window.sectionTableBorderColorPicker = new ColorPicker({
            container: document.body,
            defaultColor: '#d5d8dc',
            onChange: function(color) {
                $('#section-table-border-color-preview').css('background-color', color);
            }
        });
        $(document).on('click', '#section-table-border-color-trigger', function(e) {
            e.stopPropagation();
            if (window.sectionTableBorderColorPicker) window.sectionTableBorderColorPicker.open(this);
        });
    }
});