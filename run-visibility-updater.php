<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		$args = array(
			'post_type'			=> 'product',
			'post_status'		=> array( 'publish', 'draft' ),
			'posts_per_page'	=> -1,
		);

		$to_toggle = new WP_Query( $args );

		if ( $to_toggle->have_posts() ) {
			$i = 0;
			while ( $to_toggle->have_posts() ) {
				$to_toggle->the_post();
				$productje = wc_get_product( get_the_ID() );
				if ( is_numeric( $productje->get_sku() ) ) {
					// Gewone producten
					$productje->set_catalog_visibility('hidden');
					$productje->set_catalog_visibility('visible');
					$productje->save();
				} else {
					// Leeggoed
					$productje->set_catalog_visibility('visible');
					$productje->set_catalog_visibility('hidden');
					$productje->save();
				}
				echo $productje->get_sku()." getoggled!<br>";
				$i++;
			}
			echo "<br>".$i." producten doorlopen!";
			wp_reset_postdata();
		}
	?>
</body>

</html>