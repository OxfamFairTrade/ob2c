<?php
/**
 * Email Addresses
 *
 * @see 		https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version 	3.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';

?>
<!-- GEWIJZIGD: Extra enter zodat adres niet tegen openingsuren plakt -->
<br/>
<table id="addresses" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding: 0;" border="0">
	<tr>
		<td style="text-align:<?php echo $text_align; ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="50%">
			<h2><?php _e( 'Billing address', 'woocommerce' ); ?></h2>

			<address class="address">
				<?php echo ( $address = $order->get_formatted_billing_address() ) ? $address : __( 'N/A', 'woocommerce' ); ?>
			</address>
		</td>
		<?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && ( $shipping = $order->get_formatted_shipping_address() ) ) : ?>
			<td style="text-align:<?php echo $text_align; ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; padding:0;" valign="top" width="50%">
				<h2><?php _e( 'Shipping address', 'woocommerce' ); ?></h2>

				<address class="address"><?php echo $shipping; ?></address>
			</td>
		<?php endif; ?>
	</tr>
</table>