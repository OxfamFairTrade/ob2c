<?php
/**
 * Email Order Items
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

$text_align  = is_rtl() ? 'right' : 'left';
$margin_side = is_rtl() ? 'left' : 'right';

foreach ( $items as $item_id => $item ) :
	$product       = $item->get_product();
	$sku           = '';
	$purchase_note = '';
	$image         = '';

	if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
		continue;
	}

	if ( is_object( $product ) ) {
		// Vermeld ShopPlus-referentie i.p.v. ompaknummer
		$sku           = $product->get_meta('_shopplus_code');
		$purchase_note = $product->get_purchase_note();
		$image         = $product->get_image( $image_size );
	}

	?>
	<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
		<td class="td" style="text-align: center; min-width: 48px; padding: 4px 0; border-left-width: 0; border-right-width: 0; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap: break-word;">
			<?php
				if ( $show_image ) {
					echo wp_kses_post( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
				}
			?>
		</td>
		<td class="td" style="text-align: left; padding-left: 4px; border-left-width: 0; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap: break-word;">
			<?php
				echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );

				// GEWIJZIGD: Vermeld artikelnummer achteraan
				if ( $show_sku && $sku ) {
					echo wp_kses_post( ' (' . $sku . ')' );
				}

				do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );

				wc_display_item_meta(
					$item,
					array(
						'label_before' => '<strong class="wc-item-meta-label" style="float: ' . esc_attr( $text_align ) . '; margin-' . esc_attr( $margin_side ) . ': .25em; clear: both">',
					)
				);

				do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );
			?>
		</td>
		<td class="td" style="text-align: center; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
			<?php
				$qty = $item->get_quantity();
				$refunded_qty = $order->get_qty_refunded_for_item( $item_id );

				if ( $refunded_qty ) {
					$qty_display = '<del class="refunded">' . esc_html( $qty ) . '</del><br/>' . esc_html( $qty - ( $refunded_qty * -1 ) );
				} else {
					$qty_display = esc_html( $qty );
				}
				echo wp_kses_post( apply_filters( 'woocommerce_email_order_item_quantity', $qty_display, $item ) );
			?>
		</td>
		<td class="td" style="text-align: right; padding-right: 0; border-right-width: 0; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
			<?php
				// GEWIJZIGD: Refundbedrag tonen (zonder taxlabels)
				$inc_tax = true;
				if ( $refunded_qty ) {
					echo '<del class="refunded">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</del><br/>';
					// Geen BTW-parameter beschikbaar in get_total_refunded_for_item()
					$refunded = $order->get_total_refunded_for_item( $item_id );
					if ( $inc_tax ) {
						$order_taxes = $order->get_taxes();
						// Functie vereist tax-ID dus loop over alle taxschalen in het order
						foreach ( $order_taxes as $tax_item ) {
							$refunded += $order->get_tax_refunded_for_item( $item_id, $tax_item->get_rate_id() );
						}
					}
					echo wc_price( abs( $order->get_line_subtotal( $item, $inc_tax ) - $refunded ) );
				} else {
					echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) );
				}
			?>
		</td>
	</tr>
	<?php if ( $show_purchase_note && $purchase_note ) : ?>
		<tr>
			<td colspan="4" style="text-align: <?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
				<?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?>
			</td>
		</tr>
	<?php endif; ?>

<?php endforeach; ?>
