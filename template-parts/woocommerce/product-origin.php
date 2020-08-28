<?php 
	global $product;
?>

<div class="container product-origin-block">
	<div class="col-row">
		<div class="col-md-12">
			<?php
				// Hier komen de producten, 't is een beetje verwarrend
				if ( $product->get_meta('_herkomst_nl') !== '' ) {
					echo '<p class="herkomst">';
						echo 'Herkomst: '.$product->get_meta('_herkomst_nl');
					echo '</p>';
				}
			?>
		</div>
	</div>
</div>