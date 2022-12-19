<?php

	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// Relatief pad enkel geldig vanuit subfolder in subfolder van themamap!
	require_once '../../../../../wp-load.php';
	
	require_once WP_PLUGIN_DIR.'/mailchimp-3.0.php');
	use \DrewM\MailChimp\MailChimp;
	
	if ( ! current_user_can('update_core') ) {
		return;
	}
	
	// BELANGRIJK: zorgt ervoor dat enters uit Mac-files correct geïnterpreteerd worden!
	ini_set( 'auto_detect_line_endings', true );

	// OPMERKINGEN:
	// - Indien we de MailChimp-plugin voor WooCommerce gebruiken wordt de product-ID van het hoofdniveau gebruikt als ID voor het product en de variant
	// - Het ompaknummer wordt automatisch ingesteld als SKU van de variant (terwijl wij liever de ShopPlus-code willen gebruiken)
	// - Als product-URL wordt de (niet-publieke) link naar het hoofdniveau ingesteld 
	// - Producten die verwijderd werden uit WooCommerce worden niet verwijderd uit MailChimp
	// WELLICHT GEBRUIKEN WE DUS TOCH BETER ONS EIGEN CUSTOM SCRIPT

	// MAAK OOK ENKELE GENERISCHE PRODUCTEN AAN ZONDER PRIJS / FOTO
	// - W-prefix (OWW)
	// - F-prefix (OFTC)
	// - M-prefix (MDM)
	// - P-prefix (PUUR)
	// - X-prefix (EXT)
	// - Geschenkencheques = WGC02 / WGC05 / WGC15 / WGC25 + YYYY
	
	$cnt = 0;
	$created = 0;
	$updated = 0;
	$skipped = 0;
	$msg = '';

	$file = 'products.csv';
	$columns = array(
		'Artikelnummer',
		'ShopPlus',
		'Merk',
		'Categorie',
		'Productnaam',
		'Productnaam (FR)',
		'Lange beschrijving',
		'Korte beschrijving',
		'Lange beschrijving (FR)',
		'Korte beschrijving (FR)',
		'Consumentenprijs (incl. BTW)',
		'BestelWeb',
		'Packshot',
		'Biolabel',
		'Fairtradelabel',
		'Herkomst',
		'Bevat',
		'Kan sporen bevatten van',
		'Brutogewicht (kg)',
		'Lengte (mm)',
		'Breedte (mm)',
		'Hoogte (mm)',
		'BTW-tarief',
		'Ingrediënten',
		'Ingrediënten (FR)',
		'Energie (kJ)',
		'Vetten (g)',
		'Verzadigde vetten (g)',
		'Enkelvoudig onverzadigde vetten (g)',
		'Meervoudig onverzadigde vetten (g)',
		'Koolhydraten (g)',
		'Suikers (g)',
		'Polyolen (g)',
		'Zetmeel (g)',
		'Vezels (g)',
		'Eiwitten (g)',
		'Zout (g)',
		'Houdbaarheid (dagen)',
		'Netto-inhoud',
		'Eenheid',
		'Eenheidsprijs',
		'EAN',
		'Ompakhoeveelheid',
		'Leeggoed',
		'Gepubliceerd',
		'Laatst bijgewerkt',
	);

	$MailChimp = new MailChimp( MAILCHIMP_APIKEY );
	
	if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {

		// Loop over alle rijen (indien de datafile geopend kon worden)
		while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			
			if ( $cnt === 0 ) {
				foreach ( $row as $key => $value ) {
					if ( mb_strtolower($value) == 'artikelnummer' ) {
						// Eerste kolom wordt precies niet gevonden ... 
						$sku_index = $key;
					} elseif ( mb_strtolower($value) === 'shopplus' ) {
						$shopplus_index = $key;
					} elseif ( mb_strtolower($value) === 'merk' ) {
						$brand_index = $key;
					} elseif ( mb_strtolower($value) === 'categorie' ) {
						$category_index = $key;
					} elseif ( mb_strtolower($value) === 'productnaam' ) {
						$title_index = $key;
					} elseif ( mb_strtolower($value) === 'lange beschrijving' ) {
						$description_index = $key;
					} elseif ( mb_strtolower($value) === 'consumentenprijs (incl. btw)' ) {
						$price_index = $key;
					} elseif ( mb_strtolower($value) === 'packshot' ) {
						$image_url_index = $key;
					} elseif ( mb_strtolower($value) === 'gepubliceerd' ) {
						$publish_date_index = $key;
					}
				}
			} else {
				$sku = $row[0];
				$shopplus = $row[$shopplus_index];
				$brand = $row[$brand_index];
				$category = $row[$category_index];
				$title = $row[$title_index];
				$description = $row[$description_index];
				$price = floatval( $row[$price_index] );
				$image_url = $row[$image_url_index];
				$publish_date = date( DATE_ISO8601, strtotime( $row[$publish_date_index] ) );

				$current_product = $MailChimp->get( '/ecommerce/stores/'.$store_id.'/products/'.$shopplus );

				$args = array(
					'title' => $title,
					'url' => 'https://shop.oxfamwereldwinkels.be/?addSku='.$sku,
					'description' => $description,
					'type' => $brand,
					'vendor' => $category,
					'image_url' => $image_url,
					'variants' => array( array(
						'id' => $shopplus,
						'title' => $title,
						'sku' => $sku,
						'price' => $price,
						// Want anders duiken ze niet op bij productaanbevelingen
						'inventory_quantity' => 9999,
						'image_url' => $image_url,
					) ),
					'published_at_foreign' => $publish_date,
				);

				if ( $current_product['id'] === $shopplus ) {
					// Het product bestaat al, we kunnen het bijwerken
					$msg .= 'UPDATING '.$shopplus.' ...<br/>';
					$update = $MailChimp->patch( '/ecommerce/stores/'.$store_id.'/products/'.$shopplus, $args );
					if ( $update['id'] === $shopplus ) {
						$updated++;
					} else {
						$msg .= 'FAILED<br/>';
						$msg .= print_r( $update, true );
						$skipped++;
					}
				} else {
					// Het product bestaat nog niet, we moeten het aanmaken
					$msg .= 'CREATING '.$shopplus.'...<br/>';
					// We kunnen dezelfde argumenten gebruiken als bij update, maar we moeten de ID toevoegen
					$args['id'] = $shopplus;
					$create = $MailChimp->post( '/ecommerce/stores/'.$store_id.'/products', $args );
					if ( $create['id'] === $shopplus ) {
						$created++;
					} else {
						$msg .= 'FAILED<br/>';
						$msg .= print_r( $create, true );
						$skipped++;
					}
				}
			}

			$cnt++;
		}

		fclose($handle);
		$msg = "<p>".$msg."</p><p>=> ".($cnt-1)." products checked: ".$created." created, ".$updated." updated, ".$skipped." skipped</p>";

	} else {
		$msg .= "CSV file could not be read!</p>";
	}

	echo $msg;

?>