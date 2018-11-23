<?php
/**
 * Checkout shipping information form
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.9
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="woocommerce-shipping-fields">
	<!-- GEWIJZIGD: Blok altijd laden, klant kan nog van gedacht veranderen! -->
        <div id="ship-to-different-address" class="clearfix">
			<h3><?php esc_html_e( 'Shipping details', 'woocommerce' ); ?></h3>
            
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php _e( 'Ship to a different address?', 'woocommerce' ); ?></span>
			</label>
		</div>

		<div class="shipping_address">

			<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>
            <p>Opgelet: indien je de postcode hieronder wijzigt, kan de momenteel geselecteerde verzendmethode misschien onbeschikbaar worden. Selecteer in dat geval eerst een nieuwe methode. <?php // echo print_delivery_zips(); ?></p>
            <div class="woocommerce-shipping-fields__field-wrapper">
                <?php
					$fields = $checkout->get_checkout_fields( 'shipping' );

					foreach ( $fields as $key => $field ) {
						if ( isset( $field['country_field'], $fields[ $field['country_field'] ] ) ) {
							$field['country'] = $checkout->get_value( $field['country_field'] );
						}
						woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
					}
                ?>
            </div>

			<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>

		</div>

</div>
<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>
    
    <?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>

		<?php if ( ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ) : ?>

			<h3><?php _e( 'Additional information', 'woocommerce' ); ?></h3>

		<?php endif; ?>
        
        <div class="woocommerce-additional-fields__field-wrapper">
            <?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
                <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
            <?php endforeach; ?>
        </div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
