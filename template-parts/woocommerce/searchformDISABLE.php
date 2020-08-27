<div id="nm-header-search" style="visibility: visible; position: relative;">
    <div class="nm-header-search-wrap">
        <div class="nm-row">
            <div class="col-xs-12">
                <form id="nm-header-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <i class="nm-font nm-font-search"></i>
                    <input type="text" id="nm-header-search-input" autocomplete="off" value="" name="s" placeholder="<?php esc_attr_e( 'Search products', 'woocommerce' ); ?>" />
                    <input type="hidden" name="post_type" value="product" />
                </form>

                <?php
                    global $nm_theme_options;
                    if ( $nm_theme_options['shop_search_suggestions'] ) :
                    
                    // Column class
                    $columns_large = intval( $nm_theme_options['shop_search_suggestions_max_results'] );
                    $columns_medium = ( $columns_large >= 5 ) ? ( $columns_large - 1 ) : $columns_large;
                    $columns_class = apply_filters( 'nm_search_suggestions_product_columns_class', 'block-grid-single-row xsmall-block-grid-1 small-block-grid-1 medium-block-grid-' . $columns_medium . ' large-block-grid-' . $columns_large
            );
                ?>
                <div id="nm-search-suggestions">
                    <div class="nm-search-suggestions-inner">
                        <div id="nm-search-suggestions-notice">
                            <span class="txt-press-enter"><?php printf( esc_html__( 'press %sEnter%s to search', 'nm-framework' ), '<u>', '</u>' ); ?></span>
                            <span class="txt-has-results"><?php esc_html_e( 'Showing all results', 'nm-framework' ); ?>:</span>
                            <span class="txt-no-results"><?php esc_html_e( 'No products found.', 'woocommerce' ); ?></span>
                        </div>
                        <ul id="nm-search-suggestions-product-list" class="<?php echo esc_attr( $columns_class ); ?>"></ul>
                    </div>
                </div>
                <?php else : ?>
                <div id="nm-header-search-notice"><span><?php printf( esc_html__( 'press %sEnter%s to search', 'nm-framework' ), '<u>', '</u>' ); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>