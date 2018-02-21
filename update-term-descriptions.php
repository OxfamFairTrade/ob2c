<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			$get_terms_args = array(
				'taxonomy' => 'product_partner',
				'childless' => true,
				'fields' => 'id=>name',
				'hide_empty' => false,
			);
			$partner_terms = get_terms($get_terms_args);

			global $wpdb;
			foreach ( $partner_terms as $id => $name ) {
				echo $name.' ...<br>';
				$row = $wpdb->get_row( 'SELECT * FROM partners WHERE part_naam = "'.$name.'"' );
				if ( strlen($row->part_website) > 5 ) {
					$result = wp_update_term( $id, 'product_partner', array( 'description' => '<a href="https://www.oxfamwereldwinkels.be/'.$row->part_website.'" target="_blank">Lees meer over deze partner op onze website.</a>' ) );
					var_dump($result);
					echo '<br>';
				}
			}
		} else {
    		die("Helaba, dit mag niet!");
    	}
	?>
</body>

</html>