/**
 * 颜色选择器组件
 * 提供标准web颜色选择和自定义颜色功能
 */
class ColorPicker {
  /**
   * 构造函数
   * @param {Object} options - 配置选项
   * @param {string} options.container - 容器选择器或DOM元素
   * @param {Function} options.onChange - 颜色变化时的回调函数
   * @param {Function} options.onClose - 关闭选择器时的回调函数
   * @param {string} options.defaultColor - 默认颜色
   */
  constructor(options) {
    this.container = typeof options.container === 'string' 
      ? document.querySelector(options.container) 
      : options.container;
    this.onChange = options.onChange || function() {};
    this.onClose = options.onClose || function() {};
    this.themeColor = options.themeColor || '';
    this.defaultColor = options.defaultColor || '#000000';
    this.currentColor = this.defaultColor;
    const parsed = this._parseColor(this.defaultColor);
    this.currentRgb = parsed ? { r: parsed.r, g: parsed.g, b: parsed.b } : { r: 0, g: 0, b: 0 };
    this.currentAlpha = parsed ? parsed.a : 1;
    this.isOpen = false;
    this.element = null;
    
    // 标准web颜色
    this.standardColors = [
      // 第一行：基础颜色
      '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef', '#f3f3f3',
      // 第二行：红色系
      '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#4a86e8', '#0000ff', '#9900ff', '#ff00ff',
      // 第三行：浅色系
      '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc',
      // 第四行：中间色系
      '#dd7e6b', '#ea9999', '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#a4c2f4', '#9fc5e8', '#b4a7d6', '#d5a6bd',
      // 第五行：深色系
      '#cc4125', '#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af', '#6d9eeb', '#6fa8dc', '#8e7cc3', '#c27ba0',
      // 第六行：更深色系
      '#a61c00', '#cc0000', '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3c78d8', '#3d85c6', '#674ea7', '#a64d79',
      // 第七行：最深色系
      '#85200c', '#990000', '#b45f06', '#bf9000', '#38761d', '#134f5c', '#1155cc', '#0b5394', '#351c75', '#741b47'
    ];
    
    this.init();
  }

  _hexToRgb(hex) {
    const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
    if (!m) return { r: 0, g: 0, b: 0 };
    return { r: parseInt(m[1], 16), g: parseInt(m[2], 16), b: parseInt(m[3], 16) };
  }

  _resolveColor() {
    if (this.currentAlpha >= 1) {
      const r = this.currentRgb.r.toString(16).padStart(2, '0');
      const g = this.currentRgb.g.toString(16).padStart(2, '0');
      const b = this.currentRgb.b.toString(16).padStart(2, '0');
      return '#' + r + g + b;
    }
    return 'rgba(' + this.currentRgb.r + ',' + this.currentRgb.g + ',' + this.currentRgb.b + ',' + this.currentAlpha + ')';
  }

  _updatePreview() {
    const color = this._resolveColor();
    this.colorPreview.style.background = 'linear-gradient(' + color + ', ' + color + '), repeating-conic-gradient(#ccc 0% 25%, #fff 0% 50%) 0 0 / 10px 10px';
    this.colorInput.value = '#' + this.currentRgb.r.toString(16).padStart(2, '0') + this.currentRgb.g.toString(16).padStart(2, '0') + this.currentRgb.b.toString(16).padStart(2, '0');
    this.hexInput.value = color;
    this.opacitySlider.value = Math.round(this.currentAlpha * 100);
    this.opacityText.value = Math.round(this.currentAlpha * 100) + '%';
  }
  
  /**
   * 初始化组件
   */
  init() {
    // 创建DOM元素
    this.createElement();
    
    // 绑定事件处理函数到this
    this.handleDocumentClick = this.handleDocumentClick.bind(this);
    this.handleColorSelect = this.handleColorSelect.bind(this);
    this.handleCustomColorChange = this.handleCustomColorChange.bind(this);
    this.handleCustomColorSubmit = this.handleCustomColorSubmit.bind(this);
    
    // 添加事件监听
    document.addEventListener('click', this.handleDocumentClick);
  }
  
