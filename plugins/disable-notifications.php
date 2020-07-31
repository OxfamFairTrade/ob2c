<?php
	/*
	Plugin Name: Disable Changed Password Notifications
	Description: Kan eventueel ook omgeleid worden m.b.v. nieuwe 'wp_password_change_notification_email'-filter (WP 4.9+).
	*/

	if ( ! function_exists('wp_password_change_notification') ) {
		function wp_password_change_notification( $user ) {
			return;
		}
	}

	// Schakel nieuwe WooCommerce-features uit
	add_filter( 'woocommerce_marketing_menu_items', '__return_empty_array' );
	add_filter( 'woocommerce_admin_disabled', '__return_true' );
?>