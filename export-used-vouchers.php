<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Maandelijks rapport ingeruilde digitale cadeaubonnen</h1>
	
	<p>Hieronder vind je een overzicht van de vouchers die de afgelopen maand. To do:</p>
	<ul>
		<li>array omzetten naar Excel</li>
		<li>vorige exports opslaan</li>
		<li>registreer creditering bij het downloaden van de Excel (= vermijd dat vouchers dubbel gecrediteerd worden)</li>
		<li>enkel waarschuwing indien terugbetaling op het order groter was dan het restbedrag dat niet via vouchers betaald werd</li>
	</ul>

	<?php
		$start_date = date_i18n( 'Y-m-d', strtotime('first day of previous month') );
		$end_date = date_i18n( 'Y-m-d', strtotime('last day of previous month') );
		echo 'Startdatum: '.$start_date.'<br/>';
		echo 'Einddatum: '.$end_date.'<br/>';
		$distribution = get_credit_report_used_vouchers( $start_date, $end_date );

		echo '<pre>';
		print_r( $distribution );
		echo '</pre>';

		// Laad het collisjabloon en selecteer het eerste werkblad
		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		$spreadsheet = $reader->load( get_stylesheet_directory().'/voucher-export.xlsx' );
		$spreadsheet->setActiveSheetIndex(0);
		foreach ( $distribution as $credit_ref => $number_of_credits_per_shop ) {
			// Voorlopig enkel 1ste werkblad opvullen
			if ( $credit_ref === '08899' ) {
				$i = 2;
				foreach ( $number_of_credits_per_shop as $shop => $numbers_of_credits ) {
					$spreadsheet->getActiveSheet()->setCellValue( 'A'.$i, $shop )->setCellValue( 'B'.$i, $numbers_of_credits );
					$i++;
				}
			}
		}

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

			return $distribution;
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

				$args = array(
					'type' => 'shop_order',
					'order_number' => $row->order,
					'limit' => -1,
				);
				$orders = wc_get_orders( $args );
				
				if ( count( $orders ) === 0 ) {
					echo 'Bestelling '.$row->order.' niet gevonden, voucher '.$row->code.' niet opgenomen in export!<br/>';
				} elseif ( count( $orders ) > 1 ) {
					echo 'Meerdere bestellingen gevonden voor '.$row->order.', voucher '.$row->code.' niet opgenomen in export!<br/>';
				} else {
					$order = reset( $orders );
					if ( $order->get_status() !== 'completed' ) {
						echo '<a href="'.$order->get_edit_order_url().'" target="_blank">Bestelling '.$row->order.' is nog niet afgerond, voucher '.$row->code.' niet opgenomen in export!</a><br/>';
						restore_current_blog();
						continue;
					}

					if ( count( $order->get_refunds() ) > 0 ) {
						echo '<a href="'.$order->get_edit_order_url().'" target="_blank">Controleer '.$row->order.', bestelling bevat terugbetaling!</a><br/>';
					}
					$total_value += $row->value;

					// Markeer voucher als gecrediteerd in de database PAS DOEN NA DOWNLOADEN FILE
					// $result = $wpdb->update(
					// 	$wpdb->base_prefix.'universal_coupons',
					// 	array( 'sold' => date_i18n( 'Y-m-d H:i:s', strtotime('first day of next month') ) ),
					// 	array( 'id' => $row->id )
					// );
					$order->add_order_note( 'Digitale cadeaubon '.$row->code.' zal op '.date_i18n( 'j F Y', strtotime('first day of next month') ).' gecrediteerd worden door het NS.', 0, false );

					if ( is_regional_webshop() ) {
						$blog_path = $order->get_meta('claimed_by');
					} else {
						$current_blog = get_blog_details();
						$blog_path = str_replace( '/', '', $current_blog->path );
					}

					if ( ! array_key_exists( $blog_path, $repartition ) ) {
						$repartition[ $blog_path ] = 1;
					} else {
						$repartition[ $blog_path ] += 1;
					}
				}

				restore_current_blog();
			}

			echo 'Total amount of '.$value.' euro vouchers to be credited for '.$issuer.' from '.$start_date.' till '.$end_date.': '.wc_price( $total_value ).'<br/>';
			return $repartition;
		}
	?>

	<div class="output"></div>

	<p>&nbsp;</p>

	<button class="run" style="float: right; margin-right: 20px; width: 300px;" disabled>Registreer nieuwe / gewijzigde foto's</button>
	<div class="input"></div>
</div>