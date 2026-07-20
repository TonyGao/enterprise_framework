// 组件属性面板管理
(function() {
    let selectedComponent = null;
    
    // 初始化组件属性面板
    function initComponentProperties() {
        // 监听组件选择变化
        $(document).on('componentSelected', function(e, component) {
            selectedComponent = component;
            showComponentProperties(component);
        });
        
        // 监听组件取消选择
        $(document).on('componentDeselected', function() {
            selectedComponent = null;
            hideComponentProperties();
        });
        
        // 初始化表格属性控件事件
        initTablePropertyEvents();
        
        // 初始化删除按钮事件
        initDeleteButtonEvents();
    }
    
    // 显示组件属性面板
    function showComponentProperties(component) {
        const $component = $(component);
        
        // 隐藏默认消息
        $('#default-component-message').hide();
        
        // 根据组件类型显示相应的属性面板
        if ($component.is('table') || $component.find('table').length > 0) {
            showTableProperties($component.is('table') ? $component : $component.find('table').first());
        } else if ($component.hasClass('ef-text') || $component.find('.ef-text').length > 0) {
            showTextProperties($component.hasClass('ef-text') ? $component : $component.find('.ef-text').first());
        } else if ($component.hasClass('ef-form-label') || $component.hasClass('ef-form-widget')) {
            showFormLabelProperties($component);
        } else {
            // 其他组件类型的属性面板可以在这里添加
            hideAllPropertyPanels();
            $('#default-component-message').show();
        }
    }
    
    // 隐藏组件属性面板
    function hideComponentProperties() {
        hideAllPropertyPanels();
        $('#default-component-message').show();
    }
    
    // 隐藏所有属性面板
    function hideAllPropertyPanels() {
        // 调用各个组件模块的隐藏方法
        if (window.TableComponentProperties) {
            window.TableComponentProperties.hide();
        }
        if (window.TextComponentProperties) {
            window.TextComponentProperties.hide();
        }
        if (window.FormFieldComponentProperties) {
            window.FormFieldComponentProperties.hide();
        }
        // 其他组件属性面板也在这里隐藏
    }
    
    // 显示表格属性面板
    function showTableProperties($table) {
        hideAllPropertyPanels();
        
        // 使用表格组件模块
        if (window.TableComponentProperties) {
            window.TableComponentProperties.show($table);
        }
    }
    
    // 显示文本属性面板
    function showTextProperties($text) {
        hideAllPropertyPanels();
        
        // 使用文本组件模块
        if (window.TextComponentProperties) {
            window.TextComponentProperties.show($text);
        }
    }
    
    // 显示表单项属性面板（标签或控件均可）
    function showFormLabelProperties($el) {
        hideAllPropertyPanels();

        if (window.FormFieldComponentProperties) {
            window.FormFieldComponentProperties.show($el);
        }
    }

    // 表格属性加载已移至 table_component_properties.js
    
    // 表格属性事件初始化已移至 table_component_properties.js
    function initTablePropertyEvents() {
        // 表格相关事件处理已移至专门的表格组件模块
    }
    
    // 表格相关的辅助函数已移至 table_component_properties.js
    
    // 初始化删除按钮事件
    function initDeleteButtonEvents() {
        // 删除组件按钮
        $('#delete-component-btn').on('click', function() {
            if (selectedComponent) {
                openDeleteModal();
            }
        });
        
        // 确认删除按钮
        $('#confirm-delete-btn').on('click', function() {
            if (selectedComponent) {
                deleteSelectedComponent();
                closeDeleteModal();
            }
        });
    }
    
    // 打开删除确认模态框
    function openDeleteModal() {
        $('#deleteComponentModal').show();
    }
    
    // 关闭删除确认模态框
    function closeDeleteModal() {
        $('#deleteComponentModal').hide();
    }
    
    // 删除选中的组件
    function deleteSelectedComponent() {
        if (selectedComponent) {
            $(selectedComponent).remove();
            selectedComponent = null;
            hideComponentProperties();
            
            // 触发组件删除事件
            $(document).trigger('componentDeleted');
        }
    }
    
    // RGB转十六进制
    function rgbToHex(rgb) {
        if (!rgb || rgb === 'rgba(0, 0, 0, 0)' || rgb === 'transparent') {
            return '#cccccc';
        }
        
        const result = rgb.match(/\d+/g);
        if (result && result.length >= 3) {
            return '#' + ((1 << 24) + (parseInt(result[0]) << 16) + (parseInt(result[1]) << 8) + parseInt(result[2])).toString(16).slice(1);
        }
        return '#cccccc';
    }
    
    // 全局函数，供模态框调用
    window.closeDeleteModal = closeDeleteModal;
    
    // 导出组件属性管理接口
    window.ComponentProperties = {
        getSelectedComponent: function() {
            return selectedComponent;
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        initComponentProperties();
    });
})();