<?php 
	global $product, $partners, $featured_partner;
	
	// Nieuwe globals, te vervangen door template parameters (WP 5.5+)
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
						// Toon de herkomstinfo en voedingsinfo hier pas (om het gat op te vullen)
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
				?>
			</div>

			<div class="col-md-4 col-md-pull-8 extra-info">
				<h3>Extra informatie</h3>
				<?php get_template_part( 'template-parts/woocommerce/product-icons' ); ?>

				<div id="product-brand" class="product-info-panel brand">
					<h4>Merk</h4>
					<p><?php echo $product->get_attribute('merk'); ?></p>
				</div>

				<div id="product-sku" class="product-info-panel sku">
					<h4>Artikelnummer</h4>
					<p><?php echo $product->get_attribute('shopplus'); ?></p>
				</div>

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