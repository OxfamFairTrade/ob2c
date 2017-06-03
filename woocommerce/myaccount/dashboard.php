<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @author      WooThemes
 * @package     WooCommerce/Templates
 * @version     2.6.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $nm_theme_options;
?>

<div class="nm-MyAccount-dashboard">

	<p><?php
        $current_user = wp_get_current_user();
        printf(
            __( 'Tekst die verschijnt op de dashboardpagina van \'Mijn account\' inclusief voornaam gebruiker (%1$s) en URL\'s naar bestellingen (%2$s), adressen (%3$s), accountgegevens (%4$s) en nieuwsbriefvoorkeuren (%5$s).', 'oxfam-webshop' ),
            $current_user->user_firstname,
            esc_url( wc_get_endpoint_url( 'orders' ) ),
            esc_url( wc_get_endpoint_url( 'edit-address' ) ),
            esc_url( wc_get_endpoint_url( 'edit-account' ) ),
            esc_url( wc_get_endpoint_url( 'nieuwsbrief' ) )
        );
    ?></p>

    <?php
        /**
         * My Account dashboard.
         *
         * @since 2.6.0
         */
        do_action( 'woocommerce_account_dashboard' );

        /**
         * Deprecated woocommerce_before_my_account action.
         *
         * @deprecated 2.6.0
         */
        do_action( 'woocommerce_before_my_account' );

        /**
         * Deprecated woocommerce_after_my_account action.
         *
         * @deprecated 2.6.0
         */
        do_action( 'woocommerce_after_my_account' );
    ?>

</div>
