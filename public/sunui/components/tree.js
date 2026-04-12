$(document).ready(function () {
  $("body").on("click", ".common-tree-wrapper .arrow-icon", function (event) {
    event.stopPropagation();
    const parentLi = $(this).closest('li');
    const childTree = parentLi.children('ol').first();
    const childIndent = parentLi.children('.tree-indent');

    if (childTree.length) {
      childIndent.toggle(0);
      childTree.toggle(0);
    }

    let icon = $(this).find('i');
    if (icon.hasClass("fa-caret-down")) {
      icon.removeClass("fa-caret-down").addClass("fa-caret-right");
    } else {
      icon.removeClass("fa-caret-right").addClass("fa-caret-down");
    }
  })
})