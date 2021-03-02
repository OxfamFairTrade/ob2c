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
	if ( ! $product->is_in_stock() ) {
		
		if ( current_user_can('update_core') ) {
			// Haal de coördinaten van de huidige gekozen winkel op
			$current_store = false;
			if ( ! empty( $_COOKIE['latest_shop_id'] ) ) {
				$current_store = intval( $_COOKIE['latest_shop_id'] );
			}

			$shops = ob2c_get_pickup_locations();
			if ( $current_store === false or ! array_key_exists( $current_store, $shops ) ) {
				// De cookie slaat op een winkel uit een andere subsite (bv. door rechtstreeks switchen)
				// Stel de hoofdwinkel van de huidige subsite in als fallback
				$current_store = get_option('oxfam_shop_post_id');
			}

			// Zoek op de hoofdsite de WP Store op die past bij de post-ID
			switch_to_blog(1);
			$store_args = array(
				'post_type'	=> 'wpsl_stores',
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'meta_key' => 'wpsl_oxfam_shop_post_id',
				'meta_value' => $current_store,
			);
			$wpsl_stores = new WP_Query( $store_args );
			$wpsl_store_ids = wp_list_pluck( $wpsl_stores->posts, 'ID' );
			if ( count( $wpsl_store_ids ) > 0 ) {
				$lat = floatval( get_post_meta( $wpsl_store_ids[0], 'wpsl_lat', true ) );
				$lng = floatval( get_post_meta( $wpsl_store_ids[0], 'wpsl_lng', true ) );
			} else {
				$lat = 50.84510814431842;
				$lng = 4.349988998666601;
			}
			restore_current_blog();

			// Zoek op in welke andere webshops het product wél voorradig is
			if ( class_exists('WPSL_Frontend') and $lat > 0 and $lng > 0 ) {
				$wpsl = new WPSL_Frontend();
				$args = array(
					// Te vervangen door waarde opgeslagen in WPSL-object dat overeenkomt met huidige webshop!
					'lat' => $lat,
					'lng' => $lng,
					// Lijkt niets uit te maken!
					// 'search_radius' => 200,
					// Overrule default waarde
					'max_results' => 10,
				);
				var_dump_pre( $args );

				// Raadpleeg werking van functie in /wp-store-locator/frontend/class-frontend.php
				$stores = $wpsl->find_nearby_locations( $args );
				// var_dump_pre( $stores );
				
				// Filter de webshoploze winkels weg
				$stores_with_webshop = wp_filter_object_list( $stores, array( 'webshopBlogId' => '' ), 'not' );
				// Er kunnen dubbels voorkomen (= meerdere winkels onder één webshop) maar dat lossen we later op
				// var_dump_pre( $stores_with_webshop );
				
				// Sluit de hoofdsite en deze webshop uit
				// Geef enkel de blog-ID van de gevonden winkels door
				$sites = get_sites( array( 'site__not_in' => array( 1, get_current_blog_id() ), 'site__in' => wp_list_pluck( $stores_with_webshop, 'webshopBlogId' ), 'public' => 1 ) );
				// Resultaat in transient stoppen zodat we dit lijstje niet telkens opnieuw moeten opvragen?
				// var_dump_pre( $sites );

				$shops_instock = array();
				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					$local_product = wc_get_product( wc_get_product_id_by_sku( $product->get_sku() ) );
					if ( $local_product !== false and $local_product->is_in_stock() ) {
						$shops_instock[ $site->blog_id ] = get_webshop_name();
					}
					restore_current_blog();
				}
				// var_dump_pre( $shops_instock );

				echo '<p>Er werden '.count( $stores ).' winkels in de buurt gevonden, waarvan '.count( $stores_with_webshop ).' met een webshop, goed voor '.count( $sites ).' sites. Daarvan hebben '.count( $shops_instock ).' het product wél in voorraad.<p>';

				if ( count( $shops_instock ) > 0 ) {
					echo '<p>Dit product is online momenteel wel beschikbaar bij:<ul>';
					// Loop over $stores_with_webshop zodat we de volgorde op stijgende afstand bewaren
					foreach ( $stores_with_webshop as $store ) {
						$blog_id = intval( $store['webshopBlogId'] );
						if ( array_key_exists( $blog_id, $shops_instock ) ) {
							// Of lijsten we toch winkels i.p.v. webshops op?
							echo '<li><a href="'.esc_url( $store['webshopUrl'] ).'">'.esc_html( $shops_instock[ $blog_id ] ).'</a> ('.esc_html( $store['distance'] ).' km)</li>';
							// Verhinder dat we dezelfde webshop nog eens tonen!
							unset( $shops_instock[ $blog_id ] );
						}
					}
					echo '</ul></p>';
				}
			}
		}

	}
} else {
	
	// $shops_instock = array();
	// $sites = get_sites( array( 'path__not_in' => array('/'), 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, 'orderby' => 'path' ) );
	// foreach ( $sites as $site ) {
	// 	switch_to_blog( $site->blog_id );
	// 	$local_product = wc_get_product( wc_get_product_id_by_sku( $product->get_sku() ) );
	// 	if ( $local_product !== false and $local_product->is_in_stock() ) {
	// 		$shops_instock[ get_webshop_name() ] = get_site_url();
	// 	}
	// 	restore_current_blog();
	// }

	// if ( count( $shops_instock ) > 0 ) {
	// 	echo '<p>Dit product is online beschikbaar bij:</p><ul>';
	// 	foreach ( $shops_instock as $name => $url ) {
	// 	 	echo '<li><a href="'.esc_url( $url ).'">'.esc_html( $name ).'</a></li>';
	// 	 }
	// 	echo '</ul></p>';
	// }

}
