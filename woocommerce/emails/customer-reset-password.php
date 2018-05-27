<?php
/**
 * Customer Reset Password email
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$customer = get_user_by( 'login', $user_login );

?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
		if ( '' !== $customer->first_name ) {
			$name = $customer->first_name;
		} else {
			$name = 'klant';
		}
		echo 'Beste '.$name;
	?>
</p>

<p><?php _e( 'Someone requested that the password be reset for the following account:', 'woocommerce' ); ?></p>
<p><?php printf( __( 'Username: %s', 'woocommerce' ), $user_login ); ?></p>
<p><?php _e( 'If this was a mistake, just ignore this email and nothing will happen.', 'woocommerce' ); ?></p>

<p style="text-align: center;">
	<a class="link" href="<?php echo esc_url( add_query_arg( array( 'key' => $reset_key, 'login' => rawurlencode( $user_login ) ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); ?>">
		<b><?php _e( 'Speciale wachtwoordresetlink', 'oxfam-webshop' ); ?> &raquo;</b>
	</a>
</p>

<p><?php printf( __( 'Ondertekening van mails met accountinfo.', 'oxfam-webshop' ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>