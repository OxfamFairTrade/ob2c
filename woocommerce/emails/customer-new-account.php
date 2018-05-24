<?php
/**
 * Customer new account email
 *
 * @see 		https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version 	1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$customer = get_user_by( 'login', $user_login );
$email_heading = __( 'Titel in de header van de welkomstmail', 'oxfam-webshop' );

?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php if ( 'yes' === get_the_author_meta( 'blog_'.get_current_blog_id().'_is_b2b_customer', $customer->ID ) ) : ?>

	<p><?php printf( __( 'Eerste alinea in de uitnodingsmail aan B2B-gebruikers, inclusief naam van de webshop (%1$s) en gebruikersnaam (%2$s).', 'oxfam-webshop' ), esc_html( $blogname ), '<strong>' . esc_html( $user_login ) . '</strong>' ); ?></p>

	<p style="text-align: center;">
		<a class="link" href="<?php echo esc_url( add_query_arg( array( 'key' => $reset_key, 'login' => rawurlencode( $user_login ) ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); ?>">
			<b><?php _e( 'Speciale wachtwoordresetlink', 'oxfam-webshop' ); ?> &raquo;</b>
		</a>
	</p>

	<p><?php
			$author_metas = array(
				'billing_company' => 'Bedrijf of vereniging',
				'billing_vat' => 'BTW-nummer',
			);
			
			foreach ( $author_metas as $key => $label ) {
				if ( '' !== get_the_author_meta( $key, $customer->ID ) ) {
					echo $label.': '.get_the_author_meta( $key, $customer->ID ).'<br/>';
				}
			}
		?>
	</p>

	<p><?php _e( 'Tweede alinea in de uitnodingsmail aan B2B-gebruikers met praktische info over bestellingen en leveringen.', 'oxfam-webshop' ); ?></p>

<?php else : ?>	

	<p><?php printf( __( 'Eerste alinea in de welkomstmail aan nieuwe gebruikers, inclusief naam van de webshop (%1$s) en vetgedrukte gebruikersnaam (%2$s).', 'oxfam-webshop' ), esc_html( $blogname ), '<strong>' . esc_html( $user_login ) . '</strong>' ); ?></p>

	<p><?php printf( __( 'Uitleg over de \'Mijn account\'-pagina, inclusief URL in de webshop waar de gebruiker zich registreerde (%s).', 'oxfam-webshop' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ); ?></p>

<?php endif; ?>

<p><?php printf( __( 'Uitsmijter van de welkomstmail.', 'oxfam-webshop' ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email );