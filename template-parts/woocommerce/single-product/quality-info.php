<?php 
	global $product, $food_api_labels, $oft_quality_data;

	$food_required_keys = array( '_fat', '_fasat', '_choavl', '_sugar', '_pro', '_salteq' );
	$food_secondary_keys = array( '_fasat', '_famscis', '_fapucis', '_sugar', '_polyl', '_starch' );
	$args = wp_parse_args( $args, array( 'title_tag' => 'h4' ) );

	// Check of het überhaupt zin heeft om eraan te beginnen
	if ( $oft_quality_data and array_key_exists( 'food', $oft_quality_data ) and floatval( $oft_quality_data['food']['_energy'] ) > 0 ) {
		
		$food = array();
		foreach ( $food_api_labels as $food_key => $food_label ) {
			
			// Vermijd dat de ingrediënten ook nog eens in de tabel opduiken
			if ( $food_key === '_ingredients' ) {
				continue;
			}

			// Toon voedingswaarde als het een verplicht veld is, en in 2de instantie als er expliciet een (nul)waarde ingesteld is
			if ( in_array( $food_key, $food_required_keys ) or array_key_exists( $food_key, $oft_quality_data['food'] ) ) {

				$food_value = '';
				if ( array_key_exists( $food_key, $oft_quality_data['food'] ) ) {
					$food_value = $oft_quality_data['food'][ $food_key ];
				}

				if ( floatval( $food_value ) > 0 ) {
					// Formatteer het getal als Belgische tekst
					$food_value = str_replace( '.', ',', $food_value );
				} elseif ( in_array( $food_key, $food_required_keys ) ) {
					// Zet een nul (zonder expliciete precisie)
					$food_value = '0';
				} else {
					// Rij niet tonen, skip naar volgende key
					continue;
				}

				if ( $food_key === '_energy' ) {
					$food_value .= ' kJ';
				} elseif ( $food_key !== '_ingredients' ) {
					$food_value .= ' g';
				}

				$food[] = array(
					array( 'c' => $food_api_labels[$food_key] ),
					array( 'c' => $food_value ),
				);
			}
		}

		$table = array( 'body' => $food );

		// Maak onderscheid tussen vaste en vloeibare producten
		if ( $product->get_meta('_stat_uom') === 'L' ) {
			$unity = 'ml';
		} else {
			$unity = 'g';
		}
		
		?>
		<div id="product-quality-info" class="product-info-panel quality-info">
			<<?php echo $args['title_tag']; ?>>Voedingswaarde per 100 <?php echo $unity; ?></<?php echo $args['title_tag']; ?>>
			<table class="quality-info">
				<tbody>
					<?php foreach ( $table['body'] as $tr ) : ?>
						<tr>
							<?php foreach ( $tr as $td ) : ?>
								<td><span><?php echo $td['c']; ?></span></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
