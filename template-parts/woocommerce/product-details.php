<?php 
	global $product, $featured_partner;
	
	// Haal de kwaliteitsdata op indien product-ID uit OFT-site beschikbaar is
	// Eventueel te vervangen door: is_national_product( $product ) and ! is_crafts_product( $product )
	if ( intval( $product->get_meta('oft_product_id') ) > 0 ) {
		$oft_quality_data = get_external_product( $product );
	} else {
		$oft_quality_data = false;
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
						if ( ! is_national_product( $product ) or is_crafts_product( $product ) ) {
							// Toon de lange beschrijving bij lokale producten en artisanaat altijd (indien beschikbaar)
							?>
							<div class="col-row">
								<div class="col-md-12">
									<?php if ( strlen( $product->get_description() ) > 5 ) : ?>
										<h3>Beschrijving</h3>
										<div class="product-text-block woocommerce-product-details__long-description">
											<?php echo do_shortcode( wpautop( $product->get_description() ) ); ?>
										</div>
									<?php endif; ?>
									<?php
										if ( $product->get_attribute('merk') !== '' ) {
											if ( false !== ( $term = get_term_by( 'name', $product->get_attribute('merk'), 'pa_merk' ) ) ) {
												if ( strlen( $term->description ) > 5 ) {
												?>
													<h3>Over <?php echo $product->get_attribute('merk'); ?></h3>
													<div class="product-text-block woocommerce-product-details__brand">
														<?php echo wpautop( $term->description ); ?>
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
										get_template_part( 'template-parts/woocommerce/single-product/ingredients', NULL, array(
											'title_tag' => 'h3',
											'oft_quality_data' => $oft_quality_data,
										) );
										get_template_part( 'template-parts/woocommerce/single-product/allergens', NULL, array(
											'oft_quality_data' => $oft_quality_data,
										) );
									?>
								</div>
								<div class="col-md-6">
									<?php
										get_template_part( 'template-parts/woocommerce/single-product/quality-info', NULL, array(
											'title_tag' => 'h3',
											'oft_quality_data' => $oft_quality_data,
										) );
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

				<?php if ( $product->has_dimensions() and ( ! is_national_product( $product ) or is_crafts_product( $product ) ) ) : ?>
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

				<?php if ( intval( $product->get_meta('_multiple') ) > 1 ) : ?>
					<div id="product-multiple" class="product-info-panel multiple">
						<h4>Omverpakking</h4>
						<p><?php echo $product->get_meta('_multiple').' stuks'; ?></p>
					</div>
				<?php endif; ?>

				<?php
					if ( $featured_partner ) {
						get_template_part( 'template-parts/woocommerce/single-product/ingredients', NULL, array(
							'oft_quality_data' => $oft_quality_data,
						) );
						get_template_part( 'template-parts/woocommerce/single-product/allergens', NULL, array(
							'oft_quality_data' => $oft_quality_data,
						) );
						get_template_part( 'template-parts/woocommerce/single-product/quality-info', NULL, array(
							'oft_quality_data' => $oft_quality_data,
						) );
					}
				?>
			</div>
		</div>
	</div>
</div>