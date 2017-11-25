<?php
/**
 * Product attributes
 *
 * Used by list_attributes() in the products class.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="shop_attributes">
	<?php
	// Sluit bepaalde zichtbare taxonomiëen toch nog uit (flexibeler!)
	$forbidden_attributes = array( 'pa_eenheid', 'pa_ompak', 'pa_shopplus' );
	foreach ( $forbidden_attributes as $name ) {
		if ( array_key_exists( $name, $attributes ) ) {
			unset( $attributes[$name] );
		}
	}
	foreach ( $attributes as $attribute ) : ?>
		<tr>
			<th><?php echo wc_attribute_label( $attribute->get_name() ); ?></th>
			<td><?php
				$values = array();

				if ( $attribute->is_taxonomy() ) {
					$attribute_taxonomy = $attribute->get_taxonomy_object();
					$attribute_values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) );

					foreach ( $attribute_values as $attribute_value ) {
						$value_name = esc_html( $attribute_value->name );

						if ( $attribute_taxonomy->attribute_public ) {
							$values[] = '<a href="' . esc_url( get_term_link( $attribute_value->term_id, $attribute->get_name() ) ) . '" rel="tag">' . $value_name . '</a>';
						} else {
							$values[] = $value_name;
						}
					}
				} else {
					$values = $attribute->get_options();

					foreach ( $values as &$value ) {
						$value = esc_html( $value );
					}
				}

				echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );
			?></td>
		</tr>
	<?php endforeach; ?>

	<!-- GEWIJZIGD: Gewicht en dimensies altijd onderaan weergeven -->
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