<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	#############
	# POSTCODES #
	#############
	
	function get_flemish_zips_and_cities() {
		$zips = get_site_option('oxfam_flemish_zip_codes');
		foreach ( $zips as $zip => $cities ) {
			$parts = explode( '/', $cities );
			foreach ( $parts as $city ) {
				$content[] = array( 'label' => $zip.' '.trim($city), 'value' => $zip );
			}
		}
		return $content;
	}
	
	function get_oxfam_covered_zips() {
		global $wpdb;
		$zips = array();
	
		// Hou enkel rekening met ingeschakelde zones
		$locations = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_shipping_zone_locations LEFT JOIN ".$wpdb->prefix."woocommerce_shipping_zone_methods ON ".$wpdb->prefix."woocommerce_shipping_zone_methods.zone_id = ".$wpdb->prefix."woocommerce_shipping_zone_locations.zone_id WHERE ".$wpdb->prefix."woocommerce_shipping_zone_locations.location_type = 'postcode' AND ".$wpdb->prefix."woocommerce_shipping_zone_methods.is_enabled = 1" );
	
		if ( count( $locations ) > 0 ) {
			foreach ( $locations as $row ) {
				$zips[] = $row->location_code;
			}
			$zips = array_unique( $zips );
	
			// Verwijder de default '9999'-waarde uit ongebruikte verzendmethodes
			if ( ( $key = array_search( '9999', $zips ) ) !== false ) {
				unset( $zips[ $key ] );
			}
	
			sort( $zips, SORT_NUMERIC );
		}
	
		return $zips;
	}
	
	
	
	#############
	# SHOP INFO #
	#############
	
	function get_webshop_name( $shortened = false ) {
		$webshop_name = get_bloginfo('name');
		if ( $shortened ) {
			$webshop_name = str_replace( 'Oxfam-Wereldwinkel ', '', $webshop_name );
		}
		return $webshop_name;
	}
	
	function get_webshop_email() {
		return get_option('admin_email');
	}
	
	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_name( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['id'] );
		if ( $oww_store_data !== false ) {
			return 'Oxfam-Wereldwinkel '.$oww_store_data['title']['rendered'];
		} else {
			return false;
		}
	}
	
	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_email( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['id'] );
		if ( $oww_store_data !== false ) {
			return $oww_store_data['location']['mail'];
		} else {
			return false;
		}
	}
	
	function get_shop_contact( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		return get_shop_address( $atts )."<br/>".get_oxfam_shop_data( 'telephone', 0, false, $atts['id'] )."<br/>".get_oxfam_shop_data( 'tax', 0, false, $atts['id'] );
	}
	
	function get_shop_address( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		return get_oxfam_shop_data( 'place', 0, false, $atts['id'] )."<br/>".get_oxfam_shop_data( 'zipcode', 0, false, $atts['id'] )." ".get_oxfam_shop_data( 'city', 0, false, $atts['id'] );
	}
	
	function get_company_and_year() {
		return get_webshop_name().' &copy; 2017-'.date_i18n('Y');
	}
	
	
	
	###################
	# SHOP PROPERTIES #
	###################
	
	function does_risky_delivery() {
		// Zet 'yes'-waarde om in een echte boolean
		return ( get_option('oxfam_does_risky_delivery') === 'yes' );
	}
	
	function does_home_delivery( $zipcode = 0 ) {
		if ( intval( $zipcode ) === 0 ) {
			return boolval( get_oxfam_covered_zips() );
		} else {
			// Check of de webshop thuislevering doet voor deze specifieke postcode
			$response = in_array( $zipcode, get_oxfam_covered_zips() );
			return $response;
		}
	}
	
	function does_local_pickup() {
		$pickup_settings = get_option('woocommerce_local_pickup_plus_settings');
		if ( is_array( $pickup_settings ) ) {
			return ( 'yes' === $pickup_settings['enabled'] );
		} else {
			return false;
		}
	}
	
	function does_sendcloud_delivery() {
		if ( does_home_delivery() ) {
			$sendcloud_zone_id = 3;
			$zone = WC_Shipping_Zones::get_zone_by( 'zone_id', $sendcloud_zone_id );
			if ( $zone ) {
				// Enkel actieve methodes meetellen
				$methods = $zone->get_shipping_methods( true );
				if ( count( $methods ) > 0 ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function is_regional_webshop() {
		// Gentbrugge, Antwerpen, Leuven, Mechelen en Wetteren
		$regions = array( 15, 24, 28, 40, 53 );
		// Opgelet: vergeet de custom orderstatus 'claimed' niet te publiceren naar deze subsites!
		return in_array( get_current_blog_id(), $regions );
	}
	
	// Kan zowel productobject als post-ID ontvangen
	function is_national_product( $object ) {
		if ( is_main_site() ) {
			// Producten op het hoofdniveau zijn per definitie nationaal!
			return true;
		}
	
		if ( $object instanceof WC_Product ) {
			return ( intval( $object->get_meta('_woonet_network_is_child_site_id') ) === 1 );
		} else {
			return ( intval( get_post_meta( $object, '_woonet_network_is_child_site_id', true ) ) === 1 );
		}
	}
	
	
	
	#############
	# UTILITIES #
	#############
	
	// Verstuur een mail naar de helpdesk uit naam van de lokale webshop
	function send_automated_mail_to_helpdesk( $subject, $body ) {
		if ( wp_get_environment_type() !== 'production' ) {
			$subject = 'TEST - '.$subject.' - NO ACTION REQUIRED';
	
			// Mails eventueel volledig uitschakelen
			// return;
		}
	
		$headers = array();
		$headers[] = 'From: '.get_webshop_name().' <'.get_option('admin_email').'>';
		$headers[] = 'Content-Type: text/html';
		// $body moét effectief HTML-code bevatten, anders werpt WP Mail Log soms een error op!
		wp_mail( get_staged_recipients('webshop@oft.be'), $subject, $body, $headers );
	}
	
	// Verwissel twee associatieve keys in een array
	function array_swap_assoc( $key1, $key2, $array ) {
		$new_array = array();
		foreach ( $array as $key => $value ) {
			if ( $key == $key1 ) {
				$new_array[ $key2 ] = $array[ $key2 ];
			} elseif ( $key == $key2 ) {
				$new_array[ $key1 ] = $array[ $key1 ];
			} else {
				$new_array[ $key ] = $value;
			}
		}
		return $new_array;
	}
	
	// Creëer een random sequentie
	// Niet gebruiken voor echte beveiliging!
	function generate_pseudo_random_string( $length = 10 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_length = strlen( $characters );
		$random_string = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ rand( 0, $characters_length - 1 ) ];
		}
		return $random_string;
	}
	
	
	
	#############
	# DEBUGGING #
	#############
	
	// Print variabelen op een duidelijke manier naar een niet-publieke file
	if ( ! function_exists( 'write_log' ) ) {
		function write_log( $log ) {
			if ( defined('WP_DEBUG_LOG') and WP_DEBUG_LOG ) {
				if ( is_array( $log ) or is_object( $log ) ) {
					$log = serialize( $log );
				}
				error_log( "[".date_i18n('d/m/Y H:i:s')."] " . $log . "\n", 3, dirname( ABSPATH, 1 ) . '/activity.log' );
			}
		}
	}
	
	// Overzichtelijke dump naar het scherm
	function var_dump_pre( $variable ) {
		echo '<pre>';
		var_dump( $variable );
		echo '</pre>';
		return;
	}