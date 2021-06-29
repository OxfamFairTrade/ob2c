<?php
	if ( ! defined('ABSPATH') ) exit;

	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
	require_once WP_PLUGIN_DIR.'/phpspreadsheet/autoload.php';
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
		echo '<b>Startdatum</b>: '.$start_date.'<br/>';
		echo '<b>Einddatum</b>: '.$end_date.'<br/>';
		
		// Haal data van voorbije maand op
		$distribution = get_credit_report_used_vouchers( $start_date, $end_date );

		// Toon resultaat op scherm
		echo '<pre>';
		print_r( $distribution );
		echo '</pre>';

		// Schrijf resultaat weg naar Excel
		export_to_excel( $distribution );

		function export_to_excel( $distribution ) {
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			$spreadsheet = $reader->load( get_stylesheet_directory().'/voucher-export.xlsx' );
			foreach ( $distribution as $credit_ref => $number_of_credits_per_shop ) {
				$i = 2;
				$spreadsheet->setActiveSheetIndexByName( $credit_ref );
				foreach ( $number_of_credits_per_shop as $shop => $numbers_of_credits ) {
					$spreadsheet->getActiveSheet()->setCellValue( 'A'.$i, $shop )->setCellValue( 'B'.$i, $numbers_of_credits );
					$i++;
				}
			}
			$writer = new Xlsx( $spreadsheet );
			// $writer->save( ABSPATH . '/../'.str_replace( '-', '', $start_date ).'-'.str_replace( '-', '', $end_date ).'-vouchers-to-credit.xlsx' );
			$writer->save( WP_CONTENT_DIR . '/latest.xlsx' );
		}
	

		function get_credit_report_used_vouchers( $start_date = '2021-05-01', $end_date = '2021-05-31' ) {
			$distribution = array();

			$credit_refs = array(
				'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50 ),
				'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25 ),
				// '08901' => array( 'issuer' => 'Cera', 'value' => 30 ),
			);

			foreach ( $credit_refs as $credit_ref => $credit_params ) {
				$rows = get_number_of_times_voucher_was_used( $credit_refs[ $credit_ref ]['issuer'], $credit_refs[ $credit_ref ]['value'], $start_date, $end_date );
				echo 'Totaalbedrag te crediteren onder referentie '.$credit_ref.': '.wc_price( array_sum( $rows ) * $credit_refs[ $credit_ref ]['value'] ).'<br/>';

				if ( count( $rows ) > 0 ) {
					$distribution[ $credit_ref ] = $rows;
				}
			}

			return $distribution;
		}

		function get_number_of_times_voucher_was_used( $issuer, $value, $start_date, $end_date ) {
			global $wpdb;
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

					// Markeer voucher als gecrediteerd in de database VERHUIZEN NAAR AJAX-ACTIE
					// $result = $wpdb->update(
					// 	$wpdb->base_prefix.'universal_coupons',
					// 	array( 'sold' => date_i18n( 'Y-m-d H:i:s', strtotime('first day of next month') ) ),
					// 	array( 'id' => $row->id )
					// );
					// $order->add_order_note( 'Digitale cadeaubon '.$row->code.' zal op '.date_i18n( 'j F Y', strtotime('first day of next month') ).' gecrediteerd worden door het NS.', 0, false );

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

			// Sorteer alfabetisch op naam van de winkel
			return ksort( $repartition );
		}
	?>

	<p>&nbsp;</p>

	<?php
		output_latest_exports();

		function output_latest_exports() {
			$files = get_latest_exports();
			foreach ( $files as $file ) {
				$id = '';
				$title = str_replace( '-', ' ', $file['name'] );
				$extras = '';
				
				if ( $file['name'] === 'latest.xlsx' ) {
					$id = 'latest';
					$title = 'Huidige export';
					$extras = ' <button id="'.$id.'" class="button confirm-export" disabled>Bevestig download</button>';
				}

				// Om downloadlink te leggen naar niet-publieke map hebben we een download manager nodig ...
				echo '<a href="'.content_url( '/'.$file['name'] ).'" download><button id="'.$id.'" class="button download-excel">'.$title.'</button></a>'.$extras;
				echo '<br/><br/>';
			}
		}

		function get_latest_exports( $max = 10 ) {
			$exports = array();

			// $local_path = ABSPATH . '/../';
			$local_path = WP_CONTENT_DIR . '/';
			if ( $handle = opendir( $local_path ) ) {
				// Loop door alle files in de map
				while ( false !== ( $file = readdir( $handle ) ) ) {
					$filepath = $local_path . $file;
					// Beschouw enkel XLSX-bestanden
					if ( ends_with( $file, '.xlsx' ) ) {
						// Zet naam, timestamp, datum en pad van de upload in de array
						$exports[] = array(
							'name' => basename( $filepath ),
							'timestamp' => filemtime( $filepath ),
							'date' => get_date_from_gmt( date( 'Y-m-d H:i', filemtime( $filepath ) ), 'd/m/Y H:i' ),
							'path' => $filepath,
						);
					}
				}
				closedir( $handle );
			}
			
			// Orden chronologisch
			if ( count( $exports ) > 1 ) {
				usort( $exports, 'sort_by_time' );
			}

			// Zet recentste bestanden bovenaan en geef enkel de eerste X weer
			return array_slice( array_reverse( $exports ), 0, $max );
		}

		function ends_with( $haystack, $needle ) {
			return $needle === '' or ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 and strpos( $haystack, $needle, $temp ) !== false );
		}

		add_action( 'admin_footer', 'close_voucher_export' );

		function close_voucher_export() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery(".confirm-export").prop( "disabled", false );
						
					jQuery(".confirm-export").on( "click", function() {
						var button = jQuery(this);
						var go = confirm("Ben je zeker dat je de huidige lijst wil afsluiten?");
						if ( go == true ) {
							button.prop( "disabled", true );
							button.text("Laden ...");
							jQuery("#wpcontent").css( "background-color", "orange" );
							closeCurrentList(button);
						}
					});

					var tries = 0;
					var max = 5;

					function closeCurrentList(button) {
						var path = "<?php echo WP_CONTENT_DIR.'/latest.xlsx'; ?>"; 
						
						jQuery.ajax({
							type: 'POST',
							url: ajaxurl,
							data: {
								'action': 'oxfam_close_voucher_export_action',
								'path': path,
							},
							dataType: 'html',
							success: function(newPath) {
								tries = 0;
								jQuery("#wpcontent").css( "background-color", "limegreen" );
								button.text("Export succesvol afgesloten!");
								jQuery(".output").find( ".download-excel#"+button.attr('id') ).html("Afgesloten export");
								jQuery(".output").find( ".download-excel#"+button.attr('id') ).parent("a").attr( "href", newPath );
							},
							error: function(jqXHR, statusText, errorThrown) {
								tries++;
								var message = "Asynchroon laden van PHP-file mislukt ... (poging ###CURRENT### van ###MAXIMUM###: ###ERROR###)";
								message = message.replace( '###CURRENT###', tries );
								message = message.replace( '###MAXIMUM###', max );
								message = message.replace( '###ERROR###', errorThrown );
								jQuery(".output").prepend("<p>"+message+"</p>");
								if ( tries < max ) {
									closeCurrentList();
								} else {
									tries = 0;
									jQuery("#wpcontent").css( "background-color", "red" );
									button.text("Afsluiten van export mislukt!");
								}
							},
						});
					}
				});
			</script>
			<?php
		}
	?>

	<div class="output"></div>
</div>