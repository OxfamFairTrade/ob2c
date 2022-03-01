<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Ingeruilde vouchers</h1>

	<p>Hier tonen we alle vouchers van de voorbije maanden, en hun crediteringsstatus.</p>

	<div id="oxfam-products" style="border-spacing: 0 10px;">
		<?php
			global $wpdb;
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `blog_id` = '%s' AND DATE(`used`) BETWEEN '%s' AND '%s' AND DATE(`credited`) > '%s'";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, get_current_blog_id(), '2021-01-01', '2022-12-31', '2022-01-01' ) );
			
			foreach ( $rows as $key => $row ) {
				$args = array(
					'type' => 'shop_order',
					'order_number' => $row->order,
					'limit' => -1,
				);
				$orders = wc_get_orders( $args );
			
				if ( $row->order !== 'OFFLINE' and count( $orders ) === 1 ) {
					$current_blog = get_blog_details();
					if ( $row->order === 'OFFLINE' ) {
						$blog_path = str_replace( '/', '', $current_blog->path );
					} else {
						$order = reset( $orders );
						
						var_dump_pre( $row );
						
						if ( $order->get_status() !== 'completed' ) {
							echo 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> is nog niet afgerond, voucher '.$row->code.' niet opgenomen in export';
							continue;
						}
			
						if ( is_regional_webshop() ) {
							$blog_path = $order->get_meta('claimed_by');
						} else {
							$blog_path = str_replace( '/', '', $current_blog->path );
						}
			
						if ( strlen( $blog_path ) < 1 ) {
							echo 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> is niet toegekend aan een winkel, voucher '.$row->code.' niet opgenomen in export';
							continue;
						}
			
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
							echo $warning;
						}
					}
				}
			}
		?>
	</div>
</div>