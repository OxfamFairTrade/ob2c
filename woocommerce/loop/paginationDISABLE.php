<?php
/**
 * Pagination - Show numbered pagination for catalog pages
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.3.1
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//global $wp_query, $nm_theme_options;
global $nm_theme_options;

$total   = isset( $total ) ? $total : wc_get_loop_prop( 'total_pages' );
$current = isset( $current ) ? $current : wc_get_loop_prop( 'current_page' );
$base    = isset( $base ) ? $base : esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( array( 'add-to-cart', 'shop_load', '_', 'infload', 'ajax_filters' ), get_pagenum_link( 999999999, false ) ) ) );
$format  = isset( $format ) ? $format : '';

if ( $total <= 1 ) {
	return;
}

// Using "is_woocommerce()" since default pagination is used for product shortcodes
// GEWIJZIGD: Schakel infinite load uit indien het er meer dan 3 pagina's zijn
if ( is_woocommerce() && $nm_theme_options['shop_infinite_load'] !== '0' && $total <= 3 ) {
	$infload = true;
	$infload_class = ' nm-infload';
} else {
	$infload = false;
	$infload_class = '';
}
?>
<nav class="woocommerce-pagination nm-pagination<?php echo esc_attr( $infload_class ); ?>">
	<?php
        echo paginate_links( apply_filters( 'woocommerce_pagination_args', array( // WPCS: XSS ok.
			'base'         => $base,
			'format'       => $format,
			'add_args'     => false,
			'current'      => max( 1, $current ),
			'total'        => $total,
			'prev_text'    => '&larr;',
			'next_text'    => '&rarr;',
			'type'         => 'list',
			'end_size'     => 3,
			'mid_size'     => 3,
		) ) );
	?>
</nav>

<?php if ( $infload ) : ?>
<div class="nm-infload-link"><?php next_posts_link( '&nbsp;' ); ?></div>

<div class="nm-infload-controls <?php echo esc_attr( $nm_theme_options['shop_infinite_load'] ); ?>-mode">
    <a href="#" class="nm-infload-btn"><?php esc_html_e( 'Load More', 'nm-framework' ); ?></a>
    
    <a href="#" class="nm-infload-to-top"><?php esc_html_e( 'All products loaded.', 'nm-framework' ); ?></a>
</div>
<?php endif; ?>
