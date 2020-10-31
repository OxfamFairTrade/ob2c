<?php
/**
 * Single Product Price
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product;

?>
<p class="<?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price' ) ); ?>"><?php echo $product->get_price_html(); ?></p>

<?php if ( $product->get_attribute('inhoud') != '0' ) : ?>
	<p class="weight"><?php echo $product->get_attribute('inhoud'); ?></p>
<?php endif; ?>

<?php
	if ( floatval( $product->get_attribute('eprijs') ) !== 0.0 ) {
		// Oude systeem (nationaal), via attribuut
		echo '<p class="unit-price">&euro;/'.strtolower( $product->get_meta('_stat_uom') ).' '.number_format_i18n( $product->get_attribute('eprijs'), 2 ).'</p>';
	} elseif ( floatval( $product->get_meta('_unit_price') ) !== 0.0 ) {
		// Nieuwe systeem (lokaal), via metadata
		echo '<p class="unit-price">&euro;/'.strtolower( $product->get_meta('_stat_uom') ).' '.number_format_i18n( $product->get_meta('_unit_price'), 2 ).'</p>';
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */