<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require( '../../../wp-blog-header.php' );
		
		// switch_to_blog(10);
		// $product = wc_get_product(5488);
		// $product->set_featured(true);
		// $error = $product->save();
		// var_dump($error);
		// echo "Product geüpdatet!<br>";

		// wp_update_term_count_now( $update_terms, 'pa_merk' );
		// $term = get_term( 5031, 'pa_merk' );
		// echo "Aantal Oxfam Fair Trade's: ".$term->count."<br>";
		
		$get_terms_args = array(
			'taxonomy' => 'product_partner',
			'childless' => true,
			'fields' => 'id=>name',
			'hide_empty' => false,
		);
		$partner_terms = get_terms($get_terms_args);
		var_dump($partner_terms);
		echo '<br>';

		global $wpdb;
		foreach ( $partner_terms as $id => $name ) {
			$row = $wpdb->get_row( 'SELECT * FROM partners WHERE part_naam = "'.$name.'"' );
			if ( strlen($row->part_website) > 5 ) {
				$result = wp_update_term( $id, 'product_partner', array( 'description' => '<a href="https://www.oxfamwereldwinkels.be/'.$row->part_website.'" target="_blank">Lees meer over deze partner op onze website.</a>' ) );
				var_dump($result);
				echo '<br>';
			}
		}
	?>
</body>

</html>