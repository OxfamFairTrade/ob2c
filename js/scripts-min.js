!function(e){e(document).ready((function(){e("#menu-btn").on("click",(function(s){s.preventDefault(),e("#side-menu").addClass("active"),e("body").addClass("active")})),e("#menu-btn-close").on("click",(function(){e("#side-menu").removeClass("active"),e("body").removeClass("active")})),e(".woocommerce-product-details__short-description").readmore({collapsedHeight:72,heightMargin:24,moreLink:'<a href="#">Lees meer</a>',lessLink:'<a href="#">Lees minder</a>'}),e(".header__item_search").on("click",(function(){e(".top-search_mobile").toggleClass("hidden")})),e(".toggle-filter").on("click",(function(){e("body").addClass("active"),e(".nm-shop-sidebar-default #nm-shop-sidebar").addClass("show-me")})),e(".close-filter").on("click",(function(){e("body").removeClass("active"),e(".nm-shop-sidebar-default #nm-shop-sidebar").removeClass("show-me")}))})),e(document).mouseup((function(s){var o=e("#side-menu");o.is(s.target)||0!==o.has(s.target).length||(e("#side-menu").removeClass("active"),e("body").removeClass("active"))}))}(jQuery);