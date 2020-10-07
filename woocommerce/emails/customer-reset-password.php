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

<p>
	<?php
		printf( __( 'Iemand heeft verzocht om het wachtwoord van je account &laquo; %1$s &raquo; bij de webshop van %2$s opnieuw in te stellen.', 'oxfam-webshop' ), $user_login, get_webshop_name() );
	?>
</p>

<p><?php _e( 'Was dit een vergissing? Negeer dan gewoon deze e-mail en er zal niets gebeuren.', 'oxfam-webshop' ); ?></p>

<p style="text-align: center;">
	<a class="link" href="<?php echo esc_url( add_query_arg( array( 'key' => $reset_key, 'login' => rawurlencode( $user_login ) ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); ?>">
		<b><?php _e( 'Speciale wachtwoordresetlink', 'oxfam-webshop' ); ?> &raquo;</b>
	</a>
</p>

<p><?php printf( __( 'Ondertekening van mails met accountinfo, inclusief regio van webshop (%s).', 'oxfam-webshop' ), get_webshop_name(true) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
