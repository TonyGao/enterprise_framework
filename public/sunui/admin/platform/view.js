// jQuery Form Plugin 未随项目加载，提供兼容的 ajaxSubmit 实现
if (typeof $.fn.ajaxSubmit !== "function") {
  $.fn.ajaxSubmit = function (options) {
    options = options || {};
    var $form = this;
    var validator = $form.data("validator");
    if (validator && !validator.form()) {
      return this;
    }
    $.ajax({
      url: $form.attr("action") || window.location.href,
      type: ($form.attr("method") || "POST").toUpperCase(),
      data: $form.serialize(),
      dataType: "text",
      success: function (response) {
        if (typeof options.success === "function") {
          options.success(response);
        }
      },
      error: function (xhr) {
        if (typeof options.error === "function") {
          options.error(xhr);
        }
      }
    });
    return this;
  };
}

$(document).ready(function () {
  let alert = new Alert($('.app-content-container'));
  let createPayload = {
    parent: '',
    type: ''
  };
  let generalConfigPicker = null;
  let themeColorPicker = null;

  function initGeneralConfigPicker() {
    if (generalConfigPicker || !window.ColorPicker) return;
    generalConfigPicker = new ColorPicker({
      container: document.body,
      defaultColor: $('#generalConfigRequiredBgText').val() || '#FFF2E8',
      onChange: function(color) {
        $('#generalConfigRequiredBgPreview').css('background-color', color);
        $('#generalConfigRequiredBgText').val(color);
      }
    });
    $(document).on('click', '#generalConfigRequiredBgTrigger', function(e) {
      e.stopPropagation();
      if (generalConfigPicker) {
        generalConfigPicker.open(this);
      }
    });
  }

  function initThemeColorPicker() {
    if (themeColorPicker || !window.ColorPicker) return;
    themeColorPicker = new ColorPicker({
      container: document.body,
      defaultColor: $('#generalConfigThemeColorText').val() || '#165DFF',
      onChange: function(color) {
        $('#generalConfigThemeColorPreview').css('background-color', color);
        $('#generalConfigThemeColorText').val(color);
      }
    });
    $(document).on('click', '#generalConfigThemeColorTrigger', function(e) {
      e.stopPropagation();
      if (themeColorPicker) {
        themeColorPicker.open(this);
      }
    });
  }

  async function loadGeneralConfig() {
    let route = new Route();
    let uri = await route.generate('api_platform_view_get_general_config');
    ajax({
      url: uri.path,
      method: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (resp.data && resp.data.requiredBg) {
          const bg = resp.data.requiredBg;
          $('#generalConfigRequiredBgPreview').css('background-color', bg);
          $('#generalConfigRequiredBgText').val(bg);
          if (generalConfigPicker) generalConfigPicker.setColor(bg);
        }
        if (resp.data && resp.data.themeColor) {
          const c = resp.data.themeColor;
          $('#generalConfigThemeColorPreview').css('background-color', c);
          $('#generalConfigThemeColorText').val(c);
          if (themeColorPicker) themeColorPicker.setColor(c);
        }
      }
    });
  }

  // 通用配置按钮
  $('#generalConfigBtn').on('click', function(e) {
    e.preventDefault();
    initGeneralConfigPicker();
    initThemeColorPicker();
    loadGeneralConfig();
    $().showDrawer('generalConfigDrawer');
  });

  // 文本输入同步到预览
  $('#generalConfigRequiredBgText').on('input', function() {
    const v = $(this).val();
    if (/^#[0-9a-fA-F]{6}$/.test(v)) {
      $('#generalConfigRequiredBgPreview').css('background-color', v);
      if (generalConfigPicker) generalConfigPicker.setColor(v);
    }
  });
  $('#generalConfigThemeColorText').on('input', function() {
    const v = $(this).val();
    $('#generalConfigThemeColorPreview').css('background-color', v);
    if (themeColorPicker) themeColorPicker.setColor(v);
  });

  // 保存通用配置
  $('#generalConfigSaveBtn').on('click', async function() {
    const bg = $('#generalConfigRequiredBgText').val();
    const themeColor = $('#generalConfigThemeColorText').val();
    let route = new Route();
    let uri = await route.generate('api_platform_view_save_general_config');
    ajax({
      url: uri.path,
      method: 'POST',
      contentType: 'application/json',
      data: { requiredBg: bg, themeColor: themeColor },
      success: function(resp) {
        $().hideDrawer('generalConfigDrawer');
        alert.success(t('viewJs.js1'), { percent: '280px', title: t('viewJs.js2'), closable: false });
        setTimeout(function() { $('.app-alert').remove(); }, 3000);
      },
      error: function(xhr) {
        alert.error(t('viewJs.js3'), { percent: '280px', title: t('viewJs.js2'), closable: true });
      }
    });
  });
  
  // 监听视图编辑器按钮的点击事件
  $("#viewEditor").on("click", async function (event) {
    event.preventDefault();
    
    // 获取当前选中的视图节点
    const selectedNode = $(".tree-text-content.chosen");
    if (!selectedNode.length || selectedNode.attr("type") !== "view") {
      alert.error(t('viewJs.js4'), { percent: '40%', title: t('viewJs.js2'), closable: true });
      return;
    }
    
    // 获取选中节点的ID
    const viewId = selectedNode.attr("id");
    
    // 生成编辑器URL
    let route = new Route();
    let uri = await route.generate("platform_view_editor", { id: viewId });
    if (uri && uri.path) {
      // 在新标签页中打开编辑器
      window.open(uri.path, '_blank');
    } else {
      alert.error(t('viewJs.js5'), { percent: '40%', title: t('viewJs.js6'), closable: true });
    }
  });
  // 加载视图详情
  async function loadViewDetail(viewId) {
    let route = new Route();
    let uri = await route.generate('platform_view_detail');
    ajax({
      url: uri.path,
      method: 'GET',
      data: { id: viewId },
      dataType: 'html',
      success: function(html) {
        $('.right-content').html(html);
        // 同步 URL，刷新/前进后退可保留当前视图（popstate/初始加载时跳过避免重复入栈）
        if (!skipUrlPush) {
          pushViewUrl(viewId);
        } else {
          skipUrlPush = false;
        }
        // 删除视图按钮
        $('.right-content').off('click', '[data-delete-view]').on('click', '[data-delete-view]', function() {
          var viewId = $(this).data('view-id');
          $('.right-content').off('click', '[data-confirm-delete-view]').on('click', '[data-confirm-delete-view]', function() {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = t('viewJs.js7');
            fetch('/api/admin/platform/view/' + viewId + '/delete', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
              if (json.code === 200) {
                $('#deleteViewModal').hide();
                // 清理 URL 中的 viewId，回到视图管理首页
                history.replaceState(null, '', window.location.pathname);
                window.location.reload();
              } else {
                alert(t('viewJs.js8') + (apiMsg(json) || t('viewJs.js9')));
                btn.disabled = false;
                btn.innerHTML = t('viewJs.js10');
              }
            })
            .catch(function(err) {
              alert(t('viewJs.js11') + err.message);
              btn.disabled = false;
              btn.innerHTML = t('viewJs.js10');
            });
          });
          $('#deleteViewModal').show();
        });
        $('.right-content').off('click', '[data-close-delete-modal]').on('click', '[data-close-delete-modal]', function() {
          $('#deleteViewModal').hide();
        });
      },
      error: function() {
        alert.error(t('viewJs.js12'), { percent: '280px', title: t('viewJs.js2'), closable: true });
      }
    });
  }

  // 供模板调用的编辑视图函数
  window.loadEditView = function(viewId) {
    let route = new Route();
    route.generate('platform_view_edit_view').then(function(uri) {
      ajax({
        url: uri.path,
        method: 'GET',
        data: { id: viewId },
        dataType: 'html',
        success: function(data) {
          $('.right-content').html(data);
          $('.right-content form').on('submit', function(e) {
            $(this).ajaxSubmit({
              success: function(response) {
                if (response.includes(t('viewJs.js13'))) {
                  window.location.reload();
                } else {
                  $('.right-content').html(response);
                }
              },
              error: function(xhr) {
                if (xhr.responseJSON && apiMsg(xhr)) {
                  alert.error(apiMsg(xhr), { percent: '40%', title: t('viewJs.js14'), closable: true });
                } else {
                  alert.error(t('viewJs.js15'), { percent: '40%', title: t('viewJs.js14'), closable: true });
                }
              }
            });
            return false;
          });
        },
        error: function() {
          alert.error(t('viewJs.js16'), { percent: '280px', title: t('viewJs.js2'), closable: true });
        }
      });
    });
  };

  $(".tree-text-content").on("click", function (event) {
    let thisChosen = false;
    let type = $(this).attr("type");
    if ($(this).hasClass("chosen")) {
      thisChosen = true;
    }
    $(".tree-text-content.chosen").removeClass("chosen");
    if (!thisChosen) {
      $(this).addClass("chosen");
      createPayload.parent = $(this).attr("id");
      createPayload.type = type;
      // 视图节点：加载详情
      if (type === 'view') {
        loadViewDetail($(this).attr('id'));
      }
    }
  })
  // 监听创建文件夹按钮的点击事件
  $("#createFolder").on("click", async function (event) {
    event.preventDefault();

    // 获取当前选中的树节点作为父目录
    const selectedNode = $(".tree-text-content.chosen");
    if (selectedNode.length) {
      const nodeType = selectedNode.attr("type");
      if (nodeType === "folder" || nodeType === "root") {
        createPayload.parent = selectedNode.attr("id");
        createPayload.type = nodeType;
      }
    }

    let route = new Route();
    let uri = await route.generate("platform_view_add_folder");
    ajax({
      url: uri.path,
      method: "GET",
      data: createPayload,
      async: false,
      dataType: "html",
            success: function (data) {
        $(".right-content").html(data);
        
        // 在 form action 中带上 parent 参数，确保 POST 提交时也能识别父目录
        const form = $(".right-content form");
        if (createPayload.parent) {
          const action = form.attr("action") || "";
          const sep = action.includes("?") ? "&" : "?";
          form.attr("action", action + sep + "parent=" + encodeURIComponent(createPayload.parent));
        }
        // 监听表单提交
        form.on("submit", function(e) {
          $(this).ajaxSubmit({
            success: function(response) {
              if (response.includes(t('viewJs.js13'))) {
                // 成功提交后刷新页面
                window.location.reload();
              } else {
                $(".right-content").html(response);
              }
            },
            error: function(xhr) {
              if (xhr.responseJSON && apiMsg(xhr)) {
                alert.error(apiMsg(xhr), { percent: '40%', title: t('viewJs.js17'), closable: true });
              } else {
                alert.error(t('viewJs.js18'), { percent: '40%', title: t('viewJs.js17'), closable: true });
              }
            }
          });
          return false;
        });
      },
      error: function (xhr, status, error) {
        // 错误处理，显示错误信息
        let errorMsg = t('viewJs.js19');
        if (xhr.responseJSON && apiMsg(xhr)) {
          errorMsg = apiMsg(xhr);
        }
        console.error(t('viewJs.js20')+ errorMsg);
        alert.error(errorMsg, { percent: '40%', title: t('viewJs.js6'), closable: true });
      }
    });
  })
