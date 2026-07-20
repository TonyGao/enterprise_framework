/**
 * Shared Tree Collapse/Expand Logic
 * Used by: Employee, Department, Entity, Menu, View
 *
 * Each page must define:
 *   <div class="left-tree visible" id="leftTree">...</div>
 *   <div class="fold-icon-wrapper" id="collapseTree">...</div>
 *
 * The localStorage key is derived from the page pathname.
 * Button position is calculated dynamically from leftTree width.
 */

$(document).ready(function () {
  const leftTree = $("#leftTree");
  const btn = $("#collapseTree");

  if (!leftTree.length || !btn.length) {
    return;
  }

  const pageKey = 'tree_collapsed_' + window.location.pathname.replace(/[^a-zA-Z0-9]/g, '_');

  function positionButton(collapsed) {
    if (collapsed) {
      btn.css({ left: 0, transform: 'translate(0, -50%)' });
    } else {
      btn.css({ left: leftTree.outerWidth(), transform: 'translate(0, -50%)' });
    }
  }

  function setTreeCollapsed(collapsed) {
    const icon = btn.find("i");
    const insideWrapper = $(".inside-wrapper");

    if (collapsed) {
      leftTree.hide();
    } else {
      leftTree.show();
    }

    insideWrapper.toggleClass('tree-collapsed', collapsed);
    insideWrapper.toggleClass('tree-expanded', !collapsed);

    if (collapsed) {
      icon.removeClass("fa-chevron-left").addClass("fa-chevron-right");
    } else {
      icon.removeClass("fa-chevron-right").addClass("fa-chevron-left");
    }

    positionButton(collapsed);

    try {
      localStorage.setItem(pageKey, collapsed ? '1' : '0');
    } catch (e) {
      // Ignore storage failures in private browsing mode.
    }
  }

  const collapsedByDefault = leftTree.hasClass('collapsed') || !leftTree.hasClass('visible');
  let collapsed = collapsedByDefault;

  try {
    const saved = localStorage.getItem(pageKey);
    if (saved === '1') collapsed = true;
    if (saved === '0') collapsed = false;
  } catch (e) {
    // Keep server-rendered default state.
  }

  setTreeCollapsed(collapsed);

  btn.on("click", function () {
    const willCollapse = leftTree.is(':visible');
    setTreeCollapsed(willCollapse);
  });

  $(window).on('resize', function () {
    positionButton(leftTree.is(':hidden'));
  });
});
