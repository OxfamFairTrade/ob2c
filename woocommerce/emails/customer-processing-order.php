<?php
/**
 * Customer processing order email
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

$logger = wc_get_logger();
$context = array( 'source' => 'Oxfam Emails' );

// Is altijd ingevuld, dus geen check doen
echo '<p>Dag '.$order->get_billing_first_name().'</p>';

if ( order_contains_breakfast( $order ) ) {
	
	// Haal geschatte leverdatum op VIA GET_POST_META() WANT $ORDER->GET_META() OP DIT MOMENT NOG NIET BEPAALD
	$delivery = get_post_meta( $order->get_id(), 'estimated_delivery', true );
	echo '<p>'.sprintf( 'Je bestelde een ontbijtpakket ter ere van 40 jaar Oxfam-Wereldwinkel Merksem. We komen het pakket bij je thuis afleveren op %1$s vanaf %2$s.', date_i18n( 'l d/m/Y', $delivery ), date_i18n( 'G\ui', $delivery ) ).'</p>';

} elseif ( $order->has_shipping_method('local_pickup_plus') ) {

	$shipping_methods = $order->get_shipping_methods();
	$shipping_method = reset( $shipping_methods );
	$pickup_location_name = ob2c_get_pickup_location_name( $shipping_method, false );
	
	if ( $order->get_meta('is_b2b_sale') === 'no' ) {
		// Haal geschatte leverdatum op VIA GET_POST_META() WANT $ORDER->GET_META() OP DIT MOMENT NOG NIET BEPAALD
		$delivery = get_post_meta( $order->get_id(), 'estimated_delivery', true );
		$logger->debug( $order->get_order_number().': estimated delivery returns '.$order->get_meta('estimated_delivery').' via get_meta()', $context );
		
		if ( $delivery === '' ) {
			// Aangepast bericht zonder tijdsinschatting indien waarde ontbreekt (bv. door ontbreken van openingsuren tijdens lockdown)
			echo '<p>' . sprintf( __( 'We hebben je bestelling goed ontvangen. Onze vrijwilligers zetten je boodschappen klaar in %1$s. We sturen je een tweede bericht van zodra alles klaarstaat.', 'oxfam-webshop' ), $pickup_location_name ) . '</p>';
			$logger->info( $order->get_order_number().': pickup mail sent without time indication', $context );
		} else {
			echo '<p>' . sprintf( __( 'Bericht bovenaan de 1ste bevestigingsmail (indien afhaling), inclusief afhaallocatie (%1$s), -dag (%2$s) en -uur (%3$s).', 'oxfam-webshop' ), $pickup_location_name, date_i18n( 'l d/m', $delivery ), date_i18n( 'G\ui', $delivery ) ) . '</p>';
		}
	} else {
		echo '<p>' . sprintf( __( 'Bericht bovenaan de 1ste bevestigingsmail van een B2B-bestelling (indien afhaling), inclusief afhaallocatie (%s).', 'oxfam-webshop' ), $pickup_location_name ) . '</p>';
	}

} else {

	echo '<p>' . __( 'Bericht bovenaan de 1ste bevestigingsmail (indien thuislevering).', 'oxfam-webshop' ) . '</p>';

}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

if ( order_contains_breakfast( $order ) ) {
	// Nummer van Cis vermelden
	$phone_number = '0498/51.87.50';
} else {
	// $shop_post_id = get_option('oxfam_shop_post_id');
	// Eventueel array( 'id' => $shop_post_id ) doorgeven als argument voor de juiste $pickup_location?
	$phone_number = print_telephone();
}
echo '<p>'.sprintf( __( 'Heb je nog een vraag? Antwoord gewoon op deze mail, of bel ons op %s en vermeld je bestelnummer. Op die manier kunnen we je snel verder helpen.', 'oxfam-webshop' ), $phone_number ).'</p>';

// In principe zouden we ook naar oude bakken van 24 flesjes moeten zoeken, maar onmogelijk aangezien  
// $old_empties = array( 'WLFSG', 'WLBS6' );
$old_glass_cnt = 0;

foreach ( $order->get_items() as $item ) {
	// Opgelet: deze methode is enkel beschikbaar op WC_Order_Item_Product, wat als we een ander soort item tegenkomen?
	if ( $product = $item->get_product() ) {
		if ( $product->get_sku() === 'WLFSG' ) {
			$old_glass_cnt += $item->get_quantity();
		}
	}
}

if ( $old_glass_cnt > 0 ) {
	echo '<p><b>'.sprintf( __( 'Opgelet: deze bestelling bevat %d grote flessen fruitsap in retourglas. Gelieve dit leeggoed, inclusief eventuele plastic bakken, vòòr 15 oktober 2022 in te leveren bij een Oxfam-Wereldwinkel naar keuze. Na deze datum kunnen we de terugbetaling van de waarborg niet meer garanderen. <a href="https://www.oxfamfairtrade.be/nl/2021/12/oxfam-fair-trade-sappen-wat-is-nieuw-vanaf-2022/" target="_blank">Meer info over onze switch naar Tetra Paks.</a>', 'oxfam-webshop' ), $old_glass_cnt ).'</b></p>';
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

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
