<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Analyse bestellingen met digitale cadeaubonnen</h1>

	<p>Het openen van deze pagina genereert een CSV op de server (net boven de 'public_html'-map) voor verdere verwerking in Excel. Ter info worden de gegevens hieronder ook tekstueel weergegeven. Door de vele queries (naar het merk van het product + eerdere/latere bestellingen van dezelfde klant) is deze pagina erg traag. Eventueel moet je de analyse uitvoeren per uitgever, of in batches laten verwerken m.b.v. Action Scheduler.</p>

	<p>&nbsp;</p>

	<h2>Gezinsbond</h2>
	<?php get_total_revenue_by_voucher_issuer('Gezinsbond'); ?>

	<p>&nbsp;</p>

	<h2>Cera</h2>
	<?php get_total_revenue_by_voucher_issuer('Cera'); ?>
	
	<p>&nbsp;</p>
	
	<h2>CM</h2>
	<?php get_total_revenue_by_voucher_issuer('CM'); ?>

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
			$writer_handle = fopen( ABSPATH.'../vouchers-'.sanitize_title( $issuer ).'.csv', 'w' );
			fputcsv( $writer_handle, array( 'Order number', 'Order total (excl. tax)', 'Order total OFT (excl. tax)', 'Previous orders by customer', 'New orders by customer' ), ';' );

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

					// Bereken het percentage OFT-omzet (excl. BTW, incl. kortingen)
					$order_total_oft = 0.0;
					$order_total_non_oft = 0.0;
					$order_total_unknown = 0.0;
					foreach ( $wc_order->get_items() as $item ) {
						$product = $item->get_product();
						// Neem 'total' i.p.v. 'subtotal', zodat kortingen reeds verrekend zijn
						$line_total = $item->get_total();
						
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
						'date_created' => '<'.$wc_order->get_date_created()->getTimestamp(),
						'status' => 'wc-completed',
						'limit' => -1,
					);
					$previous_orders_by_customer = wc_get_orders( $new_args );
					
					$new_args['date_created'] = '>'.$wc_order->get_date_created()->getTimestamp();
					$new_orders_by_customer = wc_get_orders( $new_args );

					if ( count( $previous_orders_by_customer ) === 0 ) {
						if ( count( $new_orders_by_customer ) > 0 ) {
							$addendum = ', placed '.count( $new_orders_by_customer ).' new orders afterwards';
						} else {
							$addendum = '';
						}
						echo ' <span style="font-weight: bold; color: green;">=> new online customer'.$addendum.'!</span>';
					}

					echo '<br/>';
					
					// Gegevens beter groeperen per klant i.p.v. per bestelling?
					fputcsv( $writer_handle, array( $wc_order->get_order_number(), $order_total, $order_total_oft, count( $previous_orders_by_customer ), count( $new_orders_by_customer ) ), ';' );
				}

				restore_current_blog();
			}

			echo '<p>Totaalbedrag: '.wc_price( $total_incl_tax ).', goed voor '.wc_price( $total, array( 'ex_tax_label' => true ) ).' producten waarvan '.wc_price( $total_oft, array( 'ex_tax_label' => true ) ).' Oxfam Fair Trade</p>';
			
			fclose( $writer_handle );
		}
	?>
</div>