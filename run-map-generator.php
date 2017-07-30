<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require( '../../../wp-blog-header.php' );
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			$global_file = fopen("../../maps/global.kml", "w");
			$str = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
			
			// Definieer de styling (icon upscalen boven 32x32 pixels werkt helaas niet, <BalloonStyle><bgColor>ffffffbb</bgColor></BalloonStyle> evenmin)
			$str .= "<Style id='shipping'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/images/pointer-levering.png</href></Icon></IconStyle></Style>";
			$str .= "<Style id='pickup'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/images/pointer-afhaling.png</href></Icon></IconStyle></Style>";
			
			// Haal alle shopdata op (sluit portaal en gearchiveerde webshops uit)
			$sites = get_sites( array( 'site__not_in' => array( 1, 11, 25 ), 'archived' => 0, ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
					$str .= "<Placemark>";
					$str .= "<name><![CDATA[".get_company_name()."]]></name>";
					if ( does_home_delivery() ) {
						$str .= "<styleUrl>#shipping</styleUrl>";
						$extra_text = 'Deze webshop voorziet afhalingen én thuisleveringen.';
					} else {
						$str .= "<styleUrl>#pickup</styleUrl>";
						$extra_text = 'Deze webshop voorziet enkel afhalingen in de winkel.';
					}
					$str .= "<description><![CDATA[<p><a href=".get_site_url().">".get_company_address()."</a></p><p style='text-align: center;'>".$extra_text."<br><a href=".get_site_url().">Ga naar de webshop »</a></p>]]></description>";
					$str .= "<Point><coordinates>".get_oxfam_shop_data('ll')."</coordinates></Point>";
					$str .= "</Placemark>";

					if ( $locations = get_option( 'woocommerce_pickup_locations' ) ) {
						$local_file = fopen("../../maps/site-".$site->blog_id.".kml", "w");
						$txt = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
						$txt .= "<Style id='pickup'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/images/pointer-afhaling.png</href></Icon></IconStyle></Style>";
						
						foreach ( $locations as $location ) {
							$parts = explode( 'node=', $location['note'] );
							if ( isset($parts[1]) ) {
								$node = str_replace( ']', '', $parts[1] );
							} else {
								$node = get_option('oxfam_shop_node');
							}
							// Want get_company_address() en get_oxfam_shop_data('ll') nog niet gedefinieerd voor niet-wereldwinkels
							if ( is_numeric($node) ) {
								$txt .= "<Placemark>";
								$txt .= "<name><![CDATA[".$location['shipping_company']."]]></name>";
								$txt .= "<styleUrl>#pickup</styleUrl>";
								$txt .= "<description><![CDATA[<p>".get_company_address($node)."</p><p><a href=https://www.oxfamwereldwinkels.be/node/".$node." target=_blank>Naar de winkelpagina »</a></p>]]></description>";
								$txt .= "<Point><coordinates>".get_oxfam_shop_data( 'll', $node )."</coordinates></Point>";
								$txt .= "</Placemark>";
							}
						}

						$txt .= "</Document></kml>";
						fwrite($local_file, $txt);
						fclose($local_file);
					}
				restore_current_blog();
			}

			$str .= "</Document></kml>";
			fwrite($global_file, $str);
			fclose($global_file);

			echo "Mapdata bijgewerkt!";
    	} else {
    		die("Helaba, dit mag niet!");
    	}
	?>
</body>

</html>