( function( $ ){
  $(document).ready( function () {
    $("#menu-btn").click(function(e) {
      e.preventDefault();
      $("#side-menu").addClass("active");
      $("body").addClass("active");
    });

    $('.woocommerce-product-details__short-description').readmore({
      collapsedHeight: 48,
      heightMargin: 24,
      moreLink: '<a href="#">Lees meer</a>',
      lessLink: '<a href="#">Lees minder</a>'
    });
  });

  $(document).mouseup(function(e){
    var container = $("#side-menu");
    if (!container.is(e.target) && container.has(e.target).length === 0){
      $("#side-menu").removeClass("active");
      $("body").removeClass("active");
    }
  });

})(jQuery);
