(function() {
  'use strict';

  var activeComponent = null;

  $(document).on('componentSelected', function(e, component) {
    activeComponent = component;
  });

  $(document).on('componentDeselected', function() {
    activeComponent = null;
  });

  function getWidgetHtml(fieldType) {
    switch (fieldType) {
      case 'text':
        return '<div class="ef-textarea-wrapper ef-textarea-rounded"><textarea class="ef-textarea" rows="3" style="min-height: 82px;"></textarea></div>';
      case 'boolean':
        return '<button type="button" role="switch" aria-checked="false" class="ef-switch ef-switch-type-circle"><span class="ef-switch-handle"><span class="ef-switch-handle-icon"></span></span></button>';
      default:
        return '<span class="ef-input-wrapper ef-input-rounded ef-input-h36" style="height: 36px;"><input type="text" class="ef-input ef-input-size-medium" clearable="true"></span>';
    }
  }

  function bindFieldToRow($row, fieldName, fieldType, label) {
    $row.find('.ef-form-label').each(function() {
      $(this).attr('data-field-name', fieldName).find('label').text(label);
    });
    $row.find('.ef-form-widget').each(function() {
      var $widget = $(this);
      $widget.attr('data-field-name', fieldName).attr('data-field-type', fieldType);
      var hasRealContent = $widget.children().not('.ef-component-close-btn').not('.unbound-placeholder').length > 0;
      if (!hasRealContent) {
        $widget.empty();
        $widget.append(getWidgetHtml(fieldType));
      }
    });
    if ($row.find('> .drag-handle').length === 0) {
      $row.prepend('<div class="drag-handle" draggable="true"><i class="fa fa-grip-vertical"></i></div>');
    }
  }

  function findExistingRowByType(fieldType) {
    var $rows = $('.editor-field-row');
    var $match = $rows.filter(function() {
      return $(this).find('.ef-form-widget').data('field-type') === fieldType;
    });
    if ($match.length) return $match.first();
    var $any = $rows.filter(function() {
      return $(this).find('.ef-form-widget').data('field-type');
    });
    return $any.length ? $any.first() : null;
  }

  function createBoundRow(fieldName, fieldType, label) {
    var $template = findExistingRowByType(fieldType);
    var $newRow;

    if ($template) {
      $newRow = $template.clone(true, true);
      $newRow.removeAttr('style').removeClass('dragging drop-target');
    } else {
      $newRow = $([
        '<div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal editor-field-row">',
          '<div class="ef-col ef-col-8 ef-form-item-label-col editor-col">',
            '<div class="ef-component ef-form-label">',
              '<label class="ef-form-item-label"></label>',
            '</div>',
          '</div>',
          '<div class="ef-col ef-col-16 ef-form-item-wrapper-col editor-col">',
            '<div class="ef-form-item-content-wrapper">',
              '<div class="ef-form-item-content ef-form-item-content-flex">',
                '<div class="ef-component ef-form-widget">',
                '</div>',
              '</div>',
            '</div>',
          '</div>',
        '</div>'
      ].join(''));
      $newRow.prepend('<div class="drag-handle" draggable="true"><i class="fa fa-grip-vertical"></i></div>');
    }

    bindFieldToRow($newRow, fieldName, fieldType, label);

    var $itemBlock = $('.item-block').last();
    if ($itemBlock.length) {
      $itemBlock.closest('.ef-row').before($newRow);
    } else {
      $('.section-content').append($newRow);
    }

    if (window.$.alert) {
      window.$.alert.success('\u5df2\u7ed1\u5b9a\u5b57\u6bb5: ' + label);
    }
  }

  $(document).on('click', '.model-properties table tr td', function(e) {
    e.stopPropagation();

    var $td = $(this);
    var $tr = $td.closest('tr');
    var fieldName = $tr.data('field-name');
    var fieldType = $tr.data('field-type');
    var label = $tr.data('label');
    var isLabelCell = $td.index() === 0;

    var $targetRow = null;
    if (activeComponent) {
      $targetRow = $(activeComponent).closest('.editor-field-row');
    }
    if (!$targetRow || !$targetRow.length) {
      $targetRow = $('.unbound-placeholder').closest('.editor-field-row').first();
    }
    if (!$targetRow || !$targetRow.length) {
      var $selectedCol = window.__selectedEmptyCol__ ? $(window.__selectedEmptyCol__) : null;
      if ($selectedCol && $selectedCol.length) {
        $targetRow = $selectedCol.closest('.editor-field-row');
      }
    }

    if ($targetRow && $targetRow.length) {
      if (isLabelCell) {
        var $label = $targetRow.find('.ef-form-label');
        if (!$label.length) {
          $targetRow.find('.ef-form-item-label-col')
            .append('<div class="ef-component ef-form-label"><label class="ef-form-item-label"></label></div>');
          $label = $targetRow.find('.ef-form-label');
        }
        $label.attr('data-field-name', fieldName).find('label').text(label);
        var $widget = $targetRow.find('.ef-form-widget');
        if ($widget.length) {
          $widget.attr('data-field-name', fieldName);
        }
      } else {
        var $widget = $targetRow.find('.ef-form-widget');
        if (!$widget.length) {
          $targetRow.find('.ef-form-item-content-flex')
            .append('<div class="ef-component ef-form-widget"></div>');
        }
        bindFieldToRow($targetRow, fieldName, fieldType, label);
      }

      // 清除空列选中状态
      $('.editor-col.selected').removeClass('selected');
      window.__selectedEmptyCol__ = null;
    } else {
      createBoundRow(fieldName, fieldType, label);
    }

    if (window.$.alert) {
      window.$.alert.success('\u5df2\u7ed1\u5b9a\u5b57\u6bb5: ' + label);
    }
  });
})();
