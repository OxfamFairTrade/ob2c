<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Toon de decentrale structuur duidelijk in de breadcrumbs
	add_filter( 'woocommerce_get_breadcrumb', 'modify_woocommerce_breadcrumbs' );
	
	function modify_woocommerce_breadcrumbs( $crumbs ) {
		$new_crumbs = array();
		// Key 0 = Titel, Key 1 = URL
		foreach ( $crumbs as $page ) {
			if ( $page[0] === 'Home' ) {
				// Laat 'Home' naar de nationale homepage linken
				// $page[1] = 'https://'.OXFAM_MAIN_SHOP_DOMAIN.'/';
			} elseif ( $page[0] === 'Producten' and ! is_main_site() ) {
				// Voeg de lokale homepage toe tussen 'Home' en 'Producten'
				$new_crumbs[] = array( 0 => 'Webshop '.get_webshop_name(true), 1 => get_site_url() );
			}
			$new_crumbs[] = $page;
		}
		return $new_crumbs;
	}
	
	// Stel canonical tag in op lokale exemplaren van nationale pagina's (duplicate content vermijden!)
	add_filter( 'get_canonical_url', 'ob2c_tweak_canonical_url', 10, 2 );
	
	function ob2c_tweak_canonical_url( $url, $post ) {
		if ( ! is_main_site() ) {
			if ( get_post_type( $post ) === 'product' ) {
				if ( is_national_product( $post->ID ) ) {
					// Haal link van hoofdproduct op
					$national_post_id = get_post_meta( $post->ID, '_woonet_network_is_child_product_id', true );
					switch_to_blog(1);
					$url = get_permalink( $national_post_id );
					restore_current_blog();
				}
			} elseif ( is_product_tag() or is_product_category() or is_tax('partner') ) {
				// Verwijder het site path (bv. /gemeente/) uit de URL
				// Pattern komt in principe nergens anders voor, dus veilig
				$url = str_replace( get_site()->path, '/', $url );
			}
		}
		return $url;
	}
	
	// Verbeter de gestructureerde productdata voor Google
	add_filter( 'woocommerce_structured_data_product', 'ob2c_tweak_structured_data', 10, 2 );
	
	function ob2c_tweak_structured_data( $markup, $product ) {
		if ( is_main_site() ) {
			$markup['sku'] = $product->get_meta('_shopplus_code');
			$markup['gtin'] = $product->get_meta('_cu_ean');
			$markup['brand'] = array( '@type' =>  'Organization', 'name' => $product->get_attribute('merk') );
			$markup['image'] =  wp_get_attachment_image_url( $product->get_image_id(), 'shop_single' );
		}
		return $markup;
	}