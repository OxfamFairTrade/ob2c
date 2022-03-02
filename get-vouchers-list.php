
<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Ingeruilde digicheques</h1>

	<p>Hieronder vind je een overzicht van alle vouchers voor Gezinsbond, Cera, CM, ... die de voorbije 3 maanden ingeruild werden in deze webshop, inclusief crediteringsstatus.</p>

	<div id="oxfam-products" style="border-spacing: 0 10px;">
		<?php
			$credit_refs = array(
				'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50 ),
				'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25 ),
				'08917' => array( 'issuer' => 'Cera', 'value' => 30 ),
				'08924' => array( 'issuer' => 'CM', 'value' => 10 ),
			);
			
			global $wpdb;
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `blog_id` = %d AND DATE(`used`) BETWEEN '%s' AND '%s' AND DATE(`credited`) > '%s' ORDER BY `used` ASC";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, get_current_blog_id(), '2021-01-01', '2022-12-31', '2022-01-01' ) );
			
			$credit_dates = array_column( $rows, 'credited' );
			var_dump_pre( $credit_dates );
			
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
						echo $row->order.' - '.$row->used.' - '.$row->code.' - '.wc_price( $row->value ).'<br/>';
					} else {
						$order = reset( $orders );
						echo '<a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> - '.date( 'd/m/Y H:i', strtotime( $row->used ) ).' - '.$row->code.': '.wc_price( $row->value ).' gecrediteerd op '.date( 'd/m/Y', strtotime( $row->credited ) ).'<br/>';
						
						if ( $order->get_status() !== 'completed' ) {
							echo 'is nog niet afgerond, zal niet gecrediteerd worden<br/>';
							continue;
						}
			
						if ( is_regional_webshop() ) {
							$blog_path = $order->get_meta('claimed_by');
						} else {
							$blog_path = str_replace( '/', '', $current_blog->path );
						}
			
						if ( strlen( $blog_path ) < 1 ) {
							echo 'is niet toegekend aan een winkel, kan niet gecrediteerd worden<br/>';
							continue;
						}
			
						$refunds = $order->get_refunds();
						if ( count( $refunds ) > 0 ) {
							$refund_amount = 0.0;
							foreach ( $refunds as $refund ) {
								$refund_amount += $refund->get_amount();
							}
							$warning = 'bevat een terugbetaling t.w.v. '.wc_price( $refund_amount );
							if ( $refund_amount > ( $order->get_total() - ob2c_get_total_voucher_amount( $order ) ) ) {
								$warning .= ' die groter is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: red">dit mag in principe niet</span>';
							} else {
								$warning .= ' die kleiner is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: green">geen probleem</span>';
							}
							echo $warning.'<br/>';
						}
					}
				}
			}
		?>
	</div>
</div>