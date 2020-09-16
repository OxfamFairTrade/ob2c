<?php
/**
 * Product quantity inputs
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 4.0.0
NM: Modified */

defined( 'ABSPATH' ) || exit;

global $nm_theme_options;

// Quantity arrows class
$nm_quantity_arrows_class = ( $nm_theme_options['qty_arrows'] ) ? ' qty-show' : 'qty-hide';

if ( $max_value && $min_value === $max_value ) {
	?>
	<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $min_value ); ?>" />
	<?php
} else {
	?>
	<div class="nm-quantity-wrap <?php echo esc_attr( $nm_quantity_arrows_class ); ?>">
		<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>

		<div class="quantity">
			<div class="nm-qty-minus nm-font nm-font-media-play flip"></div>&nbsp;<input
			type="number"
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
			step="<?php echo esc_attr( $step ); ?>"
			min="<?php echo esc_attr( $min_value ); ?>"
			max="<?php echo esc_attr( 0 < $max_value ? $max_value : '' ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $input_value ); ?>"
			size="4"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			pattern="[0-9]*" />&nbsp;<div class="nm-qty-plus nm-font nm-font-media-play"></div>
		</div>
		<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
	</div>
	<?php
}
