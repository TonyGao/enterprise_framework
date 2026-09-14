$(document).ready(function () {
  // 如果选中的是公司，创建一级部门
  let chosedEle = $(".department-tree-wrapper").children().find(".chosen");
  let type = chosedEle.attr("type");
  let t = chosedEle.text();
  if (type === "company") {
    let company = t;
    $(`.ef-trigger-popup[for=t('deptJs.js1')] li:contains(${company})`).click();
  }

  // 如果选中的是部门，创建该部门的子部门
  if (type === "department") {
    let department = t;
  }
})
