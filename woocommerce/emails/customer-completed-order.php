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
echo '<p>Dag '.$order->get_billing_first_name().',</p>';

if ( $order->has_shipping_method('local_pickup_plus') ) {
	echo '<p>' . __( 'Bericht bovenaan de 2de bevestigingsmail (indien afhaling in de winkel).', 'oxfam-webshop' ) . '</p>';
} else {
	echo '<p>' . __( 'Bericht bovenaan de 2de bevestigingsmail (indien thuislevering).', 'oxfam-webshop' );
	// Check of we een tracking number van Bpost kunnen terugvinden
	$args = array( 'post_id' => $post_id, 'search' => 'SendCloud' );
	// Want anders zien we de private opmerkingen niet!
	remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
	$list = get_comments( $args );
	if ( count($list) > 0 ) {
		$comment = $list[0];
		preg_match( '/[0-9]{24}/', $comment->comment_content, $numbers );
		echo ' Volg de zending bij Bpost met behulp van deze barcode: <a href="http://track.bpost.be/btr/web/#/search?itemCode='.$numbers[0].'&lang=nl" target="_blank">'.$numbers[0].'</a>.';
	}
	add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
	echo '</p>';
}

echo '<p>&nbsp;</p>';

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