  /**
   * 创建颜色选择器DOM元素
   */
  createElement() {
    // 创建主容器
    this.element = document.createElement('div');
    this.element.className = 'color-picker';
    this.element.style.display = 'none';
    
    // 创建标题
    const title = document.createElement('div');
    title.className = 'color-picker-title';
    title.textContent = '选择颜色';
    this.element.appendChild(title);
    
    // 框架主题色快捷选择（置于最上方，独立区域）
    if (this.themeColor) {
      const themeSection = document.createElement('div');
      themeSection.className = 'theme-color-section';
      const themeBtn = document.createElement('div');
      themeBtn.className = 'theme-color-btn';
      themeBtn.style.backgroundColor = this.themeColor;
      themeBtn.addEventListener('click', () => {
        this.handleColorSelect(this.themeColor);
      });
      themeSection.appendChild(themeBtn);
      const themeLabel = document.createElement('span');
      themeLabel.className = 'theme-color-label';
      themeLabel.textContent = '框架主题色';
      themeSection.appendChild(themeLabel);
      const themeValue = document.createElement('span');
      themeValue.className = 'theme-color-value';
      themeValue.textContent = this.themeColor;
      themeSection.appendChild(themeValue);
      this.element.appendChild(themeSection);
    }
    
    // 创建标准颜色区域
    const standardColorsContainer = document.createElement('div');
    standardColorsContainer.className = 'standard-colors';
    
    // 添加标准颜色选项
    this.standardColors.forEach(color => {
      const colorOption = document.createElement('div');
      colorOption.className = 'color-option';
      colorOption.style.backgroundColor = color;
      colorOption.setAttribute('data-color', color);
      colorOption.addEventListener('click', () => this.handleColorSelect(color));
      standardColorsContainer.appendChild(colorOption);
    });
    
    this.element.appendChild(standardColorsContainer);
    
    // 创建自定义颜色区域
    const customColorContainer = document.createElement('div');
    customColorContainer.className = 'custom-color';
    
    // 当前选中的颜色预览（带棋盘格底纹）
    const previewWrap = document.createElement('div');
    previewWrap.className = 'color-preview-wrap';
    const colorPreview = document.createElement('div');
    colorPreview.className = 'color-preview';
    this.colorPreview = colorPreview;
    previewWrap.appendChild(colorPreview);
    customColorContainer.appendChild(previewWrap);
    
    // 自定义颜色输入
    const customColorInput = document.createElement('div');
    customColorInput.className = 'custom-color-input';
    
    // 颜色选择器
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.value = this.currentColor;
    colorInput.addEventListener('input', (e) => this.handleCustomColorChange(e));
    colorInput.addEventListener('change', (e) => this.handleCustomColorSubmit(e));
    this.colorInput = colorInput;
    
    // 颜色代码输入框
    const hexInput = document.createElement('input');
    hexInput.type = 'text';
    hexInput.className = 'hex-input';
    hexInput.value = this.currentColor;
    hexInput.placeholder = '#RRGGBB / rgba()';
    hexInput.addEventListener('input', e => {
      const value = e.target.value;
      if (/^#[0-9A-F]{6}$/i.test(value)) {
        this.handleCustomColorChange({ target: { value } });
      } else {
        const rgba = this._parseColor(value);
        if (rgba) {
          this.currentRgb = { r: rgba.r, g: rgba.g, b: rgba.b };
          this.currentAlpha = rgba.a;
          this._updatePreview();
        }
      }
    });
    hexInput.addEventListener('blur', () => {
      if (!/^#[0-9A-F]{6}$/i.test(hexInput.value) && !this._parseColor(hexInput.value)) {
        hexInput.value = this._resolveColor();
      }
    });
    this.hexInput = hexInput;
    
    customColorInput.appendChild(colorInput);
    customColorInput.appendChild(hexInput);
    customColorContainer.appendChild(customColorInput);
    
    // 透明度滑块
    const opacityRow = document.createElement('div');
    opacityRow.className = 'opacity-row';
    const opacityLabel = document.createElement('span');
    opacityLabel.className = 'opacity-label';
    opacityLabel.textContent = '透明';
    const opacitySlider = document.createElement('input');
    opacitySlider.type = 'range';
    opacitySlider.className = 'opacity-slider';
    opacitySlider.min = 0;
    opacitySlider.max = 100;
    opacitySlider.value = 100;
    const opacityText = document.createElement('span');
    opacityText.className = 'opacity-text';
    opacityText.textContent = '100%';
    this.opacitySlider = opacitySlider;
    this.opacityText = opacityText;
    opacitySlider.addEventListener('input', (e) => {
      const alpha = parseInt(e.target.value) / 100;
      this.currentAlpha = alpha;
      this._updatePreview();
    });
    opacityRow.appendChild(opacityLabel);
    opacityRow.appendChild(opacitySlider);
    opacityRow.appendChild(opacityText);
    customColorContainer.appendChild(opacityRow);
    
    // 按钮区域
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'button-container';
    
    // 确定按钮
    const applyButton = document.createElement('button');
    applyButton.className = 'apply-button';
    applyButton.textContent = '确定';
    applyButton.addEventListener('click', () => {
      this.onChange(this._resolveColor());
      this.close();
    });
    
    // 取消按钮
    const cancelButton = document.createElement('button');
    cancelButton.className = 'cancel-button';
    cancelButton.textContent = '取消';
    cancelButton.addEventListener('click', () => this.close());
    
    buttonContainer.appendChild(applyButton);
    buttonContainer.appendChild(cancelButton);
    customColorContainer.appendChild(buttonContainer);
    
    this.element.appendChild(customColorContainer);
    
    // 添加到容器
    this.container.appendChild(this.element);
    
    // 初始化预览
    this._updatePreview();
    
    // 添加样式
    this.addStyles();
  }
  
