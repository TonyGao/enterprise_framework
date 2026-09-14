$(document).ready(async function () {
  /**
   * 选中的菜单，数据形式为对象数组
   * 对象内存储id, name
   */
  let choseMenus = [];
  $('.menu-title-button').on("click", function (e) {
    e.stopPropagation();
    let titleMenuId = $(this).attr('titleMenuId');
    $("#" + titleMenuId).show();
  });

  // 初始化sortable
  $(".sub-tree-content").sortable({
    connectWith: ".sub-tree-content", // 允许在树之间拖动
    handle: ".ef-group-filed-handler-wrapper", // 限定只能通过handle拖动
    placeholder: "sortable-placeholder", // 拖动时显示的占位符
    tolerance: "pointer", // 以鼠标指针为准
    items: "> li", // 只允许拖动直接子元素li
    start: function (event, ui) {
      // 获取最近的祖先级 .sub-tree-content 的宽度
      var parentWidth = ui.item.closest('.sub-tree-content').width();
      ui.placeholder.css({
        'min-width': parentWidth,
        'min-height': '20px'
      });
      // 同样应用到被拖动的项上
      ui.item.css('min-width', parentWidth);

      ui.item.addClass("dragging");
      $(".sortable-placeholder").css({
        "height": ui.helper.outerHeight() // 占位符的高度与拖动项一致
      });
    },
    stop: function (event, ui) {
      ui.item.removeClass("dragging");
    },
    update: function (event, ui) {
      // 在此可以处理后台更新逻辑
      console.log(t('menuJs.js1'));
      // 可以在这里发送新的排序数据到后端，更新数据库
    },
    receive: function (event, ui) {
      // var parentWidth = $(this).closest(".item-content.scroll-item").width();
      // $(this).css("min-width", '210px');
    }
  }).disableSelection(); // 禁用文本选择，增强用户体验

  $("body").on("click", ".sub-tree-content .node-name", function (e) {
    $(this).siblings(".org-icon").find(".ef-checkbox").trigger("click");
  })

  // 监听复选框的状态更改
  $("body").on("change", ".ef-checkbox-target", function () {
    const $checkbox = $(this);
    const $nodeName = $checkbox.closest(".item-original").find(".tree-text-content");
    const menuId = $nodeName.attr('id');
    const menuName = $nodeName.text();

    // 根据复选框状态来添加或移除菜单信息
    if ($checkbox.is(':checked')) {
        // 添加选中的菜单信息
        choseMenus.push({ id: menuId, name: menuName });
    } else {
        // 移除取消选中的菜单信息
        choseMenus = choseMenus.filter(menu => menu.id !== menuId);
    }

    console.log(choseMenus); // 查看选中的菜单信息
  });

  const route = new Route();
  let menu = await route.generate("platform_menu_new_cache");
  $("#create").on("click", function() {
    $.ajax({
      url: menu.path,
      type: menu.methods[0],
      async: false,
      dataType: "html",
      success: function(data) {
        $(".right-content").html(data);
      }
    })
  })

  $("#modify").on("click", async function() {
    if (choseMenus.length === 0) {
      $.alert.warning(t('menuJs.js2'), { title: t('menuJs.js3') });
      return;
    }
    if (choseMenus.length > 1) {
      $.alert.warning(t('menuJs.js4'), { title: t('menuJs.js3') });
      return;
    }
    const menuId = choseMenus[0].id;
    const editRoute = await route.generate("platform_menu_edit", { id: menuId });
    $.ajax({
      url: editRoute.path,
      type: editRoute.methods[0],
      async: false,
      dataType: "html",
      success: function(data) {
        $(".right-content").html(data);
      }
    })
  })
})
