(function ($) {
  // 创建调试提示框
  const createDebugTooltip = () => {
    const $tooltip = $('<div class="key-debug-tooltip"></div>').css({
      position: 'fixed',
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      color: '#fff',
      padding: '10px 15px',
      borderRadius: '5px',
      fontSize: '16px',
      fontFamily: 'Monaco, monospace',
      zIndex: 9999,
      display: 'none',
      boxShadow: '0 2px 10px rgba(0, 0, 0, 0.3)',
      transition: 'opacity 0.3s ease-in-out'
    }).appendTo('body');

    // 存储当前的淡出定时器ID
    let fadeOutTimerId = null;

    return {
      show: (text) => {
        // 清除之前的淡出定时器，防止多次触发导致的显示问题
        if (fadeOutTimerId) {
          clearTimeout(fadeOutTimerId);
          fadeOutTimerId = null;
        }
        
        // 立即停止当前正在进行的所有动画
        $tooltip.stop(true, true);
        
        // 更新文本并显示提示框
        $tooltip.text(text).fadeIn(200);
        
        // 设置新的淡出定时器
        fadeOutTimerId = setTimeout(() => {
          $tooltip.fadeOut(500);
          fadeOutTimerId = null;
        }, 2000);
      }
    };
  };

  // 初始化调试功能
  const initKeyDebug = () => {
    if (!$('meta[name="keydebug"][content="true"]').length) return;

    const tooltip = createDebugTooltip();

    // 监听键盘事件
    $(document).on('keydown', (e) => {
      const combo = HotkeyManager.normalizeCombo(e);
      // 检查所有可能的事件类型，不仅仅是click
      const eventHandlers = HotkeyManager.getEventHandlers && HotkeyManager.getEventHandlers();
      if (eventHandlers) {
        // 遍历所有注册的事件处理程序
        for (const key in eventHandlers) {
          // 检查是否匹配当前按键组合
          if (key.startsWith(`${combo}:`)) {
            tooltip.show(`键盘事件: ${combo} -> ${key}`);
            break; // 找到一个匹配项就退出循环
          }
        }
      }
    });

    // 监听鼠标事件
    $(document).on('click', '[key-press]', function(e) {
      const keys = $(this).attr('key-press');
      const eventType = $(this).attr('key-event') || 'click';
      tooltip.show(`鼠标事件: ${keys} -> ${eventType}`);
    });
  };

  // 当input focus时，将光标挪动到最后的字符后
  $.fn.textFocus = function (v) {
    var range,
      len,
      v = v === undefined ? 0 : parseInt(v);
    this.each(function () {
      len = this.value.length;
      v === 0 ? this.setSelectionRange(len, len) : this.setSelectionRange(v, v);
      this.focus();
    });
    return this;
  };

  // 添加自定义验证方法
  $.validator.addMethod(
    "selectRequired",
    function (value, element) {
      return value !== "";
    },
    "This field is required."
  );

  // 将自定义验证方法添加为默认规则
  $.validator.setDefaults({
    ignore: [],
    rules: {
      // 将自定义验证方法作为默认规则添加到所有 input 和 select 元素上
      "input[component='select']": {
        selectRequired: true,
      },
    },
  });
  // Form validation plugin
  $.fn.formValid = function (options) {
    // Default configuration
    const defaultConfig = {
      errorPlacement: function (error, element) {
        // Default: No error label
      },
      highlight: function (element) {
        if ($(element).is("[component='input']")) {
          // Add aria-invalid="true" attribute and error class when input is invalid
          $(element).attr("aria-invalid", "true").addClass("error");
          // Add error class to parent span
          $(element).closest(".ef-input-wrapper").addClass("ef-input-error");
        }

        if ($(element).is("[component='select']")) {
          $(element).next(".ef-select").addClass("ef-select-error");
        }

        if ($(element).is("[component='textarea']")) {
          $(element).closest(".ef-textarea-wrapper").addClass("ef-textarea-error");
        }

        if ($(element).is("[component='department']")) {
          $(element).closest('.ef-deparment-element').find('.ef-department-view-single').addClass("ef-department-error");
        }
      },
      unhighlight: function (element) {
        if ($(element).is("[component='input']")) {
          // Remove aria-invalid="true" attribute and error class when input is valid
          $(element).removeAttr("aria-invalid").removeClass("error");
          // Remove error class from parent span
          $(element)
            .closest(".ef-input-wrapper")
            .removeClass("ef-input-error")
            // .removeAttr("style");
        }

        if ($(element).is("[component='select']")) {
          $(element).next(".ef-select").removeClass("ef-select-error");
        }

        if ($(element).is("[component='textarea']")) {
          $(element)
          .closest(".ef-textarea-wrapper")
          .removeClass("ef-textarea-error")
        }

        if ($(element).is("[component='department']")) {
          $(element).closest('.ef-deparment-element').find('.ef-department-view-single').removeClass("ef-department-error");
        }
      },
      invalidHandler: function (event, validator) {
        // Handle invalid form
        let errors = validator.numberOfInvalids();
        if (errors) {
          validator.errorList.forEach(function (error) {
            if ($(error.element).is("[component='input']")) {
              $(error.element).attr("aria-invalid", "true").addClass("error");
              $(error.element)
                .closest(".ef-input-wrapper")
                .addClass("ef-input-error");
            }

            if ($(error.element).is("[component='textarea']")) {
              $(error.element).closest(".ef-textarea-wrapper").addClass("ef-textarea-error");
            }
          });
        }
      },
    };

    // Merge default configuration with user-provided options
    const settings = $.extend(true, {}, defaultConfig, options);

    // Initialize validation on the selected form(s)
    this.each(function () {
      $(this).validate(settings);
    });

    // Allow chaining
    return this;
  };

  $.fn.serializeWithId = function() {
    var formArray = this.serializeArray();
    var serializedData = {};

    $.each(formArray, function() {
        // 使用 id 作为键
        var id = $('[name="' + this.name + '"]').attr('id');
        if (id) {
            serializedData[id] = this.value;
        }
    });

    return serializedData;
  };

  /**
   * HotkeyManager - 快捷键管理器
   * 负责管理和处理整个应用程序的键盘快捷键功能
   * 主要功能包括：
   * 1. 注册和注销快捷键
   * 2. 处理键盘事件
   * 3. 管理快捷键优先级
   * 4. 维护作用域状态
   */
  const HotkeyManager = (function () {
    // 存储所有已注册的处理程序
    const handlers = {};
    // 存储事件处理程序，按事件类型和优先级组织
    const eventHandlers = {};

    /**
     * 标准化组合键
     * @param {KeyboardEvent} e - 键盘事件对象
     * @returns {string} 标准化后的组合键字符串，例如: 'ctrl+alt+a'
     */
    function normalizeCombo(e) {
      const keys = [];
      // 检测并添加修饰键
      if (e.ctrlKey) keys.push('ctrl');
      if (e.altKey) keys.push('alt');
      if (e.shiftKey) keys.push('shift');
      if (e.metaKey) keys.push('cmd'); // 使用'cmd'以兼容macOS

      // 特殊按键的映射表
      const replacements = {
        ' ': 'space',
        'escape': 'esc',
        'arrowup': 'up',
        'arrowdown': 'down',
        'arrowleft': 'left',
        'arrowright': 'right',
        'delete': 'del',
        'backspace': 'backspace',
        'enter': 'enter',
        'tab': 'tab',
        'control': 'ctrl',
        'option': 'alt',
        'os': 'meta',
        'meta': 'cmd',
        'fn': 'fn'
      };

      // 转换按键名称为标准格式
      let key = (e.key || '').toLowerCase();
      key = replacements[key] || key;

      // 功能键列表
      const functionKeys = [
        'f1','f2','f3','f4','f5','f6','f7','f8','f9','f10','f11','f12',
        'home','end','insert','pageup','pagedown','pause','capslock',
        'numlock','scrolllock','printscreen','contextmenu'
      ];

      // 添加主键（非修饰键）
      if (!keys.includes(key)) keys.push(key);

      // 返回组合键字符串，例如: 'ctrl+alt+a'
      return keys.join('+');
    }

    /**
     * 注册快捷键
     * @param {string} key - 快捷键组合，例如: 'ctrl+a' 或 'shift+alt+b'
     * @param {Function} handler - 快捷键触发时的处理函数
     * @param {number} [priority=50] - 处理程序的优先级，数字越大优先级越高
     * @param {string} [eventType='click'] - 事件类型
     */
    function register(key, handler, priority = 50, eventType = 'click') {
      const combos = key.toLowerCase().split(',').map(k => k.trim());
      combos.forEach(k => {
        const eventKey = `${k}:${eventType}`;
        if (!eventHandlers[eventKey]) eventHandlers[eventKey] = [];
        eventHandlers[eventKey].push({ handler, priority });
        // 按优先级排序，优先级高的排在前面
        eventHandlers[eventKey].sort((a, b) => b.priority - a.priority);
      });
    }

    /**
     * 注销快捷键
     * @param {string} key - 要注销的快捷键组合
     * @param {Function} handler - 要注销的处理函数
     * @param {string} [eventType='click'] - 事件类型
     */
    function unregister(key, handler, eventType = 'click') {
      const k = key.toLowerCase();
      const eventKey = `${k}:${eventType}`;
      if (eventHandlers[eventKey]) {
        // 移除指定的处理函数
        eventHandlers[eventKey] = eventHandlers[eventKey].filter(h => h.handler !== handler);
        // 如果没有处理函数了，删除整个事件键
        if (eventHandlers[eventKey].length === 0) delete eventHandlers[eventKey];
      }
    }

    /**
     * 注销指定作用域的所有快捷键
     * @param {string} scopeSelector - 作用域选择器
     */
    function unregisterScope(scopeSelector) {
      for (const eventKey in eventHandlers) {
        // 移除不可见作用域的处理函数
        eventHandlers[eventKey] = eventHandlers[eventKey].filter(h => {
          return !h.scopeSelector || !$(h.scopeSelector).is(':visible');
        });
      }
    }

    /**
     * 处理键盘按下事件
     * @param {KeyboardEvent} e - 键盘事件对象
     */
    function handleKeydown(e) {
      const key = normalizeCombo(e);
      // 获取当前活动元素
      const activeElement = document.activeElement;
      // 遍历所有已注册的事件类型
      Object.keys(eventHandlers).forEach(registeredKey => {
        const [keyCombo, eventType] = registeredKey.split(':');
        if (keyCombo === key) {
          const handlers = eventHandlers[registeredKey];
          if (!handlers) return;
      
          // 按优先级顺序执行处理函数
          for (const { handler } of handlers) {
            // 检查处理函数是否与当前活动元素相关
            if (handler.element && activeElement !== handler.element && !$.contains(handler.element, activeElement) && !$.contains(activeElement, handler.element)) {
              continue; // 跳过与当前活动元素无关的处理函数
            }
            
            const result = handler();
            // 如果处理函数返回true，阻止事件冒泡并退出循环
            if (result === true) {
              e.preventDefault();
              break;
            }
          }
        }
      });
    }

    // 绑定键盘事件监听器
    $(document).on('keydown', handleKeydown);

    /**
     * 获取事件处理程序
     * @returns {Object} 事件处理程序对象
     */
    function getEventHandlers() {
      return eventHandlers;
    }

    // 返回公共API
    return {
      register,
      unregister,
      unregisterScope,
      normalizeCombo,
      getEventHandlers
    };
  })();

  /**
   * TableHotkeyManager - 表格快捷键管理器
   * 负责处理表格特有的快捷键行为，包括：
   * 1. 全选操作
   * 2. 复制粘贴
   * 3. 单元格导航
   */
  const TableHotkeyManager = (function () {
    /**
     * 初始化表格快捷键
     * @param {jQuery} $table - 表格jQuery对象
     * @param {Object} keyConfig - 快捷键配置
     */
    function initTableHotkeys($table, keyConfig) {
      const config = typeof keyConfig === 'string' ? JSON.parse(keyConfig) : keyConfig;
      
      // 遍历配置的快捷键
      Object.entries(config).forEach(([key, action]) => {
        const handler = createTableHandler($table, action);
        if (handler) {
          HotkeyManager.register(key, handler, 60, 'table-action');
        }
      });
    }

    /**
     * 创建表格操作处理函数
     * @param {jQuery} $table - 表格jQuery对象
     * @param {string} action - 操作类型
     * @returns {Function} 处理函数
     */
    function createTableHandler($table, action) {
      const handlers = {
        selectAll: () => {
          const selection = window.getSelection();
          const range = document.createRange();
          range.selectNodeContents($table[0]);
          selection.removeAllRanges();
          selection.addRange(range);
          return true;
        },
        copy: () => {
          const selection = window.getSelection();
          if (selection.rangeCount > 0) {
            const text = selection.toString();
            navigator.clipboard.writeText(text);
          }
          return true;
        },
        paste: async () => {
          try {
            const text = await navigator.clipboard.readText();
            const $activeCell = $table.find('td[data-cell-active="true"]');
            if ($activeCell.length) {
              $activeCell.text(text);
            }
          } catch (err) {
            console.error('粘贴失败:', err);
          }
          return true;
        },
        nextCell: () => {
          navigateCell($table, 'next');
          return true;
        },
        prevCell: () => {
          navigateCell($table, 'prev');
          return true;
        },
        moveUp: () => {
          // 检查当前是否有单元格处于编辑状态
          const $editingCell = $table.find('td[contenteditable="true"]');
          if ($editingCell.length > 0) {
            // 如果有单元格处于编辑状态，不拦截方向键事件，让浏览器默认行为生效
            return false;
          }
          navigateCell($table, 'up');
          return true;
        },
        moveDown: () => {
          // 检查当前是否有单元格处于编辑状态
          const $editingCell = $table.find('td[contenteditable="true"]');
          if ($editingCell.length > 0) {
            // 如果有单元格处于编辑状态，不拦截方向键事件，让浏览器默认行为生效
            return false;
          }
          navigateCell($table, 'down');
          return true;
        },
        moveLeft: () => {
          // 检查当前是否有单元格处于编辑状态
          const $editingCell = $table.find('td[contenteditable="true"]');
          if ($editingCell.length > 0) {
            // 如果有单元格处于编辑状态，不拦截方向键事件，让浏览器默认行为生效
            return false;
          }
          navigateCell($table, 'left');
          return true;
        },
        moveRight: () => {
          // 检查当前是否有单元格处于编辑状态
          const $editingCell = $table.find('td[contenteditable="true"]');
          if ($editingCell.length > 0) {
            // 如果有单元格处于编辑状态，不拦截方向键事件，让浏览器默认行为生效
            return false;
          }
          navigateCell($table, 'right');
          return true;
        },
        // 删除单元格内容
        deleteContent: () => {
          // 检查当前是否有单元格处于编辑状态
          const $editingCell = $table.find('td[contenteditable="true"]');
          if ($editingCell.length > 0) {
            // 如果有单元格处于编辑状态，不拦截Backspace事件，让浏览器默认行为生效
            return false;
          }
          
          const $activeCell = $table.find('td[data-cell-active="true"]');
          if ($activeCell.length) {
            $activeCell.text('');
          }
          return true;
        }
      };

      return handlers[action];
    }

    /**
     * 表格单元格导航
     * @param {jQuery} $table - 表格jQuery对象
     * @param {string} direction - 导航方向
     */
    function navigateCell($table, direction) {
      const $cells = $table.find('td[tabindex="0"]');
      const $activeCell = $table.find('td[data-cell-active="true"]');
      let nextIndex = 0;

      if ($activeCell.length) {
        const currentIndex = $cells.index($activeCell);
        const rowCount = $table.find('tr').length;
        const colCount = $table.find('tr:first td').length;

        switch (direction) {
          case 'next':
            nextIndex = (currentIndex + 1) % $cells.length;
            break;
          case 'prev':
            nextIndex = (currentIndex - 1 + $cells.length) % $cells.length;
            break;
          case 'up':
            nextIndex = (currentIndex - colCount + $cells.length) % $cells.length;
            break;
          case 'down':
            nextIndex = (currentIndex + colCount) % $cells.length;
            break;
          case 'left':
            nextIndex = (currentIndex - 1 + $cells.length) % $cells.length;
            break;
          case 'right':
            nextIndex = (currentIndex + 1) % $cells.length;
            break;
        }
      }

      $cells.attr('data-cell-active', 'false');
      $cells.eq(nextIndex).attr('data-cell-active', 'true').focus().trigger('click');
    }

    return {
      initTableHotkeys
    };
  })();

  /**
   * 设置表格单元格双击编辑时光标定位到文本末尾
   * 解决双击已有内容的单元格时，默认全选文本的问题
   */
  $(document).on('dblclick', '.ef-table td', function(event) {
    const $currentCell = $(this);
    
    // 设置当前单元格为可编辑状态
    $currentCell.attr('contenteditable', 'true');
    $currentCell.attr('data-cell-active', 'true');
    $currentCell.focus();
    
    // 将光标定位到文本末尾，而不是选中全部内容
    if (window.getSelection && document.createRange) {
      const range = document.createRange();
      const cellNode = $currentCell[0];
      // 检查单元格是否有子节点
      if (cellNode.childNodes.length > 0) {
        // 将光标定位到最后一个子节点的末尾
        const lastNode = cellNode.childNodes[cellNode.childNodes.length - 1];
        range.setStartAfter(lastNode);
        range.collapse(true);
      } else {
        // 如果没有子节点，就将光标定位到单元格的开始位置
        range.setStart(cellNode, 0);
        range.collapse(true);
      }
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
    }
  });

  // jQuery Plugin to bind key-press functionality
  $.fn.hotkeyManager = function () {
    const initKeyPressBindings = (container) => {
      // 初始化表格快捷键
      $(container).find('table[ef-table-hotkeys]').each(function() {
        const $table = $(this);
        // 默认的表格快捷键配置
        const defaultKeyConfig = {
          'backspace': 'deleteContent',
          'tab': 'nextCell',
          'shift+tab': 'prevCell',
          'up': 'moveUp',
          'down': 'moveDown',
          'left': 'moveLeft',
          'right': 'moveRight',
          'ctrl+a': 'selectAll',
          'ctrl+c': 'copy',
          'ctrl+v': 'paste'
        };
        
        // 获取用户自定义配置
        let keyConfig = $table.attr('data-table-keys');
        
        if (keyConfig) {
          // 合并用户配置和默认配置
          const userConfig = typeof keyConfig === 'string' ? JSON.parse(keyConfig) : keyConfig;
          keyConfig = {...defaultKeyConfig, ...userConfig};
        } else {
          // 使用默认配置
          keyConfig = defaultKeyConfig;
        }
        
        TableHotkeyManager.initTableHotkeys($table, keyConfig);
      });

      // 初始化key-map映射快捷键（JSON格式的精确映射）
      $(container).find('[key-map]').each(function () {
        const $el = $(this);
        const scopeSelector = $el.attr('key-scope') || 'body';
        const priority = parseInt($el.attr('key-priority') || 50);
        let keyMap;
        
        try {
          // 解析JSON格式的key-map属性
          keyMap = JSON.parse($el.attr('key-map'));
        } catch (e) {
          console.error('key-map解析错误:', e);
          return; // 解析失败则跳过此元素
        }
        
        // 遍历映射关系，为每个快捷键注册对应的事件处理函数
        Object.entries(keyMap).forEach(([key, eventType]) => {
          const normalizedKey = key.trim().toLowerCase();
          const normalizedEventType = eventType.trim();
          
          const handler = () => {
            if ($el.is(':visible') && $(scopeSelector).is(':visible')) {
              // 如果焦点在表单输入框，回车就提交表单
              if ($el.is('button[type="submit"], .submit-trigger') && normalizedKey === 'enter') {
                const form = $el.closest('form');
                if (form.length) {
                  form.submit(); // 提交表单
                  return true;
                }
              }

              // 如果是表格单元格，处理编辑功能
              if ($el.is('td') && normalizedKey === 'enter') {
                const cell = $el;
                // 检查是否为活动单元格
                const isActive = cell.attr('data-cell-active') === 'true';
                
                if (isActive) {
                  // 直接触发绑定的事件
                  $el.trigger(normalizedEventType);
                  return true;
                } else {
                  return false;
                }
              }

              // 触发指定的事件类型
              $el.trigger(normalizedEventType);
              return true;
            }
            return false;
          };

          handler.scopeSelector = scopeSelector;
          // 存储元素引用，用于在handleKeydown中判断当前活动元素
          handler.element = this;
          
          // 注册快捷键处理函数
          HotkeyManager.register(normalizedKey, handler, priority, normalizedEventType);
        });
      });

      // 初始化普通快捷键
      $(container).find('[key-press]').each(function () {
        // 跳过已经使用key-map的元素，避免重复绑定
        if ($(this).attr('key-map')) return;
        
        const keys = $(this).attr('key-press').split(',').map(k => k.trim().toLowerCase());
        const scopeSelector = $(this).attr('key-scope') || 'body';
        const priority = parseInt($(this).attr('key-priority') || 50);
        // 支持多个事件类型，用逗号分隔
        const eventTypes = ($(this).attr('key-event') || 'click').split(',').map(e => e.trim());
        const $el = $(this);

        const createHandler = (eventType) => {
          const handler = () => {
            if ($el.is(':visible') && $(scopeSelector).is(':visible')) {
              // 如果焦点在表单输入框，回车就提交表单
              if ($el.is('button[type="submit"], .submit-trigger') && keys.includes('enter')) {
                const form = $el.closest('form');
                if (form.length) {
                  form.submit(); // 提交表单
                  return true;
                }
              }

              // 如果是表格单元格，处理编辑功能
              if ($el.is('td') && keys.includes('enter')) {
                const cell = $el;
                // 检查是否为活动单元格
                const isActive = cell.attr('data-cell-active') === 'true';
                
                if (isActive) {
                  // 直接触发绑定的事件
                  $el.trigger(eventType);
                  return true;
                } else {
                  return false;
                }
              }

              // 触发指定的事件类型
              $el.trigger(eventType);
              return true;
            }
            return false;
          };

          handler.scopeSelector = scopeSelector;
          // 存储元素引用，用于在handleKeydown中判断当前活动元素
          handler.element = this;
          return handler;
        };

        // 为每个键和每个事件类型注册处理函数
        keys.forEach(key => {
          eventTypes.forEach(eventType => {
            const handler = createHandler(eventType);
            HotkeyManager.register(key, handler, priority, eventType);
          });
        });
      });
    };

    // 自动绑定 key-press 功能
    return this.each(function () {
      initKeyPressBindings(this);
    });
  };

  // 重写 jQuery show/hide/append 方法，自动绑定/解绑
  (function ($) {
    const oldShow = $.fn.show;
    const oldHide = $.fn.hide;
    const oldAppend = $.fn.append;

    $.fn.show = function () {
      this.each(function () {
        const $el = $(this);
        if ($el.attr('key-autobind')) {
          $el.hotkeyManager();
        }
      });
      return oldShow.apply(this, arguments);
    };

    $.fn.hide = function () {
      this.each(function () {
        const $el = $(this);
        if ($el.attr('key-autobind')) {
          HotkeyManager.unregisterScope(this);
        }
      });
      return oldHide.apply(this, arguments);
    };

    $.fn.append = function () {
      const result = oldAppend.apply(this, arguments);
      // 检查添加的元素是否包含key-press属性
      const $added = $(arguments[0]);
      if ($added.attr('key-press') || $added.find('[key-press]').length) {
        $added.hotkeyManager();
      }
      return result;
    };

    // 确保在使用droppable之前就重写_drop方法
    if ($.ui && $.ui.droppable) {
      const oldDrop = $.ui.droppable.prototype._drop;
      $.ui.droppable.prototype._drop = function(event, ui) {
        console.log('droppable重写');
        const result = oldDrop.apply(this, arguments);
        const $dropped = ui.draggable;
        // 检查拖拽的元素是否需要绑定快捷键
        if ($dropped.attr('key-press') || $dropped.find('[key-press]').length) {
          $dropped.hotkeyManager();
        }
        return result;
      };
    }
    // 初始化调试功能
    $(document).ready(function() {
      initKeyDebug();
    });
  })(jQuery);
})(jQuery);
