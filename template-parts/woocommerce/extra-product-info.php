<?php 
	global $product, $post;
	
	// Ga op zoek naar een A/B-partner om uit te lichten
	// $partners = get_field('partners'); FUNCTIE BESTAAT NIET IN OB2C
	$partners = false;
	$featured_partner = false;
	if ( $partners ) {
		foreach ( $partners as $partner ) {
			$categories = get_the_terms( $partner->ID, 'partner_cat' );
			if ( $categories ) {
				// Zoek de eerste de beste A-partner (randomisatie + voorrang aan ontwikkelingspartners nog toe te voegen)
				foreach ( $categories as $category ) {
					if ( $category->slug === 'a' and 'publish' === get_post_status( $partner->ID ) ) {
						$featured_partner = $partner;
						break;
					}
				}
				if ( $featured_partner === false ) {
					// Als we nog geen resultaat hebben: probeer het opnieuw met de B-partners
					foreach ( $categories as $category ) {
						if ( $category->slug === 'b' and 'publish' === get_post_status( $partner->ID ) ) {
							$featured_partner = $partner;
							break;
						}
					}
				}
			}
		}
	}

	function output_provenance_info( $partners ) {
		global $product, $post;
		echo '<div class="product-provenance">';

		if ( $partners !== false ) {

			echo '<h3>' . _n( 'Producent', 'Producenten', count($partners) ) . '</h3>';
			echo '<p class="partners">';
			foreach ( $partners as $post ) {
				// Variabele MOET $post heten! 
				setup_postdata($post);
				$countries = get_the_term_list( $post->ID, 'partner_country' );
				// Alle gepubliceerde partners mogen gelinkt worden!
				// Eventueel check toevoegen op waarde van '_link_to' (= externe site in nieuw tabblad openen)
				if ( 'publish' === get_post_status( $post->ID ) ) {
					echo '<a href="'.get_the_permalink().'">'.get_the_title().'</a>';
				} else {
					echo get_the_title();
				}
				echo ' ('.strip_tags($countries).')<br/>';
			}
			wp_reset_postdata();
			echo '</p>';

		} elseif ( $product->get_attribute('herkomst') !== '' ) {

			echo '<h3>Herkomst</h3>';
			echo '<ul>';
			// Indien een product geen partners heeft, tonen we gewoon de landen
			$countries = explode( ', ', $product->get_attribute('herkomst') );
			foreach( $countries as $country ) {
				echo '<li>'.$country.'</li>';
			}
			echo '</ul>';

		}

		echo '</div>';
	}

	// Definitie van labels en verplichte voedingswaarden
	$food_api_labels = array(
		'_ingredients' => 'Ingrediënten',
		'_energy' => 'Energie',
		'_fat' => 'Vetten',
		'_fasat' => 'waarvan verzadigde vetzuren',
		'_famscis' => 'waarvan enkelvoudig onverzadigde vetzuren',
		'_fapucis' => 'waarvan meervoudig onverzadigde vetzuren',
		'_choavl' => 'Koolhydraten',
		'_sugar' => 'waarvan suikers',
		'_polyl' => 'waarvan polyolen',
		'_starch' => 'waarvan zetmeel',
		'_fibtg' => 'Vezels',
		'_pro' => 'Eiwitten',
		'_salteq' => 'Zout',
	);
	$food_required_keys = array( '_fat', '_fasat', '_choavl', '_sugar', '_pro', '_salteq' );
	$food_secondary_keys = array( '_fasat', '_famscis', '_fapucis', '_sugar', '_polyl', '_starch' );

	// Functie om de passende legende op te halen bij een ingrediëntenlijst, te verhuizen naar functions.php
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

	// Check of het product nog niet gecached werd
	if ( false === ( $oft_quality_data = get_site_transient( $product->get_sku().'_quality_data' ) ) ) {
		// Haal de kwaliteitsdata op indien het een voedingsproduct is (= OFT product-ID beschikbaar) en géén geschenkencheque
		if ( $product->meta_exists('oft_product_id') and intval( $product->get_meta('oft_product_id') ) > 0 and ! in_array( 1207, $product->get_category_ids() ) ) {
			$base = 'https://www.oxfamfairtrade.be/wp-json/wc/v2';
			// Read-only API keys
			$response = wp_remote_get( $base.'/products/'.$product->get_meta('oft_product_id').'?consumer_key='.OFT_WC_KEY.'&consumer_secret='.OFT_WC_SECRET );
			$oft_product = json_decode( wp_remote_retrieve_body($response) );

			if ( $oft_product !== false ) { 
				// Stop voedingswaarden én ingrediënten in een array met als keys de namen van de eigenschappen
				foreach ( $oft_product->meta_data as $meta_data ) {
					if ( array_key_exists( $meta_data->key, $food_api_labels ) ) {
						$oft_quality_data['food'][$meta_data->key] = $meta_data->value;
					}
				}

				// Stop allergenen in een array met als keys de slugs van de allergenen
				foreach ( $oft_product->product_allergen as $product_allergen ) {
					$oft_quality_data['allergen'][$product_allergen->slug] = $product_allergen->name;
				}

				// Plaats response 24 uur lang in cache
				set_site_transient( $product->get_sku().'_quality_data', $oft_quality_data, DAY_IN_SECONDS );
			}
		}
	}

	function output_food_values( $oft_quality_data, $food_api_labels, $food_required_keys, $title_tag = 'h4' ) {
		if ( $oft_quality_data and array_key_exists( 'food', $oft_quality_data ) and floatval( $oft_quality_data['food']['_energy'] ) > 0 ) {
			if ( array_key_exists( '_ingredients', $oft_quality_data['food'] ) ) {
				// Vermijd dat de ingrediënten ook nog eens in de tabel opduiken
				unset( $oft_quality_data['food']['_ingredients'] );
			}

			$food = array();
			foreach ( $food_api_labels as $food_key => $food_label ) {
				// Toon voedingswaarde als het een verplicht veld is, en in 2de instantie als er expliciet een (nul)waarde ingesteld is
				if ( in_array( $food_key, $food_required_keys ) or array_key_exists( $food_key, $oft_quality_data['food'] ) ) {
					$food_value = '';
					if ( array_key_exists( $food_key, $oft_quality_data['food'] ) ) {
						$food_value = $oft_quality_data['food'][$food_key];
					}

					if ( floatval($food_value) > 0 ) {
						// Formatter het getal als Belgische tekst
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

			echo '<'.$title_tag.'>Voedingswaarde per 100 g</'.$title_tag.'>';
			$table = array( 'body' => $food );

			?>
			<table class="quality-data">
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
			<?php
		}
	}
?>

<div class="full-width-container">
	<div class="container product-details-block">
		<div class="col-row">
			<div class="col-md-8 col-md-push-4 partner-info">
				<?php
					if ( $featured_partner ) {

						// Variabele MOET $post heten! 
						$post = $featured_partner;
						setup_postdata($post);
						?>
						<h3>Producent in de kijker: <span style="font-weight: normal;"><?php the_title(); ?></span></h3>
						<div class="featured-partner">
							<div class="col-row">
								<div class="col-md-7">
									<?php the_post_thumbnail('post-thumbnail'); ?>
									<?php the_field('short_text'); ?>
									<p><a href="<?php the_permalink(); ?>">Maak kennis met <?php the_title(); ?></a></p>
								</div>
								<div class="col-md-5">
									<?php if ( get_field('quote_text') ) : ?>
										<blockquote>
											&#8220;<?php the_field('quote_text'); ?>&#8221;
											<?php if ( get_field('quote_name') ) : ?>
												<footer><?php the_field('quote_name'); ?></footer>
											<?php endif; ?>
										</blockquote>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<?php
						wp_reset_postdata();

					} else {

						// Toon de herkomstinfo en voedingsinfo hier pas (om het gat op te vullen)
						echo '<div class="col-row"><div class="col-md-6">';
						output_provenance_info( $partners );
						echo '</div><div class="col-md-6">';
						output_food_values( $oft_quality_data, $food_api_labels, $food_required_keys, 'h3' );
						echo '</div></div>';

					}
				?>
			</div>

			<div class="col-md-4 col-md-pull-8 extra-info">
				<h3>Extra informatie</h3>
				<?php if ( $product->get_attribute('voedingsvoorkeuren') !== '' ) : ?>
					<div class="icons">
						<?php
							$icons = explode( ', ', $product->get_attribute('voedingsvoorkeuren') );
							foreach ( $icons as $icon_name ) {
								echo '<a href="'.get_post_type_archive_link('product').'?swoof=1&pa_voedingsvoorkeuren='.sanitize_title($icon_name).'#result">';
									echo '<div class="icon" style="background-image: url('.get_stylesheet_directory_uri().'/images/voedingsvoorkeuren/'.sanitize_title($icon_name).'.svg);" alt="'.$icon_name.'" title="Bekijk alles '.strtolower($icon_name).'"></div>';
								echo '</a>';
							}
						?>
					</div>
				<?php endif; ?>

				<h4>Merk</h4>
				<p class="brand"><?php echo $product->get_attribute('merk'); ?></p>

				<h4>Artikelnummer</h4>
				<p class="sku"><?php $product->get_attribute('shopplus'); ?></p>

				<?php
					// Check of de data inmiddels wel beschikbaar is
					if ( $oft_quality_data ) {
						if ( array_key_exists( 'food', $oft_quality_data ) and array_key_exists( '_ingredients', $oft_quality_data['food'] ) ) {
							$ingredients = $oft_quality_data['food']['_ingredients'];
							if ( strlen( trim($ingredients) ) > 0 ) {
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
								if ( get_ingredients_legend($ingredients) ) {
									echo '<small class="legend">'.implode( '<br/>', get_ingredients_legend($ingredients) ).'</small>';
								}
								echo '</div>';
							}
						}

						$contains = array();
						$traces = array();
						$no_allergens = false;
						if ( array_key_exists( 'allergen', $oft_quality_data ) ) {
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
						}

						echo '<h4>Allergeneninfo</h4>';
						echo '<p class="allergens">';
						if ( $no_allergens === true or ( count($traces) === 0 and count($contains) === 0 ) ) {
							echo 'Dit product bevat geen meldingsplichtige allergenen.';
						} else {
							if ( count($contains) > 0 ) {
								echo 'Bevat '.implode( ', ', $contains ).'.';
								if ( count($traces) > 0 ) {
									echo '<br/>';
								}
							}
							if ( count($traces) > 0 ) {
								echo 'Kan sporen bevatten van '.implode( ', ', $traces ).'.';
							}
						}
						echo '</p>';

						if ( $featured_partner ) {
							output_food_values( $oft_quality_data, $food_api_labels, $food_required_keys );
						}
					}
				?>
			</div>
		</div>
	</div>
</div>