<?php 
	global $product, $oft_quality_data;

	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( $oft_quality_data and array_key_exists( 'allergen', $oft_quality_data ) ) {
		
		$contains = array();
		$traces = array();
		$no_allergens = false;
		
		foreach ( $oft_quality_data['allergen'] as $slug => $name ) {
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
		<div class="product-allergens">
			<h4>Allergeneninfo</h4>
			<p class="allergens">
				<?php
					if ( $no_allergens === true or ( count( $traces ) === 0 and count( $contains ) === 0 ) ) {
						echo 'Dit product bevat geen meldingsplichtige allergenen.';
					} else {
						if ( count( $contains ) > 0 ) {
							echo 'Bevat '.implode( ', ', $contains ).'.';
							if ( count( $traces ) > 0 ) {
								echo '<br/>';
							}
						}
						if ( count( $traces ) > 0 ) {
							echo 'Kan sporen bevatten van '.implode( ', ', $traces ).'.';
						}
					}
				?>
			</p>
		</div>
		<?php
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
