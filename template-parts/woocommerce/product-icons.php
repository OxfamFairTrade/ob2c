<?php 
	global $product;

	if ( $product->get_attribute('preferences') !== '' ) {
		?>
		<div id="product-icons" class="product-info-panel icons">
			<?php
				$icons = explode( ', ', $product->get_attribute('preferences') );
				foreach ( $icons as $icon_name ) {
					// @toDo: Icoontje voor 'biologisch' toevoegen
					if ( ! stristr( $icon_name, 'biologisch' ) ) {
						echo '<a href="'.get_post_type_archive_link('product').'?filter_preferences='.sanitize_title( $icon_name ).'#result">';
							echo '<div class="icon" style="background-image: url('.get_stylesheet_directory_uri().'/images/voedingsvoorkeuren/'.sanitize_title( $icon_name ).'.svg);" alt="'.$icon_name.'" title="Bekijk alles '.strtolower( $icon_name ).'"></div>';
						echo '</a>';
					}
				}
			?>
		</div>
		<?php
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
