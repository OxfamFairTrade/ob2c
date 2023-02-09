<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// $args = array( 'site_id' => 80 );
	// clean_old_product_terms_callback( $args );
	add_action( 'clean_old_product_terms', 'clean_old_product_terms_callback', 10, 1 );
	
	function clean_old_product_terms_callback( $args ) {
		if ( get_site( $args['site_id'] ) !== NULL ) {
			switch_to_blog( $args['site_id'] );
		
			$cnt = 0;
			$start = microtime(true);
			$to_delete = array(
				'pa_bio',
				'pa_choavl',
				'pa_ean',
				'pa_eenheid',
				'pa_ener',
				'pa_eprijs',
				'pa_fairtrade',
				'pa_famscis',
				'pa_fapucis',
				'pa_fasat',
				'pa_fat',
				'pa_fibtg',
				'pa_ompak',
				'pa_polyl',
				'pa_pro',
				'pa_salteq',
				'pa_shopplus',
				'pa_starch',
				'pa_sugar',
				'product_allergen',
				'product_grape',
				'product_recipe',
				'product_taste',
			);
			
			foreach ( $to_delete as $taxonomy ) {
				// Anders vinden we niks, ook al zwerven ze nog rond in de database!
				register_taxonomy( $taxonomy, 'product' );
				
				$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
				foreach ( $terms as $term ) {
					$name = $term->name;
					if ( wp_delete_term( $term->term_id, $taxonomy ) ) {
						$cnt++;
					}
				}
			}
			write_log( get_bloginfo('name').": deleted ".$cnt." terms in ".number_format( microtime(true)-$start, 2, ',', '.' )." seconds" );
			
			restore_current_blog();
		}
		
		if ( $args['site_id'] < 86 ) {
			$args['site_id'] += 1;
			if ( as_schedule_single_action( strtotime(), 'clean_old_product_terms', array( 'args' => $args ), 'Cleanup' ) > 0 ) {
				write_log("Cleanup for site-ID ".$args['site_id']." scheduled");
			}
		} else {
			write_log("Cleanup terminated");
		}
	}
	
	
	
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
	
	function get_current_url() {
		$url = ( isset( $_SERVER['HTTPS'] ) and 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$url .= '://' . $_SERVER['SERVER_NAME'];
		$url .= in_array( $_SERVER['SERVER_PORT'], array( '80', '443' ) ) ? '' : ':' . $_SERVER['SERVER_PORT'];
		$url .= $_SERVER['REQUEST_URI'];
		return $url;
	}
	
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
	
	function ends_with( $haystack, $needle ) {
		return $needle === '' or ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 and strpos( $haystack, $needle, $temp ) !== false );
	}
	
	function sort_by_time( $a, $b ) {
		return $b['timestamp'] - $a['timestamp'];
	}
	
	function trim_and_uppercase( $value ) {
		return str_replace( 'Oww ', 'OWW ', implode( '.', array_map( 'ucwords', explode( '.', implode( '(', array_map( 'ucwords', explode( '(', implode( '-', array_map( 'ucwords', explode( '-', mb_strtolower( trim($value) ) ) ) ) ) ) ) ) ) ) );
	}
	
	function format_tax( $value ) {
		$value = str_replace( 'BE', '', $value );
		$value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( mb_strlen($value) === 9 ) {
			$value = '0'.$value;
		}
		
		if ( mb_strlen($value) === 10 ) {
			$digit_8 = intval( substr( $value, 0, 8 ) );
			$checksum = 97 - ( $digit_8 - intval( $digit_8 / 97 ) * 97 );
			if ( $checksum === intval( substr( $value, 8, 2 ) ) ) {
				return 'BE '.substr( $value, 0, 4 ).".".substr( $value, 4, 3 ).".".substr( $value, 7, 3 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} elseif ( mb_strlen($value) >= 1 ) {
			return 'INVALID LENGTH';
		} else {
			return '';
		}
	}
	
	function format_account( $iban ) {
		$countries = array( 'BE' => 16, 'NL' => 18 );
		$translate_chars = array(
			'A' => 10,
			'B' => 11,
			'C' => 12,
			'D' => 13,
			'E' => 14,
			'F' => 15,
			'G' => 16,
			'H' => 17,
			'I' => 18,
			'J' => 19,
			'K' => 20,
			'L' => 21,
			'M' => 22,
			'N' => 23,
			'O' => 24,
			'P' => 25,
			'Q' => 26,
			'R' => 27,
			'S' => 28,
			'T' => 29,
			'U' => 30,
			'V' => 31,
			'W' => 32,
			'X' => 33,
			'Y' => 34,
			'Z' => 35,
		);
		
		$iban = str_replace( 'IBAN', '', mb_strtoupper($iban) );
		$iban = preg_replace( '/[\s\-\.\/]/', '', $iban );
		
		if ( array_key_exists( substr( $iban, 0, 2 ), $countries ) and strlen($iban) === $countries[substr( $iban, 0, 2 )] ) {
			$moved_char = substr( $iban, 4 ).substr( $iban, 0, 4 );
			$moved_char_array = str_split($moved_char);
			$controll_string = '';
			
			foreach ( $moved_char_array as $key => $value ) {
				if ( ! is_numeric($moved_char_array[$key]) ) {
					$moved_char_array[$key] = $translate_chars[$moved_char_array[$key]];
				}
				$controll_string .= $moved_char_array[$key];
			}
			
			if ( intval($controll_string) % 97 === 1 ) {
				return substr( $iban, 0, 4 )." ".substr( $iban, 4, 4 )." ".substr( $iban, 8, 4 )." ".substr( $iban, 12, 4 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} else {
			return 'INVALID LENGTH';
		}
	}
	
	function format_place( $value ) {
		return trim_and_uppercase( $value );
	}
	
	function format_zipcode( $value ) {
		// Opgelet: niet-numerieke tekens bewust niet verwijderen, anders problemen met NL-postcodes!
		// Gebruik eventueel WC_Validation::is_postcode( $postcode, $country )
		return trim( $value );
	}
	
	function format_city( $value ) {
		return trim_and_uppercase( $value );
	}
	
	function format_mail( $value ) {
		return mb_strtolower( trim($value) );
	}
	
	function format_headquarter( $value ) {
		return trim_and_uppercase( $value );
	}
	
	// Sta een optionele parameter toe om puntjes te zetten in plaats van spaties (maar: wordt omgezet in streepjes door wc_format_phone() dus niet gebruiken in verkoop!)
	function format_phone_number( $value, $delim = ' ' ) {
		if ( $delim === '.' ) {
			$slash = '/';
		} else {
			$slash = $delim;
		}
		
		// Verwijder alle non-digits
		$value = preg_replace('/[^0-9]/', '', str_replace( '+32', '0032', $value ) );
		
		// Wis Belgische landcodes
		// @toDo: Andere landcodes checken en ook formatteren?
		if ( substr( $value, 0, 4 ) === '0032' ) {
			$value = substr( $value, 4 );
		}
		
		// Voeg indien nodig leading zero toe
		if ( substr( $value, 0, 1 ) !== '0' ) {
			$value = '0' . $value;
		}
		
		if ( strlen( $value ) == 9 ) {
			// Vaste telefoonnummers
			if ( intval( $value[1] ) == 2 or intval( $value[1] ) == 3 or intval( $value[1] ) == 4 or intval( $value[1] ) == 9 ) {
				// Zonenummer van twee cijfers
				$phone = substr( $value, 0, 2 ) . $slash . substr( $value, 2, 3 ) . $delim . substr( $value, 5, 2 ) . $delim . substr( $value, 7, 2 );
			} else {
				// Zonenummer van drie cijfers
				$phone = substr( $value, 0, 3 ) . $slash . substr( $value, 3, 2 ) . $delim . substr( $value, 5, 2 ) . $delim . substr( $value, 7, 2 );
			}
		} elseif ( strlen( $value ) == 10 ) {
			// Mobiele telefoonnummers
			$phone = substr( $value, 0, 4 ) . $slash . substr( $value, 4, 2 ) . $delim . substr( $value, 6, 2 ) . $delim . substr( $value, 8, 2 );
		} else {
			// Wis ongeldige nummers
			if ( is_checkout() ) {
				// Behalve op checkout, want dan triggeren we een obscure 'Gelieve de verplichte velden in te vullen'-foutmelding!
				$phone = $value;
			} else {
				$phone = '';
			}
		}
		
		return $phone;
	}
	
	function format_hour( $value ) {
		if ( strlen($value) === 5 ) {
			// Wordpress: geen wijzigingen meer nodig!
			return $value;
		} elseif ( strlen($value) === 4 ) {
			// Drupal: voeg dubbele punt toe in het midden
			return substr( $value, 0, 2 ) . ':' . substr( $value, 2, 2 );
		} else {
			// Drupal: voeg nul toe vooraan bij ochtenduren
			return '0'.substr( $value, 0, 1 ) . ':' . substr( $value, 1, 2 );
		}
	}
	
	function format_date( $value ) {
		$new_value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( strlen($new_value) === 8 ) {
			return substr( $new_value, 0, 2 ) . '/' . substr( $new_value, 2, 2 ) . '/' . substr( $new_value, 4, 4 );
		} elseif ( strlen($new_value) === 0 ) {
			// Ontbrekende datum
			return '';
		} else {
			// Ongeldige datum (dit laat ons toe om het onderscheid te maken!)
			return '31/12/2100';
		}
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