<?php
/**
 * The template for displaying product content within loops
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 NM: Modified */

defined( 'ABSPATH' ) || exit;

global $product, $nm_globals, $nm_theme_options, $position_in_grid;

if ( ! isset( $position_in_grid ) ) {
    $position_in_grid = 1;
}

// Ensure visibility
if ( empty( $product ) || false === wc_get_loop_product_visibility( $product->get_id() ) || ! $product->is_visible() ) {
	return;
}

nm_add_page_include( 'products' );

// Wrapper link
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );

// Product variation attributes
$attributes_escaped = function_exists( 'nm_template_loop_attributes' ) ? nm_template_loop_attributes() : null;
$product_class = ( $attributes_escaped ) ? 'nm-has-attributes' : '';

// Title
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
add_action( 'woocommerce_shop_loop_item_title', 'nm_template_loop_product_title', 10 );

// Rating
if ( ! $nm_theme_options['product_rating'] ) {
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
}

// Action link
if ( ! $nm_theme_options['product_action_link'] ) {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
}
?>
<li <?php wc_product_class( $product_class, $product ); ?> data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="nm-shop-loop-product-wrap">
        <?php
            /**
             * Wishlist button - Note: Centered layout only
             */
            if ( $nm_globals['wishlist_enabled'] && $nm_theme_options['products_layout'] == 'centered' ) { nm_wishlist_button(); }
        ?>

        <?php
        /**
         * Hook: woocommerce_before_shop_loop_item.
         *
         * NM: Removed - @hooked woocommerce_template_loop_product_link_open - 10
         */
        do_action( 'woocommerce_before_shop_loop_item' );
        ?>

        <div class="nm-shop-loop-thumbnail">
            <a href="<?php echo esc_url( get_permalink() ); ?>" class="nm-shop-loop-thumbnail-link woocommerce-LoopProduct-link">
            <?php
            /**
             * Hook: woocommerce_before_shop_loop_item_title.
             *
             * @hooked woocommerce_show_product_loop_sale_flash - 10
             * @hooked woocommerce_template_loop_product_thumbnail - 10
             */
            do_action( 'woocommerce_before_shop_loop_item_title' );
            ?>
            </a>
        </div>
        
        <?php
            /**
             * Product variation attributes
             */
            if ( $attributes_escaped ) {
                echo $attributes_escaped;
            }
        ?>
        
        <div class="nm-shop-loop-details">
            <?php
                /**
                 * Wishlist button
                 */
                if ( $nm_globals['wishlist_enabled'] && $nm_theme_options['products_layout'] !== 'centered' ) { nm_wishlist_button(); }
            ?>

            <div class="nm-shop-loop-title-price">
            <?php
            /**
             * Hook: woocommerce_shop_loop_item_title.
             *
             * NM: Removed - @hooked woocommerce_template_loop_product_title - 10
             * NM: Added - @hooked nm_template_loop_product_title - 10
             */
            do_action( 'woocommerce_shop_loop_item_title' );
            
            /**
             * Hook: woocommerce_after_shop_loop_item_title.
             *
             * @hooked woocommerce_template_loop_rating - 5
             * @hooked woocommerce_template_loop_price - 10
             */
            do_action( 'woocommerce_after_shop_loop_item_title' );
            ?>
            </div>

            <div class="nm-shop-loop-actions">
            <?php
            /**
             * Hook: woocommerce_after_shop_loop_item.
             *
             * NM: Removed - @hooked woocommerce_template_loop_product_link_close - 5
             * @hooked woocommerce_template_loop_add_to_cart - 10
             */
            do_action( 'woocommerce_after_shop_loop_item' );
            
            /**
             * Quick view link
             */
            if ( $nm_theme_options['product_quickview'] && $nm_theme_options['product_quickview_link'] ) {
                echo apply_filters( 'nm_product_quickview_link', '<a href="' . esc_url( get_permalink() ) . '" class="nm-quickview-btn">' . esc_html__( 'Show more', 'nm-framework' ) . '</a>' );
            }
            ?>
            </div>
        </div>
    </div>
</li>

<!-- We nemen de brakke Conversal-logica voorlopig over -->
<?php if ( $position_in_grid === 4 and is_shop() and wc_get_loop_prop('current_page') === 1 ) : ?>
    <!-- Banner op volledige breedte -->
    <li class="promo-banner horizontal">
        <a href="<?php echo get_term_link( 'promotie', 'product_tag' ); ?>">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/promotie/promo-wvdft-2020-quinoa.png" />
        </a>
    </li>
<?php elseif ( $position_in_grid === 7 and is_main_query() and wc_get_loop_prop('current_page') === 1 ) : ?>
    <!-- Blokje op zelfde formaat als een product -->
    <li class="promo-banner vertical">
        <a href="<?php echo get_term_link( 'koffie', 'product_cat' ); ?>">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/promotie/promo-wvdft-2020-koffie.png" />
        </a>
    </li>

    <!-- Categoriespecifieke blokjes voorlopig uitschakelen, ACF-velden hier niet beschikbaar -->
    <?php $product_cat = get_queried_object(); ?>
    <?php if ( 1 === 2 ) : ?>
        <?php if ( get_field( 'promo_banner_image', $product_cat ) ) : ?>
            <div class="col-md-8">
                <div class="promo-banner-block">
                    <?php echo wp_get_attachment_image( get_field( 'promo_banner_image', $product_cat ), 'large' ); ?>
                    <div class="cap">
                        <h2 class="h1"><?php the_field( 'promo_banner_title', $product_cat ); ?></h2>
                        <?php
                            $button = get_field( 'promo_banner_button', $product_cat );
                            if ( $button ) {
                                echo '<a href="'.$button['url'].'" target="'.$button['target'].'" class="btn">'.$button['title'].'</a>';
                            }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php $position_in_grid++; ?>
