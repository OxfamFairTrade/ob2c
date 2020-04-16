<?php
/**
 * Product loop sale flash
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $post, $product, $nm_theme_options;

?>
<?php if ( $product->is_on_sale() ) : ?>

	<?php
		$fifty_percent_off_second_products = array( '20180', '20181', '20182' );
		if ( in_array( $product->get_sku(), $fifty_percent_off_second_products ) ) {
			echo apply_filters( 'woocommerce_sale_flash', '<span class="onsale"><span class="nm-onsale-before">-</span>50<span class="nm-onsale-after">%</span></span>', $post, $product );
		} else {
			// echo apply_filters( 'woocommerce_sale_flash', '<span class="onsale">1+1 gratis</span>', $post, $product );
		}
	?>

<?php endif;

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
