
<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Ingeruilde digicheques</h1>

	<p>Hieronder vind je een overzicht van alle vouchers (uitgegeven door Gezinsbond, Cera, CM, ...) die de voorbije 4 maanden ingeruild werden in deze webshop.<br/>
	De weergave is gegroepeerd per kredietnota, en vervolgens per bestelling. (Maar misschien is het logischer om te groeperen per crediteringsreferentie?)</p>

	<div id="oxfam-vouchers">
		<?php
			global $wpdb;
			$start_date = date( 'Y-m-d', strtotime('first day of this month -4 months') );
			$end_date = date( 'Y-m-d', strtotime('last day of this month') );
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `blog_id` = %d AND DATE(`used`) BETWEEN '%s' AND '%s' ORDER BY `used` ASC";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, get_current_blog_id(), $start_date, $end_date ) );
			
			$credit_dates = array_unique( array_column( $rows, 'credited' ) );
			rsort( $credit_dates );
			if ( in_array( '0000-00-00', $credit_dates ) ) {
				array_unshift( $credit_dates, $last = array_pop( $credit_dates ) );
			}
			
			$credit_refs = array(
				'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50 ),
				'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25 ),
				'08917' => array( 'issuer' => 'Cera', 'value' => 30 ),
				'08924' => array( 'issuer' => 'CM', 'value' => 10 ),
			);
			
			foreach ( $credit_dates as $credit_date ) {
				$credit_date_formatted = date_i18n( 'j F Y', strtotime( $credit_date ) );
				
				if ( $credit_date === '0000-00-00' ) {
					echo '<h2>Nog te verwerken crediteringen</h2>';
					echo '<p>Deze codes zijn nog niet verwerkt op het Nationaal Secretariaat. Als de bestelling al afgerond is, hoef je zelf niets meer te doen.<br/>Hou er rekening mee dat we een minimum wachtperiode van 1 maand hanteren vòòr we overgaan tot creditering, om ruimte te laten voor retours en correcties.</p>';
				} else {
					echo '<h2>Kredietnota van '.$credit_date_formatted.'</h2>';
				}
				
				// Externe variabelen zijn standaard niet beschikbaar binnen callback, gebruik 'use'!
				$all_codes = array_filter( $rows, function($row) use ($credit_date) {
					return ( $row->credited == $credit_date );
				});
				
				$all_order_numbers = array_unique( array_column( $all_codes, 'order' ) );
				foreach ( $all_order_numbers as $order_number ) {
					$all_codes_for_order_number = array_filter( $all_codes, function($row) use ($order_number) {
						return ( $row->order == $order_number );
					});
					
					$args = array(
						'type' => 'shop_order',
						'order_number' => $order_number,
						'limit' => -1,
					);
					$orders = wc_get_orders( $args );
					
					echo '<div id="'.$order_number.'" class="row">';
					
					if ( $order_number === 'OFFLINE' or count( $orders ) === 1 ) {
						$warnings = array();
						$messages = array();
						$shop_to_credit = false;
						
						if ( $order_number !== 'OFFLINE' ) {
							$order = reset( $orders );
							
							if ( $order->get_status() !== 'completed' ) {
								$warnings[] = 'is niet afgerond, kan nog niet gecrediteerd worden';
							} else {
								if ( is_regional_webshop() ) {
									$shop_to_credit = $order->get_meta('claimed_by');
									
									if ( strlen( $shop_to_credit ) < 1 ) {
										$warnings[] = 'is niet geclaimd door winkel, kan nog niet gecrediteerd worden';
									}
								}
							}
						} else {
							$order = false;
						}
						
						echo '<div class="column first-column">';
						if ( $order ) {
							echo '<a href="'.$order->get_edit_order_url().'" target="_blank">'.$order_number.'</a> ('.$order->get_date_created()->date_i18n('d/m/Y').')';
							
							if ( $order ) {
								$refunds = $order->get_refunds();
								if ( count( $refunds ) > 0 ) {
									$refund_amount = 0.0;
									foreach ( $refunds as $refund ) {
										$refund_amount += $refund->get_amount();
									}
									if ( $refund_amount > ( $order->get_total() - ob2c_get_total_voucher_amount( $order ) ) ) {
										$messages[] = 'bevat een terugbetaling t.w.v. '.wc_price( $refund_amount ).' die groter is dan het restbedrag dat niet met vouchers betaald werd!';
									}
								}
							}
						} else {
							echo $order_number;
						}
						echo '</div>';
						
						echo '<div class="column second-column">';
						echo '<ul>';
						
						foreach ( $all_codes_for_order_number as $row ) {
							echo '<li>'.$row->code.' ('.$row->issuer.') t.w.v. '.wc_price( $row->value ).': ';
							
							if ( count( $warnings ) === 0 ) {
								if ( $row->credited > date('Y-m-d') ) {
									echo 'ingepland voor creditering';
								} else {
									if ( $row->credited === '0000-00-00' ) {
										echo 'creditering nog in te plannen';
									} else {
										echo 'gecrediteerd';
									}
								}
								
								$filtered_refs = array_filter( $credit_refs, function($ref) use ($row) {
									// Eventueel $row->expires == $ref['expires'] toevoegen als er bonnen met zelfde waarde maar andere vervaldatum bij komen
									return ( $row->value == $ref['value'] );
								});
								echo ' (art.nr. '.key( $filtered_refs ).')';
								
								if ( $shop_to_credit ) {
									echo ' aan OWW '.ucfirst( $shop_to_credit );	
								}
							}
							
							echo '</li>';
						}
						
						if ( count( $warnings ) > 0 ) {
							echo '<li class="warning">'.implode( ', ', $warnings ).'</li>';
						}
						
						if ( count( $messages ) > 0 ) {
							echo '<li class="message">'.implode( ', ', $messages ).'</li>';
						}
						
						echo '</ul>';
						echo '</div>';
					} else {
						echo '<p>Geen / te veel orders gevonden!</p>';
					}
					
					echo '</div>';
				}
			}
		?>
	</div>
</div>