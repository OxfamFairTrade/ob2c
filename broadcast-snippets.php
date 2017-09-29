<?php

	// Startpagina instellen
	$homepage = get_page_by_title( 'Startpagina' );
	if ( $homepage ) {
	    update_option( 'show_on_front', 'page' );
	    update_option( 'page_on_front', $homepage->ID );
	}

	// Voorwaardenpagina opsnorren
	$terms = get_page_by_title( 'Algemene voorwaarden' );
	if ( $terms ) {
	    update_option( 'woocommerce_terms_page_id', $terms->ID );
	}

	// Relevanssi-index opbouwen
	relevanssi_build_index();

	// Leeggoed verbergen en op voorraad zetten
	$args = array(
		'post_type'			=> 'product',
		'post_status'		=> array( 'publish' ),
		'posts_per_page'	=> -1,
	);

	$all_products = new WP_Query( $args );

	if ( $all_products->have_posts() ) {
		while ( $all_products->have_posts() ) {
			$all_products->the_post();
			$productje = wc_get_product( get_the_ID() );
			if ( ! is_numeric( $productje->get_sku() ) ) {
				$productje->set_stock_status( 'instock' );
				$productje->set_catalog_visibility('hidden');
				$productje->save();
			}
		}
		wp_reset_postdata();
	}

	// Product-ID's in kortingsbon lokaal maken
	$args = array(
		'post_type'		=> 'shop_coupon',
		'post_status'	=> array( 'publish' ),
		'title'		=> 'torrontes',
	);

	$all_coupons = new WP_Query( $args );
	
	if ( $all_coupons->have_posts() ) {
		while ( $all_coupons->have_posts() ) {
			$all_coupons->the_post();
			$ids = get_post_meta( get_the_ID(), 'product_ids', true );
			if ( $ids !== false ) {
				$global_ids = explode( ',', $ids );
				translate_main_to_local_ids( get_the_ID(), 'product_ids', $global_ids );
			}
		}
		wp_reset_postdata();
	}

	// Een welbepaalde foto verwijderen
	$photo_id = wp_get_attachment_id_by_post_name( '21515-1' );
	if ( $photo_id ) {
		// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!)
		wp_delete_attachment( $photo_id, true );
	}

	// Product weer linken juiste (geüpdatete) foto
	$product_id = wc_get_product_id_by_sku( '21515' );
	$new_photo_id = wp_get_attachment_id_by_post_name( '21515' );
	if ( $product_id and $new_photo_id ) {
		$product = wc_get_product( $product_id );
		
		// Update de mapping tussen globale en lokale foto
		switch_to_blog( 1 );
		// OPGELET: NA IMPORT BEVAT DE TITEL OP HET HOOFDNIVEAU DE OMSCHRIJVING VAN HET PRODUCT
		$new_global_photo_id = 886;
		restore_current_blog();
		$new_value = array( $new_global_photo_id => $new_photo_id );
		update_post_meta( $product_id, '_woonet_images_mapping', $new_value );
		
		// Koppel nieuw packshot aan product
		$product->set_image_id( $new_photo_id );
		$product->save();
		
		// Stel de uploadlocatie van de nieuwe afbeelding in
		wp_update_post(
			array(
				'ID' => $new_photo_id, 
				'post_parent' => $product_id,
			)
		);
	}

	// Zet een specifiek artikel uit voorraad
	$product_id = wc_get_product_id_by_sku( '21515' );
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$product->set_stock_status( 'outofstock' );
		$product->save();
	}

	// Werk de datum van een product bij
	$product_id = wc_get_product_id_by_sku( '24532' );
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$product->set_date_created( '2017-09-06T00:00:00Z' );
		$product->save();
	}

	// Tabel met stopwoorden kopiëren

?>