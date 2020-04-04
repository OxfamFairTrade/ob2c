<?php
/**
 * Pagination - Show numbered pagination for catalog pages
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.2.2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wp_query, $nm_theme_options;

if ( $wp_query->max_num_pages <= 1 ) {
	return;
}

// Enable infinite loading via URL query
// Note: This will not work with filters etc. (WooCommerce doesn't preserve all queries)
if ( isset( $_GET['infload'] ) ) {
	$nm_theme_options['shop_infinite_load'] = $_GET['infload'];
}

if ( $nm_theme_options['shop_infinite_load'] !== '0' ) {
	$infload = true;
	$infload_class = ' nm-infload';
} else {
	$infload = false;
	$infload_class = '';
}

// GEWIJZIGD: Wijzig laadmodus indien het een categoriepagina met meer dan 3 pagina's is
$mode = 'scroll';
if ( is_product_category() and intval( $wp_query->max_num_pages ) > 3 ) {
	$mode = 'button';
}

?>
<nav class="woocommerce-pagination nm-pagination<?php echo $infload_class; ?>">
	<?php
		echo paginate_links( apply_filters( 'woocommerce_pagination_args', array(
			'base'         	=> esc_url( str_replace( 999999999, '%#%', remove_query_arg( array( 'add-to-cart', 'shop_load', '_', 'infload', 'ajax_filters' ), get_pagenum_link( 999999999, false ) ) ) ),
			'format'       	=> '',
			'current'      	=> max( 1, get_query_var( 'paged' ) ),
			'total'        	=> $wp_query->max_num_pages,
			'prev_text'		=> '&larr;',
			'next_text'    	=> '&rarr;',
			'type'         	=> 'list',
			'end_size'     	=> 3,
			'mid_size'     	=> 3
		) ) );
	?>
</nav>

<?php if ( $infload ) : ?>
<div class="nm-infload-link"><?php next_posts_link( '&nbsp;' ); ?></div>

<div class="nm-infload-controls <?php echo $mode; ?>-mode">
    <a href="#" class="nm-infload-btn"><?php esc_html_e( 'Load More', 'nm-framework' ); ?></a>
    
    <a href="#" class="nm-infload-to-top"><?php esc_html_e( 'All products loaded.', 'nm-framework' ); ?></a>
</div>
<?php endif; ?>
