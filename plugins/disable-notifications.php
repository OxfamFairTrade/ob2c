<?php
	/*
	Plugin Name: Disable Changed Password Notifications To Admins
	Description: Kan eventueel ook omgeleid worden m.b.v. nieuwe 'wp_password_change_notification_email'-filter (WP 4.9+).
	*/

	if ( ! function_exists( 'wp_password_change_notification' ) ) {
		function wp_password_change_notification() {
			return;
		}
	}
?>