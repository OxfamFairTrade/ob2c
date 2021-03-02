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
			// Zoek op in welke andere webshops het product wél voorradig is
			if ( class_exists('WPSL_Frontend') ) {
				$wpsl = new WPSL_Frontend();
				$args = array(
					// Te vervangen door waarde opgeslagen in WPSL-object dat overeenkomt met huidige webshop!
					'lat' => 51.228443,
					'lng' => 2.934465,
					// Lijkt niets uit te maken!
					// 'search_radius' => 200,
					// Overrule default waarde
					'max_results' => 10,
				);

				// Raadpleeg werking van functie in /wp-store-locator/frontend/class-frontend.php
				$stores = $wpsl->find_nearby_locations( $args );
				// var_dump_pre( $stores );
				
				// Filter de webshoploze winkels weg
				$stores_with_webshop = wp_filter_object_list( $stores, array( 'webshopBlogId' => '' ), 'not' );
				// Er kunnen dubbels voorkomen (= meerdere winkels onder één webshop) maar dat lossen we later op
				// var_dump_pre( $stores_with_webshop );
				
				// Sluit deze webshop uit en geef enkel de blog-ID door
				$sites = get_sites( array( 'site__not_in' => array( get_current_blog_id() ), 'site__in' => wp_list_pluck( $stores_with_webshop, 'webshopBlogId' ), 'public' => 1 ) );
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

				var_dump_pre( $shops_instock );
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