  /**
   * 添加组件样式
   */
  addStyles() {
    if (!document.getElementById('color-picker-styles')) {
      const style = document.createElement('style');
      style.id = 'color-picker-styles';
      style.textContent = `
        .color-picker {
          position: absolute;
          width: 300px;
          background-color: #ffffff;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
          padding: 15px;
          z-index: 2000;
          font-family: Arial, sans-serif;
        }
        
        .color-picker .color-picker-title {
          font-size: 16px;
          font-weight: bold;
          margin-bottom: 15px;
          color: #333;
          text-align: center;
        }
        
        .color-picker .standard-colors {
          display: grid;
          grid-template-columns: repeat(10, 1fr);
          gap: 5px;
          margin-bottom: 15px;
        }
        
        .color-picker .color-option {
          width: 20px;
          height: 20px;
          border-radius: 3px;
          cursor: pointer;
          transition: transform 0.1s;
          border: 1px solid #ddd;
        }
        
        .color-picker .color-option:hover {
          transform: scale(1.1);
          box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
        }
        
        .color-picker .theme-color-section {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 14px;
          padding: 8px 10px;
          background: #f5f7fa;
          border-radius: 6px;
          border: 1px solid #e8e8e8;
          cursor: pointer;
          transition: background 0.15s;
        }
        .color-picker .theme-color-section:hover {
          background: #e8f0fe;
          border-color: #165dff;
        }
        .color-picker .theme-color-btn {
          width: 28px;
          height: 28px;
          border-radius: 6px;
          border: 2px solid #d0d0d0;
          flex-shrink: 0;
        }
        .color-picker .theme-color-label {
          font-size: 13px;
          font-weight: 600;
          color: #333;
          flex: 1;
        }
        .color-picker .theme-color-value {
          font-size: 11px;
          color: #999;
          font-family: monospace;
        }
        
        .color-picker .custom-color {
          padding: 10px 0;
          border-top: 1px solid #eee;
        }
        
        .color-picker .color-preview {
          width: 30px;
          height: 30px;
          border-radius: 4px;
          margin-right: 10px;
          border: 1px solid #ddd;
          display: inline-block;
          vertical-align: middle;
        }
        
        .color-picker .custom-color-input {
          display: inline-block;
          vertical-align: middle;
          width: calc(100% - 50px);
        }
        
        .color-picker input[type="color"] {
          width: 40px;
          height: 30px;
          border: none;
          padding: 0;
          background: none;
          cursor: pointer;
          vertical-align: middle;
        }
        
        .color-picker .hex-input {
          width: calc(100% - 50px);
          height: 30px;
          border: 1px solid #ddd;
          border-radius: 4px;
          padding: 0 8px;
          margin-left: 5px;
          vertical-align: middle;
          font-size: 14px;
        }
        
        .color-picker .opacity-row {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-top: 10px;
          padding-top: 10px;
          border-top: 1px solid #eee;
        }
        .color-picker .opacity-label {
          font-size: 12px;
          color: #666;
          white-space: nowrap;
          min-width: 36px;
        }
        .color-picker .opacity-slider {
          flex: 1;
          height: 4px;
          accent-color: #4a86e8;
          cursor: pointer;
        }
        .color-picker .opacity-text {
          font-size: 12px;
          color: #666;
          min-width: 36px;
          text-align: right;
          font-family: monospace;
        }
        .color-picker .button-container {
          display: flex;
          justify-content: flex-end;
          margin-top: 12px;
        }
        
        .color-picker button {
          padding: 6px 12px;
          border-radius: 4px;
          border: none;
          cursor: pointer;
          font-size: 14px;
          margin-left: 10px;
        }
        
        .color-picker .apply-button {
          background-color: #4a86e8;
          color: white;
        }
        
        .color-picker .apply-button:hover {
          background-color: #3a76d8;
        }
        
        .color-picker .cancel-button {
          background-color: #f1f1f1;
          color: #333;
        }
        
        .color-picker .cancel-button:hover {
          background-color: #e1e1e1;
        }

        .color-preview-wrap {
          display: inline-block;
          vertical-align: middle;
          position: relative;
          width: 30px;
          height: 30px;
          margin-right: 10px;
          border-radius: 4px;
          overflow: hidden;
          background: repeating-conic-gradient(#ccc 0% 25%, #fff 0% 50%) 0 0 / 10px 10px;
          border: 1px solid #ddd;
        }
        .color-preview-wrap .color-preview {
          width: 100%;
          height: 100%;
          margin: 0;
          border: none;
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  /**
   * 处理标准颜色选择
   * @param {string} color - 选中的颜色
   */
  handleColorSelect(color) {
    this.currentColor = color;
    this.currentRgb = this._hexToRgb(color);
    this.currentAlpha = 1;
    this._updatePreview();
  }
  
  /**
   * 处理自定义颜色变化
   * @param {Event} e - 输入事件
   */
  handleCustomColorChange(e) {
    const color = e.target.value;
    this.currentColor = color;
    this.currentRgb = this._hexToRgb(color);
    this._updatePreview();
  }
  
  /**
   * 处理自定义颜色提交
   */
  handleCustomColorSubmit(e) {
    const color = e.target.value;
    if (/^#[0-9A-F]{6}$/i.test(color)) {
      this.handleColorSelect(color);
    }
  }
  
  /**
   * 处理文档点击事件，用于关闭选择器
   * @param {Event} e - 点击事件
   */
  handleDocumentClick(e) {
    if (this.isOpen && !this.element.contains(e.target) && 
        (this.triggerElement && !this.triggerElement.contains(e.target))) {
      this.close();
    }
  }
  
  /**
   * 打开颜色选择器
   * @param {HTMLElement} triggerElement - 触发打开的元素
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
   * 关闭颜色选择器
   */
  close() {
    this.isOpen = false;
    this.element.style.display = 'none';
    this.onClose();
  }
  
  /**
   * 设置当前颜色
   * @param {string} color - 颜色值 (#RRGGBB 或 rgba)
   */
  setColor(color) {
    this.currentColor = color;
    const rgba = this._parseColor(color);
    if (rgba) {
      this.currentRgb = { r: rgba.r, g: rgba.g, b: rgba.b };
      this.currentAlpha = rgba.a;
    }
    this._updatePreview();
  }

  _parseColor(color) {
    const hex = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color);
    if (hex) return { r: parseInt(hex[1], 16), g: parseInt(hex[2], 16), b: parseInt(hex[3], 16), a: 1 };
    const rgba = /^rgba?\((\d+),(\d+),(\d+)(?:,([\d.]+))?\)$/.exec(color);
    if (rgba) return { r: parseInt(rgba[1]), g: parseInt(rgba[2]), b: parseInt(rgba[3]), a: rgba[4] !== undefined ? parseFloat(rgba[4]) : 1 };
    return null;
  }
  
  /**
   * 获取当前颜色
   * @returns {string} 当前颜色值（alpha=100 返回 #RRGGBB，否则返回 rgba）
   */
  getColor() {
    return this._resolveColor();
  }
  
  /**
   * 销毁组件
   */
  destroy() {
    document.removeEventListener('click', this.handleDocumentClick);
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
    }
  }
}

// 导出组件
window.ColorPicker = ColorPicker;