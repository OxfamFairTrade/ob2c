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

		require_once '../../../wp-blog-header.php';
		require_once '../../../wc-api/autoload.php';

		use Automattic\WooCommerce\Client;

		$woocommerce = new Client(
			site_url(), WC_KEY, WC_SECRET,
			[
        		'wp_api' => true,
        		// v2 nodig voor WC 3.0
        		'version' => 'wc/v2',
        		// Moet erbij, anders miserie!
        		'query_string_auth' => true,
    		]
		);

		$endpoint = 'products/categories';
		$parameters = array( 'orderby' => 'name', 'per_page' => 10, 'parent' => 0 );
		
		// $results = $woocommerce->get($endpoint, $parameters);
		
		foreach ($results as $category) {
			// echo $category['name'].' ('.$category['count'].')<br>';
		}

		$endpoint = 'products';
		unset($parameters);
		// Term-ID 601 = Oxfam Fair Trade, dus 'attribute_term' => 601 toevoegen om te filteren
		// Parameter 'per_page' mag niet te groot zijn, anders error!
		$parameters1 = array( 'attribute' => 'pa_merk', 'status' => 'publish', 'orderby' => 'date', 'order' => 'asc', 'per_page' => 100, 'page' => 1, );
		$parameters2 = array( 'attribute' => 'pa_merk', 'status' => 'publish', 'orderby' => 'date', 'order' => 'asc', 'per_page' => 100, 'page' => 2, );
		$parameters3 = array( 'attribute' => 'pa_merk', 'status' => 'publish', 'orderby' => 'date', 'order' => 'asc', 'per_page' => 100, 'page' => 3, );
		$results = array_merge( $woocommerce->get($endpoint, $parameters1), $woocommerce->get($endpoint, $parameters2), $woocommerce->get($endpoint, $parameters3) );
		// var_dump($results[0]);
		
		$cnt = 0;

		echo '<div class="container-fluid">';
			echo '<div class="row">';

			foreach ($results as $product) {
				// Opgelet: indien er geen foto aan het product gelinkt is krijgen we de placeholder door, maar zonder id!
				$wp_full = wp_get_attachment_image_src( $product['images'][0]['id'], 'full' );
				$wp_large = wp_get_attachment_image_src( $product['images'][0]['id'], 'large' );
				$wp_medium = wp_get_attachment_image_src( $product['images'][0]['id'], 'medium' );
				$shop_single = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_single' );
				$shop_catalog = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_catalog' );
				$shop_thumbnail = wp_get_attachment_image_src( $product['images'][0]['id'], 'shop_thumbnail' );
				
				if ( ! empty($shop_thumbnail) ) {
					$cnt++;
					
					// Staat pa_merk nu toevallig als eerste in de lijst of niet? 
					echo '<div class="col-sm-6 col-md-4 col-xl-3" style="padding: 2em; text-align: center; border-bottom: 1px solid black;"><small style="color: vampire grey; font-style: italic;">'.$product['attributes'][0]['options'][0].' '.$product['sku'].'</small><br>';
					echo '<p style="padding: 0 10%; height: 30px;"><b>'.$product['name'].'</b></p>';
					echo '<a href="'.$product['permalink'].'"><img style="max-width: 100%;" src="'.$shop_catalog[0].'"></a><br>';
					echo '<u>Downloads:</u><br>';
					echo '<a href="'.$wp_full[0].'" target="_blank">Full</a> ('.$wp_full[1].' x '.$wp_full[2].' pixels)<br>';
					if ( $wp_full[1] !== $wp_large[1] ) {
						echo '<a href="'.$wp_large[0].'" target="_blank">Large</a> ('.$wp_large[1].' x '.$wp_large[2].' pixels)<br>';
					}
					if ( $wp_large[1] !== $wp_medium[1] ) {
						echo '<a href="'.$wp_medium[0].'" target="_blank">Medium</a> ('.$wp_medium[1].' x '.$wp_medium[2].' pixels)<br>';
					}
					if ( $wp_medium[1] !== $shop_single[1] ) {
						echo '<a href="'.$shop_single[0].'" target="_blank">Detail</a> ('.$shop_single[1].' x '.$shop_single[2].' pixels)<br>';
					}
					echo '<a href="'.$shop_catalog[0].'" target="_blank">Catalog</a> ('.$shop_catalog[1].' x '.$shop_catalog[2].' pixels)<br>';
					echo '<a href="'.$shop_thumbnail[0].'" target="_blank">Thumbnail</a> ('.$shop_thumbnail[1].' x '.$shop_thumbnail[2].' pixels)</div>';
				}
			}

			echo '</div>';
		echo '</div>';

		echo '<p>Deze pagina toont '.$cnt.' producten.</p>';

	?>

	<script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>

</body>

</html>