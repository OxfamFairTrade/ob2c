<!DOCTYPE html>
<html lang="nl">

<head>
	<meta charset="utf-8">
	<title>Lokale voorraden paaschocolade</title>
</head>

<body style="margin: 20px;">

	<?php

		require_once '../../../wp-load.php';

		$skus = array('24634', '24648', '24647' );
		$extra_skus = array( '24529', '24644', '24645' );
		$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, 'path__not_in' => array('/') ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			echo '<b>'.get_bloginfo('name').': </b>';
			
			$in_stock = array();
			foreach ( $skus as $sku ) {
				$product_id = wc_get_product_id_by_sku($sku);
				$product = wc_get_product( $product_id );
				if ( $product !== false ) {
					if ( $product->is_in_stock() ) {
						$in_stock[] = $sku.' '.$product->get_name();
					}
				}
			}
			
			$easter_products = true;
			if ( count( $in_stock ) === 0 ) {
				$easter_products = false;
			}

			foreach ( $extra_skus as $sku ) {
				$product_id = wc_get_product_id_by_sku($sku);
				$product = wc_get_product( $product_id );
				if ( $product !== false ) {
					if ( $product->is_in_stock() ) {
						$in_stock[] = $sku.' '.$product->get_name();
					}
				}
			}

			if ( count( $in_stock ) === 0 ) {
				echo '<span style="color: red;">Geen enkel paaspromoproduct op voorraad!</span><br/>';
			} else {
				if ( ! $easter_products ) {
					echo '<span style="color: orange;">Geen enkel paaspromoproduct op voorraad, wel '.implode( ' / ', $in_stock ).'</span><br/>';
				} else {
					echo '<span style="color: green;">'.implode( ' / ', $in_stock ).'</span><br/>';
				}
			}
		}

	?>

</body>

</html>