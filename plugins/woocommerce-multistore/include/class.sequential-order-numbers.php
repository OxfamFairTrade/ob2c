<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOO_SON {


	function __construct() {
		// add _order_number meta data to order
		add_action( 'woocommerce_process_shop_order_meta', 'WOO_SON::woocommerce_process_shop_order_meta', 10, 2 );
		add_action( 'wp_insert_post', 'WOO_SON::wp_insert_post', 10, 2 );

		// retrieve _order_number
		add_filter( 'woocommerce_order_number', 'WOO_SON::get_order_number', 10, 2 );

		add_filter( 'woocommerce_shortcode_order_tracking_order_id', 'WOO_SON::woocommerce_shortcode_order_tracking_order_id', 10, 1 );

		add_filter( 'woocommerce_shop_order_search_fields', 'WOO_SON::add_sequential_shop_order_search_fields' );
	}


	static function network_update_order_numbers() {
		global $wpdb;

		$orders_to_process    = 200;
		$highest_order_number = 1;

		$network_site_ids = WOO_MSTORE_functions::get_active_woocommerce_blog_ids();

		foreach ( $network_site_ids as $network_site_id ) {
				switch_to_blog( $network_site_id );

				restore_current_blog();

    			do {
    				    $mysql_query = 'SELECT DISTINCT ID FROM ' . $wpdb->posts . ' as P
                                                    JOIN ' . $wpdb->postmeta . " AS PM ON PM.post_id = P.ID
                                                    WHERE  P.post_type = 'shop_order' AND ID NOT IN 
                                                                ( SELECT post_id FROM " . $wpdb->postmeta . '
                                                                       WHERE ' . $wpdb->postmeta . ".`meta_key` = '_order_number'
                                                                        AND " . $wpdb->postmeta . '.`post_id` =   P.ID
                                                                )
                                                    ORDER BY ID ASC
                                                    LIMIT ' . $orders_to_process;

    					$results = $wpdb->get_results( $mysql_query );

    				if ( count( $results ) > 0 ) {
    					foreach ( $results as $result ) {
    						add_post_meta( $result->ID, '_order_number', $result->ID );
    					}
    				}
    			} while ( count( $results ) > 0 );

				// get the highest _order_number on current blog
				$mysql_query = 'SELECT MAX(PM.meta_value) as highest FROM ' . $wpdb->posts . ' as P
                                        JOIN ' . $wpdb->postmeta . " AS PM ON PM.post_id = P.ID
                                        WHERE  P.post_type = 'shop_order' AND PM.meta_key = '_order_number'";

				$highest = $wpdb->get_var( $mysql_query );

    			if ( $highest_order_number < $highest ) {
    				$highest_order_number = $highest;
    			}

				restore_current_blog();
				self::update_network_order_number( $highest_order_number );
		}

	}


	/**
	 * retirve next order_number
	 */
	static function get_next_network_order_number() {
		$network_order_number = get_site_option( 'mstore_current_network_order_number' );

		$network_order_number++;

		return $network_order_number;

	}


	/**
	 * set next order_number
	 */
	static function update_network_order_number( $order_number ) {
		update_site_option( 'mstore_current_network_order_number', $order_number );

	}


	static function add_order_number( $post_id ) {
		// check if there's already an order_number
		$order_number = get_post_meta( $post_id, '_order_number', true );

		if ( $order_number > 0 ) {
			return $order_number;
		}

		$network_order_number = self::get_next_network_order_number();

		update_post_meta( $post_id, '_order_number', $network_order_number );

		self::update_network_order_number( $network_order_number );

		return $network_order_number;

	}


	static function woocommerce_process_shop_order_meta( $post_id, $post ) {
		if ( $post->post_type != 'shop_order' ) {
			return;
		}

		// If this is just a revision, don't send the email.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::add_order_number( $post_id );

	}

	static function wp_insert_post( $post_id, $post ) {
		if ( $post->post_type != 'shop_order' ) {
			return;
		}

		// If this is just a revision, don't send the email.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::add_order_number( $post_id );

	}


	/**
	 * Return the order number
	 */
	static function get_order_number( $order_number, $order ) {
		$_order_number = get_post_meta( $order_number, '_order_number', true );
		if ( $_order_number > 0 ) {
			// GEWIJZIGD: Voeg prefix en leading zero's toe
			return "OWW" . sprintf( '%05d', $_order_number );
		}

		remove_filter( 'woocommerce_order_number', 'WOO_SON::get_order_number', 10, 2 );
		$_order_nubmer = $order->get_order_number();
		add_filter( 'woocommerce_order_number', 'WOO_SON::get_order_number', 10, 2 );

		// if set the order number, return
		if ( ! empty( $_order_nubmer ) ) {
				return $_order_nubmer;
		}

		return $order_number;

	}

	static function add_sequential_shop_order_search_fields( $search_fields ) {
		$search_fields[] = '_order_number';

		return $search_fields;
	}

	static function woocommerce_shortcode_order_tracking_order_id( $order_id ) {
        global $wpdb;

        $order_number = $wpdb->get_var("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='_order_number' AND meta_value='{$order_id}'");

        if ( !empty($order_number) ) {
            return $order_number;
        }
        
		return $order_id;
	}

	static function get_current_sequential_order_number() {
		return get_site_option( 'mstore_current_network_order_number', 0 );
	}
}
