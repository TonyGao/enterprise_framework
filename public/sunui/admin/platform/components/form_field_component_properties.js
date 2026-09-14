(function() {
    'use strict';

    let currentLabel = null;
    let bgPicker = null;
    let regularBgPicker = null;

    function generateHTML() {
        return `
            <div id="form-label-properties" style="display: none;">
                <div class="property-group compact" id="label-field-group">
                    <div class="property-group-title">${t('formFieldComp.m35')}</div>
                    <div class="property-item p-row">
                        <span class="property-label">${t('formFieldComp.m36')}</span>
                        <div class="property-control">
                            <span id="form-label-field-name" style="color: #999; font-size: 12px; line-height: 20px;"></span>
                        </div>
                    </div>
                    <div class="property-item compact" id="prop-item-label-text">
                        <div class="property-label">${t('formFieldComp.m37')}</div>
                        <div class="property-control">
                            <input type="text" id="form-label-text" class="ef-input ef-input-size-small" placeholder=t('formFieldComponentPropsJs.js1') style="width: 100%;">
                        </div>
                    </div>
                </div>
                <div class="property-group compact" id="widget-properties-group">
                    <div class="property-group-title">${t('formFieldComp.m38')}</div>
                    <div class="property-item compact" data-property="placeholder">
                        <div class="property-label">${t('formFieldComp.m39')}</div>
                        <div class="property-control">
                            <input type="text" id="form-placeholder" class="ef-input ef-input-size-small" placeholder=t('formFieldComponentPropsJs.js2') style="width: 100%;">
                        </div>
                    </div>
                    <div class="property-item compact" data-property="height">
                        <div class="property-label">${t('formFieldComp.m40')}</div>
                        <div class="property-control slider-with-input">
                            <div class="slider-container">
                                <input type="range" id="form-height" min="1" max="20" value="3" class="property-slider" step="1">
                            </div>
                            <input type="number" id="form-height-value" value="3" min="1" max="20" class="property-input" style="width: 55px;">
                        </div>
                    </div>
                    <div class="property-item p-row" data-property="rounded">
                        <span class="property-label">${t('formFieldComp.m41')}</span>
                        <div class="property-control">
                            <div class="ef-switch ef-switch-type-circle" id="form-rounded-wrapper" aria-checked="true">
                                <div class="ef-switch-handle"></div>
                                <input type="hidden" id="form-rounded" value="1">
                            </div>
                            <span class="ef-switch-label" id="form-rounded-label" style="margin-left: 6px; font-size: 12px;">${t('formFieldComp.m42')}</span>
                        </div>
                    </div>
                    <div class="property-item p-row" data-property="required">
                        <span class="property-label">${t('formFieldComp.m43')}</span>
                        <div class="property-control" style="display: flex; align-items: center;">
                            <div class="ef-switch ef-switch-type-circle" id="form-required-wrapper" aria-checked="false">
                                <div class="ef-switch-handle"></div>
                                <input type="hidden" id="form-required" value="0">
                            </div>
                            <span class="ef-switch-label" id="form-required-label" style="margin-left: 6px; font-size: 12px;">${t('formFieldComp.m44')}</span>
                            <span id="form-required-bg-group" style="margin-left: 10px; display: none; align-items: center;">
                                <span style="font-size: 12px; color: #666;">${t('formFieldComp.m45')}</span>
                                <span class="ef-input-wrapper ef-input-rounded" style="padding: 2px; width: auto; cursor: pointer; margin-left: 4px;" id="form-required-bg-trigger">
                                    <span id="form-required-bg-preview" style="display: block; width: 32px; height: 20px; background-color: #FFF2E8; border-radius: 2px;"></span>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="property-item p-row" data-property="regular-bg" style="display: none;">
                        <span class="property-label">${t('formFieldComp.m46')}</span>
                        <div class="property-control">
                            <span class="ef-input-wrapper ef-input-rounded" style="padding: 2px; width: auto; cursor: pointer;" id="form-regular-bg-trigger">
                                <span id="form-regular-bg-preview" style="display: block; width: 32px; height: 20px; background-color: #FFFFFF; border-radius: 2px;"></span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="property-group compact" id="label-layout-group">
                    <div class="property-group-title">${t('formFieldComp.m47')}</div>
                    <div class="property-item compact" data-property="col-span">
                        <div class="property-label">${t('formFieldComp.m48')}</div>
                        <div class="property-control slider-with-input">
                            <div class="slider-container">
                                <input type="range" id="form-col-span" min="1" max="4" value="1" class="property-slider">
                            </div>
                            <input type="number" id="form-col-span-value" value="1" min="1" max="4" class="property-input" style="width: 55px;">
                        </div>
                    </div>
                    <div class="property-item compact" data-property="label-col">
                        <div class="property-label">${t('formFieldComp.m49')}</div>
                        <div class="property-control slider-with-input">
                            <div class="slider-container">
                                <input type="range" id="form-label-col" min="1" max="23" value="8" class="property-slider">
                            </div>
                            <input type="number" id="form-label-col-value" value="8" min="1" max="23" class="property-input" style="width: 55px;">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function setBg(el, bg) {
        if (!el) return;
        if (bg) {
            el.style.setProperty('background-color', bg);
        } else {
            el.style.removeProperty('background-color');
        }
    }

    function applyWidgetBg($row, regularBg, requiredBg) {
        if (!$row || !$row.length) return;
        const $el = $row.find('.ef-form-widget').children().first();
        if (!$el.length) return;
        // 编辑器无值概念，始终当作"未填写"→ 优先未填底色，降级到常规底色
        const bg = requiredBg || regularBg || '';
        // 开关（switch）：仅使用常规底色，生产环境 switch 的未填底色由 toggleSwitchBg 处理
        if ($el.is('button.ef-switch')) {
            setBg($el[0], regularBg);
            return;
        }
        if (bg) {
            $el.css('background-color', bg);
            $el.find('input, textarea').each(function() { setBg(this, bg); });
        } else {
            $el.css('background-color', '');
            $el.find('input, textarea').each(function() { setBg(this, ''); });
        }
    }

    function init() {
        const $panel = $('.component-panel .panel-body');
        if ($panel.length > 0 && $('#form-label-properties').length === 0) {
            $panel.append(generateHTML());
        }
        if (!bgPicker && window.ColorPicker) {
            bgPicker = new ColorPicker({
                container: document.body,
                defaultColor: (window.__GENERAL_CONFIG__ && window.__GENERAL_CONFIG__.requiredBg) || '#FFF2E8',
                themeColor: (window.__GENERAL_CONFIG__ && window.__GENERAL_CONFIG__.themeColor) || '#165DFF',
                onChange: function(color) {
                    if (color.toUpperCase() === '#FFFFFF') {
                        if (currentLabel) currentLabel.removeAttr('data-required-bg');
                        $('#form-required-bg-preview').css('background-color', '');
                        return;
                    }
                    $('#form-required-bg-preview').css('background-color', color);
                    if (currentLabel) {
                        currentLabel.attr('data-required-bg', color);
                        applyWidgetBg(currentLabel.closest('.editor-field-row'), currentLabel.attr('data-regular-bg'), color);
                    }
                }
            });
            $(document).on('click', '#form-required-bg-trigger', function(e) {
                e.stopPropagation();
                if (bgPicker) bgPicker.open(this);
            });

            regularBgPicker = new ColorPicker({
                container: document.body,
                defaultColor: '#FFFFFF',
                themeColor: (window.__GENERAL_CONFIG__ && window.__GENERAL_CONFIG__.themeColor) || '#165DFF',
                onChange: function(color) {
                    if (color.toUpperCase() === '#FFFFFF') {
                        if (currentLabel) currentLabel.removeAttr('data-regular-bg');
                        $('#form-regular-bg-preview').css('background-color', '');
                        return;
                    }
                    $('#form-regular-bg-preview').css('background-color', color);
                    if (currentLabel) {
                        currentLabel.attr('data-regular-bg', color);
                        applyWidgetBg(currentLabel.closest('.editor-field-row'), color, currentLabel.attr('data-required-bg'));
                    }
                }
            });
            $(document).on('click', '#form-regular-bg-trigger', function(e) {
                e.stopPropagation();
                if (regularBgPicker) regularBgPicker.open(this);
            });
        }
    }

    function show($el) {
        currentLabel = $el.hasClass('ef-form-label')
            ? $el
            : $el.closest('.editor-field-row').find('.ef-form-label').first();

        if (!currentLabel.length) return;

        const isLabelMode = $el.hasClass('ef-form-label');

        $('.component-panel').show();

        const $tab = $('#property-tabs .tabs li').eq(1);
        if ($tab.length > 0) {
            $('#property-tabs .tabs li').removeClass('tabs-selected');
            $('#property-tabs .panel').hide();
            $tab.addClass('tabs-selected');
            $('.component-panel').show();
        }

        $('#form-label-properties').show();

        const fieldName = currentLabel.attr('data-field-name') || '';
        $('#form-label-field-name').text(fieldName);

        if (isLabelMode) {
            $('#label-field-group').show();
            $('#prop-item-label-text').show();
            $('#label-layout-group').show();
            $('[data-property="col-span"]').show();
            $('[data-property="label-col"]').show();
            $('#widget-properties-group').hide();

            const $label = currentLabel.find('label');
            $('#form-label-text').val($label.length ? $label.text().trim() : '');

            const cs = readDataAttr(currentLabel, 'col-span', '1');
            $('#form-col-span').val(cs);
            $('#form-col-span-value').val(cs);

            const col = readDataAttr(currentLabel, 'label-col', '8');
            $('#form-label-col').val(col);
            $('#form-label-col-value').val(col);
        } else {
            $('#label-field-group').show();
            $('#prop-item-label-text').hide();
            $('#label-layout-group').show();
            $('[data-property="col-span"]').show();
            $('[data-property="label-col"]').hide();
            $('#widget-properties-group').show();

            const cs = readDataAttr(currentLabel, 'col-span', '1');
            $('#form-col-span').val(cs);
            $('#form-col-span-value').val(cs);

            const $widget = $el.hasClass('ef-form-widget')
                ? $el
                : currentLabel.closest('.editor-field-row').find('.ef-form-widget');
            const type = $widget.attr('data-field-type') || '';
            const ph = ['string', 'integer', 'text', 'entity'].includes(type);
            const h = type === 'text';
            const r = ['string', 'integer', 'text', 'entity'].includes(type);
            const showReq = type !== 'boolean';

            $('[data-property="placeholder"]').toggle(ph);
            $('[data-property="height"]').toggle(h);
            $('[data-property="rounded"]').toggle(r);
            $('[data-property="required"]').toggle(showReq);
            $('[data-property="regular-bg"]').show();

            if (ph) {
                var phVal = readDataAttr(currentLabel, 'placeholder', '');
                $('#form-placeholder').val(phVal);
            }
            if (h) {
                const height = readDataAttr(currentLabel, 'height', '3');
                $('#form-height').val(height);
                $('#form-height-value').val(height);
                updateWidgetHeight(height);
            }
            if (r) {
                const v = readDataAttr(currentLabel, 'rounded', 'true') !== 'false';
                setSwitchState($('#form-rounded-wrapper'), v);
                $('#form-rounded-label').text(v ? t('formFieldComponentPropsJs.js3') : t('formFieldComponentPropsJs.js4'));
            }
            if (showReq) {
                const req = readDataAttr(currentLabel, 'required', 'false') === 'true';
                setSwitchState($('#form-required-wrapper'), req);
                $('#form-required-label').text(req ? t('formFieldComponentPropsJs.js5') : t('formFieldComponentPropsJs.js6'));
                $('#form-required-bg-group').toggle(req).css('display', req ? 'inline-flex' : 'none');
            }

            const regularBg = currentLabel.attr('data-regular-bg') || '';
            const requiredBg = currentLabel.attr('data-required-bg') || '';

            if (regularBg && regularBg.toUpperCase() !== '#FFFFFF' && regularBgPicker) regularBgPicker.setColor(regularBg);
            if (requiredBg && requiredBg.toUpperCase() !== '#FFFFFF' && bgPicker) bgPicker.setColor(requiredBg);

            $('#form-regular-bg-preview').css('background-color', (regularBg && regularBg.toUpperCase() !== '#FFFFFF') ? regularBg : '');
            const reqBg = (requiredBg && requiredBg.toUpperCase() !== '#FFFFFF') ? requiredBg : resolveRequiredBg(currentLabel);
            const reqShow = readDataAttr(currentLabel, 'required', 'false') === 'true';
            $('#form-required-bg-preview').css('background-color', reqShow ? reqBg : '');

            const $row = currentLabel.closest('.editor-field-row');
            applyWidgetBg($row, regularBg, requiredBg);
            var phVal = readDataAttr(currentLabel, 'placeholder', '');
            $row.find('.ef-form-widget input:not([type="hidden"]), .ef-form-widget textarea').attr('placeholder', phVal);
            $row.find('.ef-form-widget .ef-select-view-input, .ef-form-widget .ef-department-view-input, .ef-form-widget .ef-user-view-input').attr('placeholder', phVal || t('formFieldComponentPropsJs.js2'));
        }
    }

    function setSwitchState($w, on) {
        $w.attr('aria-checked', on ? 'true' : 'false');
        $w.toggleClass('ef-switch-checked', on);
        $w.find('input[type="hidden"]').val(on ? '1' : '0');
    }

    function hide() {
        if (bgPicker) bgPicker.close();
        if (regularBgPicker) regularBgPicker.close();
        $('#form-label-properties').hide();
        currentLabel = null;
    }

    function resolveRequiredBg($el) {
        const OLD_DEFAULT = '#FFF2E8';
        const cur = ($el.attr('data-required-bg') || '').toUpperCase();
        if (cur && cur !== OLD_DEFAULT && cur !== '#FFFFFF') return $el.attr('data-required-bg');
        const gc = window.__GENERAL_CONFIG__ || {};
        return gc.requiredBg || OLD_DEFAULT;
    }

    function readDataAttr($el, attr, def) {
        const v = $el.attr('data-' + attr);
        if (v !== undefined && v !== '') return v;
        return def;
    }

    function saveToDataAttr(attr) {
        if (!currentLabel) return;
        currentLabel.attr('data-' + attr, $('#form-' + attr + (attr === 'height' ? '-value' : '')).val());
    }

    $(document).on('input', '#form-label-text', function() {
        if (!currentLabel) return;
        const $l = currentLabel.find('label');
        if ($l.length) $l.text($(this).val().trim());
    });

    $(document).on('input', '#form-placeholder', function() {
        saveToDataAttr('placeholder');
        if (!currentLabel) return;
        var val = $(this).val();
        var $row = currentLabel.closest('.editor-field-row');
        if (!$row.length) return;
        var $widget = $row.find('.ef-form-widget');
        $widget.find('input:not([type="hidden"]), textarea').attr('placeholder', val);
        $widget.find('.ef-select-view-input, .ef-department-view-input, .ef-user-view-input').attr('placeholder', val || t('formFieldComponentPropsJs.js2'));
    });

    function updateWidgetHeight(height) {
        if (!currentLabel) return;
        var $row = currentLabel.closest('.editor-field-row');
        if (!$row.length) return;
        var $textarea = $row.find('.ef-form-widget').find('textarea');
        if ($textarea.length) {
            $textarea.attr('min-rows', height);
            $textarea.attr('max-rows', height);
            $textarea.attr('rows', height);
        }
    }

    $(document).on('input', '#form-height', function() {
        const v = $(this).val();
        $('#form-height-value').val(v);
        saveToDataAttr('height');
        updateWidgetHeight(v);
    });
    $(document).on('input', '#form-height-value', function() {
        let v = parseInt($(this).val()) || 3;
        v = Math.max(1, Math.min(20, v));
        $(this).val(v);
        $('#form-height').val(v);
        saveToDataAttr('height');
        updateWidgetHeight(v);
    });

    $(document).on('click', '#form-rounded-wrapper', function() {
        const on = $(this).attr('aria-checked') === 'true';
        $('#form-rounded-label').text(on ? t('formFieldComponentPropsJs.js3') : t('formFieldComponentPropsJs.js4'));
        if (currentLabel) {
            currentLabel.attr('data-rounded', on ? 'true' : 'false');
            const $row = currentLabel.closest('.editor-field-row');
            if ($row.length) {
                const $first = $row.find('.ef-form-widget').children().first();
                if ($first.is('.ef-input-wrapper, .ef-select-view-single')) {
                    $first.toggleClass('ef-input-rounded', on);
                } else if ($first.is('.ef-textarea-wrapper')) {
                    $first.toggleClass('ef-textarea-rounded', on);
                }
            }
        }
    });
    $(document).on('click', '#form-required-wrapper', function() {
        const on = $(this).attr('aria-checked') === 'true';
        $('#form-required-label').text(on ? t('formFieldComponentPropsJs.js5') : t('formFieldComponentPropsJs.js6'));
        $('#form-required-bg-group').toggle(on).css('display', on ? 'inline-flex' : 'none');
        if (!currentLabel) return;
        currentLabel.attr('data-required', on ? 'true' : 'false');
        const regularBg = currentLabel.attr('data-regular-bg') || '';
        if (on) {
            const useRequiredBg = resolveRequiredBg(currentLabel);
            currentLabel.attr('data-required-bg', useRequiredBg);
            if (bgPicker) bgPicker.setColor(useRequiredBg);
            $('#form-required-bg-preview').css('background-color', useRequiredBg);
        } else {
            currentLabel.removeAttr('data-required-bg');
            $('#form-required-bg-preview').css('background-color', '');
        }
        const $row = currentLabel.closest('.editor-field-row');
        applyWidgetBg($row, regularBg, on ? resolveRequiredBg(currentLabel) : '');
    });

    function updateLabelCol(val) {
        if (!currentLabel) return;
        saveToDataAttr('label-col');
        const $row = currentLabel.closest('.editor-field-row');
        if (!$row.length) return;
        const c = parseInt(val) || 8;
        const w = 24 - c;
        $row.find('.ef-form-item-label-col').removeClass(function(i, cls) {
            return cls.split(' ').filter(x => x.startsWith('ef-col-')).join(' ');
        }).addClass('ef-col-' + c);
        $row.find('.ef-form-item-wrapper-col').removeClass(function(i, cls) {
            return cls.split(' ').filter(x => x.startsWith('ef-col-')).join(' ');
        }).addClass('ef-col-' + w);
    }
    $(document).on('input', '#form-label-col', function() {
        const v = $(this).val();
        $('#form-label-col-value').val(v);
        updateLabelCol(v);
    });
    $(document).on('input', '#form-label-col-value', function() {
        let v = parseInt($(this).val()) || 8;
        v = Math.max(1, Math.min(23, v));
        $(this).val(v);
        $('#form-label-col').val(v);
        updateLabelCol(v);
    });

    function updateColSpan(val) {
        if (!currentLabel) return;
        currentLabel.attr('data-col-span', val);
        const $row = currentLabel.closest('.editor-field-row');
        if ($row.length) {
            if (parseInt(val) > 1) {
                $row.css('grid-column', 'span ' + val);
            } else {
                $row.css('grid-column', '');
            }
        }
    }
    $(document).on('input', '#form-col-span', function() {
        const v = $(this).val();
        $('#form-col-span-value').val(v);
        updateColSpan(v);
    });
    $(document).on('input', '#form-col-span-value', function() {
        let v = parseInt($(this).val()) || 1;
        v = Math.max(1, Math.min(4, v));
        $(this).val(v);
        $('#form-col-span').val(v);
        updateColSpan(v);
    });

    function applyWidgetBackgrounds() {
        $('.editor-field-row').each(function() {
            const $label = $(this).find('.ef-form-label').first();
            if (!$label.length) return;
            const regularBg = $label.attr('data-regular-bg') || '';
            const requiredBg = $label.attr('data-required-bg') || '';
            applyWidgetBg($(this), regularBg, requiredBg);
        });
    }

    window.FormFieldComponentProperties = { show, hide, applyWidgetBackgrounds };

    function applyColSpanStyles() {
        $('.editor-field-row').each(function() {
            const $label = $(this).find('.ef-form-label').first();
            if (!$label.length) return;
            const cs = $label.attr('data-col-span');
            if (cs && parseInt(cs) > 1) {
                $(this).css('grid-column', 'span ' + cs);
            }
        });
    }

    $(document).ready(function() {
        init();
        applyWidgetBackgrounds();
        applyColSpanStyles();
    });
})();
