<?php
/**
 * Customer new account email
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

	<p><?php printf( __( 'Eerste alinea in de welkomstmail aan nieuwe gebruikers, inclusief naam van de webshop (%1$s) en vetgedrukte gebruikersnaam (%2$s).', 'oxfam-webshop' ), esc_html( $blogname ), '<strong>' . esc_html( $user_login ) . '</strong>' ); ?></p>

<?php if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) && $password_generated ) : ?>

	<p><?php printf( __( 'Your password has been automatically generated: %s', 'woocommerce' ), '<strong>' . esc_html( $user_pass ) . '</strong>' ); ?></p>

<?php endif; ?>

	<p><?php printf( __( 'Uitleg over en URL naar (%s) de \'Mijn account\'-pagina in de webshop waar de gebruiker zich registreerde.', 'oxfam-webshop' ), make_clickable( esc_url( wc_get_page_permalink( 'myaccount' ) ) ) ); ?></p>

	<p><?php printf( __( 'Uitsmijter van de welkomstmail.', 'oxfam-webshop' ), make_clickable( esc_url( wc_get_page_permalink( 'myaccount' ) ) ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email );
