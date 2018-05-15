<?php
/**
 * Product quantity inputs
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $max_value && $min_value === $max_value ) {
	?>
	<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $min_value ); ?>" />
	<?php
} else {
    ?>
    <div class="nm-quantity-wrap">
        <label><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></label>
        <label class="nm-qty-label-abbrev"><?php esc_html_e( 'Qty', 'woocommerce' ); ?></label>

        <div class="quantity">
            <div class="nm-qty-minus nm-font nm-font-media-play flip"></div>

            <input type="number" id="<?php echo esc_attr( $input_id ); ?>" class="input-text qty text" step="<?php echo esc_attr( $step ); ?>" min="<?php echo esc_attr( $min_value ); ?>" max="<?php echo esc_attr( 0 < $max_value ? $max_value : '' ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $input_value ); ?>" size="4" pattern="[0-9]*" aria-labelledby="<?php echo ! empty( $args['product_name'] ) ? sprintf( esc_attr__( '%s quantity', 'woocommerce' ), $args['product_name'] ) : ''; ?>" />

            <div class="nm-qty-plus nm-font nm-font-media-play"></div>
        </div>
    </div>
<?php
}
