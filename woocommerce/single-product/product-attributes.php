<?php
/**
 * Product attributes
 *
 * Used by list_attributes() in the products class.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! $product_attributes ) {
	return;
}
?>
<table class="woocommerce-product-attributes shop_attributes">
	<?php
		// Sluit bepaalde zichtbare taxonomiëen toch nog uit
		// Kan eventueel vervangen worden door 'woocommerce_display_product_attributes'-filter
		$forbidden_attributes = array( 'eenheid', 'shopplus' );
		foreach ( $forbidden_attributes as $name ) {
			if ( array_key_exists( $name, $product_attributes ) ) {
				unset( $product_attributes[ 'attribute_pa_'.$name ] );
			}
		}
	?>
	<?php foreach ( $product_attributes as $product_attribute_key => $product_attribute ) : ?>
		<tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr( $product_attribute_key ); ?>">
			<th class="woocommerce-product-attributes-item__label"><?php echo wp_kses_post( $product_attribute['label'] ); ?></th>
			<td class="woocommerce-product-attributes-item__value"><?php echo wp_kses_post( $product_attribute['value'] ); ?></td>
		</tr>
	<?php endforeach; ?>

	<!-- GEWIJZIGD: Gewicht en dimensies onderaan weergeven -->
	<?php if ( $product->has_weight() ) : $has_row = true; ?>
		<tr>
			<th>Brutogewicht</th>
			<td class="product_weight"><?php echo number_format( $product->get_weight(), 2, ',', '.' ) . ' ' . esc_attr( get_option( 'woocommerce_weight_unit' ) ); ?></td>
		</tr>
	<?php endif; ?>

	<?php if ( $product->has_dimensions() ) : $has_row = true; ?>
		<tr>
			<th>Afmetingen</th>
			<td class="product_dimensions"><?php echo wc_format_dimensions( $product->get_dimensions(false) ); ?></td>
		</tr>
	<?php endif; ?>
</table>