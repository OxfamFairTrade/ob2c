<?php
/**
 *	NM: Shop (AJAX) - Full shop template
 */

defined( 'ABSPATH' ) || exit;

global $nm_theme_options, $nm_globals;

// Actions
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

// Product taxonomy
$is_product_taxonomy = ( is_product_taxonomy() ) ? true : false;
?> 


<?php
	/* Page title */

	if ( $nm_theme_options['shop_ajax_update_title'] ) {
        printf( '<div id="nm-wp-title">%s</div>', wp_get_document_title() );
	}
?>

<?php
    /* Taxonomy banner/header and heading */
    if ( $is_product_taxonomy ) {
        if ( $nm_theme_options['shop_content_taxonomy'] == 'taxonomy_header' ) {
            wc_get_template_part( 'content', 'product_nm_taxonomy_header' );
        } else if ( $nm_theme_options['shop_content_taxonomy'] == 'taxonomy_heading' ) {
            ?>
            <div class="nm-shop-taxonomy-heading">
                <div class="nm-row">
                    <div class="col-xs-12">
                        <h1><?php woocommerce_page_title(); ?></h1>
                    </div>
                </div>
            </div>
            <?php
        }
    }
?>

<?php 
	/* Categories */

	if ( $nm_theme_options['shop_categories'] ) :
?>  
<ul id="nm-shop-categories" class="nm-shop-categories <?php echo esc_attr( $nm_theme_options['shop_categories_layout'] ); ?>">
    <?php nm_category_menu(); ?>
</ul>                
<?php endif; ?>


<?php
    /* Sidebar/Filters */

    if ( $nm_theme_options['shop_filters'] != 'disabled' ) :

    $ul_class_attr_escaped = ( $_REQUEST['shop_filters_layout'] == 'header' ) ? ' class="small-block-grid-' . esc_attr( $nm_theme_options['shop_filters_columns'] ) . '"' : ''; // Shop-header filters grid class
?>
<ul id="nm-shop-widgets-ul"<?php echo $ul_class_attr_escaped; ?>>
    <?php
        // if ( is_active_sidebar( 'widgets-shop' ) ) {
        //     dynamic_sidebar( 'widgets-shop' );
        // }

        $args = array( 'before_widget' => '<li class="widget %s">', 'after_widget' => '</li>', 'before_title' => '<h3 class="nm-widget-title">', 'after_title' => '</h3>' );

        the_widget( 'WC_Widget_Layered_Nav_Filters', array(), $args );
        the_widget( 'WC_Widget_Product_Categories', array( 'title' => 'Categorieën', 'orderby' => 'order', 'show_children_only' => 1 ), $args );
        the_widget( 'WC_Widget_Layered_Nav', array( 'title' => 'Voedingsvoorkeuren', 'attribute' => 'preferences' ), $args );
        the_widget( 'WC_Widget_Layered_Nav', array( 'title' => 'Herkomstland', 'attribute' => 'countries', 'display_type' => 'dropdown' ), $args );
    ?>
</ul>
<?php endif; ?>


<?php
    /* Shop */

    // Page content
    if ( $is_product_taxonomy ) {
        $show_taxonomy_banner = ( $nm_theme_options['shop_content_taxonomy'] == 'static_banner' ) ? true : false;
        $show_taxonomy_description = ( ! $show_taxonomy_banner && $nm_theme_options['shop_category_description'] ) ? true : false;
    } else {
        $show_taxonomy_description = ( $nm_theme_options['shop_category_description'] ) ? true : false;
    }
?>
<div id="nm-shop-browse-wrap" class="nm-shop-description-<?php echo esc_attr( $nm_theme_options['shop_description_layout'] ); ?>">
<?php
    /**
     * Results bar
     */
    // GEWIJZIGD: Balk uitschakelen
    // wc_get_template_part( 'content', 'product_nm_results_bar' );
    
    /**
     * Taxonomy description - Top
     */
    if ( $show_taxonomy_description && $nm_theme_options['shop_description_position'] == 'top' ) {    
        if ( $is_product_taxonomy ) {
            /**
             * Hook: woocommerce_archive_description.
             *
             * @hooked woocommerce_taxonomy_archive_description - 10
             * @hooked woocommerce_product_archive_description - 10
             */
            do_action( 'woocommerce_archive_description' );
        } else {
            nm_shop_description(); // Default shop description
        }
    }
	
	if ( have_posts() ) {
        /**
         * Hook: woocommerce_before_shop_loop.
         *
         * @hooked wc_print_notices - 10
         * NM: Removed - @hooked woocommerce_result_count - 20
         * NM: Removed - @hooked woocommerce_catalog_ordering - 30
         */
        do_action( 'woocommerce_before_shop_loop' );
        
        // Set column sizes
		global $woocommerce_loop;
		$woocommerce_loop['columns'] = $nm_theme_options['shop_columns'];
        $woocommerce_loop['columns_small'] = '2';
		$woocommerce_loop['columns_medium'] = '3';
		
		woocommerce_product_loop_start();
            
        $nm_globals['is_categories_shortcode'] = false;
        
        if ( wc_get_loop_prop( 'total' ) ) {
            while ( have_posts() ) {
                the_post();

                /**
                 * Hook: woocommerce_shop_loop.
                 *
                 * @hooked WC_Structured_Data::generate_product_data() - 10
                 */
                do_action( 'woocommerce_shop_loop' );

                wc_get_template_part( 'content', 'product' );
            }
        }

		woocommerce_product_loop_end();
        
        /**
         * Hook: woocommerce_after_shop_loop.
         *
         * @hooked woocommerce_pagination - 10
         */
		do_action( 'woocommerce_after_shop_loop' );
	} else {
        /**
         * Hook: woocommerce_no_products_found.
         *
         * @hooked wc_no_products_found - 10
         */
        do_action( 'woocommerce_no_products_found' );
	}
    
    /**
     * Taxonomy description - Bottom
     */
    if ( $show_taxonomy_description && $nm_theme_options['shop_description_position'] == 'bottom' ) {
        if ( $is_product_taxonomy ) {
            /**
             * Hook: woocommerce_archive_description.
             *
             * @hooked woocommerce_taxonomy_archive_description - 10
             * @hooked woocommerce_product_archive_description - 10
             */
            do_action( 'woocommerce_archive_description' );
        } else {
            nm_shop_description(); // Default shop description
        }
    }
?>
</div>
