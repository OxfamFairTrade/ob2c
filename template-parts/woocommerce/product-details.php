<?php 
	global $product, $partners, $featured_partner;
	
	// @toDo: Globals vervangen door template parameters (WP 5.5+)
	global $food_api_labels, $oft_quality_data;

	// Definitie van labels en verplichte voedingswaarden BEETJE AMBETANT DAT WE DIT HIER OOK AL NODIG HEBBEN ...
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

	// Check of het product nog niet gecached werd
	if ( false === ( $oft_quality_data = get_site_transient( $product->get_sku().'_quality_data' ) ) ) {
		// Haal de kwaliteitsdata op indien een OFT product-ID beschikbaar is (+ uitsluiten non-food?)
		if ( intval( $product->get_meta('oft_product_id') ) > 0 ) {
			// @toDo: Upgrade naar v3 van zodra OFT-site up to date?
			$base = 'https://www.oxfamfairtrade.be/wp-json/wc/v2';
			$response = wp_remote_get( $base.'/products/'.$product->get_meta('oft_product_id').'?consumer_key='.OFT_WC_KEY.'&consumer_secret='.OFT_WC_SECRET );
			
			if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
				$oft_product = json_decode( wp_remote_retrieve_body( $response ) );
				
				// Stop voedingswaarden én ingrediënten in een array met als keys de namen van de eigenschappen
				foreach ( $oft_product->meta_data as $meta_data ) {
					// Functie array_key_exists() werkt ook op objecten
					if ( array_key_exists( $meta_data->key, $food_api_labels ) ) {
						$oft_quality_data['food'][ $meta_data->key ] = $meta_data->value;
					}
				}

				// Stop allergenen in een array met als keys de slugs van de allergenen
				foreach ( $oft_product->product_allergen as $product_allergen ) {
					$oft_quality_data['allergen'][ $product_allergen->slug ] = $product_allergen->name;
				}

				set_site_transient( $product->get_sku().'_quality_data', $oft_quality_data, DAY_IN_SECONDS );
			}
		}
	}
?>

<div class="full-width-container">
	<div class="container product-details-block">
		<div class="col-row">
			<div class="col-md-8 col-md-push-4 partner-info">
				<?php
					if ( $featured_partner !== false ) {
						get_template_part( 'template-parts/woocommerce/single-product/featured-partner' );
					} else {
						if ( ! is_national_product( $product ) or strpos( $product->get_meta('_shopplus_code'), 'M' ) === 0 ) {
							// Toon de lange beschrijving bij lokale producten altijd (indien beschikbaar)
							?>
							<div class="col-row">
								<div class="col-md-12">
									<?php if ( strlen( $product->get_description() ) > 5 ) : ?>
										<h3>Beschrijving</h3>
										<div class="product-text-block woocommerce-product-details__long-description">
											<?php echo $product->get_description(); ?>
										</div>
									<?php endif; ?>
									<?php
										if ( $product->get_attribute('merk') !== '' ) {
											if ( false !== ( $term = get_term_by( 'name', $product->get_attribute('merk'), 'pa_merk' ) ) ) {
												if ( strlen( $term->description ) > 5 ) {
												?>
													<h3>Over <?php echo $product->get_attribute('merk'); ?></h3>
													<div class="product-text-block woocommerce-product-details__brand">
														<?php echo $term->description; ?>
													</div>
												<?php
												}
											}
										}
									?>
								</div>
							</div>
							<?php
						} else {
							// Toon de herkomstinfo en voedingsinfo hier pas (om het gat op te vullen)
							// Deze gegevens ontbreken sowieso bij lokale producten en crafts, dus mag weggelaten worden
							?>
							<div class="col-row">
								<div class="col-md-6">
									<?php
										get_template_part( 'template-parts/woocommerce/single-product/ingredients', NULL, array( 'title_tag' => 'h3' ) );
										get_template_part( 'template-parts/woocommerce/single-product/allergens' );
									?>
								</div>
								<div class="col-md-6">
									<?php
										get_template_part( 'template-parts/woocommerce/single-product/quality-info', NULL, array( 'title_tag' => 'h3' ) );
									?>
								</div>
							</div>
							<?php
						}
					}
				?>
			</div>

			<div class="col-md-4 col-md-pull-8 extra-info">
				<h3>Extra informatie</h3>
				<?php get_template_part( 'template-parts/woocommerce/product-icons' ); ?>

				<?php if ( $product->has_dimensions() and ( ! is_national_product( $product ) or strpos( $product->get_meta('_shopplus_code'), 'M' ) === 0 ) ) : ?>
					<div id="product-dimensions" class="product-info-panel dimensions">
						<h4>Afmetingen</h4>
						<p><?php echo wc_format_dimensions( $product->get_dimensions( false ) ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $product->get_attribute('merk') !== '' ) : ?>
					<div id="product-brand" class="product-info-panel brand">
						<h4>Merk</h4>
						<p><?php echo $product->get_attribute('merk'); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $product->get_meta('_shopplus_code') !== '' ) : ?>
					<div id="product-sku" class="product-info-panel sku">
						<h4>Artikelnummer</h4>
						<p><?php echo $product->get_meta('_shopplus_code'); ?></p>
					</div>
				<?php endif; ?>

				<?php
					if ( $featured_partner ) {
						get_template_part( 'template-parts/woocommerce/single-product/ingredients' );
						get_template_part( 'template-parts/woocommerce/single-product/allergens' );
						get_template_part( 'template-parts/woocommerce/single-product/quality-info' );
					}
				?>
			</div>
		</div>
	</div>
</div>