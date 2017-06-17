<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require( '../../../wp-blog-header.php' );
		
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
		        $countries_nl = get_countries_by_product( $productje );
				
				// Check of er wel herkomstinfo beschikbaar is
				if ( $countries_nl !== false ) {
					update_post_meta( get_the_ID(), '_herkomst_nl', implode( ', ', $countries_nl ) );
				
					foreach ( $countries_nl as $country ) {
						unset($countries_fr);
						unset($countries_en);
						$nl = get_site_option( 'countries_nl' );
						$code = array_search( $country, $nl, true );
						// We hebben een geldige landencode gevonden
						if ( strlen($code) === 3 ) {
							$countries_fr[] = translate_to_fr( $code );
							$countries_en[] = translate_to_en( $code );
						}
					}

					sort($countries_fr, SORT_STRING);
					update_post_meta( get_the_ID(), '_herkomst_fr', implode( ', ', $countries_fr ) );
					sort($countries_en, SORT_STRING);
					update_post_meta( get_the_ID(), '_herkomst_en', implode( ', ', $countries_en ) );
				}
		        echo $productje->get_sku()." bijgewerkt!<br>";
		        $i++;
			}
			echo "<br>".$i." producten doorlopen!";
			wp_reset_postdata();
		}
	?>
</body>

</html>