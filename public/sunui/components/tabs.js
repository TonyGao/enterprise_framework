$(document).ready(function () {
  $(".ef-tabs").on("click", ".tabs li", function() {
    let liid = $(this).attr("id");
    $(this).addClass("tabs-selected");
    $(this).siblings().removeClass("tabs-selected");

    let li = $(".panel-htop[liid='"+ liid + "']");
    li.css("display", "");
    li.siblings().css("display", "none");
  })
})

