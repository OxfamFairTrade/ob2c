<?php
/**
 * Loop Add to Cart
 *
 * @see 		https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version 	3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product, $nm_page_includes;

$nm_page_includes['products'] = true; // Required for the "Add to cart" element/shortcode

if ( is_b2b_customer() ) {
	$multiple = intval( $product->get_attribute('ompak') );
	if ( $multiple < 1 ) {
		$multiple = 1;
	}
} else {
	$multiple = 1;
}

write_log('ADD TO CART '.$product->get_sku().': '.$quantity.' quantity - '.$multiple.' multiple');

echo apply_filters( 'woocommerce_loop_add_to_cart_link',
	sprintf( '<a rel="nofollow" href="%s" data-quantity="%s" data-product_id="%s" data-product_sku="%s" class="%s">%s</a>',
		esc_url( $product->add_to_cart_url() ),
		esc_attr( isset( $quantity ) ? $quantity : $multiple ),
		esc_attr( $product->get_id() ),
		esc_attr( $product->get_sku() ),
		esc_attr( isset( $class ) ? $class : 'button' ),
		esc_html( $product->add_to_cart_text() )
	),
$product );
