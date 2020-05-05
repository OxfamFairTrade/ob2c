<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			// Verplaats alle WP Stores naar de prullenbak
			$all_store_args = array(
				'post_type'	=> 'wpsl_stores',
				'post_status' => 'publish',
				'posts_per_page' => -1,
			);

			$trashers = new WP_Query($all_store_args);
		
			if ( $trashers->have_posts() ) {
				while ( $trashers->have_posts() ) {
					$trashers->the_post();
					wp_trash_post( get_the_ID() );
				}
				wp_reset_postdata();
			}

			// Leeg de 'store data'-cache zodat openingsuren onmiddellijk bijgewerkt worden, ook als er nog een transient bestaat van vòòr de wijzigingen
			global $wpdb;
			$wpdb->query( "DELETE FROM `$wpdb->sitemeta` WHERE `meta_key` LIKE ('%_store_data')" );

			// Sluit afgeschermde en gearchiveerde webshops uit
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
					
					// Sluit hoofdsite uit
					if ( ! is_main_site() ) {
						// Maak de lokale kaart aan met alle deelnemende winkelpunten, inclusief externen
						if ( $locations = get_option( 'woocommerce_pickup_locations' ) ) {
							$local_file = fopen("../../maps/site-".$site->blog_id.".kml", "w");
							$txt = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
							// Icon upscalen boven 32x32 pixels werkt helaas niet, <BalloonStyle><bgColor>ffffffbb</bgColor></BalloonStyle> evenmin
							$txt .= "<Style id='pickup'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/markers/placemarker-afhaling.png</href></Icon></IconStyle></Style>";
							
							foreach ( $locations as $location ) {
								$shop_post_id = intval( get_option('oxfam_shop_post_id') );
								$parts = explode( 'id=', $location['note'] );
								if ( isset( $parts[1] ) ) {
									$custom_shop_post_id = intval( str_replace( ']', '', $parts[1] ) );
									if ( $custom_shop_post_id > 0 ) {
										$shop_post_id = $custom_shop_post_id;
									}
								}

								// Want get_company_address() en get_oxfam_shop_data('ll') enkel gedefinieerd voor wereldwinkels!
								if ( $shop_post_id > 0 ) {
									$txt .= "<Placemark>";
									$txt .= "<name><![CDATA[".$location['shipping_company']."]]></name>";
									$txt .= "<styleUrl>#pickup</styleUrl>";
									$oww_store_data = get_external_wpsl_store( $shop_post_id );
									$txt .= "<description><![CDATA[<p>".get_company_address( $shop_post_id )."</p><p><a href=".$oww_store_data['link']." target=_blank>Naar de winkelpagina »</a></p>]]></description>";
									$txt .= "<Point><coordinates>".get_oxfam_shop_data( 'll', 0, false, $shop_post_id )."</coordinates></Point>";
									$txt .= "</Placemark>";
								}
							}

							$txt .= "</Document></kml>";
							fwrite($local_file, $txt);
							fclose($local_file);
						}

						// Vraag de bestaande winkel op
						$post_args = array(
							'post_type'	=> 'wpsl_stores',
							'post_status' => 'trash',
							'posts_per_page' => 1,
							'meta_key' => 'wpsl_oxfam_shop_post_id',
							'meta_value' => get_option('oxfam_shop_post_id'),
						);

						// Zoek op de hoofdsite de zonet verwijderde WP Store op die past bij de OWW-node
						switch_to_blog(1);
						$stores = new WP_Query( $post_args );
						switch_to_blog( $site->blog_id );

						if ( $stores->have_posts() ) {
							$stores->the_post();
							$store_id = get_the_ID();
							wp_reset_postdata();
						} else {
							// Maak nieuwe store aan door de ID op 0 te zetten
							$store_id = 0;
						}
						
						$ll = explode( ',', get_oxfam_shop_data('ll') );
						$store_args = array(
							'ID' =>	$store_id,
							'post_title' => get_company_name(),
							'post_status' => 'publish',
							'post_author' => 1,
							'post_type' => 'wpsl_stores',
							'meta_input' => array(
								'wpsl_oxfam_shop_post_id' => get_option('oxfam_shop_post_id'),
								'wpsl_address' => get_oxfam_shop_data('place'),
								'wpsl_city' => get_oxfam_shop_data('city'),
								'wpsl_zip' => get_oxfam_shop_data('zipcode'),
								'wpsl_country' => 'België',
								'wpsl_lat' => $ll[1],
								'wpsl_lng' => $ll[0],
								'wpsl_url' => get_site_url().'/',
								'wpsl_email' => get_company_email(),
								'wpsl_phone' => get_oxfam_shop_data('telephone'),
							),
						);

						if ( get_closing_days() !== false ) {
							// Neem de ingestelde sluitingsdagen over uit de OWW-site
							update_option( 'oxfam_holidays', get_closing_days() );
						} else {
							// Zorg dat we geen lege array achterlaten die de default waardes blokkeert
							delete_option('oxfam_holidays');
						}

						if ( ! does_home_delivery() ) {
							// Alternatieve marker indien enkel afhaling
							$store_args['meta_input']['wpsl_alternate_marker_url'] = get_stylesheet_directory_uri().'/markers/placemarker-afhaling.png';
						}

						// Maak aan op hoofdsite
						switch_to_blog(1);
						$result = wp_insert_post( $store_args );
						// Winkelcategorie op deze manier instellen, 'tax_input'-argument bij wp_insert_post() werkt niet
						wp_set_object_terms( $result, 'afhaling', 'wpsl_store_category', false );
						if ( ! array_key_exists( 'wpsl_alternate_marker_url', $store_args['meta_input'] ) ) {
							// Tweede categorie instellen indien niet enkel afhaling
							wp_set_object_terms( $result, 'levering', 'wpsl_store_category', true );
						}
						switch_to_blog( $site->blog_id );
					}
					
				restore_current_blog();
			}

			write_log("Kaarten bijgewerkt voor ".( count($sites) - 1 )." webshops!");
			echo "The end";
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>