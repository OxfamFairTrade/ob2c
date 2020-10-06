<div class="nm-shop-search-inner">
    <div class="nm-shop-search-input-wrap">
        <a href="#" id="nm-shop-search-close"><i class="nm-font nm-font-close2"></i></a>
        <form id="nm-shop-search-form" role="search" method="get" action="<?php echo esc_url( home_url('/') ); ?>">
            <input type="text" id="nm-shop-search-input" autocomplete="off" value="<?php the_search_query(); ?>" name="s" placeholder="<?php esc_attr_e( 'Search products', 'woocommerce' ); ?>" />
            <input type="hidden" name="post_type" value="product" />
        </form>
    </div>
    
    <div id="nm-shop-search-notice"><span><?php printf( esc_html__( 'press %sEnter%s to search', 'nm-framework' ), '<u>', '</u>' ); ?></span></div>
</div>