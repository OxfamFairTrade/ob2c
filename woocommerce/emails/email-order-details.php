<?php
/**
 * Order details table shown in emails.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<br/>
<h2>
	<?php
	if ( $sent_to_admin ) {
		$before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
		$after  = '</a>';
	} else {
		$before = '';
		$after  = '';
	}
	// GEWIJZIGD: Geen hashtag voor bestelnummer
	echo wp_kses_post( $before . sprintf( __( 'Order', 'woocommerce' ).' %s' . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	?>
</h2>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="3" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align: center;"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align: center; border-right-width: 0;"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align: center; border-left-width: 0;"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			// GEWIJZIGD: Fotootjes tonen aan klanten (op iets groter formaat)
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => ! $sent_to_admin,
					'image_size'    => array( 48, 48 ),
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				)
			);
			?>
		</tbody>
		<tfoot>
			<?php
			$item_totals = $order->get_order_item_totals();

			if ( $item_totals ) {
				$i = 0;
				foreach ( $item_totals as $total ) {
					$i++;
					?>
					<tr>
						<th class="td" scope="row" style="text-align: right; <?php echo ( count($totals) === $i ) ? 'border-bottom-width: 0;' : ''; ?> <?php echo ( 1 === $i ) ? 'border-top: 2px solid black;' : ''; ?>"><?php echo wp_kses_post( $total['label'] ); ?></th>
						<td class="td" colspan="2" style="text-align: right; <?php echo ( count($totals) === $i ) ? 'border-bottom-width: 0;' : ''; ?> <?php echo ( 1 === $i ) ? 'border-top: 2px solid black;' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
				}
			}

			// Te verhuizen naar klantgegevens?
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" style="text-align: right;"><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
					<td class="td" colspan="2" style="text-align: right;"><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>
