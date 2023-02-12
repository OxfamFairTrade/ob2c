<?php 
	global $product;
	$args = wp_parse_args( $args, array( 'title_tag' => 'h4', 'oft_quality_data' => false ) );

	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( is_array( $args['oft_quality_data'] ) and array_key_exists( 'food', $args['oft_quality_data'] ) and array_key_exists( '_ingredients', $args['oft_quality_data']['food'] ) ) {
		
		$ingredients = $args['oft_quality_data']['food']['_ingredients'];
		
		if ( strlen( trim( $ingredients ) ) > 0 ) {
			?>
			<div id="product-ingredients" class="product-info-panel ingredients">
				<<?= $args['title_tag']; ?>><?= __( 'Ingrediënten', 'oxfam-webshop' ); ?></<?= $args['title_tag']; ?>>
				<ul class="ingredients">
					<li>
						<?php
							// Splits de commentaren op het einde af (o.a. cacaobestanddelen)
							// Eventueel ook splitsen op ' // ' voor de producten die uit meerdere componenten bestaan (bv. noussines)?
							$parts = explode( ' - ', trim( $ingredients ) );
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
						<?= implode( '<br/>', get_ingredients_legend( $ingredients ) ); ?>
					</small>
				<?php endif; ?>
			</div>
			<?php
		}
	}