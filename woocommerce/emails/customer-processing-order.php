<?php
/**
 * Customer processing order email
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

$logger = wc_get_logger();
$context = array( 'source' => 'Oxfam Emails' );

// Is altijd ingevuld, dus geen check doen
echo '<p>Dag '.$order->get_billing_first_name().'</p>';

if ( $order->has_shipping_method('local_pickup_plus') ) {
	$methods = $order->get_shipping_methods();
	$method = reset( $methods );
	$pickup_location = $method->get_meta('pickup_location');
	
	if ( $order->get_meta('is_b2b_sale') === 'no' ) {
		// Haal geschatte leverdatum op VIA GET_POST_META() WANT $ORDER->GET_META() OP DIT MOMENT NOG NIET BEPAALD
		$delivery = get_post_meta( $order->get_id(), 'estimated_delivery', true );
		$logger->debug( $order->get_order_number().': estimated delivery returns '.$order->get_meta('estimated_delivery').' via get_meta()', $context );
		
		if ( $delivery === '' ) {
			// Aangepast bericht zonder tijdsinschatting indien waarde ontbreekt (bv. door ontbreken van openingsuren tijdens lockdown)
			echo '<p>' . sprintf( 'We hebben je bestelling goed ontvangen. Onze vrijwilligers zetten je boodschappen klaar in %1$s. We sturen je een tweede bericht van zodra alles klaarstaat.', $pickup_location['shipping_company'] ) . '</p>';
			$logger->info( $order->get_order_number().': pickup mail sent without time indication', $context );
		} else {
			echo '<p>' . sprintf( __( 'Bericht bovenaan de 1ste bevestigingsmail (indien afhaling), inclusief afhaallocatie (%1$s), -dag (%2$s) en -uur (%3$s).', 'oxfam-webshop' ), $pickup_location['shipping_company'], date_i18n( 'l d/m', $delivery ), date_i18n( 'G\ui', $delivery ) ) . '</p>';
		}
	} else {
		echo '<p>' . sprintf( __( 'Bericht bovenaan de 1ste bevestigingsmail van een B2B-bestelling (indien afhaling), inclusief afhaallocatie (%s).', 'oxfam-webshop' ), $pickup_location['shipping_company'] ) . '</p>';
	}
} else {
	echo '<p>' . __( 'Bericht bovenaan de 1ste bevestigingsmail (indien thuislevering).', 'oxfam-webshop' ) . '</p>';
}

// Eventueel array( 'id' => ... ) doorgeven als argument voor de juiste $pickup_location?
echo '<p>Heb je nog een vraag? Beantwoord gewoon deze mail of bel ons op '.print_telephone().' en vermeld je bestelnummer. Op die manier kunnen we je snel verder helpen.</p>';

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