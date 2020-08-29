<?php 
	global $product, $oft_quality_data;

	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( $oft_quality_data and array_key_exists( 'food', $oft_quality_data ) and array_key_exists( '_ingredients', $oft_quality_data['food'] ) ) {
		
		$ingredients = $oft_quality_data['food']['_ingredients'];
		
		if ( strlen( trim( $ingredients ) ) > 0 ) {
			?>
			<h4>Ingrediënten</h4>
			<div class="ingredients">
				<ul>
					<li>
						<?php
							// Splits de commentaren op het einde af (o.a. cacaobestanddelen)
							// Eventueel ook splitsen op ' // ' voor de producten die uit meerdere componenten bestaan (bv. noussines)?
							$parts = explode( ' - ', $ingredients );
							// Verhinder het splitsen van subingrediëntenlijsten tussen haakjes!
							echo implode( '</li><li>', preg_split( "/, (?![^()]*\))/", $parts[0], -1, PREG_SPLIT_NO_EMPTY ) );
							if ( count( $parts ) > 1 ) {
								// Plak de commentaren op het einde er weer aan
								unset( $parts[0] );
								foreach ( $parts as $comment ) {
									echo '</li><li>' . implode( '</li><li>', preg_split( "/, (?![^()]*\))/", $comment, -1, PREG_SPLIT_NO_EMPTY ) );
								}
							}
						?>
					</li>
				</ul>
				<?php if ( get_ingredients_legend( $ingredients ) ) : ?>
					<small class="legend">
						<?php implode( '<br/>', get_ingredients_legend( $ingredients ) ); ?>
					</small>
				<?php endif; ?>
			</div>
			<?php
		}	
	}

	function get_ingredients_legend( $ingredients ) {
		$legend = array();
		if ( ! empty( $ingredients ) ) {
			if ( strpos( $ingredients, '*' ) !== false ) {
				$legend[] = '* ingrediënt uit een eerlijke handelsrelatie';
			}
			if ( strpos( $ingredients, '°' ) !== false ) {
				$legend[] = '° ingrediënt van biologische landbouw';
			}
			if ( strpos( $ingredients, '†' ) !== false ) {
				$legend[] = '† ingrediënt verkregen in de periode van omschakeling naar biologische landbouw';
			}
		}
		return $legend;
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
