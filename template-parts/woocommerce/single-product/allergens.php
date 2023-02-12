<?php 
	global $product;
	$args = wp_parse_args( $args, array( 'oft_quality_data' => false ) );
	
	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( is_array( $args['oft_quality_data'] ) and array_key_exists( 'allergen', $args['oft_quality_data'] ) ) {
		
		$contains = array();
		$traces = array();
		$no_allergens = false;
		
		foreach ( $args['oft_quality_data']['allergen'] as $slug => $name ) {
			$parts = explode( '-', $slug );
			if ( $parts[0] === 'c' ) {
				$contains[] = $name;
			} elseif ( $parts[0] === 'mc' ) {
				$traces[] = $name;
			} elseif ( $parts[0] === 'none' ) {
				$no_allergens = true;
			}
		}

		?>
		<div id="product-allergens" class="product-info-panel allergens">
			<h4><?= __( 'Allergeneninfo', 'oxfam-webshop' ); ?></h4>
			<p>
				<?php
					if ( $no_allergens === true or ( count( $traces ) === 0 and count( $contains ) === 0 ) ) {
						echo __( 'Dit product bevat geen meldingsplichtige allergenen.', 'oxfam-webshop' );
					} else {
						if ( count( $contains ) > 0 ) {
							echo sprintf( __( 'Bevat %s.', 'oxfam-webshop' ), implode( ', ', $contains ) );
							if ( count( $traces ) > 0 ) {
								echo '<br/>';
							}
						}
						if ( count( $traces ) > 0 ) {
							echo sprintf( __( 'Kan sporen bevatten van %s.', 'oxfam-webshop' ), implode( ', ', $traces ) );
						}
					}
				?>
			</p>
		</div>
		<?php
	}