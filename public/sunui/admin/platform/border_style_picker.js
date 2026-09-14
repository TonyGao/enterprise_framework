/**
 * 边框样式选择器组件
 * 提供边框宽度、样式、颜色选择和四个方向边框控制功能
 */
class BorderStylePicker {
  /**
   * 构造函数
   * @param {Object} options - 配置选项
   * @param {string} options.container - 容器选择器或DOM元素
   * @param {Function} options.onChange - 样式变化时的回调函数
   * @param {Function} options.onClose - 关闭选择器时的回调函数
   */
  constructor(options) {
    this.container = typeof options.container === 'string' 
      ? document.querySelector(options.container) 
      : options.container;
    this.onChange = typeof options.onChange === 'function' ? options.onChange : function() {};
    this.onClose = typeof options.onClose === 'function' ? options.onClose : function() {};
    this.isOpen = false;
    this.element = null;
    
    // 默认样式
    this.currentStyle = {
      width: '1px',
      style: 'solid',
      color: '#000000',
      // 四个方向的边框状态
      top: true,
      right: true,
      bottom: true,
      left: true
    };
    
    // 自定义颜色
    this.customColor = '#000000';
    
    // 边框宽度选项
    this.borderWidths = [
      {width: '1px', className: 'width-thin'},
      {width: '2px', className: 'width-medium'},
      {width: '3px', className: 'width-thick'},
      {width: '4px', className: 'width-extra-thick'},
      {width: '5px', className: 'width-ultra-thick'}
    ];
    
    // 边框样式选项及其显示名称
    this.borderStyles = [
      {value: 'solid', name: t('borderStylePickerJs.js1')},
      {value: 'dashed', name: t('borderStylePickerJs.js2')},
      {value: 'dotted', name: t('borderStylePickerJs.js3')},
      {value: 'double', name: t('borderStylePickerJs.js4')},
      {value: 'groove', name: t('borderStylePickerJs.js5')},
      {value: 'ridge', name: t('borderStylePickerJs.js6')},
      {value: 'inset', name: t('borderStylePickerJs.js7')},
      {value: 'outset', name: t('borderStylePickerJs.js8')}
    ];
    
    // 绑定方法到实例
    this.handleConfirm = this.handleConfirm.bind(this);
    this.handleCancel = this.handleCancel.bind(this);
    
    // 边框颜色选项
    this.borderColors = [
      // 第一行：基础颜色
      '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef', '#f3f3f3', '#ffffff',
      // 第二行：红色系
      '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#4a86e8', '#0000ff', '#9900ff', '#ff00ff',
      // 第三行：浅色系
      '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc'
    ];
    
    // 自定义颜色
    this.customColor = '#000000';
    
    this.init();
  }
  
  /**
   * 初始化方法
   */
  init() {
    // 创建DOM元素
    this.createElement();
    
    // 绑定事件处理函数到this
    this.handleDocumentClick = this.handleDocumentClick.bind(this);
    this.handleStyleSelect = this.handleStyleSelect.bind(this);
    this.handleConfirm = this.handleConfirm.bind(this);
    this.handleCancel = this.handleCancel.bind(this);
    
    // 标记是否暂时禁用文档点击事件处理
    this.disableDocumentClick = false;
    
    // 添加事件监听
    document.addEventListener('click', this.handleDocumentClick);
    
    // 添加样式
    this.addStyles();
  }
  
