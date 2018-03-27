<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			// Sluit afgeschermde en gearchiveerde webshops uit
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				$data_store = WC_Data_Store::load( 'order' );
				$unpaid_order_ids = $data_store->get_unpaid_orders( strtotime( '-3 days', current_time( 'timestamp' ) ) );
				
				if ( $unpaid_order_ids ) {
					foreach ( $unpaid_order_ids as $unpaid_order_id ) {
						$order = wc_get_order( $unpaid_order_id );
						if ( apply_filters( 'woocommerce_cancel_unpaid_order', 'checkout' === $order->get_created_via(), $order ) ) {
							$order->update_status( 'cancelled', 'Automatisch geannuleerd wegens niet betaald na 3 dagen.' );
							write_log("Bestelling ".$order->get_order_number()." geannuleerd bij ".$site->blogname."!");
						}
					}
				}

				restore_current_blog();
			}

			// DATABASE UPDATEN
			// https://shop.oxfamwereldwinkels.be/regioleuven/wp-admin/admin.php?page=wc-settings&do_update_woocommerce=true
			// IN DE KIJKERS CHECKEN

			write_log("Onbetaalde bestellingen geannuleerd voor ".count($sites)." webshops!");
			echo "The end";
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>