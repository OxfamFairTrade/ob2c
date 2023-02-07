<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 NM: Modified */

defined( 'ABSPATH' ) || exit;

global $product, $nm_theme_options, $nm_globals;

/* Product summary: Opening tags */
function nm_single_product_summary_open() {
	echo '<div class="nm-product-summary-inner-col nm-product-summary-inner-col-1">';
}
/* Product summary: Divider tags */
function nm_single_product_summary_divider() {
	echo '</div><div class="nm-product-summary-inner-col nm-product-summary-inner-col-2">';
}
/* Product summary: Closing tag */
function nm_single_product_summary_close() {
	echo '</div>';
}

// Action: woocommerce_before_single_product
remove_action( 'woocommerce_before_single_product', 'wc_print_notices', 10 );
// Action: woocommerce_before_single_product_summary
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
// Action: woocommerce_single_product_summary
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
add_action( 'woocommerce_single_product_summary', 'nm_single_product_summary_open', 1 );
add_action( 'woocommerce_single_product_summary', 'nm_single_product_summary_divider', 15 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 21 );
add_action( 'woocommerce_single_product_summary', 'nm_single_product_summary_close', 100 );

// GEWIJZIGD: Unhook de producttabbladen en voeg de balk met artikelnummer en tags onderaan niet toe
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
// add_action( 'woocommerce_after_single_product_summary', 'woocommerce_template_single_meta', 12 );

// Layout
$product_layout = get_post_meta( $product->get_id(), 'nm_product_layout', true );
$nm_globals['product_layout'] = ( $product_layout !== '' ) ? $product_layout : $nm_theme_options['product_layout'];

// Layout: Wrapper elements
if ( strpos( $nm_globals['product_layout'], 'scroll') !== false ) {
    nm_add_page_include( 'product-layout-scroll' );
    
    $summary_pin_wrapper_open_escaped = '<div id="nm-summary-pin">';
    $summary_pin_wrapper_close_escaped = '</div>';
} else {
    $summary_pin_wrapper_open_escaped = '';
    $summary_pin_wrapper_close_escaped = '';
}

// Class: Main container
$post_class = 'nm-single-product layout-' . $nm_globals['product_layout'];
// Class: Gallery column
$post_class .= ' gallery-col-' . $nm_theme_options['product_image_column_size'];
// Class: Summary column
$summary_column_size = 12 - intval( $nm_theme_options['product_image_column_size'] );
$post_class .= ' summary-col-' . $summary_column_size;
// Class: Thumbnails
$post_class .= ( $nm_globals['product_layout'] == 'default-thumbs-h' ) ? ' thumbnails-horizontal' : ' thumbnails-vertical';
// Class: Background color
$post_class .= ( $nm_theme_options['single_product_background_color'] == 'transparent' ) ? ' no-bg-color' : ' has-bg-color';

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
    echo get_the_password_form();
    return;
}

// Instellen als extra global, zodat info ook beschikbaar is in short-description.php (zonder expliciet door te geven als argument)
global $featured_partner;

$partners = array();
$featured_partner = false;
$partner_terms = get_partner_terms_by_product( $product );

if ( count( $partner_terms ) > 0 ) {
    foreach ( $partner_terms as $term_id => $partner_name ) {
        $partners[] = get_info_by_partner( get_term_by( 'id', $term_id, 'product_partner' ) );
    }
    
    // Fallback voor OWW-partnerpagina's waar 'type' nog de partnercategorie bevat
    if ( in_array( $partner['type'], array( 'A', 'B' ) ) ) {
        $partner['type'] = 'partner';
    }
    
    $partners_with_page = wp_list_filter( $partners, array( 'type' => 'partner' ) );
    $partners_with_quote = array_filter( $partners, function( $partner ) {
        return ! empty( $partner['partner_quote']['rendered'] );
    } );
    
    if ( count( $partners_with_page ) > 0 ) {
        // Licht random een partner uit waarvan er een partnerpagina bestaat (en dus wellicht ook een quote)
        $featured_partner = $partners_with_page[ array_rand( $partners_with_page ) ];
    }
    
    if ( current_user_can('update_core') ) {
        // var_dump_pre( $partners_with_page );
        // var_dump_pre( $partners_with_quote );
        // var_dump_pre( $featured_partner );
    }
}
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( $post_class, $product ); ?>>
    <div class="nm-single-product-bg clear">
    
        <?php wc_get_template( 'single-product/breadcrumb_nm.php' ); ?>
        
        <?php nm_print_shop_notices(); ?>

        <div class="nm-single-product-showcase">
            <div class="nm-single-product-summary-row nm-row">
                <div class="nm-single-product-summary-col col-xs-12">
                    <?php
                    /**
                     * Hook: woocommerce_before_single_product_summary.
                     *
                     * @hooked woocommerce_show_product_sale_flash - 10
                     * @hooked woocommerce_show_product_images - 20
                     */
                    do_action( 'woocommerce_before_single_product_summary' );
                    ?>

                    <div class="summary entry-summary">
                        <?php echo $summary_pin_wrapper_open_escaped; ?>
                        <?php
                        /**
                         * Hook: Woocommerce_single_product_summary.
                         *
                         * @hooked woocommerce_template_single_title - 5
                         * @hooked woocommerce_template_single_rating - 10
                         * @hooked woocommerce_template_single_price - 10
                         * @hooked woocommerce_template_single_excerpt - 20
                         * @hooked woocommerce_template_single_add_to_cart - 30
                         * @hooked woocommerce_template_single_meta - 40
                         * @hooked woocommerce_template_single_sharing - 50
                         * @hooked WC_Structured_Data::generate_product_data() - 60
                         */
                        do_action( 'woocommerce_single_product_summary' );

                        get_template_part( 'template-parts/store-selector/current' );
                        ?>
                        <?php echo $summary_pin_wrapper_close_escaped; ?>
                    </div>
                </div>
            </div>
        </div>
    
    </div>
        
	<div class="container product-origin-block">
        <div class="col-row">
            <div class="col-md-12">
                <?php get_template_part( 'template-parts/woocommerce/product-origin', NULL, array( 'partners' => $partners ) ); ?>
            </div>
        </div>
    </div>

    <?php
    get_template_part( 'template-parts/woocommerce/product-details' );

    /**
     * Hook: woocommerce_after_single_product_summary.
     *
     * @hooked woocommerce_output_product_data_tabs - 10
     * @hooked woocommerce_upsell_display - 15
     * @hooked woocommerce_output_related_products - 20
     */
    do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
