<?php
/**
 * Single Product stock.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ook al doen we voorraadbeheer, statuslabel nooit tonen op nationaal niveau
if ( ! is_main_site() ) {
	?>
		<p class="stock <?php echo esc_attr( $class ); ?>"><?php echo wp_kses_post( $availability ); ?></p>
	<?php

	// Variable $product wordt via template argumenten doorgegeven door WooCommerce, geen global nodig
	if ( ( ! $product->is_in_stock() or $product->is_on_backorder() ) and is_national_product( $product->get_id() ) ) {
		
		if ( class_exists('WPSL_Frontend') and current_user_can('update_core') ) {

			if ( false === ( $neighbouring_webshops = get_transient('oxfam_neighbouring_webshops') ) ) {
				$current_shop_id = get_option('oxfam_shop_post_id');
				
				// Zoek de coördinaten op van de (hoofd)winkel die overeenkomt met deze webshop
				switch_to_blog(1);
				$store_args = array(
					'post_type'	=> 'wpsl_stores',
					'post_status' => 'publish',
					'posts_per_page' => 1,
					'meta_key' => 'wpsl_oxfam_shop_post_id',
					'meta_value' => $current_shop_id,
				);
				$wpsl_stores = new WP_Query( $store_args );
				
				$wpsl_store_ids = wp_list_pluck( $wpsl_stores->posts, 'ID' );
				if ( count( $wpsl_store_ids ) > 0 ) {
					$lat = floatval( get_post_meta( $wpsl_store_ids[0], 'wpsl_lat', true ) );
					$lng = floatval( get_post_meta( $wpsl_store_ids[0], 'wpsl_lng', true ) );
				} else {
					write_log("Geen winkellocatie gevonden, gebruik de coördinaten van Manneken Pis");
					$lat = 50.84510814431842;
					$lng = 4.349988998666601;
				}
				restore_current_blog();

				// Stop de naburige webshops in een lijst
				if ( $lat > 0 and $lng > 0 ) {
					$wpsl = new WPSL_Frontend();
					$args = array(
						// Te vervangen door waarde opgeslagen in WPSL-object dat overeenkomt met huidige webshop!
						'lat' => $lat,
						'lng' => $lng,
						// Lijkt niets uit te maken!
						'search_radius' => 100,
						// Overrule default waarde
						'max_results' => 20,
					);

					// Raadpleeg werking van functie in /wp-store-locator/frontend/class-frontend.php
					$stores = $wpsl->find_nearby_locations( $args );
					// var_dump_pre( $stores );
					
					// Filter de webshoploze winkels weg
					$neighbouring_webshops = wp_filter_object_list( $stores, array( 'webshopBlogId' => '' ), 'not' );
					// Er kunnen dubbels voorkomen (= meerdere winkels onder één webshop) maar dat lossen we later op
					set_transient( 'oxfam_neighbouring_webshops', $neighbouring_webshops, DAY_IN_SECONDS );

					// echo '<p>Er werden '.count( $stores ).' winkels in de buurt van '.$lat.','.$lng.' gevonden, waarvan '.count( $neighbouring_webshops ).' met een webshop.<p>';
				}
			}

			// Zoek op in welke andere webshops het product wél voorradig is
			if ( count( $neighbouring_webshops ) > 0 ) {
				// Sluit de hoofdsite en deze webshop uit
				// Geef enkel de blog-ID van de gevonden winkels door
				$neighbouring_sites = get_sites( array( 'site__not_in' => array( 1, get_current_blog_id() ), 'site__in' => wp_list_pluck( $neighbouring_webshops, 'webshopBlogId' ), 'public' => 1 ) );

				$shops_instock = array();
				$shops_outofstock = array();
				foreach ( $neighbouring_sites as $site ) {
					switch_to_blog( $site->blog_id );
					$local_product = wc_get_product( wc_get_product_id_by_sku( $product->get_sku() ) );
					if ( $local_product !== false ) {
						if ( $local_product->is_in_stock() ) {
							$shops_instock[ $site->blog_id ] = get_webshop_name();
						} else {
							$shops_outofstock[ $site->blog_id ] = get_webshop_name();
						}
					}
					restore_current_blog();
				}
				// We zouden ook dit resultaat in een kortlevende transient per SKU kunnen stoppen, als de data echt frequent opgevraagd wordt
				// var_dump_pre( $shops_instock );

				// echo '<p>We vonden '.count( $neighbouring_webshops ).' webshops in de buurt, goed voor '.count( $neighbouring_sites ).' andere subsites. Daarvan hebben '.count( $shops_instock ).' het product wél in voorraad.<p>';

				if ( count( $shops_instock ) > 0 ) {
					echo '<ul class="neighbouring-webshops">';
					// Loop over $neighbouring_webshops zodat we de volgorde op stijgende afstand bewaren
					foreach ( $neighbouring_webshops as $store ) {
						$blog_id = intval( $store['webshopBlogId'] );
						if ( array_key_exists( $blog_id, $shops_instock ) ) {
							// Link meteen naar het product in kwestie, maak de nationale URL daarvoor lokaal
							echo '<li class="available"><a href="'.esc_url( str_replace( home_url('/'), $store['webshopUrl'], $product->get_permalink() ) ).'">'.esc_html( $shops_instock[ $blog_id ] ).'</a> <small>('.esc_html( $store['distance'] ).' km)</small></li>';
							// Verhinder dat we dezelfde webshop nog eens tonen!
							unset( $shops_instock[ $blog_id ] );
						} elseif ( array_key_exists( $blog_id, $shops_outofstock ) ) {
							echo '<li class="unavailable">'.esc_html( $shops_outofstock[ $blog_id ] ).'</li>';
							unset( $shops_outofstock[ $blog_id ] );
						}
					}
					echo '</ul>';
				}
			}
		}
	}
}
