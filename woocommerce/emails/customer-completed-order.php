<?php
/**
 * Customer completed order email
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

// Is altijd ingevuld, dus geen check doen
echo '<p>Dag '.$order->get_billing_first_name().'</p>';

if ( $order->has_shipping_method('local_pickup_plus') ) {
	echo '<p>' . __( 'Bericht bovenaan de 2de bevestigingsmail (indien afhaling in de winkel).', 'oxfam-webshop' ) . '</p>';
} else {
	echo '<p>';
	if ( $order->get_meta('is_b2b_sale') !== 'yes' ) {
		$text = __( 'Bericht bovenaan de 2de bevestigingsmail (indien thuislevering).', 'oxfam-webshop' );
		if ( false !== ( $tracking_info = get_tracking_info( $order ) ) ) {
			if ( $order->has_shipping_method('service_point_shipping_method') ) {
				echo str_replace( 'Een vrijwilliger komt er binnenkort mee langs.', 'Je kunt het binnenkort oppikken in het afhaalpunt dat je koos.', $text );
			} elseif ( $tracking_info[0]['carrier'] === 'Bpost' ) {
				echo str_replace( 'Een vrijwilliger ', 'De postbode ', $text );
			} elseif ( $tracking_info[0]['carrier'] === 'DPD' ) {
				echo str_replace( 'Een vrijwilliger ', 'De koerier ', $text );
			}
			echo ' ';
			$clickable_tracking_numbers = '';
			foreach ( $tracking_info as $index => $tracking_info_details ) {
				if ( $index === 0 ) {
					$clickable_tracking_numbers .= '<a href="'.esc_url( $tracking_info_details['link'] ).'" target="_blank">'.$tracking_info_details['number'].'</a>'; 
				} else {
					$clickable_tracking_numbers .= ', <a href="'.esc_url( $tracking_info_details['link'] ).'" target="_blank">'.$tracking_info_details['number'].'</a>';
				}
			}
			// Mocht de link om één of andere reden ontbreken zal dit 'zacht' breken
			printf( __( 'Trackinginfo inclusief aanklikbare traceercodes (%s).', 'oxfam-webshop' ), $clickable_tracking_numbers );
		} else {
			echo $text;
		}
	} else {
		_e( 'Bericht bovenaan de 2de bevestigingsmail (indien B2B-levering op locatie).', 'oxfam-webshop' );
	}
	echo '</p>';
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

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
	echo '<p>'.sprintf( __( 'Opgelet: deze bestelling bevat %d grote flessen fruitsap van 1 liter in retourglas. Gelieve dit leeggoed, inclusief eventuele plastic bakken, <b>vòòr 15 oktober 2022</b> in te leveren bij een Oxfam-Wereldwinkel naar keuze. Na deze datum kunnen we de terugbetaling van de waarborg niet meer garanderen. <a href="https://www.oxfamfairtrade.be/nl/2021/12/oxfam-fair-trade-sappen-wat-is-nieuw-vanaf-2022/" target="_blank">Meer info over onze switch naar Tetra Paks.</a>', 'oxfam-webshop' ), $old_glass_cnt ).'</p>';
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
