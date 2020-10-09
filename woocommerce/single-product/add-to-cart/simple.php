<?php
/**
 * Simple product add to cart
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.4.0
NM: Modified - Added "nm-simple-add-to-cart-button" class to button */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

if ( is_main_site() and $product->get_meta('_woonet_publish_to_23') !== 'yes' ) {
	// Het product wordt niet online verkocht (o.b.v. aanwezigheid in webshop Oostende als test case)
	return '<span class="unavailable">Niet online beschikbaar</span>';
}

echo wc_get_stock_html( $product );

// GEWIJZID: Ook verbergen indien 'onbackorder'
if ( $product->is_in_stock() and ! $product->is_on_backorder() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php
			do_action( 'woocommerce_before_add_to_cart_quantity' );

			woocommerce_quantity_input( array(
				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
				'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(),
			) );

			do_action( 'woocommerce_after_add_to_cart_quantity' );

			// GEWIJZIGD: Store locator triggeren op hoofdniveau
			if ( is_main_site() ) {
				?><button type="button" name="add-to-cart" class="store-selector-open button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button><?php
			} else {
				?><button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="nm-simple-add-to-cart-button single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button><?php
			}
		?>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
