<?php
/**
 * Customer completed order email
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

// Is altijd ingevuld, dus geen check doen
echo '<p>Dag '.$order->get_billing_first_name().'</p>';

if ( $order->has_shipping_method('local_pickup_plus') ) {
	echo '<p>' . __( 'Bericht bovenaan de 2de bevestigingsmail (indien afhaling in de winkel).', 'oxfam-webshop' ) . '</p>';
} else {
	echo '<p>';
	if ( $order->get_meta('is_b2b_sale') !== 'yes' ) {
		$text = __( 'Bericht bovenaan de 2de bevestigingsmail (indien thuislevering).', 'oxfam-webshop' );
		if ( get_tracking_number( $order->get_id() ) ) {
			if ( $order->has_shipping_method('service_point_shipping_method') ) {
				echo str_replace( 'Een vrijwilliger of een fietskoerier komt er binnenkort mee langs.', 'Je kunt het binnenkort oppikken in het afhaalpunt dat je koos.', $text );
			} else {
				echo str_replace( 'Een vrijwilliger of een fietskoerier', 'De postbode', $text );
			}
			echo ' ';
			printf( __( 'Tracking bij Bpost, inclusief barcode (%1$s) en volglink (%2$s).', 'oxfam-webshop' ), get_tracking_number( $order->get_id() ), get_tracking_link( $order->get_id() ) );
		} else {
			echo $text;
		}
	} else {
		_e( 'Bericht bovenaan de 2de bevestigingsmail (indien B2B-levering op locatie).', 'oxfam-webshop' );
	}
	echo '</p>';
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
