/**
 * 文本组件属性面板
 * 负责生成文本组件的属性控制界面和相关逻辑
 */
(function() {
    
    // 生成文本属性面板HTML
    function generateTextPropertiesHTML() {
        return `
            <!-- 文本组件属性面板 -->
            <div id="text-properties" style="display: none;">
                <div class="property-group">
                    <div class="property-group-title">文本样式</div>
                    
                    <!-- 字体大小 -->
                    <div class="property-item">
                        <label class="property-label">字体大小</label>
                        <div class="property-control">
                            <input type="number" id="text-font-size" class="ef-input text" min="8" max="72" value="14" style="width: 80px;">
                            <span style="margin-left: 5px;">px</span>
                        </div>
                    </div>
                    
                    <!-- 字体颜色 -->
                    <div class="property-item">
                        <label class="property-label">字体颜色</label>
                        <div class="property-control">
                            <span class="ef-input-wrapper ef-input-rounded" style="padding: 2px; width: auto; cursor: pointer;" id="text-color-trigger">
                                <span id="text-color-preview" style="display: block; width: 50px; height: 30px; background: #000000; border-radius: 2px;"></span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- 字体粗细 -->
                    <div class="property-item">
                        <label class="property-label">字体粗细</label>
                        <div class="property-control">
                            <span class="ef-select-view-single ef-select ef-select-view ef-select-view-size-medium" style="width: 120px;" id="text-font-weight-select">
                                <input class="ef-select-view-input" placeholder="选择粗细" readonly>
                                <span class="ef-select-view-value ef-select-view-value-hidden">normal</span>
                                <span class="ef-select-view-suffix">
                                    <span class="ef-select-view-icon">
                                        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-expand" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter" style="transform: rotate(-45deg);">
                                            <path d="M7 26v14c0 .552.444 1 .996 1H22m19-19V8c0-.552-.444-1-.996-1H26"></path>
                                        </svg>
                                    </span>
                                </span>
                            </span>
                            <div class="ef-select-content" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 120px;">
                                <div class="ef-select-option" data-value="normal">正常</div>
                                <div class="ef-select-option" data-value="bold">粗体</div>
                                <div class="ef-select-option" data-value="lighter">细体</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 文本对齐 -->
                    <div class="property-item">
                        <label class="property-label">文本对齐</label>
                        <div class="property-control">
                            <span class="ef-select-view-single ef-select ef-select-view ef-select-view-size-medium" style="width: 120px;" id="text-align-select">
                                <input class="ef-select-view-input" placeholder="选择对齐" readonly>
                                <span class="ef-select-view-value ef-select-view-value-hidden">left</span>
                                <span class="ef-select-view-suffix">
                                    <span class="ef-select-view-icon">
                                        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-expand" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter" style="transform: rotate(-45deg);">
                                            <path d="M7 26v14c0 .552.444 1 .996 1H22m19-19V8c0-.552-.444-1-.996-1H26"></path>
                                        </svg>
                                    </span>
                                </span>
                            </span>
                            <div class="ef-select-content" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 120px;">
                                <div class="ef-select-option" data-value="left">左对齐</div>
                                <div class="ef-select-option" data-value="center">居中</div>
                                <div class="ef-select-option" data-value="right">右对齐</div>
                                <div class="ef-select-option" data-value="justify">两端对齐</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 删除组件按钮 -->
                <div class="property-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button class="btn red medium long" id="delete-component-btn">
                        <i class="fa-solid fa-trash-can"></i> 删除组件
                    </button>
                </div>
            </div>
        `;
    }
    
    // 初始化文本属性面板
    function initTextProperties() {
        // 将HTML插入到组件面板中
        const $componentPanel = $('.component-panel .panel-body');
        if ($componentPanel.length > 0) {
            // 检查是否已经存在文本属性面板
            if ($('#text-properties').length === 0) {
                $componentPanel.append(generateTextPropertiesHTML());
            }
        }
    }
    
    // 显示文本属性面板
    function showTextProperties($text) {
        // 确保组件面板是可见的
        $('.component-panel').show();
        
        // 切换到组件标签页
        const $componentTab = $('#property-tabs .tabs li').eq(1);
        if ($componentTab.length > 0) {
            $('#property-tabs .tabs li').removeClass('tabs-selected');
            $('#property-tabs .panel').hide();
            $componentTab.addClass('tabs-selected');
            $('.component-panel').show();
        }
        
        $('#text-properties').show();
        loadTextProperties($text);
    }
    
    // 隐藏文本属性面板
    function hideTextProperties() {
        $('#text-properties').hide();
    }
    
    // 加载文本属性值
    function loadTextProperties($text) {
        // 字体大小
        const fontSize = parseInt($text.css('font-size')) || 14;
        $('#text-font-size').val(fontSize);
        
        // 字体颜色
        const color = rgbToHex($text.css('color')) || '#000000';
        $('#text-color-preview').css('background-color', color);
        if (window.textColorPicker) window.textColorPicker.setColor(color);
        
        // 字体粗细
        const fontWeight = $text.css('font-weight') || 'normal';
        updateSelectValue('#text-font-weight-select', fontWeight);
        
        // 文本对齐
        const textAlign = $text.css('text-align') || 'left';
        updateSelectValue('#text-align-select', textAlign);
    }
    
    // 初始化文本属性控件事件
    function initTextPropertyEvents() {
        // 字体大小
        $(document).on('input', '#text-font-size', function() {
            const selectedComponent = window.ComponentProperties?.getSelectedComponent();
            if (selectedComponent) {
                updateTextProperty('font-size', $(this).val() + 'px');
            }
        });
        
        // 字体颜色
        if (!window.textColorPicker && window.ColorPicker) {
            window.textColorPicker = new ColorPicker({
                container: document.body,
                defaultColor: '#000000',
                onChange: function(color) {
                    $('#text-color-preview').css('background-color', color);
                    const selectedComponent = window.ComponentProperties?.getSelectedComponent();
                    if (selectedComponent) {
                        updateTextProperty('color', color);
                    }
                }
            });
            $(document).on('click', '#text-color-trigger', function(e) {
                e.stopPropagation();
                if (window.textColorPicker) window.textColorPicker.open(this);
            });
        }
        
        // 字体粗细选择器
        initFontWeightSelect();
        
        // 文本对齐选择器
        initTextAlignSelect();
        
        // 删除组件按钮
        $(document).on('click', '#delete-component-btn', function() {
            if (window.ComponentProperties?.getSelectedComponent()) {
                showDeleteModal();
            }
        });
    }
    
    // 初始化字体粗细选择器
    function initFontWeightSelect() {
        $(document).on('click', '#text-font-weight-select', function(e) {
            e.stopPropagation();
            $(this).next('.ef-select-content').toggle();
        });
        
        $(document).on('click', '#text-font-weight-select + .ef-select-content .ef-select-option', function(e) {
            e.stopPropagation();
            const value = $(this).data('value');
            const text = $(this).text();
            
            // 更新选择器显示
            const $select = $('#text-font-weight-select');
            $select.find('.ef-select-view-input').val(text);
            $select.find('.ef-select-view-value').text(value);
            
            // 隐藏选项列表
            $(this).parent().hide();
            
            // 更新文本样式
            const selectedComponent = window.ComponentProperties?.getSelectedComponent();
            if (selectedComponent) {
                updateTextProperty('font-weight', value);
            }
        });
    }
    
    // 初始化文本对齐选择器
    function initTextAlignSelect() {
        $(document).on('click', '#text-align-select', function(e) {
            e.stopPropagation();
            $(this).next('.ef-select-content').toggle();
        });
        
        $(document).on('click', '#text-align-select + .ef-select-content .ef-select-option', function(e) {
            e.stopPropagation();
            const value = $(this).data('value');
            const text = $(this).text();
            
            // 更新选择器显示
            const $select = $('#text-align-select');
            $select.find('.ef-select-view-input').val(text);
            $select.find('.ef-select-view-value').text(value);
            
            // 隐藏选项列表
            $(this).parent().hide();
            
            // 更新文本样式
            const selectedComponent = window.ComponentProperties?.getSelectedComponent();
            if (selectedComponent) {
                updateTextProperty('text-align', value);
            }
        });
    }
    
    // 更新选择器值
    function updateSelectValue(selector, value) {
        const $select = $(selector);
        const $option = $select.next('.ef-select-content').find(`[data-value="${value}"]`);
        if ($option.length > 0) {
            $select.find('.ef-select-view-input').val($option.text());
            $select.find('.ef-select-view-value').text(value);
        }
    }
    
    // 更新文本属性
    function updateTextProperty(property, value) {
        const selectedComponent = window.ComponentProperties?.getSelectedComponent();
        if (selectedComponent) {
            const $text = $(selectedComponent).hasClass('ef-text') ? $(selectedComponent) : $(selectedComponent).find('.ef-text').first();
            $text.css(property, value);
        }
    }
    
    // 显示删除确认模态框
    function showDeleteModal() {
        $('#delete-component-modal').show();
    }
    
    // RGB转十六进制
    function rgbToHex(rgb) {
        if (!rgb || rgb === 'rgba(0, 0, 0, 0)' || rgb === 'transparent') {
            return '#000000';
        }
        
        const result = rgb.match(/\d+/g);
        if (result && result.length >= 3) {
            return '#' + ((1 << 24) + (parseInt(result[0]) << 16) + (parseInt(result[1]) << 8) + parseInt(result[2])).toString(16).slice(1);
        }
        return '#000000';
    }
    
    // 导出文本组件属性接口
    window.TextComponentProperties = {
        init: initTextProperties,
        show: showTextProperties,
        hide: hideTextProperties,
        initEvents: initTextPropertyEvents
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        initTextProperties();
        initTextPropertyEvents();
    });
    
})();