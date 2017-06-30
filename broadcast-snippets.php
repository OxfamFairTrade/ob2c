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
				$productje->set_stock( 'instock' );
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

	// Tabel met stopwoorden kopiëren

?>