<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require( '../../../wp-blog-header.php' );
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			// Negeer main site én gearchiveerde sites
			$sites = get_sites( array( 'site__not_in' => array(1), 'archived' => 0, ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				$data_store = WC_Data_Store::load( 'order' );
				$unpaid_order_ids = $data_store->get_unpaid_orders( strtotime( '-7 days', current_time( 'timestamp' ) ) );
				
				if ( $unpaid_order_ids ) {
					foreach ( $unpaid_order_ids as $unpaid_order_id ) {
						$order = wc_get_order( $unpaid_order_id );
						if ( apply_filters( 'woocommerce_cancel_unpaid_order', 'checkout' === $order->get_created_via(), $order ) ) {
							$order->update_status( 'cancelled', 'Automatisch geannuleerd wegens niet betaald na 7 dagen.' );
							write_log('Bestelling '.$order->get_order_number().' geannuleerd bij '.$site->blogname.'!');
						}
					}
				} else {
					write_log('Geen bestellingen te annuleren bij '.$site->blogname.'!');	
				}

				restore_current_blog();
			}
			echo "Oude onbetaalde bestellingen geannuleerd!";

			// DATABASE UPDATEN
			// https://shop.oxfamwereldwinkels.be/regioleuven/wp-admin/admin.php?page=wc-settings&do_update_woocommerce=true
			// IN DE KIJKERS CHECKEN
    	} else {
    		die("Helaba, dit mag niet!");
    	}
	?>
</body>

</html>