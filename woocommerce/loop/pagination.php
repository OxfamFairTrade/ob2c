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

global $wp_query, $nm_theme_options, $paged;

$total   = isset( $total ) ? $total : wc_get_loop_prop( 'total_pages' );
$current = isset( $current ) ? $current : wc_get_loop_prop( 'current_page' );
$base    = isset( $base ) ? $base : esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( array( 'add-to-cart', 'shop_load', '_', 'infload', 'ajax_filters' ), get_pagenum_link( 999999999, false ) ) ) );
$format  = isset( $format ) ? $format : '';

if ( $total <= 1 ) {
	return;
}

// Using "is_woocommerce()" since default pagination is used for product shortcodes
if ( is_woocommerce() && $nm_theme_options['shop_infinite_load'] !== '0' ) {
	$infload = true;
	$infload_class = ' nm-infload';
} else {
	$infload = false;
	$infload_class = '';
}
?>
<nav class="woocommerce-pagination nm-pagination<?php echo esc_attr( $infload_class ); ?>">
	<?php
    echo paginate_links(
        apply_filters(
            'woocommerce_pagination_args',
            array( // WPCS: XSS ok.
                'base'         => $base,
                'format'       => $format,
                'add_args'     => false,
                'current'      => max( 1, $current ),
                'total'        => $total,
                'prev_text'    => '<i class="nm-font nm-font-angle-thin-left"></i>',
                'next_text'    => '<i class="nm-font nm-font-angle-thin-right"></i>',
                'type'         => 'list',
                'end_size'     => 3,
                'mid_size'     => 3,
            )
        )
    );
	?>
</nav>

<?php if ( $infload ) : ?>
<div class="nm-infload-link"><?php next_posts_link( '&nbsp;' ); ?></div>

<div class="nm-infload-controls <?php echo esc_attr( $nm_theme_options['shop_infinite_load'] ); ?>-mode">
    <!-- GEWIJZIGD: Toon progressie in productenlijst => wc_get_loop_prop('current_page') geeft altijd 1 door? -->
    <p>Weergave <?php echo min( $wp_query->found_posts, max( 1, $paged ) * $wp_query->get('posts_per_page') ); ?> van <?php echo $wp_query->found_posts; ?> producten</p>
    <?php if ( $paged < $wp_query->max_num_pages ) : ?>
        <a href="#" class="nm-infload-btn"><?php echo 'PAGE '.$paged.' - PAGE '.get_query_var('paged').' - '; ?>Meer producten laden</a>
    <?php endif; ?>
    <a href="#" class="nm-infload-to-top"><?php esc_html_e( 'All products loaded.', 'nm-framework' ); ?></a>
</div>
<?php endif; ?>
