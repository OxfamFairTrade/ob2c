<?php
/**
 * Customer Reset Password email
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 4.0.0
 */

defined( 'ABSPATH' ) || exit;

$customer = get_user_by( 'login', $user_login );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

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
		printf( esc_html__( 'Iemand heeft verzocht om het wachtwoord van je account &laquo; %1$s &raquo; bij de webshop van %2$s opnieuw in te stellen.', 'oxfam-webshop' ), $user_login, get_webshop_name() );
	?>
</p>

<p><?php esc_html_e( 'Was dit een vergissing? Negeer dan gewoon deze e-mail en er zal niets gebeuren.', 'oxfam-webshop' ); ?></p>

<p style="text-align: center;">
	<a class="link" href="<?php echo esc_url( add_query_arg( array( 'key' => $reset_key, 'id' => $user_id ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); ?>">
		<b><?php esc_html_e( 'Speciale wachtwoordresetlink', 'oxfam-webshop' ); ?> &raquo;</b>
	</a>
</p>

<?php
	/**
	 * Show user-defined additional content - this is set in each email's settings. DISABLED
	 */
	if ( $additional_content ) {
		// echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	}
?>

<p><?php printf( esc_html__( 'Ondertekening van mails met accountinfo, inclusief regio van webshop (%s).', 'oxfam-webshop' ), get_webshop_name(true) ); ?></p>

<?php

do_action( 'woocommerce_email_footer', $email );
