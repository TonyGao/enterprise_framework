(function() {
  'use strict';

  let contextMenu = null;
  let currentRow = null;

  function createContextMenu() {
    const menuHTML = `
      <div id="field-row-context-menu" class="context-menu" style="display: none;">
        <div class="menu-item" data-action="insert-row-above">
            <i class="fa-solid fa-arrow-up"></i>
            <span>向上插入行</span>
        </div>
        <div class="menu-item" data-action="insert-row-below">
            <i class="fa-solid fa-arrow-down"></i>
            <span>向下插入行</span>
        </div>
        <div class="menu-separator"></div>
        <div class="menu-item" data-action="delete-row">
            <i class="fa-solid fa-trash-can"></i>
            <span>删除行</span>
        </div>
      </div>
    `;

    $('body').append(menuHTML);
    contextMenu = $('#field-row-context-menu');

    contextMenu.on('click', '.menu-item', function(e) {
      e.stopPropagation();
      const action = $(this).data('action');
      executeAction(action);
      hideContextMenu();
    });
  }

  function showContextMenu(e, row) {
    if (!contextMenu) {
      createContextMenu();
    }

    currentRow = row;

    const menuWidth = 180;
    const menuHeight = 160;
    let left = e.pageX;
    let top = e.pageY;

    if (left + menuWidth > $(window).width()) {
      left = e.pageX - menuWidth;
    }
    if (top + menuHeight > $(window).height()) {
      top = e.pageY - menuHeight;
    }

    contextMenu.css({
      display: 'block',
      left: left + 'px',
      top: top + 'px'
    });
  }

  function hideContextMenu() {
    if (contextMenu) {
      contextMenu.hide();
    }
    currentRow = null;
  }

  function createEmptyFieldRow($template) {
    const $newRow = $template.clone(true, true);

    $newRow.find('.ef-form-label').each(function() {
      const $label = $(this);
      $label.removeAttr('data-field-name data-placeholder data-required data-required-bg');
      $label.find('label').text('-- 未绑定 --');
    });

    $newRow.find('.ef-form-widget').each(function() {
      const $widget = $(this);
      $widget.removeAttr('data-field-name data-field-type');
      $widget.empty();
      $widget.append('<div class="unbound-placeholder">从数据源绑定字段</div>');
    });

    return $newRow;
  }

  function executeAction(action) {
    if (!currentRow) return;

    const $row = $(currentRow);
    const $parent = $row.parent();

    switch (action) {
      case 'delete-row':
        if ($parent.find('.editor-field-row').length <= 1) {
          return;
        }
        $row.remove();
        break;

      case 'insert-row-above':
        $row.before(createEmptyFieldRow($row));
        break;

      case 'insert-row-below':
        $row.after(createEmptyFieldRow($row));
        break;
    }
  }

  function initFieldRowContextMenu() {
    $(document).on('contextmenu', '.editor-field-row', function(e) {
      e.preventDefault();
      e.stopPropagation();
      showContextMenu(e, this);
    });

    $(document).on('mousedown', function(e) {
      if (contextMenu && contextMenu.is(':visible') && !$(e.target).closest('#field-row-context-menu').length) {
        hideContextMenu();
      }
    });

    $(document).on('keydown', function(e) {
      if (e.key === 'Escape') {
        hideContextMenu();
      }
    });
  }

  $(document).ready(function() {
    initFieldRowContextMenu();
  });
})();
