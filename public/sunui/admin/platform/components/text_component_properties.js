/**
 * 文本组件属性面板
 * 负责生成文本组件的属性控制界面和相关逻辑
 */
(function() {
    
    // 生成文本属性面板HTML
    function generateTextPropertiesHTML() {
        return `
            <div id="text-properties" style="display: none;">
                <div class="tp-section">
                    <div class="tp-section-title">${t('textComp.m50')}</div>
                    <div class="tp-grid tp-grid-2">
                        <div class="tp-field">
                            <label class="tp-label">${t('textComp.m51')}</label>
                            <div class="tp-input-group">
                                <input type="number" id="text-font-size" class="tp-input" min="8" max="72" value="14">
                                <span class="tp-unit">px</span>
                            </div>
                        </div>
                        <div class="tp-field">
                            <label class="tp-label">${t('textComp.m52')}</label>
                            <div class="tp-btn-group" id="text-font-weight-select">
                                <button class="tp-btn" data-value="lighter">${t('textComp.m53')}</button>
                                <button class="tp-btn tp-btn-active" data-value="400">${t('textComp.m54')}</button>
                                <button class="tp-btn" data-value="bold">${t('textComp.m55')}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tp-section">
                    <div class="tp-section-title">${t('textComp.m56')}</div>
                    <div class="tp-field">
                        <label class="tp-label">${t('textComp.m57')}</label>
                        <span class="tp-color-swatch" id="text-color-trigger">
                            <span id="text-color-preview" style="background:#000000"></span>
                        </span>
                    </div>
                    <div class="tp-field tp-align-row">
                        <label class="tp-label">${t('textComp.m58')}</label>
                        <div class="tp-align-row-group">
                            <div class="tp-align-group" id="text-align-select-h">
                                <button class="tp-btn tp-btn-icon" data-value="left" title=t('textComponentPropsJs.js1')><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 2h12v1.5H1V2zm0 3.5h8v1.5H1V5.5zM1 9h12v1.5H1V9zm0 3.5h8V14H1v-1.5z"/></svg></button>
                                <button class="tp-btn tp-btn-icon" data-value="center" title=t('textComponentPropsJs.js2')><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 2h12v1.5H1V2zm3 3.5h8v1.5H4V5.5zM1 9h12v1.5H1V9zm3 3.5h8V14H4v-1.5z"/></svg></button>
                                <button class="tp-btn tp-btn-icon" data-value="right" title=t('textComponentPropsJs.js3')><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 2h12v1.5H1V2zm4 3.5h8v1.5H5V5.5zM1 9h12v1.5H1V9zm4 3.5h8V14H5v-1.5z"/></svg></button>
                                <button class="tp-btn tp-btn-icon" data-value="justify" title=t('textComponentPropsJs.js4')><svg viewBox="0 0 14 14" fill="currentColor"><path d="M1 2h12v1.5H1V2zm0 3.5h12v1.5H1V5.5zM1 9h12v1.5H1V9zm0 3.5h12V14H1v-1.5z"/></svg></button>
                            </div>
                            <span class="tp-align-divider"></span>
                            <div class="tp-align-group" id="text-v-align-select">
                                <button class="tp-btn tp-btn-icon" data-value="top" title=t('textComponentPropsJs.js5')><i class="fa-solid fa-align-left fa-rotate-90"></i></button>
                                <button class="tp-btn tp-btn-icon" data-value="middle" title=t('textComponentPropsJs.js6')><i class="fa-solid fa-align-center fa-rotate-90"></i></button>
                                <button class="tp-btn tp-btn-icon" data-value="bottom" title=t('textComponentPropsJs.js7')><i class="fa-solid fa-align-right fa-rotate-90"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tp-section tp-section-danger">
                    <button class="tp-delete-btn" id="delete-component-btn">
                        <svg viewBox="0 0 16 16" fill="currentColor" width="14" height="14"><path d="M5.5 1a.5.5 0 0 0-.5.5V2H2.5a.5.5 0 0 0 0 1h.257l.547 9.846A1.5 1.5 0 0 0 4.8 14.5h6.4a1.5 1.5 0 0 0 1.497-1.654l.546-9.846h.257a.5.5 0 0 0 0-1H11v-.5a.5.5 0 0 0-.5-.5h-5zM6 2h4v1H6V2zM4.05 4h7.9l-.535 9.634a.5.5 0 0 1-.5.466H5.085a.5.5 0 0 1-.5-.466L4.05 4z"/></svg>
                        t('textCompProps.delete')
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
        // 字体大小 — 优先选区，其次整行
        const selFontSize = window.getSelectedStyle ? window.getSelectedStyle('font-size') : null;
        const fontSize = parseInt(selFontSize || $text.css('font-size')) || 14;
        $('#text-font-size').val(fontSize);
        
        // 字体颜色 — 优先选区，其次整行
        const selColor = window.getSelectedStyle ? window.getSelectedStyle('color') : null;
        const color = rgbToHex(selColor || $text.css('color')) || '#000000';
        $('#text-color-preview').css('background-color', color);
        if (window.textColorPicker) window.textColorPicker.setColor(color);
        
        // 字体粗细 — 优先选区，其次整行
        const selWeight = window.getSelectedStyle ? window.getSelectedStyle('font-weight') : null;
        const fontWeight = selWeight || $text.css('font-weight') || '400';
        updateBtnGroup('text-font-weight-select', fontWeight === 'bold' || fontWeight === '700' ? 'bold' : fontWeight === 'lighter' || fontWeight === '100' ? 'lighter' : '400');
        
        // 文本对齐
        const textAlign = $text.css('text-align') || 'left';
        updateBtnGroup('text-align-select-h', textAlign);
        
        // 纵向对齐
        const vAlign = $text.css('vertical-align') || 'baseline';
        updateBtnGroup('text-v-align-select', vAlign);
    }

    // 选区变化时同步属性面板
    function initSelectionSync() {
        var syncTimer = null;
        $(document).on('selectionchange', function() {
            var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
            if (!$rich.length || $('#text-properties').is(':hidden')) return;
            var sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;
            if (!$rich[0].contains(sel.getRangeAt(0).commonAncestorContainer)) return;
            clearTimeout(syncTimer);
            syncTimer = setTimeout(function() {
                loadTextProperties($rich);
            }, 100);
        });
    }
    
    // 初始化文本属性控件事件
    function initTextPropertyEvents() {
        // 与 toolbar 一致：mousedown 时保存选区（click 时焦点可能已丢失）
        $(document).on('mousedown', '#text-properties', function() {
            var $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
            if (!$rich.length) return;
            var sel = window.getSelection();
            window._savedRange = (sel && sel.rangeCount) ? sel.getRangeAt(0).cloneRange() : null;
        });

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
                    updateTextProperty('color', color);
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
        
        // 纵向对齐选择器
        initVAlignSelect();
        
        // t('textCompProps.delete')按钮
        $(document).on('click', '#delete-component-btn', function() {
            if (window.ComponentProperties?.getSelectedComponent()) {
                showDeleteModal();
            }
        });
    }
    
    // 按钮组通用点击处理
    function initBtnGroup(containerId, property) {
        $(document).on('click', `#${containerId} .tp-btn`, function(e) {
            e.stopPropagation();
            const $btn = $(this);
            if ($btn.hasClass('tp-btn-active')) return;
            $btn.addClass('tp-btn-active').siblings().removeClass('tp-btn-active');
            const value = $btn.data('value');
            const selectedComponent = window.ComponentProperties?.getSelectedComponent();
            if (selectedComponent) updateTextProperty(property, value);
        });
    }

    function initBtnGroupIcon(containerId, property) {
        $(document).on('click', `#${containerId} .tp-btn-icon`, function(e) {
            e.stopPropagation();
            const $btn = $(this);
            if ($btn.hasClass('tp-btn-active')) return;
            $btn.addClass('tp-btn-active').siblings().removeClass('tp-btn-active');
            const value = $btn.data('value');
            const selectedComponent = window.ComponentProperties?.getSelectedComponent();
            if (selectedComponent) updateTextProperty(property, value);
        });
    }
    
    // 初始化字体粗细选择器
    function initFontWeightSelect() {
        initBtnGroup('text-font-weight-select', 'font-weight');
    }
    
    // 初始化文本对齐选择器（text-align 为块级属性，直接作用于整行）
    function initTextAlignSelect() {
        $(document).on('click', '#text-align-select-h .tp-btn-icon', function(e) {
            e.stopPropagation();
            const $btn = $(this);
            if ($btn.hasClass('tp-btn-active')) return;
            $btn.addClass('tp-btn-active').siblings().removeClass('tp-btn-active');
            const value = $btn.data('value');
            const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
            if ($rich.length) {
                $rich.css('text-align', value);
                $(document).trigger('selectionchange');
            }
        });
    }
    
    // 初始化纵向对齐选择器
    function initVAlignSelect() {
        $(document).on('click', '#text-v-align-select .tp-btn-icon', function(e) {
            e.stopPropagation();
            const $btn = $(this);
            if ($btn.hasClass('tp-btn-active')) return;
            $btn.addClass('tp-btn-active').siblings().removeClass('tp-btn-active');
            const value = $btn.data('value');
            const $rich = $('.ef-text-component.selected .ef-rich-text, .ef-text.selected .ef-rich-text');
            if ($rich.length) {
                $rich.css('vertical-align', value);
                $(document).trigger('selectionchange');
            }
        });
    }
    
    // 更新按钮组值
    function updateBtnGroup(containerId, value) {
        $(`#${containerId} .tp-btn, #${containerId} .tp-btn-icon`).removeClass('tp-btn-active');
        $(`#${containerId} .tp-btn[data-value="${value}"], #${containerId} .tp-btn-icon[data-value="${value}"]`).addClass('tp-btn-active');
    }
    
    // 更新文本属性（优先选区格式化，无选区则全元素）
    function updateTextProperty(property, value) {
        if (typeof window.applyStyleToSelection === 'function') {
            window.applyStyleToSelection(property, value);
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
        initSelectionSync();
    });
    
})();