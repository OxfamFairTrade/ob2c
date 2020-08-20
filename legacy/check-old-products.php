<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		$sites = get_sites( array( 'site__not_in' => array(1), 'archived' => 0, ) );
		
		switch_to_blog(1);

		$args = array(
			'post_type'			=> 'product',
			'post_status'		=> array( 'publish' ),
			'posts_per_page'	=> -1,
			'meta_key'			=> '_in_bestelweb',
			'meta_value'		=> 'nee',
		);
		$old_products = new WP_Query( $args );
		
		while ( $old_products->have_posts() ) {
			$old_products->the_post();
			$main_product = wc_get_product( get_the_ID() );
			
			if ( $main_product->get_catalog_visibility() !== 'hidden' ) {
				$sku = $main_product->get_sku();
				echo '<b>'.$main_product->get_name().' ('.$main_product->get_sku().')</b><br/>';

				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					
					$local_product = wc_get_product( wc_get_product_id_by_sku($sku) );
					if ( $local_product->get_stock_status() === 'instock' ) {
						echo 'Nog op voorraad bij '.$site->blogname.'<br/>';	
					}

					restore_current_blog();
				}

				echo '<br/><br/>';
			}
		}
		wp_reset_postdata();
	?>
</body>

</html>