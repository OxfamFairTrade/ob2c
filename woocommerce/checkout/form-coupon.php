<?php
/**
 * Checkout coupon form
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.4.4
 NM: Modified */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
	return;
}

// GEWIJZIGD: Pas tekst aan
$info_message = apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Kortingsbon of digitale cadeaubon gekregen?', 'oxfam-webshop' ) . ' <a href="#" class="showcoupon">' . __( 'Klik hier om je code in te vullen', 'oxfam-webshop' ) . '</a>' );  

global $nm_globals;
$nm_globals['checkout_coupon_message'] = $info_message;
?>

<div id="nm-coupon-login-form" class="nm-coupon-popup-wrap mfp-hide">
    <form class="checkout_coupon" method="post" style="display:none">
        
        <!-- GEWIJZIGD: Pas tekst aan -->
        <h4><?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?></h4>
        
        <p><?php esc_html_e( 'If you have a coupon code, please apply it below.', 'woocommerce' ); ?></p>
        
        <p class="form-row form-row-first">
            <input type="text" name="coupon_code" class="input-text" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" id="coupon_code" value="" />
        </p>

        <p class="form-row form-row-last">
            <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
        </p>

        <div class="clear"></div>
    </form>
</div>
