/**
 * 撤销/重做管理器
 * 用于管理View Editor的操作历史记录
 */
class UndoRedoManager {
  constructor(maxSteps = 50) {
    this.maxSteps = maxSteps;
    this.history = [];
    this.currentIndex = -1;
    this.isRecording = true;
    
    // 从meta标签获取配置
    const undoStepsMeta = document.querySelector('meta[name="view-editor-undo-steps"]');
    if (undoStepsMeta) {
      this.maxSteps = parseInt(undoStepsMeta.content) || 50;
    }
    
    console.log(`UndoRedoManager initialized with ${this.maxSteps} max steps`);
  }
  
  /**
   * 记录操作状态
   * @param {string} action - 操作类型
   * @param {Object} data - 操作数据
   */
  recordAction(action, data) {
    if (!this.isRecording) return;
    
    // 获取当前画布状态
    const canvasState = this.captureCanvasState();
    
    const record = {
      timestamp: Date.now(),
      action: action,
      data: data,
      canvasState: canvasState
    };
    
    // 如果当前不在历史记录的末尾，删除后面的记录
    if (this.currentIndex < this.history.length - 1) {
      this.history = this.history.slice(0, this.currentIndex + 1);
    }
    
    // 添加新记录
    this.history.push(record);
    this.currentIndex++;
    
    // 限制历史记录数量
    if (this.history.length > this.maxSteps) {
      this.history.shift();
      this.currentIndex--;
    }
    
    // 更新按钮状态
    this.updateButtonStates();
    
    console.log(`Recorded action: ${action}`, record);
  }
  
  /**
   * 撤销操作
   */
  undo() {
    if (!this.canUndo()) return false;
    
    this.currentIndex--;
    
    if (this.currentIndex >= 0) {
      const record = this.history[this.currentIndex];
      this.restoreCanvasState(record.canvasState);
      console.log(`Undid action: ${record.action}`);
    } else {
      // 恢复到初始状态
      this.restoreToInitialState();
      console.log('Restored to initial state');
    }
    
    this.updateButtonStates();
    return true;
  }
  
  /**
   * 重做操作
   */
  redo() {
    if (!this.canRedo()) return false;
    
    this.currentIndex++;
    const record = this.history[this.currentIndex];
    this.restoreCanvasState(record.canvasState);
    
    this.updateButtonStates();
    console.log(`Redid action: ${record.action}`);
    return true;
  }
  
  /**
   * 检查是否可以撤销
   */
  canUndo() {
    return this.currentIndex >= 0;
  }
  
  /**
   * 检查是否可以重做
   */
  canRedo() {
    return this.currentIndex < this.history.length - 1;
  }
  
  /**
   * 捕获当前画布状态
   */
  captureCanvasState() {
    const canvas = $('#canvas');
    if (canvas.length === 0) return null;
    
    return {
      html: canvas.html(),
      timestamp: Date.now()
    };
  }
  
  /**
   * 恢复画布状态
   */
  restoreCanvasState(state) {
    if (!state) return;
    
    this.isRecording = false; // 暂停记录
    
    try {
      const canvas = $('#canvas');
      canvas.html(state.html);
      
      // 重新初始化拖拽功能
      if (window.viewEditor && window.viewEditor.initDraggableDroppable) {
        window.viewEditor.initDraggableDroppable();
      }
      
      // 重新绑定事件
      this.rebindEvents();
      
    } catch (error) {
      console.error('Error restoring canvas state:', error);
    } finally {
      this.isRecording = true; // 恢复记录
    }
  }
  
  /**
   * 恢复到初始状态
   */
  restoreToInitialState() {
    this.isRecording = false;
    
    try {
      const canvas = $('#canvas');
      
      // 生成随机ID
      const sectionId = Math.floor(Math.random() * 1000000000);
      
      // 使用正确的初始状态HTML结构
      const initialState = `
        <button class="add-section-button" style="right: 319.67px;">
          <i class="fa-solid fa-plus"></i>
        </button>
        <div class="section active" id="${sectionId}">
          <div class="section-controls">
            <button class="btn-toggle-header" title=t('undoRedoJs.js1')><i class="fa-solid fa-eye"></i></button>
            <button class="btn-toggle-collapse" title=t('undoRedoJs.js2')><i class="fa-solid fa-chevron-up"></i></button>
          </div>
          <div class="section-header">
            <button class="btn-add">
              <i class="fa-solid fa-plus" style="font-size: 1em;"></i>
            </button>
            <button class="btn-layout">
              <i class="fa-solid fa-grip" style="font-size: 1em;"></i>
            </button>
            <button class="btn-close">
              <i class="fa-solid fa-times" style="font-size: 1em;"></i>
            </button>
          </div>
          <div class="section-content ui-droppable"></div>
        </div>
      `;
      canvas.html(initialState);
      
      // 重新初始化
      if (window.viewEditor && window.viewEditor.initDraggableDroppable) {
        window.viewEditor.initDraggableDroppable();
      }
      
    } catch (error) {
      console.error('Error restoring to initial state:', error);
    } finally {
      this.isRecording = true;
    }
  }
  
  /**
   * 重新绑定事件
   */
  rebindEvents() {
    // 重新绑定表格单元格选择事件
    $('.ef-table td, .ef-table th').off('mousedown mousemove click');
    
    // 这里可以添加更多事件重新绑定逻辑
    if (window.viewEditor && window.viewEditor.bindTableEvents) {
      window.viewEditor.bindTableEvents();
    }
  }
  
  /**
   * 更新撤销/重做按钮状态
   */
  updateButtonStates() {
    const undoBtn = $('.toolbar-btn.previous-step');
    const redoBtn = $('.toolbar-btn.next-step');
    
    if (this.canUndo()) {
      undoBtn.removeClass('disabled').prop('disabled', false);
    } else {
      undoBtn.addClass('disabled').prop('disabled', true);
    }
    
    if (this.canRedo()) {
      redoBtn.removeClass('disabled').prop('disabled', false);
    } else {
      redoBtn.addClass('disabled').prop('disabled', true);
    }
  }
  
  /**
   * 清空历史记录
   */
  clear() {
    this.history = [];
    this.currentIndex = -1;
    this.updateButtonStates();
    console.log('History cleared');
  }
  
  /**
   * 获取历史记录信息
   */
  getHistoryInfo() {
    return {
      total: this.history.length,
      current: this.currentIndex,
      canUndo: this.canUndo(),
      canRedo: this.canRedo(),
      maxSteps: this.maxSteps
    };
  }
}

// 全局实例
window.undoRedoManager = null;

// 初始化函数
function initUndoRedoManager() {
  if (!window.undoRedoManager) {
    window.undoRedoManager = new UndoRedoManager();
    
    // 绑定撤销/重做按钮事件
    $('.toolbar-btn.previous-step').on('click', function() {
      if (!$(this).hasClass('disabled')) {
        window.undoRedoManager.undo();
      }
    });
    
    $('.toolbar-btn.next-step').on('click', function() {
      if (!$(this).hasClass('disabled')) {
        window.undoRedoManager.redo();
      }
    });
    
    // 绑定键盘快捷键
    $(document).on('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        window.undoRedoManager.undo();
      } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
        e.preventDefault();
        window.undoRedoManager.redo();
      }
    });
    
    console.log('UndoRedoManager initialized');
  }
}

// 自动初始化
$(document).ready(function() {
  initUndoRedoManager();
});