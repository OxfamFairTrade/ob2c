<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require( '../../../wp-blog-header.php' );
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			$myfile = fopen("../../../map.kml", "w");
			$str = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
			
			// Definieer de styling (icon upscalen boven 32x32 pixels werkt helaas niet)
			$str .= "<Style id='shipping'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/pointer-levering.png</href></Icon></IconStyle></Style>";
			$str .= "<Style id='pickup'><IconStyle><w>32</w><h>32</h><Icon><href>".get_stylesheet_directory_uri()."/pointer-afhaling.png</href></Icon></IconStyle></Style>";
			$str .= "<Style id='balloon'><BalloonStyle><color>ff007db3</color></BalloonStyle></Style>";
			
			// Haal alle shopdata op (sluit portaal en gearchiveerde webshops uit)
			$sites = get_sites( array( 'site__not_in' => array(1), 'archived' => 0, ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
					$str .= "<Placemark id='balloon'>";
					$str .= "<name><![CDATA[".get_company_name()."]]></name>";
					$str .= "<styleUrl>#balloon</styleUrl>";
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
				restore_current_blog();
			}

			$str .= "</Document></kml>";
			fwrite($myfile, $str);
			fclose($myfile);

			echo "Mapdata bijgewerkt!";
    	} else {
    		die("Helaba, dit mag niet!");
    	}
	?>
</body>

</html>