  /**
   * 创建DOM元素
   */
  createElement() {
    // 创建主容器
    this.element = document.createElement('div');
    this.element.className = 'border-style-picker';
    this.element.style.display = 'none';
    
    // 创建标题
    const title = document.createElement('div');
    title.className = 'border-style-picker-title';
    title.textContent = t('borderStylePickerJs.js9');
    this.element.appendChild(title);
    
    // 创建预览区域（提前创建，用于边框方向选择）
    const previewContainer = document.createElement('div');
    previewContainer.className = 'preview-container';
    
    const previewLabel = document.createElement('div');
    previewLabel.className = 'option-label';
    previewLabel.textContent = t('borderStylePickerJs.js10');
    previewContainer.appendChild(previewLabel);
    
    // 创建预览框，用于显示边框效果和选择边框方向
    const previewBox = document.createElement('div');
    previewBox.className = 'border-preview-box';
    
    // 创建四个边框方向的选择区域
    const directions = [
      { name: 'top', label: t('borderStylePickerJs.js11') },
      { name: 'right', label: t('borderStylePickerJs.js12') },
      { name: 'bottom', label: t('borderStylePickerJs.js13') },
      { name: 'left', label: t('borderStylePickerJs.js14') }
    ];
    
    // 创建预览内容
    const previewContent = document.createElement('div');
    previewContent.className = 'preview-content';
    previewContent.textContent = 'ABC';
    previewBox.appendChild(previewContent);
    
    // 创建四个方向的边框选择器
    directions.forEach(direction => {
      const borderSelector = document.createElement('div');
      borderSelector.className = `border-selector ${direction.name} active`;
      borderSelector.setAttribute('data-direction', direction.name);
      borderSelector.setAttribute('title', direction.label);
      
      borderSelector.addEventListener('click', () => {
        // 切换方向选择器的激活状态
        borderSelector.classList.toggle('active');
        this.currentStyle[direction.name] = borderSelector.classList.contains('active');
        this.updatePreview();
      });
      
      previewBox.appendChild(borderSelector);
    });
    
    previewContainer.appendChild(previewBox);
    this.element.appendChild(previewContainer);
    
    // 保存预览内容引用
    this.preview = previewContent;
    
    // 创建边框样式选择区域（移到宽度选择之前）
    const styleContainer = document.createElement('div');
    styleContainer.className = 'border-style-container';
    
    const styleLabel = document.createElement('div');
    styleLabel.className = 'option-label';
    styleLabel.textContent = t('borderStylePickerJs.js15');
    styleContainer.appendChild(styleLabel);
    
    const styleOptions = document.createElement('div');
    styleOptions.className = 'style-options';
    
    this.borderStyles.forEach(style => {
      const styleBtn = document.createElement('button');
      styleBtn.className = 'style-btn';
      styleBtn.setAttribute('data-style', style.value);
      styleBtn.setAttribute('title', style.name);
      styleBtn.style.borderBottomWidth = '2px';
      styleBtn.style.borderBottomStyle = style.value;
      styleBtn.style.borderBottomColor = '#000';
      styleBtn.textContent = style.name;
      
      if (style.value === this.currentStyle.style) {
        styleBtn.classList.add('active');
      }
      
      styleBtn.addEventListener('click', () => {
        // 移除其他样式按钮的激活状态
        styleOptions.querySelectorAll('.style-btn').forEach(btn => {
          btn.classList.remove('active');
        });
        
        // 添加当前样式按钮的激活状态
        styleBtn.classList.add('active');
        
        this.currentStyle.style = style.value;
        this.updateWidthAvailability(style.value);
        this.updatePreview();
      });
      
      styleOptions.appendChild(styleBtn);
    });
    
    styleContainer.appendChild(styleOptions);
    this.element.appendChild(styleContainer);
    
    // 创建边框宽度选择区域
    const widthContainer = document.createElement('div');
    widthContainer.className = 'border-width-container';
    
    const widthLabel = document.createElement('div');
    widthLabel.className = 'option-label';
    widthLabel.textContent = t('borderStylePickerJs.js16');
    widthContainer.appendChild(widthLabel);
    
    const widthOptions = document.createElement('div');
    widthOptions.className = 'width-options';
    
    this.borderWidths.forEach((widthObj, index) => {
      const widthBtn = document.createElement('button');
      widthBtn.className = `width-btn ${widthObj.className}`;
      widthBtn.setAttribute('data-width', widthObj.width);
      widthBtn.style.height = widthObj.width;
      widthBtn.style.borderTopWidth = widthObj.width;
      widthBtn.style.borderTopStyle = 'solid';
      widthBtn.style.borderTopColor = '#000';
      widthBtn.style.marginBottom = '5px';
      
      // 默认选中第一个宽度选项
      if (index === 0 && !this.currentStyle.width) {
        widthBtn.classList.add('active');
        this.currentStyle.width = widthObj.width;
      } else if (widthObj.width === this.currentStyle.width) {
        widthBtn.classList.add('active');
      }
      
      widthBtn.addEventListener('click', (e) => {
        // 检查按钮是否被禁用
        if (widthBtn.classList.contains('disabled')) {
          e.preventDefault();
          return;
        }
        
        // 移除其他宽度按钮的激活状态
        widthOptions.querySelectorAll('.width-btn').forEach(btn => {
          btn.classList.remove('active');
        });
        
        // 添加当前宽度按钮的激活状态
        widthBtn.classList.add('active');
        
        this.currentStyle.width = widthObj.width;
        this.updatePreview();
      });
      
      widthOptions.appendChild(widthBtn);
    });
    
    widthContainer.appendChild(widthOptions);
    this.element.appendChild(widthContainer);
    
    // 保存宽度选项引用，用于后续更新可用状态
    this.widthOptions = widthOptions;
    
    // 创建边框颜色选择区域
    const colorContainer = document.createElement('div');
    colorContainer.className = 'border-color-container';
    
    const colorLabel = document.createElement('div');
    colorLabel.className = 'option-label';
    colorLabel.textContent = t('borderStylePickerJs.js17');
    colorContainer.appendChild(colorLabel);
    
    const colorGrid = document.createElement('div');
    colorGrid.className = 'color-grid';
    
    this.borderColors.forEach(color => {
      const colorOption = document.createElement('div');
      colorOption.className = 'color-option';
      colorOption.style.backgroundColor = color;
      colorOption.setAttribute('data-color', color);
      
      if (color === this.currentStyle.color) {
        colorOption.classList.add('selected');
      }
      
      colorOption.addEventListener('click', () => {
        this.currentStyle.color = color;
        this.updatePreview();
        
        // 移除其他颜色选项的选中状态
        colorGrid.querySelectorAll('.color-option').forEach(option => {
          option.classList.remove('selected');
        });
        
        // 添加当前颜色选项的选中状态
        colorOption.classList.add('selected');
        
        // 更新自定义颜色输入框
        this.customColorInput.value = color;
        this.customColor = color;
      });
      
      colorGrid.appendChild(colorOption);
    });
    
    colorContainer.appendChild(colorGrid);
    
    // 添加自定义颜色输入区域
    const customColorContainer = document.createElement('div');
    customColorContainer.className = 'custom-color-container';
    
    const customColorLabel = document.createElement('div');
    customColorLabel.className = 'option-label';
    customColorLabel.textContent = t('borderStylePickerJs.js18');
    customColorContainer.appendChild(customColorLabel);
    
    const customColorInputContainer = document.createElement('div');
    customColorInputContainer.className = 'custom-color-input-container';
    
    const customColorInput = document.createElement('input');
    customColorInput.type = 'text';
    customColorInput.className = 'custom-color-input';
    customColorInput.value = this.customColor;
    customColorInput.placeholder = '#RRGGBB';
    this.customColorInput = customColorInput;
    
    customColorInput.addEventListener('input', (e) => {
      this.customColor = e.target.value;
      // 验证颜色格式并直接应用
      if (/^#[0-9A-F]{6}$/i.test(this.customColor)) {
        this.currentStyle.color = this.customColor;
        this.updatePreview();
        
        // 更新自定义颜色预览
        this.customColorPreview.style.backgroundColor = this.customColor;
        
        // 移除其他颜色选项的选中状态
        colorGrid.querySelectorAll('.color-option').forEach(option => {
          option.classList.remove('selected');
        });
      }
    });
    
    // 解决 Backspace 键被拦截的问题
    customColorInput.addEventListener('keydown', (e) => {
      // 阻止事件冒泡，防止表格快捷键拦截
      e.stopPropagation();
    });
    
    const customColorPreview = document.createElement('div');
    customColorPreview.className = 'custom-color-preview';
    customColorPreview.style.backgroundColor = this.customColor;
    customColorPreview.setAttribute('title', t('borderStylePickerJs.js19'));
    this.customColorPreview = customColorPreview;
    
    // 点击颜色预览区域时弹出颜色选择器
    customColorPreview.addEventListener('click', (e) => {
      // 阻止事件冒泡，防止触发 handleDocumentClick 导致窗体关闭
      e.stopPropagation();
      
      // 暂时禁用文档点击事件处理
      this.disableDocumentClick = true;
      
      // 创建隐藏的颜色选择器输入框
      const colorPicker = document.createElement('input');
      colorPicker.type = 'color';
      colorPicker.value = this.customColor;
      colorPicker.style.position = 'fixed';
      colorPicker.style.opacity = '0';
      colorPicker.style.pointerEvents = 'none';
      colorPicker.style.zIndex = '-1';
      
      // 获取预览区域的位置信息
      const previewRect = customColorPreview.getBoundingClientRect();
      const scrollTop = window.scrollY || document.documentElement.scrollTop;
      const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
      
      // 设置颜色选择器的位置在预览区域下方（虽然是隐藏的）
      colorPicker.style.left = (previewRect.left + scrollLeft) + 'px';
      colorPicker.style.top = (previewRect.bottom + scrollTop - 20) + 'px';
      
      document.body.appendChild(colorPicker);
      
      // 选择完成后应用颜色并移除选择器
      colorPicker.addEventListener('change', (e) => {
        this.customColor = e.target.value;
        this.customColorInput.value = this.customColor;
        this.customColorPreview.style.backgroundColor = this.customColor;
        
        // 直接应用颜色
        this.currentStyle.color = this.customColor;
        this.updatePreview();
        
        // 移除其他颜色选项的选中状态
        colorGrid.querySelectorAll('.color-option').forEach(option => {
          option.classList.remove('selected');
        });
        
        if (document.body.contains(colorPicker)) {
          document.body.removeChild(colorPicker);
        }
        
        // 恢复文档点击事件处理
        setTimeout(() => {
          this.disableDocumentClick = false;
        }, 100);
      });
      
      // 处理取消选择的情况
      colorPicker.addEventListener('blur', () => {
        if (document.body.contains(colorPicker)) {
          document.body.removeChild(colorPicker);
        }
        
        // 恢复文档点击事件处理
        setTimeout(() => {
          this.disableDocumentClick = false;
        }, 100);
      });
      
      // 立即触发点击打开颜色选择器
      setTimeout(() => {
        colorPicker.click();
      }, 50);
    });
    
    customColorInputContainer.appendChild(customColorInput);
    customColorInputContainer.appendChild(customColorPreview);
    
    customColorContainer.appendChild(customColorInputContainer);
    colorContainer.appendChild(customColorContainer);
    
    this.element.appendChild(colorContainer);
    
    // 创建预览区域
    const borderPreviewContainer = document.createElement('div');
    borderPreviewContainer.className = 'preview-container';
    
    const borderPreviewLabel = document.createElement('div');
    borderPreviewLabel.className = 'option-label';
    borderPreviewLabel.textContent = t('borderStylePickerJs.js20');
    borderPreviewContainer.appendChild(borderPreviewLabel);
    
    const preview = document.createElement('div');
    preview.className = 'border-preview';
    preview.textContent = 'ABC';
    this.preview = preview;
    borderPreviewContainer.appendChild(preview);
    
    this.element.appendChild(borderPreviewContainer);
    
    // 创建按钮区域
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'button-container';
    
    const confirmButton = document.createElement('button');
    confirmButton.className = 'confirm-button';
    confirmButton.textContent = t('borderStylePickerJs.js21');
    confirmButton.addEventListener('click', this.handleConfirm);
    buttonContainer.appendChild(confirmButton);
    
    const cancelButton = document.createElement('button');
    cancelButton.className = 'cancel-button';
    cancelButton.textContent = t('borderStylePickerJs.js22');
    cancelButton.addEventListener('click', this.handleCancel);
    buttonContainer.appendChild(cancelButton);
    
    this.element.appendChild(buttonContainer);
    
    // 将选择器添加到容器
    document.body.appendChild(this.element);
    
    // 初始化预览
    this.updatePreview();
  }
  
