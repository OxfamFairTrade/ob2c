<?php
/**
 * The template for displaying product category thumbnails within loops
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 2.6.1
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $nm_globals;

/*
 *  Shortcode (part): Product categories
 */
if ( isset( $nm_globals['is_categories_shortcode'] ) && $nm_globals['is_categories_shortcode'] ) :
    
    // Category thumbnail
    $thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
    if ( $thumbnail_id ) {
        $category_image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
        $category_image_src = $category_image[0];
    // Check of er een beeld in de themamap staat!
    } elseif ( file_exists( get_stylesheet_directory().'/images/productgroepen/'.$category->slug.'.png' ) ) {
        $category_image = true;
        $category_image_src = get_stylesheet_directory_uri().'/images/productgroepen/'.$category->slug.'.png';
    } else {
        $category_image = false;
    }

    // Category link
    $category_link = get_term_link( $category->slug, 'product_cat' );
    
    // Get custom title from "Categories Grid Title" field
    $custom_category_title = get_option( 'nm_taxonomy_product_cat_' . $category->term_id . '_description' );

    // Category heading
    $heading_tag_open = '<' . $nm_globals['categories_shortcode_heading_tag'] . ' class="nm-product-category-heading">';
    $heading_tag_close = '</' . $nm_globals['categories_shortcode_heading_tag'] . '>';
    if ( $custom_category_title ) {
        $show_link = true;
        $category_heading_escaped = $heading_tag_open . $custom_category_title . $heading_tag_close;
    } else {
        $show_link = false;
        $category_heading_escaped = $heading_tag_open . '<a href="' . esc_url( $category_link ) . '">' . $category->name . '</a>' . $heading_tag_close;
    }

    ?>
    <li <?php wc_product_cat_class(); ?>>

        <div class="nm-product-category-inner" style="border-width: 0;">
            <?php 
                /**
                 * nm_before_subcategory hook.
                 */
                do_action( 'nm_before_subcategory', $category );
            ?>

            <a href="<?php echo esc_url( $category_link ); ?>" style="width: 100%;">
            <?php
                /**
                 * nm_before_subcategory_title hook.
                 */
                do_action( 'nm_before_subcategory_title', $category );

                if ( $category_image ) {
                    // Prevent esc_url from breaking spaces in urls for image embeds
                    // Ref: http://core.trac.wordpress.org/ticket/23605
                    $category_image_src = str_replace( ' ', '%20', $category_image_src );

                    echo '<img src="' . esc_url( $category_image_src ) . '" alt="' . esc_attr( $category->name ) . '" width="' . esc_attr( $category_image[1] ) . '" height="' . esc_attr( $category_image[2] ) . '" />';
                } else {
                    echo '<img src="' . esc_url( wc_placeholder_img_src() ) . '" />';
                }
            ?>
            </a>

            <div class="nm-product-category-text" style="display: none;">
                <?php echo $category_heading_escaped; ?>

                <?php
                    /**
                     * nm_after_subcategory_title hook.
                     */
                    do_action( 'nm_after_subcategory_title', $category );
                ?>

                <?php if ( $show_link ) : ?>
                <a href="<?php echo esc_url( $category_link ); ?>" class="invert-color"><?php echo esc_html( $category->name ); ?></a>
                <?php endif; ?>
            </div>

            <?php 
                /**
                 * nm_after_subcategory hook.
                 */
                do_action( 'nm_after_subcategory', $category ); ?>
        </div>

    </li>

<?php 
/*
 *  Default product categories
 */
else :
?>
    <li <?php wc_product_cat_class( '', $category ); ?>>
        <?php
        /**
         * woocommerce_before_subcategory hook.
         *
         * @hooked woocommerce_template_loop_category_link_open - 10
         */
        do_action( 'woocommerce_before_subcategory', $category );

        /**
         * woocommerce_before_subcategory_title hook.
         *
         * @hooked woocommerce_subcategory_thumbnail - 10
         */
        do_action( 'woocommerce_before_subcategory_title', $category );

        /**
         * woocommerce_shop_loop_subcategory_title hook.
         *
         * @hooked woocommerce_template_loop_category_title - 10
         */
        do_action( 'woocommerce_shop_loop_subcategory_title', $category );

        /**
         * woocommerce_after_subcategory_title hook.
         */
        do_action( 'woocommerce_after_subcategory_title', $category );

        /**
         * woocommerce_after_subcategory hook.
         *
         * @hooked woocommerce_template_loop_category_link_close - 10
         */
        do_action( 'woocommerce_after_subcategory', $category );
        ?>
    </li>

<?php endif; ?>
