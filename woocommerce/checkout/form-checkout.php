<?php
/**
 * Checkout Form
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.5.0
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $nm_theme_options, $nm_globals;

$nm_validation_notices_class = ( $nm_theme_options['checkout_inline_notices'] ) ? ' nm-validation-inline-notices' : '';

wc_print_notices();

?>

<div class="container">
    <div class="breadcrumb">
        <a href="https://<?php echo OXFAM_MAIN_SITE_DOMAIN; ?>/">Home</a> <span class="sep"></span> <a href="<?php echo get_site_url(); ?>">Webshop <?php echo get_webshop_name(true); ?></a> <span class="sep"></span> <a href="<?php echo get_permalink( wc_get_page_id('shop') ); ?>">Producten</a> <span class="sep"></span> <span class="breadcrumb_last" aria-current="page">Winkelmandje</span>
    </div>
    <div class="col-row">
        <div class="col-md-12">
            <?php
            do_action( 'woocommerce_before_checkout_form', $checkout );

            // If checkout registration is disabled and not logged in, the user cannot checkout
            if ( ! $checkout->enable_signup && ! $checkout->enable_guest_checkout && ! is_user_logged_in() ) {
                echo apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) );
                return;
            }
            ?>
        </div>
    </div>

    <form name="checkout" method="post" class="checkout woocommerce-checkout clear col-row" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
        <!-- @ToDo: Hier verschijnt een notices wrapper? Deze heeft volgende classe nodig: .col-xs-12  -->
        <div class="col-md-12">
            <ul class="nm-checkout-login-coupon nm-shop-notices">
                <?php if ( isset( $nm_globals['checkout_login_message'] ) ) : ?>
                    <?php wc_print_notice( $nm_globals['checkout_login_message'], 'notice' ); ?>
                <?php endif; ?>
                <?php
                // GEWIJZIGD: Definieer een extra actie voor notices met zelfde layout als inlogherinnering
                echo do_action( 'woocommerce_just_before_checkout_form', $checkout );
                ?>
                <?php if ( isset( $nm_globals['checkout_coupon_message'] ) ) : ?>
                    <?php wc_print_notice( $nm_globals['checkout_coupon_message'], 'notice' ); ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="col-md-8">
            <?php if ( sizeof( $checkout->checkout_fields ) > 0 ) : ?>

                <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

                <div class="col2-set<?php echo $nm_validation_notices_class; ?>" id="customer_details">
                    <div class="col-1">
                        <?php do_action( 'woocommerce_checkout_billing' ); ?>
                    </div>

                    <div class="col-2">
                        <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                    </div>
                </div>

                <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

            <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

            <div id="order_review" class="woocommerce-checkout-review-order">
                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
            </div>

            <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
        </div>
    </form>

    <?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

</div>