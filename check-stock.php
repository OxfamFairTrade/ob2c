<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		switch_to_blog(1);

		$args = array(
			'post_type'			=> 'product',
			'post_status'		=> array( 'publish' ),
			'posts_per_page'	=> -1,
		);

		$all_products = new WP_Query( $args );

		$total = 0;

		if ( $all_products->have_posts() ) {
			while ( $all_products->have_posts() ) {
				$all_products->the_post();
				$productje = wc_get_product( get_the_ID() );
				if ( $productje->get_catalog_visibility() !== 'hidden' ) {
					$total++;
				}
			}
			wp_reset_postdata();
		}

		restore_current_blog();

		$sites = get_sites( array( 'site__not_in' => array(1), 'archived' => 0, ) );
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			// $args = array(
			// 	'post_type'			=> 'product',
			// 	'post_status'		=> array( 'publish' ),
			// 	'posts_per_page'	=> -1,
			// );

			// $all_products = new WP_Query( $args );

			// $instock = 0;
			// $outofstock = 0;

			// if ( $all_products->have_posts() ) {
			// 	while ( $all_products->have_posts() ) {
			// 		$all_products->the_post();
			// 		$productje = wc_get_product( get_the_ID() );
			// 		if ( $productje->get_catalog_visibility() !== 'hidden' ) {
			// 			if ( $productje->get_stock_status() === 'instock' ) {
			// 				$instock++;
			// 			} else {
			// 				$outofstock++;
			// 			}
			// 		}
			// 	}
			// 	wp_reset_postdata();
			// }

			// echo $site->blogname.': '.$instock.' producten op voorraad, '.$outofstock.' producten uit voorraad.<br>';
			
			$term = get_term_by( 'slug', 'outofstock', 'product_visibility' ); 
			
			echo $site->blogname.': '.( $total - $term->count ).' producten op voorraad, '.$term->count.' producten uit voorraad.<br>';

			echo 'Eerstvolgende winkelafhaling: '.date( 'd/m/Y H:i', estimate_delivery_date( 'local_pickup' ) ).'<br>';
			echo 'Eerstvolgende thuislevering: '.date( 'd/m/Y H:i', estimate_delivery_date( 'flat_rate' ) ).'<br><br>';
			
			restore_current_blog();
		}
	?>
</body>

</html>