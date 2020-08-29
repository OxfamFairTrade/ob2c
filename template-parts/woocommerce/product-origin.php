<?php 
	global $product, $partners;
?>

<div class="product-provenance">
	<?php if ( count( $partners ) > 0 ) : ?>
		
		<h3><?php echo _n( 'Producent', 'Producenten', count( $partners ) ); ?></h3>
		<p class="partners">
			<?php
				foreach ( $partners as $partner ) {
					// Niet alle partners zullen de eigenschap 'link' bevatten (o.a. alle C-partners)
					// Eventueel check toevoegen op waarde van '_link_to' (= bij crafts, externe site in nieuw tabblad openen)
					if ( ! empty( $partner['link'] ) ) {
						echo '<a href="'.esc_url( $partner['link'] ).'">'.$partner['name'].'</a>';
					} else {
						echo $partner['name'];
					}
					echo ' ('.$partner['country'].')<br/>';
				}
			?>
		</p>

	<?php elseif ( $product->get_meta('_herkomst_nl') !== '' ) : ?>

		<h3>Herkomst</h3>
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