  /**
   * 添加样式
   */
  addStyles() {
    if (!document.getElementById('border-style-picker-styles')) {
      const style = document.createElement('style');
      style.id = 'border-style-picker-styles';
      style.textContent = `
        .border-style-picker {
          position: absolute;
          width: 300px;
          background-color: #ffffff;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
          padding: 15px;
          z-index: 1000;
          font-family: Arial, sans-serif;
        }
        
        .border-style-picker .border-style-picker-title {
          font-size: 16px;
          font-weight: bold;
          margin-bottom: 15px;
          color: #333;
          text-align: center;
        }
        
        .border-style-picker .option-label {
          font-size: 14px;
          margin-bottom: 5px;
          color: #555;
        }
        
        .border-style-picker .border-direction-container,
        .border-style-picker .border-width-container,
        .border-style-picker .border-style-container,
        .border-style-picker .border-color-container,
        .border-style-picker .preview-container {
          margin-bottom: 15px;
        }
        
        /* 边框预览和方向选择样式 */
        .border-style-picker .border-preview-box {
          position: relative;
          width: 100px;
          height: 100px;
          margin: 10px auto;
          background-color: #f9f9f9;
          border: 1px solid #ddd;
        }
        
        .border-style-picker .preview-content {
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          font-size: 16px;
        }
        
        .border-style-picker .border-selector {
          position: absolute;
          background-color: transparent;
          cursor: pointer;
        }
        
        .border-style-picker .border-selector.top {
          top: 0;
          left: 10px;
          right: 10px;
          height: 10px;
          border-top: 3px solid #4a86e8;
        }
        
        .border-style-picker .border-selector.right {
          top: 10px;
          right: 0;
          bottom: 10px;
          width: 10px;
          border-right: 3px solid #4a86e8;
        }
        
        .border-style-picker .border-selector.bottom {
          bottom: 0;
          left: 10px;
          right: 10px;
          height: 10px;
          border-bottom: 3px solid #4a86e8;
        }
        
        .border-style-picker .border-selector.left {
          top: 10px;
          left: 0;
          bottom: 10px;
          width: 10px;
          border-left: 3px solid #4a86e8;
        }
        
        .border-style-picker .border-selector.active {
          background-color: rgba(74, 134, 232, 0.2);
        }
        
        .border-style-picker .border-selector:not(.active) {
          border-color: #ccc;
          background-color: transparent;
        }
        
        /* 宽度选择按钮样式 */
        .border-style-picker .width-options {
          display: flex;
          justify-content: space-between;
          margin-top: 5px;
        }
        
        .border-style-picker .width-btn {
          width: 40px;
          height: 30px;
          background-color: #fff;
          border: 1px solid #ccc;
          border-radius: 4px;
          cursor: pointer;
          margin-right: 5px;
        }
        
        .border-style-picker .width-btn.active {
          border-color: #4a86e8;
          box-shadow: 0 0 0 2px rgba(74, 134, 232, 0.3);
        }
        
        /* 样式选择按钮样式 */
        .border-style-picker .style-options {
          display: flex;
          flex-wrap: wrap;
          margin-top: 5px;
        }
        
        .border-style-picker .style-btn {
          min-width: 60px;
          height: 30px;
          background-color: #fff;
          border: 1px solid #ccc;
          border-radius: 4px;
          cursor: pointer;
          margin-right: 5px;
          margin-bottom: 5px;
          padding: 0 8px;
          font-size: 12px;
        }
        
        .border-style-picker .style-btn.active {
          border-color: #4a86e8;
          box-shadow: 0 0 0 2px rgba(74, 134, 232, 0.3);
        }
        
        /* 颜色选择样式 */
        .border-style-picker .color-grid {
          display: grid;
          grid-template-columns: repeat(10, 1fr);
          gap: 5px;
          margin-top: 5px;
        }
        
        .border-style-picker .color-option {
          width: 20px;
          height: 20px;
          border-radius: 3px;
          cursor: pointer;
          border: 1px solid #ccc;
        }
        
        .border-style-picker .color-option.selected {
          box-shadow: 0 0 0 2px #4a86e8;
        }
        
        /* 自定义颜色样式 */
        .border-style-picker .custom-color-container {
          margin-top: 10px;
        }
        
        .border-style-picker .custom-color-input-container {
          display: flex;
          align-items: center;
          margin-top: 5px;
        }
        
        .border-style-picker .custom-color-input {
          flex: 1;
          padding: 6px 8px;
          border: 1px solid #ccc;
          border-radius: 4px;
          font-size: 13px;
          font-family: 'Consolas', 'Monaco', monospace;
          color: #333;
          box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .border-style-picker .custom-color-input:focus {
          outline: none;
          border-color: #4a86e8;
          box-shadow: 0 0 0 2px rgba(74, 134, 232, 0.2);
        }
        
        .border-style-picker .custom-color-preview {
          width: 24px;
          height: 24px;
          border: 1px solid #ccc;
          border-radius: 3px;
          margin: 0 5px;
          cursor: pointer;
          transition: transform 0.1s ease;
        }
        
        .border-style-picker .custom-color-preview:hover {
          transform: scale(1.1);
          box-shadow: 0 0 3px rgba(0, 0, 0, 0.2);
        }
        
        .border-style-picker .apply-custom-color-btn {
          padding: 5px 10px;
          background-color: #f1f1f1;
          border: 1px solid #ccc;
          border-radius: 4px;
          cursor: pointer;
          font-size: 13px;
          transition: background-color 0.2s ease;
        }
        
        .border-style-picker .apply-custom-color-btn:hover {
          background-color: #e5e5e5;
        }
        
        /* 自定义颜色选择器容器样式 */
        .custom-color-picker-container {
          background-color: white;
          border: 1px solid #ccc;
          border-radius: 4px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
          padding: 8px;
          min-width: 120px;
        }
        
        .open-color-picker-btn {
          width: 100%;
          display: flex;
          justify-content: center;
          align-items: center;
          transition: all 0.2s ease;
        }
        
        .open-color-picker-btn:hover {
          transform: scale(1.02);
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* 预览区域样式 */
        .border-style-picker .border-preview {
          width: 100%;
          height: 50px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 18px;
          margin-top: 5px;
          background-color: #f9f9f9;
        }
        
        /* 按钮区域样式 */
        .border-style-picker .button-container {
          display: flex;
          justify-content: space-between;
          margin-top: 15px;
        }
        
        .border-style-picker .confirm-button,
        .border-style-picker .cancel-button {
          padding: 8px 15px;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
        }
        
        .border-style-picker .confirm-button {
          background-color: #4a86e8;
          color: white;
        }
        
        .border-style-picker .cancel-button {
          background-color: #f1f1f1;
          color: #333;
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  /**
   * 更新宽度按钮的可用状态
   */
  updateWidthAvailability(selectedStyle) {
    if (!this.widthOptions) return;
    
    const widthBtns = this.widthOptions.querySelectorAll('.width-btn');
    
    widthBtns.forEach(btn => {
      const width = btn.getAttribute('data-width');
      const className = btn.className;
      
      // 移除禁用状态
      btn.classList.remove('disabled');
      btn.style.opacity = '1';
      btn.style.cursor = 'pointer';
      
      // 根据选择的样式设置可用状态
      if (selectedStyle === 'double') {
        // 双线样式：width-thin、width-medium、width-thick不可用
        if (className.includes('width-thin') || className.includes('width-medium') || className.includes('width-thick')) {
          btn.classList.add('disabled');
          btn.style.opacity = '0.5';
          btn.style.cursor = 'not-allowed';
          
          // 如果当前选中的是被禁用的宽度，自动选择width-extra-thick
          if (btn.classList.contains('active')) {
            btn.classList.remove('active');
            const extraThickBtn = this.widthOptions.querySelector('.width-extra-thick');
            if (extraThickBtn) {
              extraThickBtn.classList.add('active');
              this.currentStyle.width = extraThickBtn.getAttribute('data-width');
            }
          }
        }
      } else if (selectedStyle === 'groove' || selectedStyle === 'ridge') {
        // 凹槽、凸槽样式：width-thin、width-medium不可用
        if (className.includes('width-thin') || className.includes('width-medium')) {
          btn.classList.add('disabled');
          btn.style.opacity = '0.5';
          btn.style.cursor = 'not-allowed';
          
          // 如果当前选中的是被禁用的宽度，自动选择width-thick
          if (btn.classList.contains('active')) {
            btn.classList.remove('active');
            const thickBtn = this.widthOptions.querySelector('.width-thick');
            if (thickBtn) {
              thickBtn.classList.add('active');
              this.currentStyle.width = thickBtn.getAttribute('data-width');
            }
          }
        }
      }
    });
  }
  
  /**
   * 更新预览
   */
  updatePreview() {
    if (this.preview) {
      // 重置所有边框
      this.preview.style.borderWidth = '0';
      this.preview.style.borderStyle = 'none';
      this.preview.style.borderColor = 'transparent';
      
      // 根据方向设置边框
      if (this.currentStyle.top) {
        this.preview.style.borderTopWidth = this.currentStyle.width;
        this.preview.style.borderTopStyle = this.currentStyle.style;
        this.preview.style.borderTopColor = this.currentStyle.color;
      }
      
      if (this.currentStyle.right) {
        this.preview.style.borderRightWidth = this.currentStyle.width;
        this.preview.style.borderRightStyle = this.currentStyle.style;
        this.preview.style.borderRightColor = this.currentStyle.color;
      }
      
      if (this.currentStyle.bottom) {
        this.preview.style.borderBottomWidth = this.currentStyle.width;
        this.preview.style.borderBottomStyle = this.currentStyle.style;
        this.preview.style.borderBottomColor = this.currentStyle.color;
      }
      
      if (this.currentStyle.left) {
        this.preview.style.borderLeftWidth = this.currentStyle.width;
        this.preview.style.borderLeftStyle = this.currentStyle.style;
        this.preview.style.borderLeftColor = this.currentStyle.color;
      }
      
      // 更新边框方向选择器的状态
      const selectors = this.element.querySelectorAll('.border-selector');
      selectors.forEach(selector => {
        const direction = selector.getAttribute('data-direction');
        if (direction) {
          selector.classList.toggle('active', this.currentStyle[direction]);
        }
      });
    }
  }
  
  /**
   * 处理样式选择
   */
  handleStyleSelect() {
    this.onChange(this.currentStyle);
  }
  
  /**
   * 处理确认按钮点击
   */
  handleConfirm() {
    this.onChange(this.currentStyle);
    this.close();
  }
  
  /**
   * 处理取消按钮点击
   */
  handleCancel() {
    this.close();
  }
  
  /**
   * 处理文档点击事件
   * @param {Event} e - 点击事件
   */
  handleDocumentClick(e) {
    // 如果暂时禁用了文档点击事件处理，则直接返回
    if (this.disableDocumentClick) return;
    
    if (this.isOpen && !this.element.contains(e.target) && 
        (this.triggerElement && !this.triggerElement.contains(e.target))) {
      this.close();
    }
  }
  
  /**
   * 打开选择器
   * @param {HTMLElement} triggerElement - 触发元素
   */
  open(triggerElement) {
    this.triggerElement = triggerElement;
    this.isOpen = true;
    this.element.style.display = 'block';
    
    // 定位选择器
    const rect = triggerElement.getBoundingClientRect();
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
    
    // 计算位置，确保选择器在视口内
    const top = rect.bottom + scrollTop;
    const left = rect.left + scrollLeft;
    
    this.element.style.top = `${top}px`;
    this.element.style.left = `${left}px`;
    
    // 检查是否超出右边界
    const rightEdge = left + this.element.offsetWidth;
    const windowWidth = window.innerWidth + scrollLeft;
    if (rightEdge > windowWidth) {
      this.element.style.left = `${windowWidth - this.element.offsetWidth - 10}px`;
    }
    
    // 检查是否超出下边界
    const bottomEdge = top + this.element.offsetHeight;
    const windowHeight = window.innerHeight + scrollTop;
    if (bottomEdge > windowHeight) {
      this.element.style.top = `${rect.top + scrollTop - this.element.offsetHeight}px`;
    }
  }
  
  /**
   * 关闭选择器
   */
  close() {
    this.isOpen = false;
    this.element.style.display = 'none';
    this.onClose();
  }
  
  /**
   * 设置当前样式
   * @param {Object} style - 样式对象
   */
  setStyle(style) {
    this.currentStyle = { ...this.currentStyle, ...style };
    
    // 更新边框方向选择器的状态
    const borderSelectors = this.element.querySelectorAll('.border-selector');
    borderSelectors.forEach(selector => {
      const direction = selector.getAttribute('data-direction');
      if (direction && this.currentStyle[direction] !== undefined) {
        selector.classList.toggle('active', this.currentStyle[direction]);
      }
    });
    
    // 更新宽度按钮的状态
    const widthBtns = this.element.querySelectorAll('.width-btn');
    widthBtns.forEach(btn => {
      const width = btn.getAttribute('data-width');
      btn.classList.toggle('active', width === this.currentStyle.width);
    });
    
    // 更新样式按钮的状态
    const styleBtns = this.element.querySelectorAll('.style-btn');
    styleBtns.forEach(btn => {
      const style = btn.getAttribute('data-style');
      btn.classList.toggle('active', style === this.currentStyle.style);
    });
    
    // 更新颜色选项的选中状态
    const colorOptions = this.element.querySelectorAll('.color-option');
    colorOptions.forEach(option => {
      option.classList.remove('selected');
      if (option.getAttribute('data-color') === this.currentStyle.color) {
        option.classList.add('selected');
      }
    });
    
    // 更新自定义颜色输入框
    if (this.customColorInput) {
      this.customColorInput.value = this.currentStyle.color;
      this.customColor = this.currentStyle.color;
      
      if (this.customColorPreview) {
        this.customColorPreview.style.backgroundColor = this.currentStyle.color;
      }
    }
    
    this.updatePreview();
  }
  
  /**
   * 获取当前样式
   * @return {Object} 当前样式对象
   */
  getStyle() {
    return this.currentStyle;
  }
  
  /**
   * 销毁选择器
   */
  destroy() {
    document.removeEventListener('click', this.handleDocumentClick);
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
    }
  }
}