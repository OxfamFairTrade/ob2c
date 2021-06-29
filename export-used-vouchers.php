<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Maandelijks rapport ingeruilde digitale cadeaubonnen</h1>
	
	<p>Hieronder vind je een overzicht van de vouchers die de afgelopen maand. To do: array omzetten naar Excel, vorige exports opslaan, vermijden dat vouchers dubbel gecrediteerd worden, waarschuwing indien er een terugbetaling was op het order die groter was dan het restbedrag dat niet via vouchers betaald werd.</p>

	<?php
		function get_credit_report_used_vouchers( $start_date = '2021-05-01', $end_date = '2021-05-31' ) {
			$distribution = array();

			$credit_refs = array(
				'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50 ),
				'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25 ),
				// '08901' => array( 'issuer' => 'Cera', 'value' => 30 ),
			);

			foreach ( $credit_refs as $credit_ref => $credit_params ) {
				$distribution[ $credit_ref ] = get_number_of_times_voucher_was_used( $credit_refs[ $credit_ref ]['issuer'], $credit_refs[ $credit_ref ]['value'], $start_date, $end_date );
			}		

			echo '<pre>';
			print_r( $distribution );
			echo '</pre>';
		}

		function get_number_of_times_voucher_was_used( $issuer, $value, $start_date, $end_date ) {
			global $wpdb;
			$total_value = 0;
			$repartition = array();

			// Kolom 'sold' herinterpreteren als 'credited' en filteren op lege datums?
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE issuer = '".$issuer."' AND value = ".$value." AND DATE(used) BETWEEN '" . $start_date . "' AND '" . $end_date . "';";
			$rows = $wpdb->get_results( $query );

			foreach ( $rows as $key => $row ) {
				switch_to_blog( $row->blog_id );
				$current_blog = get_blog_details();

				$args = array(
					'type' => 'shop_order',
					'status' => array('wc-completed'),
					'order_number' => $row->order,
					'limit' => -1,
				);
				$orders = wc_get_orders( $args );
				
				if ( count( $orders ) === 1 ) {
					$order = reset( $orders );
					if ( count( $order->get_refunds() ) > 0 ) {
						echo '<a href="'.$order->get_edit_order_url().'" target="_blank">Check '.$row->order.', bevat terugbetaling!</a><br/>';
					}
					$total_value += $row->value;

					// Markeer voucher als gecrediteerd in de database
					$result = $wpdb->update(
						$wpdb->base_prefix.'universal_coupons',
						array( 'sold' => date_i18n( 'Y-m-d H:i:s', strtotime('first day of next month') ) ),
						array( 'id' => $row->id )
					);
					$order->add_order_note( 'Digitale cadeaubon '.$row->code.' zal op '.date_i18n( 'j F Y', strtotime('first day of next month') ).' gecrediteerd worden door het NS.', 0, false );

					if ( is_regional_webshop() ) {
						$blog_path = $order->get_meta('claimed_by');
					} else {
						$blog_path = str_replace( '/', '', $current_blog->path );
					}

					if ( ! array_key_exists( $blog_path, $repartition ) ) {
						$repartition[ $blog_path ] = 1;
					} else {
						$repartition[ $blog_path ] += 1;
					}
				} else {
					echo 'Onverwacht aantal orders gevonden voor '.$row->order.'!<br/>';
				}

				restore_current_blog();
			}

			echo 'Total amount of '.$value.' euro vouchers to be credited for '.$issuer.' from '.$start_date.' till '.$end_date.': '.wc_price( $total_value ).'<br/>';
			return $repartition;
		}

		echo 'Startdatum: '.date_i18n( 'Y-m-d', strtotime('first day of previous month') ).'<br/>';
		echo 'Einddatum: '.date_i18n( 'Y-m-d', strtotime('last day of previous month') ).'<br/>';

		get_credit_report_used_vouchers();
	?>

	<div class="output"></div>

	<p>&nbsp;</p>

	<button class="run" style="float: right; margin-right: 20px; width: 300px;" disabled>Registreer nieuwe / gewijzigde foto's</button>
	<div class="input"></div>
</div>