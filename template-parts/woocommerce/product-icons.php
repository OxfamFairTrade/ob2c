<?php 
	global $product;

	if ( $product->get_attribute('voedingsvoorkeuren') !== '' ) {
		?>
		<div id="product-icons" class="product-info-panel icons">
			<?php
				$icons = explode( ', ', $product->get_attribute('voedingsvoorkeuren') );
				foreach ( $icons as $icon_name ) {
					echo '<a href="'.get_post_type_archive_link('product').'?swoof=1&pa_voedingsvoorkeuren='.sanitize_title( $icon_name ).'#result">';
						echo '<div class="icon" style="background-image: url('.get_stylesheet_directory_uri().'/images/voedingsvoorkeuren/'.sanitize_title( $icon_name ).'.svg);" alt="'.$icon_name.'" title="Bekijk alles '.strtolower( $icon_name ).'"></div>';
					echo '</a>';
				}
			?>
		</div>
		<?php
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
