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

			$trashers = new WP_Query( $all_store_args );
		
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
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1 ) );
			$site_ids_vs_blog_ids = array();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				if ( ! is_main_site() ) {
					// Maak de lokale kaart aan met alle deelnemende winkelpunten, exclusief externen
					$locations = ob2c_get_pickup_locations();
					if ( count( $locations ) > 0 ) {
						$local_file = fopen( "../../maps/site-".$site->blog_id.".kml", "w" );
						$txt = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
						// Icon upscalen boven 32x32 pixels werkt helaas niet, <BalloonStyle><bgColor>ffffffbb</bgColor></BalloonStyle> evenmin
						$txt .= "<Style id='pickup'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/markers/placemarker-afhaling.png</href></Icon></IconStyle></Style>";
						
						foreach ( $locations as $shop_post_id => $shop_name ) {
							// Want get_shop_address() en get_oxfam_shop_data('ll') enkel gedefinieerd voor wereldwinkels!
							if ( $shop_post_id > 0 ) {
								$txt .= "<Placemark>";
								$txt .= "<name><![CDATA[".$shop_name."]]></name>";
								$txt .= "<styleUrl>#pickup</styleUrl>";
								$oww_store_data = get_external_wpsl_store( $shop_post_id );
								$txt .= "<description><![CDATA[<p>".get_shop_address( array( 'id' => $shop_post_id ) )."</p><p><a href=".$oww_store_data['link']." target=_blank>Naar de winkelpagina »</a></p>]]></description>";
								$txt .= "<Point><coordinates>".get_oxfam_shop_data( 'll', 0, false, $shop_post_id )."</coordinates></Point>";
								$txt .= "</Placemark>";

								// Maak een handige lijst met alle shop-ID's en hun bijbehorende blog-ID
								// Ook als we de kaarten volledig zouden uitschakelen blijft deze stap nodig voor de rest van het script!
								$site_ids_vs_blog_ids[ $shop_post_id ] = array(
									'blog_id' => get_current_blog_id(),
									'blog_url' => get_site_url().'/',
									'home_delivery' => does_home_delivery(),
								);
							}
						}

						$txt .= "</Document></kml>";
						fwrite( $local_file, $txt );
						fclose( $local_file );
					}
				}
					
				restore_current_blog();
			}

			var_dump_pre( $site_ids_vs_blog_ids );

			// Vraag alle huidige winkels in de OWW-site op
			$oww_stores = get_external_wpsl_stores();

			foreach ( $oww_stores as $oww_store_data ) {
				// Zoek op de hoofdsite de zonet verwijderde WP Store op die past bij de post-ID
				$post_args = array(
					'post_type'	=> 'wpsl_stores',
					'post_status' => 'trash',
					'posts_per_page' => 1,
					'meta_key' => 'wpsl_oxfam_shop_post_id',
					'meta_value' => $oww_store_data['id'],
				);
				$wpsl_stores = new WP_Query( $post_args );
				
				if ( $wpsl_stores->have_posts() ) {
					$wpsl_stores->the_post();
					$wpsl_store_id = get_the_ID();
					wp_reset_postdata();
				} else {
					// Maak nieuwe store aan door de ID op 0 te zetten
					$wpsl_store_id = 0;
				}

				$ll = explode( ',', $oww_store_data['location']['ll'] );
				
				if ( array_key_exists( $oww_store_data['id'], $site_ids_vs_blog_ids ) ) {
					// Dit moét uit de lijst met sites komen (data in OWW-site kan vervuild zijn met andere webshops!)
					$webshop_url = $site_ids_vs_blog_ids[ $oww_store_data['id'] ]['blog_url'];
					$webshop_blog_id = $site_ids_vs_blog_ids[ $oww_store_data['id'] ]['blog_id'];
					$home_delivery = $site_ids_vs_blog_ids[ $oww_store_data['id'] ]['home_delivery'];
				} else {
					$webshop_url = '';
					$webshop_blog_id = '';
					$home_delivery = false;
				}
				
				$store_args = array(
					'ID' =>	$wpsl_store_id,
					'post_title' => $oww_store_data['title']['rendered'],
					'post_status' => 'publish',
					'post_author' => 1,
					'post_type' => 'wpsl_stores',
					'meta_input' => array(
						'wpsl_oxfam_shop_post_id' => $oww_store_data['id'],
						'wpsl_address' => $oww_store_data['location']['place'],
						'wpsl_city' => $oww_store_data['location']['city'],
						'wpsl_zip' => $oww_store_data['location']['zipcode'],
						'wpsl_country' => 'België',
						'wpsl_lat' => $ll[1],
						'wpsl_lng' => $ll[0],
						'wpsl_phone' => $oww_store_data['location']['telephone'],
						'wpsl_url' => $oww_store_data['link'],
						'wpsl_webshop' => $webshop_url,
						'wpsl_webshop_blog_id' => $webshop_blog_id,
						// Vul hier bewust het algemene mailadres in (ook voor winkels mét webshop)
						'wpsl_email' => $oww_store_data['location']['mail'],
						// Openingsuren toch internaliseren?
						'wpsl_hours' => $oww_store_data['opening_hours'],
						'wpsl_mailchimp' => $oww_store_data['mailchimp_url'],
					),
				);

				if ( count( $oww_store_data['closing_days'] ) > 0 ) {
					// Neem de ingestelde sluitingsdagen over uit de OWW-site
					update_option( 'oxfam_holidays', $oww_store_data['closing_days'] );
				} else {
					// Verwijder de optie zodat we géén lege array achterlaten die de default waardes blokkeert
					delete_option('oxfam_holidays');
				}

				$result_post_id = wp_insert_post( $store_args );
				if ( ! is_wp_error( $result_post_id ) ) {
					// Verwijder de '_wp_old_date'-keys die elke dag toegevoegd worden door wp_insert_post() te gebruiken
					if ( delete_post_meta( $result_post_id, '_wp_old_date' ) ) {
						echo "Oude datums verwijderd op post-ID ".$result_post_id."<br/>";
					}

					// Winkelcategorie instellen DEPRECATED
					wp_set_object_terms( $result_post_id, 'afhaling', 'wpsl_store_category', false );
					if ( $home_delivery ) {
						// Tweede categorie instellen indien niet enkel afhaling
						wp_set_object_terms( $result_post_id, 'levering', 'wpsl_store_category', true );
					}
				}
			}

			echo "THE END";
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>