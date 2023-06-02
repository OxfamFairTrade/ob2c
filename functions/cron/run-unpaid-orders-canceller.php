<?php
	
	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// Gebruik van absoluut pad vereist om ook te werken als cron job met PHP i.p.v. WGET
	// dirname() enkel geldig vanuit subfolder in subfolder van themamap
	require_once dirname( __FILE__, 6 ) . '/wp-load.php';
	
	if ( ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) or ( isset( $argv ) and $argv[1] === 'RUN_FROM_CRON' ) ) {
		// Sluit afgeschermde en gearchiveerde webshops uit
		$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, ) );
		$logger = wc_get_logger();
		$context = array( 'source' => 'Clean & Tidy' );
		
		$headers[] = 'From: "Helpdesk E-Commerce" <'.get_site_option('admin_email').'>';
		$headers[] = 'Content-Type: text/html';
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			echo get_bloginfo('name').'<br/>';
			
			$unpaid_args = array(
				'type' => 'shop_order',
				'status' => 'pending',
				'date_created' => '<'.strtotime('-3 days'),
				'limit' => -1,
			);
			$unpaid_orders = wc_get_orders( $unpaid_args );
			
			if ( count( $unpaid_orders ) > 0 ) {
				// Als we dit meteen in dezelfde run doen werkt het niet, dus 2de cron job 5 minuten later met extra parameter
				if ( isset( $_GET['fix_mollie_bug'] ) ) { 
					echo 'UNPAID ORDERS FIX<br/>';
					foreach ( $unpaid_orders as $order ) {
						echo $order->get_order_number().'<br/>';
						$order->update_status( 'cancelled', 'Fix heropenen van bestelling na 1ste annulatie door bug in Mollie-plugin.');
					}
				} else {
					echo 'UNPAID ORDERS<br/>';
					foreach ( $unpaid_orders as $order ) {
						echo $order->get_order_number().'<br/>';
						if ( $order->update_status( 'cancelled', 'Automatisch geannuleerd wegens niet betaald na 3 dagen.' ) ) {
							$logger->info( $order->get_order_number().": geannuleerd wegens onbetaald", $context );
						} else {
							$logger->warning( $order->get_order_number().": annulatie mislukt", $context );
						}
					}
				}
			}
			
			$unfinished_args = array(
				'type' => 'shop_order',
				'status' => 'processing',
				'date_paid' => '<'.strtotime('-4 weekdays'),
				'limit' => -1,
			);
			$unfinished_orders = wc_get_orders( $unfinished_args );
			
			// Bij custom statussen moeten we de 'wc'-prefix blijven toevoegen, anders vinden we gewoon alle orders! 
			$unfinished_args['status'] = 'wc-claimed';
			$unfinished_orders = array_merge( $unfinished_orders, wc_get_orders( $unfinished_args ) );
			
			if ( count( $unfinished_orders ) > 0 ) {
				echo 'LATE ORDERS<br/>';
				foreach ( $unfinished_orders as $order ) {
					// Sluit B2B-orders (die geen gegarandeerde doorlooptijd hebben) uit
					if ( $order->get_meta('is_b2b_sale') !== 'yes' and $order->get_meta('estimated_delivery') !== '' ) {
						// Check of de deadline al gepasseerd is
						if ( current_time('timestamp') > $order->get_meta('estimated_delivery') ) {
							// Verstuur meldingen slechts om de 3 werkdagen
							if ( $order->get_meta('_overdue_reminder_sent') === '' or $order->get_meta('_overdue_reminder_sent') < strtotime('-3 weekdays') ) {
								echo $order->get_order_number().'<br/>';
								$attachments[] = WP_CONTENT_DIR.'/uploads/xlsx/'.$order->get_meta('_excel_file_name');
								$body = '<p>Opgelet: bestelling '.$order->get_order_number().' zou tegen '.date_i18n( 'd/m/Y H:i', $order->get_meta('estimated_delivery') ).' geleverd worden maar het order is nog niet als afgerond gemarkeerd in de webshop! Hierdoor blijft de klant online in het ongewisse. Gelieve actie te ondernemen.</p><p><a href="'.$order->get_edit_order_url().'" target="_blank">Bekijk het order in de back-end (inloggen vereist) &raquo;</a></p><p>&nbsp;</p><p><i>Dit is een automatisch bericht.</i></p>';
								if ( wp_mail( get_webshop_email(), $order->get_order_number().' wacht op verwerking', '<html>'.$body.'</html>', $headers, $attachments ) ) {
									$logger->warning( $order->get_order_number().": waarschuwing verstuurd over laattijdige afwerking", $context );
									$order->add_order_note( 'Bestelling nog niet afgewerkt! Automatische herinnering verstuurd naar webshopmailbox.' );
									
									if ( $order->get_meta('_overdue_reminder_sent') !== '' ) {
										// Admins enkel verwittigen vanaf 2de herinnering
										send_automated_mail_to_helpdesk( $order->get_order_number().' wacht op verwerking bij '.get_webshop_name(), $body );
									}
									
									$order->update_meta_data( '_overdue_reminder_sent', current_time('timestamp') );
									$order->save();
								} else {
									$logger->warning( $order->get_order_number().": waarschuwing versturen mislukt", $context );
								}
								// Voorkom dat we de Excel ook naar de volgende bestemmeling sturen!
								unset( $attachments );
							}
						}
					}
				}
			}
			
			// DOOR EEN BUG IN DE MOLLIE-PLUGIN WORDEN GEDEELTELIJK TERUGBETAALDE BESTELLINGEN OP ON-HOLD GEZET
			$refunded_args = array(
				'type' => 'shop_order',
				'status' => 'on-hold',
				'limit' => -1,
			);
			$refunded_orders = wc_get_orders( $refunded_args );
			
			if ( count( $refunded_orders ) > 0 ) {
				echo 'REFUNDED ORDERS<br/>';
				foreach ( $refunded_orders as $order ) {
					// We gebruiken bewust geen $order->update_status() om te vermijden dat we nogmaals een mail naar de klant triggeren
					if ( wp_update_post( array( 'ID' => $order->get_id(), 'post_status' => 'wc-completed' ) ) == $order->get_id() ) {
						$order->add_order_note( 'Fix automatisch heropenen van bestelling door bug in Mollie-plugin na gedeeltelijke terugbetaling.' );
						$order->save();
						echo $order->get_order_number().' OK<br/>';
						$logger->info( $order->get_order_number().": opnieuw afgerond na gedeeltelijke terugbetaling", $context );
					} else {
						echo $order->get_order_number().' NOT OK<br/>';
						$logger->warning( $order->get_order_number().": opnieuw afronden mislukt", $context );
					}
				}
			}
			
			echo '<br/>';
			restore_current_blog();
		}
	} else {
		die("Access prohibited!");
	}
	
?>