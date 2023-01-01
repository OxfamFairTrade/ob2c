<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	#############
	# HOUTHALEN #
	#############
	
	add_action( 'init', 'delay_actions_and_filters_till_load_completed_72' );
	
	function delay_actions_and_filters_till_load_completed_72() {
		if ( get_current_blog_id() === 72 ) {
			// Schakel afrekenen in de webshop van Houthalen uit van 18/10/2021 t.e.m. 14/11/2021
			add_filter( 'woocommerce_available_payment_gateways', 'houthalen_disable_all_payment_methods', 10, 1 );
			add_filter( 'woocommerce_no_available_payment_methods_message', 'houthalen_print_explanation_if_disabled', 10, 1 );
			add_filter( 'woocommerce_order_button_html', 'houthalen_disable_checkout_button', 10, 1 );
		}
	}
	
	function houthalen_disable_all_payment_methods( $methods ) {
		if ( date_i18n('Y-m-d') >= '2021-10-18' and date_i18n('Y-m-d') <= '2021-11-14' ) {
			return array();
		}
		return $methods;
	}
	
	function houthalen_print_explanation_if_disabled( $text ) {
		return get_option('oxfam_sitewide_banner_top');
	}
	
	function houthalen_disable_checkout_button( $html ) {
		if ( date_i18n('Y-m-d') >= '2021-10-18' and date_i18n('Y-m-d') <= '2021-11-14' ) {
			$original_button = __( 'Place order', 'woocommerce' );
			return str_replace( '<input type="submit"', '<input type="submit" disabled="disabled"', str_replace( $original_button, 'Bestellen tijdelijk onmogelijk', $html ) );
		}
		return $html;
	}