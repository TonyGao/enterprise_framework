(function() {
    var selectedEl = null;
    var selectionOverlay = null;
    var isDragging = false;
    var dragStartX, dragStartY, dragRect = null;

    function init() {
        initSelectionOverlay();
        bindCanvasClick();
        bindCssEditorEvents();
    }

    function initSelectionOverlay() {
        if (!$('#css-selection-overlay').length) {
            $('<div id="css-selection-overlay" style="position:absolute;pointer-events:none;border:2px solid #3b82f6;background:rgba(59,130,246,0.08);z-index:9999;display:none;transition:all 0.1s">').appendTo('#canvas');
        }
        selectionOverlay = $('#css-selection-overlay');
    }

    function bindCanvasClick() {
        var canvasEl = $('#canvas')[0];
        canvasEl.addEventListener('click', function(e) {
            if ($(e.target).closest('.section-header, .section-controls, .add-section-button, .guide-lines, #css-selection-overlay').length) return;
            selectElement(e.target);
        }, true);

        $('#canvas').on('mousedown', function(e) {
            if (e.shiftKey && e.button === 0) {
                isDragging = true;
                dragStartX = e.pageX;
                dragStartY = e.pageY;
                var canvasOffset = $('#canvas').offset();
                if (!dragRect) {
                    dragRect = $('<div id="css-drag-rect" style="position:absolute;border:1px dashed #3b82f6;background:rgba(59,130,246,0.06);z-index:9998;display:none;pointer-events:none">').appendTo('#canvas');
                }
                dragRect.css({left: e.pageX - canvasOffset.left, top: e.pageY - canvasOffset.top, width: 0, height: 0}).show();
                e.preventDefault();
            }
        }).on('mousemove', function(e) {
            if (isDragging && dragRect) {
                var canvasOffset = $('#canvas').offset();
                var x1 = Math.min(dragStartX, e.pageX) - canvasOffset.left;
                var y1 = Math.min(dragStartY, e.pageY) - canvasOffset.top;
                var x2 = Math.max(dragStartX, e.pageX) - canvasOffset.left;
                var y2 = Math.max(dragStartY, e.pageY) - canvasOffset.top;
                dragRect.css({left: x1, top: y1, width: x2 - x1, height: y2 - y1});
            }
        }).on('mouseup', function(e) {
            if (isDragging && dragRect) {
                isDragging = false;
                dragRect.hide();
                var canvasOffset = $('#canvas').offset();
                var rect = {
                    left: Math.min(dragStartX, e.pageX) - canvasOffset.left + $('#canvas').scrollLeft(),
                    top: Math.min(dragStartY, e.pageY) - canvasOffset.top + $('#canvas').scrollTop(),
                    right: Math.max(dragStartX, e.pageX) - canvasOffset.left + $('#canvas').scrollLeft(),
                    bottom: Math.max(dragStartY, e.pageY) - canvasOffset.top + $('#canvas').scrollTop()
                };
                selectElementsInRect(rect);
            }
        });

        $(document).on('click', function(e) {
            if (!$.contains($('#canvas')[0], e.target) && selectedEl) {
                clearSelection();
            }
        });

        $('#canvas').on('scroll', function() {
            if (selectedEl) updateOverlay();
        });
    }

    function selectElement(el) {
        if (el && el.id === 'canvas') { clearSelection(); return; }
        if (el && $(el).closest('.section-header, .section-controls, .add-section-button').length) return;
        // 表单控件（字段行/标签/控件）单击优先显示"组件"面板：不切到 CSS tab，也不叠加 CSS 选择框
        var isFormField = el && $(el).closest('.ef-form-widget, .ef-form-label, .editor-field-row').length > 0;
        selectedEl = el;
        if (isFormField) {
            if (selectionOverlay) selectionOverlay.hide();
            updateCssPanel();
            dispatchEvent('cssElementSelected', { element: el });
            return;
        }
        updateOverlay();
        updateCssPanel();
        switchToCssTab();
        dispatchEvent('cssElementSelected', { element: el });
    }

    function selectElementsInRect(rect) {
        var elements = [];
        $('#canvas').find('*').each(function() {
            var $el = $(this);
            if ($el.is('.section-header, .section-controls, .add-section-button, .guide-lines, #css-selection-overlay, #css-drag-rect')) return;
            var offset = $el.offset();
            if (!offset) return;
            var elRect = {
                left: offset.left - $('#canvas').offset().left + $('#canvas').scrollLeft(),
                top: offset.top - $('#canvas').offset().top + $('#canvas').scrollTop(),
                right: offset.left - $('#canvas').offset().left + $el.outerWidth() + $('#canvas').scrollLeft(),
                bottom: offset.top - $('#canvas').offset().top + $el.outerHeight() + $('#canvas').scrollTop()
            };
            if (elRect.left >= rect.left && elRect.top >= rect.top && elRect.right <= rect.right && elRect.bottom <= rect.bottom) {
                elements.push(this);
            }
        });
        if (elements.length > 0) {
            var isFormField = $(elements[0]).closest('.ef-form-widget, .ef-form-label, .editor-field-row').length > 0;
            selectedEl = elements[0];
            if (isFormField) {
                if (selectionOverlay) selectionOverlay.hide();
                updateCssPanel();
                dispatchEvent('cssElementSelected', { element: selectedEl, elements: elements });
                return;
            }
            updateOverlay();
            updateCssPanel();
            switchToCssTab();
            dispatchEvent('cssElementSelected', { element: selectedEl, elements: elements });
        }
    }

    function updateOverlay() {
        if (!selectedEl || !selectionOverlay) return;
        var $el = $(selectedEl);
        var canvasOffset = $('#canvas').offset();
        var elOffset = $el.offset();
        selectionOverlay.css({
            display: 'block',
            left: (elOffset.left - canvasOffset.left + $('#canvas').scrollLeft()) + 'px',
            top: (elOffset.top - canvasOffset.top + $('#canvas').scrollTop()) + 'px',
            width: $el.outerWidth() + 'px',
            height: $el.outerHeight() + 'px'
        });
    }

    function updateCssPanel() {
        if (!selectedEl) { $('#css-properties-content').hide(); $('#css-properties-placeholder').show(); return; }
        $('#css-properties-placeholder').hide();
        $('#css-properties-content').show();
        var $el = $(selectedEl);
        var tagName = selectedEl.tagName.toLowerCase();
        var idInfo = selectedEl.id ? '#' + selectedEl.id : '';
        var classes = (selectedEl.className || '').split(' ').filter(Boolean);
        var classInfo = classes.length ? '.' + classes.join('.') : '';
        $('#css-selected-info').text('<' + tagName + idInfo + classInfo + '>');
        var inlineStyle = $el.attr('style') || '';
        $('#css-inline-textarea').val(inlineStyle);
        renderPropertyList(inlineStyle);
    }

    function renderPropertyList(styleStr) {
        var $list = $('#css-property-list').empty();
        if (!styleStr) { $list.html('<p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px 0">无内联样式</p>'); return; }
        var props = styleStr.split(';').map(function(s) { return s.trim(); }).filter(Boolean);
        props.forEach(function(prop) {
            var colonIdx = prop.indexOf(':');
            if (colonIdx === -1) return;
            var key = prop.substring(0, colonIdx).trim();
            var val = prop.substring(colonIdx + 1).trim();
            addPropertyRow(key, val);
        });
    }

    function addPropertyRow(key, val) {
        var $row = $('<div class="css-prop-row" style="display:flex;align-items:center;gap:4px;margin-bottom:3px;font-size:11px">');
        var $keyInput = $('<input class="css-prop-key" type="text" value="' + escHtml(key) + '" style="flex:0 0 90px;padding:2px 4px;border:1px solid #e2e8f0;border-radius:3px;font-family:monospace;font-size:11px;outline:none">');
        var $valInput = $('<input class="css-prop-val" type="text" value="' + escHtml(val) + '" style="flex:1;padding:2px 4px;border:1px solid #e2e8f0;border-radius:3px;font-family:monospace;font-size:11px;outline:none">');
        var $delBtn = $('<button style="padding:2px 6px;background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:13px;line-height:1">×</button>');
        $delBtn.on('click', function() { $row.remove(); applyCss(); });
        $keyInput.on('input', debounce(applyCss, 200));
        $valInput.on('input', debounce(applyCss, 200));
        $row.append($keyInput).append($valInput).append($delBtn);
        $('#css-property-list').append($row);
    }

    function applyCss() {
        if (!selectedEl) return;
        var parts = [];
        $('#css-property-list .css-prop-row').each(function() {
            var key = $(this).find('.css-prop-key').val().trim();
            var val = $(this).find('.css-prop-val').val().trim();
            if (key && val) parts.push(key + ': ' + val);
        });
        var styleStr = parts.join('; ');
        $(selectedEl).attr('style', styleStr);
        $('#css-inline-textarea').val(styleStr);
        updateOverlay();
        dispatchEvent('cssStyleChanged', { element: selectedEl, style: styleStr });
    }

    function clearCss() {
        if (!selectedEl) return;
        $(selectedEl).removeAttr('style');
        $('#css-inline-textarea').val('');
        $('#css-property-list').empty().html('<p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px 0">无内联样式</p>');
        updateOverlay();
        dispatchEvent('cssStyleChanged', { element: selectedEl, style: '' });
    }

    function clearSelection() {
        selectedEl = null;
        if (selectionOverlay) selectionOverlay.hide();
        $('#css-properties-content').hide();
        $('#css-properties-placeholder').show();
        dispatchEvent('cssElementDeselected');
    }

    function switchToCssTab() {
        var $cssTab = $('#property-tabs .tabs li').eq(3);
        if ($cssTab.length > 0) {
            $('#property-tabs .tabs li').removeClass('tabs-selected');
            $('#property-tabs .panel').hide();
            $cssTab.addClass('tabs-selected');
            $('.css-panel').show();
        }
    }

    function bindCssEditorEvents() {
        $('#css-apply-btn').on('click', function() {
            var styleStr = $('#css-inline-textarea').val();
            if (selectedEl) {
                $(selectedEl).attr('style', styleStr);
                renderPropertyList(styleStr);
                updateOverlay();
                dispatchEvent('cssStyleChanged', { element: selectedEl, style: styleStr });
            }
        });
        $('#css-clear-btn').on('click', clearCss);
        $('#css-add-prop-btn').on('click', function() {
            var hasEmpty = false;
            $('#css-property-list .css-prop-row').each(function() {
                if (!$(this).find('.css-prop-key').val().trim() || !$(this).find('.css-prop-val').val().trim()) hasEmpty = true;
            });
            if (!hasEmpty) addPropertyRow('', '');
        });
        $('#css-select-parent').on('click', function() {
            if (selectedEl && selectedEl.parentNode && selectedEl.parentNode !== document && selectedEl.parentNode !== $('#canvas')[0]) {
                if ($(selectedEl.parentNode).closest('#canvas').length) {
                    selectElement(selectedEl.parentNode);
                }
            }
        });
        $('#css-select-children').on('click', function() {
            if (selectedEl && selectedEl.children.length > 0) {
                selectElement(selectedEl.children[0]);
            }
        });
    }

    function dispatchEvent(name, detail) {
        try { document.dispatchEvent(new CustomEvent(name, { detail: detail || {} })); } catch(e) {}
    }

    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function debounce(fn, ms) {
        var timer;
        return function() {
            clearTimeout(timer);
            var args = arguments, ctx = this;
            timer = setTimeout(function() { fn.apply(ctx, args); }, ms);
        };
    }

    window.CssProperties = {
        init: init,
        selectElement: selectElement,
        clearSelection: clearSelection,
        getSelectedElement: function() { return selectedEl; }
    };

    $(document).ready(function() {
        if (window.CssProperties) window.CssProperties.init();
    });
})();