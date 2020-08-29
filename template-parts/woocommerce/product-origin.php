<?php 
	global $product, $partners;
?>

<div class="product-provenance">
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

	<?php elseif ( $product->get_meta('_herkomst_nl') !== '' ) : ?>

		<p class="herkomst">
			Herkomst: <?php echo $product->get_meta('_herkomst_nl'); ?>

			<?php
				// Eventueel kunnen we er ook een opsomming van maken
				// echo '<ul>';
				// $countries = explode( ', ', $product->get_meta('_herkomst_nl') );
				// foreach( $countries as $country ) {
				// 	echo '<li>'.$country.'</li>';
				// }
				// echo '</ul>';
			?>
		</p>
	<?php endif; ?>
</div>