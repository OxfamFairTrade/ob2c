<?php 
	global $product;
	$partners = $args['partners'];
?>

<div class="product-origin">
	<?php if ( count( $partners ) > 0 ) : ?>
		<p class="partners">
			<?php
				$terms = array();
				foreach ( $partners as $partner ) {
					// Eventueel kunnen we gewoon linken naar de interne partnerterm à la https://shop.oxfamwereldwinkels.be/oostende/partner/apropal/
					// if ( false !== ( $term = get_term_by( 'slug', $partner['slug'], 'product_partner' ) ) ) {
					// 	$output = '<a href="'.get_term_link( $term ).'">' . $partner['name'] . '</a>';
					// }
					
					// Niet alle partners bevatten de eigenschap 'link' naar de externe partnerpagina (o.a. alle C-partners)
					if ( ! empty( $partner['link'] ) ) {
						$output = '<a href="'.esc_url( $partner['link'] ).'" target="_blank">' . $partner['name'] . '</a>';
					} else {
						$output = $partner['name'];
					}
					$terms[] = $output . ' (' . $partner['country'] . ')';
				}
				echo _n( 'Producent', 'Producenten', count( $terms ) ) . ': ' . implode( ', ', $terms );
			?>
		</p>
	<?php elseif ( $product->get_attribute('countries') !== '' ) : ?>
		<p class="countries">
			Herkomst: <?= $product->get_attribute('countries'); ?>
		</p>
	<?php endif; ?>
</div>