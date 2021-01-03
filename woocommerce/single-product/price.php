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

<?php if ( ! empty( $product->get_meta('_net_content') ) ) : ?>
	<p class="weight"><?php echo $product->get_meta('_net_content').' '.$product->get_meta('_net_unit'); ?></p>
<?php elseif ( ! empty( $product->get_attribute('inhoud') ) ) : ?>
	<p class="weight"><?php echo $product->get_attribute('inhoud'); ?></p>
<?php endif; ?>

<?php
	if ( floatval( $product->get_meta('_unit_price') ) !== 0.0 ) {
		// Nationaal vs. lokale producten
		if ( $product->get_meta('_stat_uom') === 'L' or $product->get_meta('_net_unit') === 'cl' ) {	
			$unit = '&euro;/l';
		} else {
			$unit = '&euro;/kg';
		}
		echo '<p class="unit-price">'.$unit.' '.number_format_i18n( $product->get_meta('_unit_price'), 2 ).'</p>';
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */