<!DOCTYPE html>
<html lang="nl">

<head>
	 <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Productfoto's</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
</head>

<body style="margin: 20px;">

	<?php

		require_once 'wp-blog-header.php';
		require_once 'wc-api/autoload.php';

		use Automattic\WooCommerce\Client;

		$woocommerce = new Client(
			site_url(), WC_KEY, WC_SECRET,
			[
        		'wp_api' => true,
        		'version' => 'wc/v2',
        		'query_string_auth' => true,
    		]
		);

		$cat_parameters = array( 'orderby' => 'name', 'order' => 'asc', 'per_page' => 10, 'parent' => 0 );
		$categories = $woocommerce->get( 'products/categories', $cat_parameters );

		$empty_parameters = array( 'status' => 'publish', 'sku' => 'WLBS6M,WLBS24M,WLFSG,WLFSK', 'orderby' => 'id', 'order' => 'asc' );
		$empty_products = $woocommerce->get( 'products', $empty_parameters );

		foreach ( $empty_products as $empty ) {
			$empties[] = $empty['id'];
		}

		$product_count = 0;
		$photo_count = 0;

		echo '<div class="container-fluid">';
			foreach ( $categories as $category ) {
				echo '<div class="row" style="margin-bottom: 5em;">';
					echo '<div class="col-sm-12 col-md-12 col-xl-12" style="padding: 0; margin: 0; border-bottom: 3px solid black;">';
						echo '<h2>'.$category['name'].' ('.$category['count'].' producten)</h2>';
					echo '</div>';
					// Parameter 'per_page' mag niet te groot zijn, anders error!
					// Laat de leeggoeditems weg uit de telling
					$prod_parameters = array( 'category' => $category['id'], 'status' => 'publish', 'exclude' => $empties, 'orderby' => 'title', 'order' => 'asc', 'per_page' => 100, );
					$products = $woocommerce->get( 'products', $prod_parameters );

					// Stop alle producten in een array met als key hun artikelnummer
					foreach ( $products as $product ) {
						$ordered_products[$product['sku']] = $product;
					}
					// Orden de resultaten uit de categorie op artikelnummer
					ksort($ordered_products);

					foreach ( $ordered_products as $product ) {
						$product_count++;
						
						// Opgelet: indien er geen foto aan het product gelinkt is krijgen we de placeholder door, maar zonder id!
						$wp_full = wp_get_attachment_image_src( $product['images'][0]['id'], 'full' );
						$wp_large = wp_get_attachment_image_src( $product['images'][0]['id'], 'large' );
						$wp_medium = wp_get_attachment_image_src( $product['images'][0]['id'], 'medium' );
						$shop_single = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_single' );
						$shop_catalog = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_catalog' );
						$shop_thumbnail = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_thumbnail' );
						
						if ( ! empty($shop_thumbnail) ) {
							$photo_count++;
							
							echo '<div class="col-sm-6 col-md-4 col-xl-3" style="padding: 2em 1em; text-align: center; border-bottom: 1px solid black;">';
								// Het 'pa_merk'-attribuut stond slechts toevallig als eerste in de lijst, refactor!
								foreach ( $product['attributes'] as $attribute ) {
									if ( $attribute['name'] === 'Merk' ) {
										$merk = $attribute['options'][0];
										break;
									}
								}
								// echo '<pre>'.var_export($product['attributes'], true).'</pre>';
								echo '<small style="color: vampire grey; font-style: italic;">'.$merk.' '.$product['sku'].'</small><br>';
								echo '<div style="padding: 0; height: 50px; display: flex; align-items: center;"><p style="font-weight: bold; margin: 0; text-align: center; width: 100%;">'.$product['name'].'</p></div>';
								echo '<a href="'.str_replace( 'shop.oxfamwereldwinkels.be/', 'shop.oxfamwereldwinkels.be/regiobrugge/', $product['permalink'] ).'" title="Bekijk in webshop van Brugge" target="_blank"><img style="max-width: 100%;" src="'.$shop_catalog[0].'"></a><br>';
								echo '<u>Downloads:</u><br>';
								echo '<a href="'.$wp_full[0].'" title="Download" target="_blank">Full</a> ('.$wp_full[1].' x '.$wp_full[2].' pixels)<br>';
								if ( $wp_full[1] !== $wp_large[1] ) {
									echo '<a href="'.$wp_large[0].'" title="Download" target="_blank">Large</a> ('.$wp_large[1].' x '.$wp_large[2].' pixels)<br>';
								}
								if ( $wp_large[1] !== $wp_medium[1] ) {
									echo '<a href="'.$wp_medium[0].'" title="Download" target="_blank">Medium</a> ('.$wp_medium[1].' x '.$wp_medium[2].' pixels)<br>';
								}
								if ( $wp_medium[1] !== $shop_single[1] ) {
									echo '<a href="'.$shop_single[0].'" title="Download" target="_blank">Detail</a> ('.$shop_single[1].' x '.$shop_single[2].' pixels)<br>';
								}
								echo '<a href="'.$shop_catalog[0].'" title="Download" target="_blank">Catalog</a> ('.$shop_catalog[1].' x '.$shop_catalog[2].' pixels)<br>';
								echo '<a href="'.$shop_thumbnail[0].'" title="Download" target="_blank">Thumbnail</a> ('.$shop_thumbnail[1].' x '.$shop_thumbnail[2].' pixels)';
							echo '</div>';
						}
					}
				echo '</div>';
				// Zorg dat de producten niet opduiken in de volgende categorie
				unset($ordered_products);
			}
		echo '</div>';

		echo '<p style="text-align: right; width: 100%;">';
			echo '<i>Deze pagina toont '.$photo_count.' packshots uit een totaal van '.$product_count.' actuele wereldwinkelproducten.</i>';
		echo '</p>';

	?>

	<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>

</body>

</html>