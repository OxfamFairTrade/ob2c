<?php
	if ( ! defined('ABSPATH') ) exit;

	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
	require_once WP_PLUGIN_DIR.'/phpspreadsheet/autoload.php';
?>

<div class="wrap">
	<h1>Ingeruilde digitale cadeaubonnen</h1>
	
	<p>Hieronder vind je een overzicht van de digitale cadeaubonnen die in de loop van <u>vorige maand ingeruild</u> werden én ondertussen <u>nog niet gecrediteerd</u> zijn.</p>

	<p>Open op het einde van de maand (ten vroegste op de 20ste) deze pagina, en controleer de eventuele waarschuwingen. Als alles in orde is (m.a.w. alle bestellingen zijn inmiddels afgerond en bevatten géén terugbetalingen die groter zijn dan het restbedrag), download je de Excel-file. Per terugbetalingsreferentie verschijnt een werkblad met de juiste aantallen per winkel. Zet deze gegevens over naar de Access-file voor creditering op de eerste dag van de volgende maand. Vergeet de creditering tot slot niet te bevestigen, zodat alles correct geregistreerd wordt in de webshopdatabase en de Excel-file gearchiveerd wordt!</p>

	<p>Er zit dus steeds een veiligheidsmarge van minstens één maand tussen het inruilen en het crediteren van een cadeaubon, zodat de bestelling rustig afgerond kan worden en eventuele problemen reeds afgehandeld zijn. In geval van nood kan Frederik het datumbereik aanpassen en/of handmatige correcties doorvoeren.</p>

	<?php
		$start_date = date_i18n( 'Y-m-d', strtotime('first day of previous month') );
		$end_date = date_i18n( 'Y-m-d', strtotime('last day of previous month') );
		echo '<p><b>Startdatum</b>: '.$start_date.'<br/><b>Einddatum</b>: '.$end_date.'</p>';
		$voucher_ids = array();
		
		// Haal data van voorbije maand op
		$distribution = get_credit_report_used_vouchers( $start_date, $end_date );

		if ( count( $distribution ) > 0 ) {
			// Toon resultaat op scherm
			echo '<pre>';
			print_r( $distribution );
			echo '</pre>';

			// Schrijf resultaat weg naar Excel
			export_to_excel( $distribution );
		} else {
			echo '<p><b><span style="color: red">Er valt deze maand niets (meer) te crediteren!</span></b></p>';
		}

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
			$writer->save( WP_CONTENT_DIR . '/exports/latest.xlsx' );
		}

		function get_credit_report_used_vouchers( $start_date = '2021-05-01', $end_date = '2021-05-31' ) {
			$distribution = array();

			$credit_refs = array(
				'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50 ),
				'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25 ),
				'08917' => array( 'issuer' => 'Cera', 'value' => 30 ),
			);

			foreach ( $credit_refs as $credit_ref => $credit_params ) {
				$rows = get_number_of_times_voucher_was_used( $credit_refs[ $credit_ref ]['issuer'], $credit_refs[ $credit_ref ]['value'], $start_date, $end_date );
				echo '<p><b>Totaalbedrag te crediteren onder referentie '.$credit_ref.': '.wc_price( array_sum( $rows ) * $credit_refs[ $credit_ref ]['value'] ).'</b></p>';

				if ( count( $rows ) > 0 ) {
					$distribution[ $credit_ref ] = $rows;
				}
			}

			return $distribution;
		}

		function get_number_of_times_voucher_was_used( $issuer, $value, $start_date, $end_date ) {
			global $wpdb, $voucher_ids;
			$repartition = array();
			$warnings = array();

			// Vereis een lege 'credited'-datum zodat we verhinderen dat vouchers twee keer in een opgeslagen export kunnen opduiken 
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `issuer` = '%s' AND value = %d AND DATE(`used`) BETWEEN '%s' AND '%s' AND DATE(`credited`) < '2001-01-01'";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, $issuer, $value, $start_date, $end_date ) );

			foreach ( $rows as $key => $row ) {
				switch_to_blog( $row->blog_id );

				$args = array(
					'type' => 'shop_order',
					'order_number' => $row->order,
					'limit' => -1,
				);
				$orders = wc_get_orders( $args );
				
				if ( $row->order !== 'OFFLINE' and count( $orders ) === 0 ) {
					$warnings[ $row->order ] = 'Bestelling '.$row->order.' niet gevonden, voucher '.$row->code.' niet opgenomen in export';
				} elseif ( count( $orders ) > 1 ) {
					$warnings[ $row->order ] = 'Meerdere bestellingen gevonden voor '.$row->order.', voucher '.$row->code.' niet opgenomen in export';
				} else {
					if ( $row->order === 'OFFLINE' ) {
						$voucher_ids[] = $row->id;
					} else {
						$order = reset( $orders );
						if ( $order->get_status() !== 'completed' ) {
							$warnings[ $row->order ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> is nog niet afgerond, voucher '.$row->code.' niet opgenomen in export';
							restore_current_blog();
							continue;
						}

						$voucher_ids[] = $row->id;
						$refunds = $order->get_refunds();
						if ( count( $refunds ) > 0 ) {
							$refund_amount = 0.0;
							foreach ( $refunds as $refund ) {
								$refund_amount += $refund->get_amount();
							}
							$warning = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> bevat een terugbetaling t.w.v. '.wc_price( $refund_amount );
							if ( $refund_amount > ( $order->get_total() - ob2c_get_total_voucher_amount( $order ) ) ) {
								$warning .= ' die groter is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: red">dit mag in principe niet</span>';
							} else {
								$warning .= ' die kleiner is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: green">geen probleem</span>';
							}
							$warnings[ $row->order ] = $warning;
						}
					}

					if ( is_regional_webshop() ) {
						$blog_path = $order->get_meta('claimed_by');
					} else {
						$current_blog = get_blog_details();
						$blog_path = str_replace( '/', '', $current_blog->path );
					}

					if ( strlen( $blog_path ) < 1 ) {
						$warnings[ $row->order ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> kan niet toegekend worden aan een winkel, <span style="color: red">dit is problematisch</span>';
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
			ksort( $repartition );

			if ( count( $warnings ) > 0 ) {
				echo '<p>Waarschuwingen:</p><ol><li>'.implode( '</li><li>', $warnings ).'</li></ol>';
			}

			return $repartition;
		}
	?>

	<?php
		output_latest_exports( $start_date, $end_date );

		function output_latest_exports( $start_date, $end_date ) {
			global $voucher_ids;
			$files = get_latest_exports('xlsx');

			foreach ( $files as $file ) {
				$id = '';
				$title = ucwords( str_replace( '-', ' ', $file['name'] ) );
				$extras = '';
				
				if ( $file['name'] === 'latest.xlsx' ) {
					$id = 'latest';
					$title = 'Download openstaande export';
					// Afsluiten ten vroegste toestaan vanaf de 20ste van de maand
					if ( intval( date_i18n('j') ) >= 20 ) {
						$extras = ' <button id="'.$id.'" data-voucher-ids="'.implode( ',', $voucher_ids ).'" data-start-date="'.str_replace( '-', '', $start_date ).'" data-end-date="'.str_replace( '-', '', $end_date ).'" class="button confirm-export" disabled>Bevestig creditering</button>';
					}
				}

				// Om downloadlink te leggen naar niet-publieke map hebben we een download manager nodig ...
				echo '<br/><a href="'.content_url( '/exports/'.$file['name'] ).'" download><button id="'.$id.'" class="button download-excel">'.$title.'</button></a>'.$extras.'<br/>';
			}
		}

		function get_latest_exports( $extension = false, $max = false ) {
			$exports = array();
			$local_path = WP_CONTENT_DIR.'/exports/';

			if ( $handle = opendir( $local_path ) ) {
				// Loop door alle files in de map
				while ( false !== ( $file = readdir( $handle ) ) ) {
					$filepath = $local_path . $file;
					// Check of we enkel de opgegeven extensie moeten beschouwen
					if ( ! $extension or ends_with( $file, '.'.$extension ) ) {
						// Zet naam, timestamp, datum en pad van de upload in de array
						$exports[] = array(
							'name' => basename( $filepath ),
							'timestamp' => filemtime( $filepath ),
							'date' => get_date_from_gmt( date( 'Y-m-d H:i:s', filemtime( $filepath ) ), 'd/m/Y H:i:s' ),
							'path' => $filepath,
						);
					}
				}
				closedir( $handle );
			}
			
			// Orden in dalende chronologisch volgorde
			usort( $exports, 'sort_by_time' );

			if ( $max ) {
				// Geef enkel de eerste X weer
				return array_slice( $exports, 0, $max );
			} else {
				return $exports;
			}
		}

		function ends_with( $haystack, $needle ) {
			return $needle === '' or ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 and strpos( $haystack, $needle, $temp ) !== false );
		}

		function sort_by_time( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		}

		add_action( 'admin_footer', 'close_voucher_export' );

		function close_voucher_export() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery(".confirm-export").prop( "disabled", false );
						
					jQuery(".confirm-export").on( "click", function() {
						var button = jQuery(this);
						var go = confirm("Ben je zeker dat deze lijst wil afsluiten? Alle vouchers uit de Excel worden als terugbetaald gemarkeerd in de database en zullen niet meer opduiken in volgende exports! Bij de bestellingen in kwestie wordt een nota toegevoegd dat de cadeaubon op <?php echo date_i18n( 'd/m/Y', strtotime( '+1 weekday', strtotime('last day of this month') ) ); ?> gecrediteerd zal worden.");
						if ( go == true ) {
							button.prop( "disabled", true );
							button.text("Laden ...");
							jQuery("#wpcontent").css( "background-color", "gold" );
							closeCurrentList(button);
						}
					});

					var tries = 0;
					var max = 5;

					function closeCurrentList(button) {
						jQuery.ajax({
							type: 'POST',
							url: ajaxurl,
							data: {
								'action': 'oxfam_close_voucher_export_action',
								'path': '<?php echo WP_CONTENT_DIR . '/exports/latest.xlsx'; ?>',
								'start_date': button.data('start-date'),
								'end_date': button.data('end-date'),
								'voucher_ids': button.data('voucher-ids'),
							},
							dataType: 'html',
							success: function(newPath) {
								tries = 0;
								jQuery("#wpcontent").css( "background-color", "lightgreen" );
								button.text("Creditering succesvol afgesloten!");
								jQuery( ".download-excel#"+button.attr('id') ).html("Afgesloten creditering");
								jQuery( ".download-excel#"+button.attr('id') ).parent("a").attr( "href", newPath );
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
									button.text("Afsluiten van creditering mislukt!");
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

	<p>&nbsp;</p>

	<h2>Alle bestellingen met cadeaubonnen van Gezinsbond</h2>
	<?php get_total_revenue_by_voucher_issuer('Gezinsbond'); ?>

	<p>&nbsp;</p>

	<h2>Alle bestellingen met cadeaubonnen van Cera</h2>
	<?php get_total_revenue_by_voucher_issuer('Cera'); ?>
	
	<?php
		function get_total_revenue_by_voucher_issuer( $issuer = 'Cera' ) {
			global $wpdb;

			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `order` <> '' AND `issuer` = '%s' ORDER BY `order` ASC";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, $issuer ) );
			$checked_orders = array();
			$total = 0.0;
			$total_oft = 0.0;
			$total_incl_tax = 0.0;
			$args = array(
				'type' => 'shop_order',
				'limit' => -1,
			);

			foreach ( $rows as $key => $row ) {
				// Elk order slechts één keer checken, ook al werden er meerdere bonnen tegelijk ingeruild
				if ( in_array( $row->order, $checked_orders ) ) {
					continue;
				} else {
					$checked_orders[] = $row->order;
				}

				switch_to_blog( $row->blog_id );
				
				$args['order_number'] = $row->order;
				$orders = wc_get_orders( $args );

				if ( count( $orders ) === 1 ) {
					$wc_order = reset( $orders );

					// Bereken het percentage OFT-omzet (excl. BTW en kortingen)
					$order_total_oft = 0.0;
					$order_total_non_oft = 0.0;
					$order_total_unknown = 0.0;
					foreach ( $wc_order->get_items() as $item ) {
						$product = $item->get_product();
						// Neem 'line_total' i.p.v. 'line_subtotal', zodat kortingen reeds verrekend zijn
						$line_total = $item['line_total'] + $item['line_total_tax'];
						if ( $product === false ) {
							$order_total_unknown += $line_total;
						} else {
							if ( in_array( $product->get_attribute('merk'), array( 'Oxfam Fair Trade', 'Maya' ) ) ) {
								$order_total_oft += $line_total;
							} else {
								$order_total_non_oft += $line_total;
							}
						}
					}
					$order_total = $order_total_oft + $order_total_non_oft + $order_total_unknown;
					
					$total += $order_total;
					$total_oft += $order_total_oft;
					$total_incl_tax += $wc_order->get_total();

					echo '<a href="'.$wc_order->get_edit_order_url().'" target="_blank">'.$wc_order->get_order_number().'</a>: '.wc_price( $order_total, array( 'ex_tax_label' => true ) ).' waarvan '.round( 100 * $order_total_oft / $order_total ).'% OFT &mdash; '.$wc_order->get_billing_email();
					
					$new_args = array(
						'type' => 'shop_order',
						'billing_email' => $wc_order->get_billing_email(),
						'date_created' => '<'.$wc_order->get_date_created()->date_i18n('Y-m-d'),
						'status' => 'wc-completed',
						'limit' => -1,
					);
					$previous_orders_by_customer = wc_get_orders( $new_args );
					
					if ( count( $previous_orders_by_customer ) === 0 ) {
						$new_args['date_created'] = '>'.$wc_order->get_date_created()->date_i18n('Y-m-d');
						$new_orders_by_customer = wc_get_orders( $new_args );
						
						if ( count( $new_orders_by_customer ) > 0 ) {
							$addendum = ', placed '.count( $new_orders_by_customer ).' new orders afterwards';
						} else {
							$addendum = '';
						}
						echo ' <span style="font-weight: bold; color: green;">=> new online customer'.$addendum.'!</span>';
					}

					echo '<br/>';
				}

				restore_current_blog();
			}

			echo '<p>Totaalbedrag: '.wc_price( $total_incl_tax ).', goed voor '.wc_price( $total, array( 'ex_tax_label' => true ) ).' producten waarvan '.wc_price( $total_oft, array( 'ex_tax_label' => true ) ).' Oxfam Fair Trade</p>';
		}
	?>
</div>