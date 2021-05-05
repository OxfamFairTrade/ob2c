<?php
/**
 * Admin new order email
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails\HTML
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>Je hebt een bestelling ontvangen van %s.</p>', $order->get_formatted_billing_full_name() );

if ( $order->has_shipping_method('local_pickup_plus') ) {
	$shipping_methods = $order->get_shipping_methods();
	$shipping_method = reset( $shipping_methods );
	$pickup_location_name = ob2c_get_pickup_location_name( $shipping_method, false );
	echo '<p><b>Dit is een afhaling voor '.$pickup_location_name.'.</b> Vergeet de bestelling in de webshop niet als \'Afgerond\' te markeren van zodra het pakje samengesteld is. Pas dan ontvangt de klant een tweede mail waarin hij/zij op de hoogte gebracht wordt dat de bestelling klaarstaat voor afhaling in de winkel.</p>';
} elseif ( floatval( $order->get_shipping_total() ) > 0.00 ) {
	$shipping_cost_details = ob2c_get_shipping_cost_details( $order );

	if ( $shipping_cost_details['tax_rate'] == 0.21 ) {
		if ( $shipping_cost_details['qty'] > 1 ) {
			echo '<p style="color: red;">Dit is een bestelling met enkel producten aan het tarief van 21% BTW met levering naar het buitenland! Zorg ervoor dat je bij de verwerking in ShopPlus ' . $shipping_cost_details['qty'] . 'x de levercode \'WEB21\' inscant. '.sprintf( 'Als winkel hou je aan deze thuislevering netto %1$s i.p.v. %2$s over.', wc_price( $shipping_cost_details['total_excl_tax'] ), wc_price( $shipping_cost_details['qty'] * REDUCED_VAT_SHIPPING_COST ) ).'</p>';
		} else {
			echo '<p style="color: red;">Dit is een bestelling met enkel producten aan het tarief van 21% BTW! Zorg ervoor dat je bij de verwerking in ShopPlus de levercode \'WEB21\' inscant. '.sprintf( 'Als winkel hou je aan deze thuislevering netto %1$s i.p.v. %2$s over.', wc_price( $shipping_cost_details['total_excl_tax'] ), wc_price( REDUCED_VAT_SHIPPING_COST ) ).'</p>';
		}
	} else {
		if ( $shipping_cost_details['qty'] > 1 ) {
			echo '<p style="color: red;">Dit is een bestelling met levering naar het buitenland! Zorg ervoor dat je bij de verwerking in ShopPlus ' . $shipping_cost_details['qty'] . 'x de levercode \'WEB6\' inscant.</p>';
		}
	}
}

echo '<p>In bijlage vind je een Excel met alle gegevens in printvriendelijk formaat. Bezorg dit eventueel aan een winkelier zodat hij/zij de bestelling kan klaarzetten. In de laatste kolom is ruimte voorzien om de effectief geleverde aantallen te noteren.</p>';

$voucher_codes = array();
$voucher_amount = 0.0;
foreach ( $order->get_coupons() as $coupon_item ) {
	// Negeer in deze stap de rate limiting per IP-adres
	$db_coupon = ob2c_is_valid_voucher_code( $coupon_item->get_code(), true );
	if ( is_object( $db_coupon ) ) {
		$voucher_codes[] = strtoupper( $coupon_item->get_code() );
		$voucher_amount += $coupon_item->get_discount() + $coupon_item->get_discount_tax();
	}
}

// Fallback indien de mail verzonden wordt nà het omvormen van de coupon in een fee
foreach ( $order->get_fees() as $fee_item ) {
	if ( $fee_item->get_meta('voucher_amount') !== '' ) {
		$voucher_codes[] = $fee_item->get_meta('voucher_code');
		$voucher_amount += floatval( $fee_item->get_meta('voucher_amount') );
	}
}

if ( count( $voucher_codes ) > 0 ) {
	$percentage = ( $order->get_total() > 0 ) ? 'gedeeltelijk' : 'volledig';
	echo '<p><b>Deze bestelling werd '.$percentage.' betaald met '.sprintf( _n( '%d digitale cadeaubon', '%d digitale cadeaubonnen', count( $voucher_codes ) ), count( $voucher_codes ) ).' t.w.v. '.wc_price( $voucher_amount ).'.</b> Dit bedrag zal volgende maand automatisch gecrediteerd worden aan de winkel die de bestelling behandelt. Contacteer de <a href="mailto:webshop@oft.be?subject=Terugbetaling digitale cadeaubon in '.$order->get_order_number().'">Helpdesk E-Commerce</a> indien je door onvoorziene omstandigheden een (grote) terugbetaling dient uit te voeren op dit order.</p>';
}

if ( $order->get_meta('is_b2b_sale') === 'yes' ) {
	echo '<p style="color: red;">Opgelet, dit is een B2B-bestelling die nog niet betaald werd! Je zult geen bedrag ontvangen via Mollie. Stel een factuur op voor de effectief geleverde goederen en volg zelf de betaling op.</p>';
}

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
