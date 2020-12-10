<?php
/**
 * Single Product stock.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ook al doen we voorraadbeheer, statuslabel nooit tonen op nationaal niveau
if ( ! is_main_site() ) {
	?>
		<p class="stock <?php echo esc_attr( $class ); ?>"><?php echo wp_kses_post( $availability ); ?></p>
	<?php
} else {
	// Variable $product wordt via template argumenten doorgegeven door WooCommerce, geen global nodig!
	if ( ! $product->is_purchasable() ) {
		// @toDo: Zoek op in welke andere webshops het product wél voorradig is
	}
}
