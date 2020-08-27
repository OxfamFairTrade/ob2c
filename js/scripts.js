jQuery(document).ready(function($) {
  $("#menu-btn").click(function(e) {
    e.preventDefault();
    $("#side-menu").addClass("active");
    $("body").addClass("active");
  });

  $(document).mouseup(function(e){
    var container = $("#side-menu");
    if (!container.is(e.target) && container.has(e.target).length === 0){
      $("#side-menu").removeClass("active");
      $("body").removeClass("active");
    }
  });
});
