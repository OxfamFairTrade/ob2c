<?php

/**
 * Check if WooCommerce is active
 */
$active_plugins = apply_filters( 'active_plugins', get_option('active_plugins') );

if ( in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
	
	add_filter( 'woocommerce_shipping_methods', 'add_b2b_delivery_shipping_method' );
	function add_b2b_delivery_shipping_method( $methods ) {
		$methods['b2b_delivery_shipping_method'] = 'WC_B2B_Delivery_Shipping_Method';
		return $methods;
	}
	
	add_action( 'woocommerce_shipping_init', 'b2b_delivery_shipping_method_init' );
	function b2b_delivery_shipping_method_init(){
		require_once 'class-b2b-delivery-shipping-method.php';
	}
}

?>