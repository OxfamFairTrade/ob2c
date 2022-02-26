<?php 
	global $product;
	$partners = $args['partners'];
	
	$partners_with_quote = array_filter( $partners, function( $p ) {
		return ! empty( $partner['quote']['content'] );
	} );
	// var_dump_pre( $partners_with_quote );
?>

<div class="product-origin">
	<?php if ( count( $partners ) > 0 ) : ?>
		
		<p class="partners">
			<?php
				$terms = array();
				foreach ( $partners as $partner ) {
					// Niet alle partners zullen de eigenschap 'link' bevatten (o.a. alle C-partners)
					// Eventueel check toevoegen op waarde van '_link_to' (= bij crafts, externe site in nieuw tabblad openen)
					if ( ! empty( $partner['link'] ) ) {
						$output = '<a href="'.esc_url( $partner['link'] ).'">'.$partner['name'].'</a>';
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
			Herkomst: <?php echo $product->get_attribute('countries'); ?>
		</p>
	<?php endif; ?>
</div>