;

  $("#createView").on("click", async function (event) {
    event.preventDefault();

    // 获取当前选中的树节点作为父目录
    const selectedNode = $(".tree-text-content.chosen");
    if (selectedNode.length) {
      const nodeType = selectedNode.attr("type");
      if (nodeType === "folder" || nodeType === "root") {
        createPayload.parent = selectedNode.attr("id");
        createPayload.type = nodeType;
      }
    }

    let route = new Route();
    let uri = await route.generate("platform_view_add_view");
    ajax({
      url: uri.path,
      method: "GET",
      data: createPayload,
      async: false,
      dataType: "html",
      success: function (data) {
        $(".right-content").html(data);
        
        // 在 form action 中带上 parent 参数，确保 POST 提交时也能识别父目录
        const form = $(".right-content form");
        if (createPayload.parent) {
          const action = form.attr("action") || "";
          const sep = action.includes("?") ? "&" : "?";
          form.attr("action", action + sep + "parent=" + encodeURIComponent(createPayload.parent));
        }
        // 监听表单提交
        form.on("submit", function(e) {
          $(this).ajaxSubmit({
            success: function(response) {
              // AI 二次加工：控制器返回 JSON {code:200, data:{viewId, taskId}}
              try {
                var parsed = JSON.parse(response);
                if (parsed && parsed.code === 200 && parsed.data && parsed.data.taskId) {
                  if (window.EFViewAi && typeof window.EFViewAi.start === "function") {
                    window.EFViewAi.start(parsed.data.taskId, parsed.data.viewId);
                    return;
                  }
                }
              } catch (err) { /* 非 JSON 响应，走原有逻辑 */ }
              if (response.includes(t('viewJs.js13'))) {
                // 成功提交后刷新页面
                window.location.reload();
              } else {
                $(".right-content").html(response);
              }
            },
            error: function(xhr) {
              if (xhr.responseJSON && apiMsg(xhr)) {
                alert.error(apiMsg(xhr), { percent: '40%', title: t('viewJs.js17'), closable: true });
              } else {
                alert.error(t('viewJs.js21'), { percent: '40%', title: t('viewJs.js17'), closable: true });
              }
            }
          });
          return false;
        });
      },
      error: function (xhr, status, error) {
        // 错误处理，显示错误信息
        let errorMsg = t('viewJs.js22');
        if (xhr.responseJSON && apiMsg(xhr)) {
          errorMsg = apiMsg(xhr);
        }
        console.error(t('viewJs.js23')+ errorMsg);
        alert.error(errorMsg, { percent: '40%', title: t('viewJs.js6'), closable: true });
      }
    });
  })

  $("#editView").on("click", async function (event) {
    event.preventDefault();

    const selectedNode = $(".tree-text-content.chosen");
    if (!selectedNode.length || selectedNode.attr("type") !== "view") {
      alert.error(t('viewJs.js4'), { percent: '40%', title: t('viewJs.js2'), closable: true });
      return;
    }

    const viewId = selectedNode.attr("id");
    let route = new Route();
    let uri = await route.generate("platform_view_edit_view");
    ajax({
      url: uri.path,
      method: "GET",
      data: { id: viewId },
      async: false,
      dataType: "html",
      success: function (data) {
        $(".right-content").html(data);

        $(".right-content form").on("submit", function(e) {
          $(this).ajaxSubmit({
            success: function(response) {
              if (response.includes(t('viewJs.js13'))) {
                window.location.reload();
              } else {
                $(".right-content").html(response);
              }
            },
            error: function(xhr) {
              if (xhr.responseJSON && apiMsg(xhr)) {
                alert.error(apiMsg(xhr), { percent: '40%', title: t('viewJs.js14'), closable: true });
              } else {
                alert.error(t('viewJs.js15'), { percent: '40%', title: t('viewJs.js14'), closable: true });
              }
            }
          });
          return false;
        });
      },
      error: function (xhr, status, error) {
        let errorMsg = t('viewJs.js24');
        if (xhr.responseJSON && apiMsg(xhr)) {
          errorMsg = apiMsg(xhr);
        }
        console.error(t('viewJs.js25')+ errorMsg);
        alert.error(errorMsg, { percent: '40%', title: t('viewJs.js6'), closable: true });
      }
    });
  })

  // 重命名文件夹（弹窗）
  $("#renameFolder").on("click", async function (event) {
    event.preventDefault();

    const selectedNode = $(".tree-text-content.chosen");
    if (!selectedNode.length || selectedNode.attr("type") !== "folder") {
      alert.error(t('viewJs.js26'), { percent: '40%', title: t('viewJs.js2'), closable: true });
      return;
    }

    const folderId = selectedNode.attr("id");
    let route = new Route();
    let uri = await route.generate("platform_view_rename_folder");
    ajax({
      url: uri.path,
      method: "GET",
      data: { id: folderId },
      dataType: "html",
      success: function (data) {
        $('#renameFolderModalBody').html(data);
        openModal('renameFolderModal');
      },
      error: function (xhr, status, error) {
        let errorMsg = t('viewJs.js27');
        if (xhr.responseJSON && apiMsg(xhr)) {
          errorMsg = apiMsg(xhr);
        }
        alert.error(errorMsg, { percent: '40%', title: t('viewJs.js6'), closable: true });
      }
    });
  })

  // ===== 重命名文件夹弹窗表单提交 =====
  $('#renameFolderModalBody').on('submit', 'form', function(e) {
    e.preventDefault();
    var $form = $(this);
    $.ajax({
      url: $form.attr('action'),
      method: 'POST',
      data: $form.serialize(),
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          closeModal('renameFolderModal');
          window.location.reload();
        } else if (response.html) {
          $('#renameFolderModalBody').html(response.html);
        }
      },
      error: function(xhr) {
        var msg = t('viewJs.js28');
        if (xhr.responseJSON && apiMsg(xhr)) {
          msg = apiMsg(xhr);
        }
        alert.error(msg, { percent: '40%', title: t('viewJs.js29'), closable: true });
      }
    });
  });

  // ===== Tree Node Context Menu =====
  var treeContextMenu = $('#tree-context-menu');
  var ctxMenuItems = treeContextMenu.find('.menu-item');
  var ctxSepFolder = $('#ctx-sep-folder');
  var ctxSepView = $('#ctx-sep-view');
  var ctxSepDelete = $('#ctx-sep-delete');

  function hideContextMenu() {
    treeContextMenu.hide();
  }

  function showContextMenu(e, nodeData) {
    e.preventDefault();
    e.stopPropagation();

    // 先隐藏所有可切换项
    ctxMenuItems.hide();
    ctxSepFolder.hide();
    ctxSepView.hide();
    ctxSepDelete.hide();

    if (!nodeData) {
      // 空白区域右键
      ctxMenuItems.filter('[data-action="createFolder"]').show();
      ctxMenuItems.filter('[data-action="generalConfig"]').show();
    } else if (nodeData.type === 'root') {
      ctxMenuItems.filter('[data-action="createView"]').show();
      ctxMenuItems.filter('[data-action="createFolder"]').show();
    } else if (nodeData.type === 'folder') {
      ctxMenuItems.filter('[data-action="createView"]').show();
      ctxMenuItems.filter('[data-action="createFolder"]').show();
      ctxSepFolder.show();
      ctxMenuItems.filter('[data-action="renameFolder"]').show();
      ctxSepDelete.show();
      ctxMenuItems.filter('[data-action="deleteNode"]').show();
    } else if (nodeData.type === 'view') {
      ctxSepView.show();
      ctxMenuItems.filter('[data-action="viewEditor"]').show();
      ctxMenuItems.filter('[data-action="editView"]').show();
      ctxSepDelete.show();
      ctxMenuItems.filter('[data-action="deleteNode"]').show();
    }

    // 定位菜单
    var menuWidth = 180;
    var menuHeight = treeContextMenu.find('.menu-item:visible').length * 34 + 16;
    var x = e.clientX;
    var y = e.clientY;
    var winW = $(window).width();
    var winH = $(window).height();
    if (x + menuWidth > winW) x = winW - menuWidth - 8;
    if (y + menuHeight > winH) y = winH - menuHeight - 8;
    if (x < 0) x = 8;
    if (y < 0) y = 8;

    treeContextMenu.css({ left: x, top: y, display: 'block' });
  }

  // 右击树节点
  $('.common-tree-wrapper').on('contextmenu', '.tree-text-content', function(e) {
    // 选中该节点
    $('.tree-text-content.chosen').removeClass('chosen');
    $(this).addClass('chosen');
    createPayload.parent = $(this).attr('id');
    createPayload.type = $(this).attr('type');

    var nodeData = { type: $(this).attr('type'), id: $(this).attr('id') };
    showContextMenu(e, nodeData);
  });

  // 右击树的空白区域
  $('.common-tree-wrapper, .left-tree-wrapper').on('contextmenu', function(e) {
    // 如果点击的是空白区域而非节点
    if ($(e.target).closest('.tree-text-content').length) return;
    // 取消选中
    $('.tree-text-content.chosen').removeClass('chosen');
    createPayload.parent = '';
    createPayload.type = '';
    showContextMenu(e, null);
  });

  // 菜单项点击
  treeContextMenu.on('click', '.menu-item', function() {
    var action = $(this).data('action');
    hideContextMenu();
    switch (action) {
      case 'createView':
        $('#createView').trigger('click');
        break;
      case 'createFolder':
        $('#createFolder').trigger('click');
        break;
      case 'renameFolder':
        $('#renameFolder').trigger('click');
        break;
      case 'viewEditor':
        $('#viewEditor').trigger('click');
        break;
      case 'editView':
        $('#editView').trigger('click');
        break;
      case 'generalConfig':
        $('#generalConfigBtn').trigger('click');
        break;
      case 'deleteNode':
        showDeleteConfirm();
        break;
    }
  });

  // 点击菜单外部隐藏
  $(document).on('click', function(e) {
    if (treeContextMenu.is(':visible') && !$(e.target).closest('#tree-context-menu').length) {
      hideContextMenu();
    }
  });

  // ===== 删除确认弹窗 =====
  var _deleteNodeId = null;

  function showDeleteConfirm() {
    var chosen = $('.tree-text-content.chosen');
    if (!chosen.length) return;
    _deleteNodeId = chosen.attr('id');
    // 节点主名称 + 副名称（postscript/label）
    var nodeName = chosen.text().trim() || t('viewJs.js30');
    var $postscript = chosen.siblings('.postscript');
    if ($postscript.length) {
      nodeName += ' (' + $postscript.text().trim() + ')';
    }

    $('#delete-confirm-input').val('').trigger('input');
    $('#delete-confirm-error').hide();
    $('#delete-confirm-submit').prop('disabled', true).css('opacity', '0.5');
    $('#delete-confirm-message').html(
      '<strong style="color: #ff4d4f;">' + t('viewJs.irreversible') + '</strong>' + t('viewJs.noRecover') + '<br>' +
      t('viewJs.js31') + $('<span>').text(nodeName).html() + '</strong><br><br>' +
      t('viewJs.enter') + ' <strong style="color: #ff4d4f;">' + t('viewJs.confirmDelete') + '</strong>' + t('viewJs.toContinue')
    );
    openModal('deleteConfirmModal');
  }

  // 输入监听：只有输入"确认删除"才启用按钮
  $(document).on('input', '#delete-confirm-input', function() {
    var val = $(this).val().trim();
    if (val === t('viewJs.js32')) {
      $('#delete-confirm-submit').prop('disabled', false).css('opacity', '1');
      $('#delete-confirm-error').hide();
    } else {
      $('#delete-confirm-submit').prop('disabled', true).css('opacity', '0.5');
    }
  });

  // 确认删除
  $(document).on('click', '#delete-confirm-submit', function() {
    if ($(this).prop('disabled')) return;
    if (!_deleteNodeId) return;

    var $btn = $(this);
    $btn.prop('disabled', true).html(t('viewJs.js7'));

    $.ajax({
      url: '/api/admin/platform/view/' + _deleteNodeId + '/delete',
      method: 'POST',
      success: function(resp) {
        closeModal('deleteConfirmModal');
        _deleteNodeId = null;
        location.reload();
      },
      error: function(xhr) {
        var msg = t('viewJs.js33');
        try {
          var r = JSON.parse(xhr.responseText);
          if (r && r.message) msg = r.message;
        } catch(e) {}
        $('#delete-confirm-error').text(msg).show();
        $btn.prop('disabled', false).text(t('viewJs.js32'));
      }
    });
  });

  // 弹窗关闭时清除状态
  $(document).on('ef:modalClose', '#deleteConfirmModal', function() {
    _deleteNodeId = null;
  });

  // ===== End 删除确认弹窗 =====

  // 用 SortableJS 实现跨文件夹拖拽（原生支持嵌套容器 group）
  if (typeof Sortable !== 'undefined') {
    setTimeout(function() {
    var containers = document.querySelectorAll('.common-tree-wrapper .sub-tree-content');
    for (var i = 0; i < containers.length; i++) {
      new Sortable(containers[i], {
        group: {
          name: 'view-tree',
          pull: true,
          put: true
        },
        animation: 150,
        forceFallback: true,
        fallbackOnBody: true,
        scroll: false,
        direction: 'vertical',
        onStart: function () {
          // reveal 所有 hidden 容器供 SortableJS onMove 检测
          document.querySelectorAll('.sub-tree-content').forEach(function(el) {
            if (el.style.display === 'none') {
              el.style.display = '';
              var span = el.previousElementSibling;
              if (span && span.classList.contains('tree-indent')) {
                span.style.display = '';
              }
            }
            // 空容器内可能有空白文本节点导致 :empty 不匹配，
            // 直接设 min-height + min-width 确保有放置区
            if (!el.querySelector('li')) {
              el.style.minHeight = '20px';
              el.style.minWidth = 'calc(100% - 16px)';
            }
          });
        },
        onEnd: function (evt) {
          // 拖拽结束后清理 min-height，隐藏仍然空的容器
          document.querySelectorAll('.sub-tree-content').forEach(function(el) {
            el.style.minHeight = '';
            el.style.minWidth = '';
            if (!el.querySelector('li')) {
              el.style.display = 'none';
              var span = el.previousElementSibling;
              if (span && span.classList.contains('tree-indent')) {
                span.style.display = 'none';
              }
            }
          });
          var $item = $(evt.item);
          var $targetOl = $(evt.to);
          var $parentLi = $targetOl.closest('li');
          var $parentNode = $parentLi.find('.tree-text-content').first();
          var parentId = $parentNode.attr('type') === 'root' ? null : ($parentNode.attr('id') || null);
          var nodeId = $item.find('.tree-text-content').first().attr('id');
          if (!nodeId) return;
          var payload = { nodeId: nodeId, parentId: parentId };
          // 同容器排序：通过下一个兄弟的 id 定位插入位置
          if (evt.from === evt.to) {
            var nextSibling = evt.item.nextElementSibling;
            if (nextSibling) {
              var nextIdEl = nextSibling.querySelector('.tree-text-content');
              if (nextIdEl) {
                payload.siblingId = nextIdEl.getAttribute('id');
                delete payload.parentId;
              }
            }
          }
          ajax({
            url: '/api/admin/platform/view/move',
            method: 'POST',
            contentType: 'application/json',
            data: payload,
            error: function(xhr) {
              alert.error(t('viewJs.js34'), { percent: '280px', title: t('viewJs.js2'), closable: true });
              window.location.reload();
            }
          });
        }
      });
    }
    }, 100);
  }

  // ============ 视图详情 URL 同步（pushState / popstate） ============
  var skipUrlPush = false;

  function getUrlParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  function pushViewUrl(viewId) {
    var params = new URLSearchParams(window.location.search);
    if (viewId) {
      params.set('viewId', viewId);
    } else {
      params.delete('viewId');
    }
    var qs = params.toString();
    var url = window.location.pathname + (qs ? '?' + qs : '');
    try {
      if (url === window.location.pathname + window.location.search) {
        // URL 未变化：用 replaceState 避免产生重复历史条目
        window.history.replaceState({ viewId: viewId }, '', url);
      } else {
        window.history.pushState({ viewId: viewId }, '', url);
      }
    } catch (e) { /* 忽略 */ }
  }

  window.addEventListener('popstate', function (e) {
    var viewId = e.state && e.state.viewId;
    skipUrlPush = true;
    if (viewId) {
      $('.tree-text-content.chosen').removeClass('chosen');
      var $node = $('.tree-text-content[id="' + viewId + '"]');
      if ($node.length) {
        $node.addClass('chosen');
      }
      loadViewDetail(viewId);
    } else {
      // 回到首页
      window.location.reload();
    }
  });

  // 初始加载：URL 带 viewId 时直接打开对应视图详情
  var initialViewId = getUrlParam('viewId');
  if (initialViewId) {
    skipUrlPush = true;
    var $initNode = $('.tree-text-content[id="' + initialViewId + '"]');
    if ($initNode.length) {
      $initNode.addClass('chosen');
      loadViewDetail(initialViewId);
    } else {
      // 视图已不存在（可能被删除），清理 URL
      history.replaceState(null, '', window.location.pathname);
    }
  }
})