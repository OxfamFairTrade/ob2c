<?php 
	global $product, $oft_quality_data;

	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( $oft_quality_data and array_key_exists( 'food', $oft_quality_data ) and array_key_exists( '_ingredients', $oft_quality_data['food'] ) ) {
		
		$ingredients = $oft_quality_data['food']['_ingredients'];
		
		if ( strlen( trim( $ingredients ) ) > 0 ) {
			echo '<h4>Ingrediënten</h4>';
			// Splits de commentaren op het einde af (o.a. cacaobestanddelen)
			// Eventueel ook splitsen op ' // ' voor de producten die uit meerdere componenten bestaan (bv. noussines)?
			$parts = explode( ' - ', $ingredients );
			echo '<div class="ingredients"><ul><li>';
			// Verhinder het splitsen van subingrediëntenlijsten tussen haakjes!
			echo implode( '</li><li>', preg_split( "/, (?![^()]*\))/", $parts[0], -1, PREG_SPLIT_NO_EMPTY ) );
			if ( count( $parts ) > 1 ) {
				// Plak de commentaren op het einde er weer aan
				unset( $parts[0] );
				foreach ( $parts as $comment ) {
					echo '</li><li>' . implode( '</li><li>', preg_split( "/, (?![^()]*\))/", $comment, -1, PREG_SPLIT_NO_EMPTY ) );
				}
			}
			echo '</li></ul>';
			if ( get_ingredients_legend( $ingredients ) ) {
				echo '<small class="legend">'.implode( '<br/>', get_ingredients_legend( $ingredients ) ).'</small>';
			}
			echo '</div>';
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
?>