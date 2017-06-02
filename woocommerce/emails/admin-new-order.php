<?php
/**
 * Admin new order email
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author WooThemes
 * @package WooCommerce/Templates/Emails/HTML
 * @version 2.5.0
 */

 if ( ! defined( 'ABSPATH' ) ) {
 	exit;
 }

 /**
  * @hooked WC_Emails::email_header() Output the email header
  */
 do_action( 'woocommerce_email_header', $email_heading, $email );

printf( '<p>Je hebt een bestelling ontvangen van %s.</p>', $order->get_formatted_billing_full_name() );
$tax_classes = $order->get_items_tax_classes();
if ( in_array( 'voeding', $tax_classes ) === false and $order->get_shipping_total() > 0 ) {
    echo '<p style="color: red; font-weight: bold;">Opgelet, dit is een bestelling met enkel producten aan 21% BTW-tarief! Zorg dat je bij de verwerking in ShopPlus dus de levercode WEB21 inscant. Als winkel hou je aan deze thuislevering dus netto 5,74 i.p.v. 6,56 euro over.</p>';
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
