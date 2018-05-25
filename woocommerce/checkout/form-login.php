<?php
/**
 * Checkout login form
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.0.0
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( is_user_logged_in() || 'no' === get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
	return;
}

$info_message  = apply_filters( 'woocommerce_checkout_login_message', __( 'Returning customer?', 'woocommerce' ) );
$info_message .= ' <a href="#" class="showlogin">' . __( 'Click here to login', 'woocommerce' ) . '</a>';

global $nm_globals;
$nm_globals['checkout_login_message'] = $info_message;
?>

<div id="nm-checkout-login-form" class="nm-login-popup-wrap mfp-hide">
    <?php
        woocommerce_login_form(
            array(
                // GEWIJZIGD: Aangepaste tekst
                'message'  => __( 'Vul hieronder je gegevens in als je je tijdens een vorige bestelling al registreerde. Nog geen account? Sluit dan dit venster en vink tijdens het afwerken van je bestelling aan dat je een wachtwoord wil aanmaken.', 'oxfam-webshop' ),
                'redirect' => wc_get_page_permalink( 'checkout' ),
                'hidden'   => true
            )
        );
    ?>
</div>
