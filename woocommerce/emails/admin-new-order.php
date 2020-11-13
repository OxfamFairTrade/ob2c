<?php
/**
 * Admin new order email
 *
 * @see			https://docs.woocommerce.com/document/template-structure/
 * @author		WooThemes
 * @package		WooCommerce/Templates/Emails/HTML
 * @version		2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>Je hebt een bestelling ontvangen van %s.</p>', $order->get_formatted_billing_full_name() );

echo '<p>In bijlage vind je een Excel met alle gegevens in printvriendelijk formaat. Bezorg dit eventueel aan een winkelier zodat hij/zij de bestelling kan klaarzetten. In de laatste kolom is ruimte voorzien om de effectief geleverde aantallen te noteren.</p>';

if ( $order->has_shipping_method('local_pickup_plus') ) {
	echo '<p>Vergeet de bestelling in de webshop niet als \'Afgerond\' te markeren van zodra het pakje samengesteld is. Pas dan ontvangt de klant een tweede mail waarin hij/zij op de hoogte gebracht wordt dat de bestelling klaarstaat voor afhaling in de winkel.</p>';
}

if ( $order->get_meta('is_b2b_sale') === 'yes' ) {
	echo '<p style="color: red; font-weight: bold;">Opgelet, dit is een B2B-bestelling die nog niet betaald werd! Je zult geen bedrag ontvangen via Mollie. Stel een factuur op voor de effectief geleverde goederen en volg zelf de betaling op.</p>';
}

$tax_classes = $order->get_items_tax_classes();
if ( in_array( 'voeding', $tax_classes ) === false and $order->get_shipping_total() > 0 ) {
	echo sprintf( '<p style="color: red; font-weight: bold;">Opgelet, dit is een bestelling met enkel producten aan het tarief van 21% BTW! Zorg ervoor dat je bij de verwerking in ShopPlus de levercode \'WEB21\' inscant. Als winkel hou je aan deze thuislevering netto %1$s i.p.v. %2$s over.</p>', wc_price( STANDARD_VAT_SHIPPING_COST ), wc_price( REDUCED_VAT_SHIPPING_COST ) );
}

/**
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );