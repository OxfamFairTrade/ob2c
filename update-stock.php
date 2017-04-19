<div class="wrap">
	<h1>Voorraadbeheer van lokale producten</h1>

	<p>Vink een product aan om het op de homepage te plaatsen of selecteer de juiste voorraadstatus om een product in of uit verkoop via jullie webshop te halen. Je aanpassing wordt onmiddellijk opgeslagen! Een bevestigingsvenster behoedt je voor onbedoelde wijzigingen.</p>

	<p>Nieuwe producten (= de afgelopen twee maanden beschikbaar geworden op <a href="http://www.bestelweb.be" target="_blank">bestelweb.be</a>) hebben een blauwe achtergrond. Ze verschijnen aanvankelijk als 'niet op voorraad' in jullie lokale webshop, zodat jullie zelf alle tijd hebben om te beslissen of je het product online wil aanbieden. Oude producten worden pas verwijderd van zodra we zeker zijn dat er geen lokale voorraden meer bestaan.</p>

	<div style="display: table; border-collapse: separate; border-spacing: 0px 25px; width: 100%;" id="oxfam-products">
		<?php
			// Query alle gepubliceerde producten en stel voorraadstatus + uitlichting in
			// Ordenen op artikelnummer, nieuwe producten van de afgelopen maand rood markeren?
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array( 'publish' ),
				'posts_per_page'	=> -1,
				'meta_key'			=> '_sku',
				'orderby'			=> 'meta_value_num',
				'order'				=> 'ASC',
			);

			$products = new WP_Query( $args );

			if ( $products->have_posts() ) {
				$i = 1;
				while ( $products->have_posts() ) {
					$products->the_post();
					$product = wc_get_product( get_the_ID() );
					// Verhinder dat leeggoed ook opduikt
					if ( is_numeric( $product->get_sku() ) ) {
						if ( $i % 2 === 1 ) echo '<div style="display: table-row;">';
						$color = $product->is_in_stock() ? '#61a534' : '#e70052'; 
						
						// Linkerdeel
						echo '<div id="'.get_the_ID().'" class="oxfam-products-admin-left"';
						if ( get_the_date('U') > strtotime('-1 months') ) echo ' style="background-color: blue;"';
						echo '>';
							echo '<p class="oxfam-products-title">'.$product->get_sku().': '.$product->get_title().'</p>';
							echo '<p>';
								echo '<input type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'>';
								echo '<label for="'.get_the_ID().'-featured" style="margin-left: 5px;">In de kijker?</label>';
							echo '</p>';
							echo '<select id="'.get_the_ID().'-stockstatus" style="margin-top: 10px;">';
								echo '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option>';
								echo '<option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Niet op voorraad</option>';
							echo '</select>';
							echo '<p class="output" style="color: orange;">&nbsp;</p>';
						echo '</div>';
						
						// Rechterdeel
						echo '<div class="oxfam-products-admin-right" style="border-left: 5px solid '.$color.'">';
							// Verhinder dat de (grote) placeholder uitgespuwd wordt indien een product per ongeluk geen foto heeft
							echo '<a href="'.get_permalink().'" target="_blank">'.$product->get_image( 'thumbnail', $attr, false ).'</a>';
						echo '</div>';
						
						if ( $i % 2 === 0 ) echo '</div>';
						$i++;
					}
				}
				wp_reset_postdata();
			}

			add_action('admin_footer', 'oxfam_action_javascript');

			function oxfam_action_javascript() { ?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery("#oxfam-products input[type=checkbox], #oxfam-products select").on( 'change', function() {
							var name = jQuery(this).parents('div').first().children(".oxfam-products-title").first().html();
							var parts = jQuery(this).attr('id').split("-");
							var id = parts[0];
							var meta = parts[1];
							var go = confirm("Ben je zeker dat je de "+meta+" wil bijwerken van "+name+"?");
							if ( go == true ) {
								if ( meta == 'featured' ) {
									ajaxCall(id, meta, jQuery(this).is(":checked"));
								}
								if ( meta == 'stockstatus' ) {
									ajaxCall( id, meta, jQuery(this).find(":selected").val() );
									if ( jQuery(this).find(":selected").val() == 'outofstock' ) {
										var color = '#e70052';
									} else {
										var color = '#61a534';
									}
									jQuery("#"+id).next('div').css('border-left-color', color);
								}
							} else {
								if ( meta == 'featured' ) {
									jQuery(this).prop("checked", !jQuery(this).is(":checked") );
								}
								if ( meta == 'stockstatus' ) {
									if ( jQuery(this).find(":selected").val() == 'outofstock' ) {
										var fallback = 'instock';
									} else {
										var fallback = 'outofstock';
									}
									jQuery(this).val(fallback);
								}
								alert("Geannuleerd, er wordt niets gewijzigd!");
							}
						});

						var tries = 0;

						function ajaxCall(id, meta, value) {
							jQuery("#"+id).find(".output").html("Aan het opslaan ...");

							var input = {
								'action': 'oxfam_product_action',
								'id': id,
								'meta': meta,
								'value': value,
							};
				    		
				    		jQuery.ajax({
				    			type: 'POST',
	  							url: ajaxurl,
				    			data: input,
				    			dataType: 'html',
				    			success: function(msg) {
							    	tries = 0;
							    	jQuery("#"+id).find(".output").html("Wijzigingen opgeslagen!").delay(3000).animate({
							    		opacity: 0,
							    	}, 1000, function(){
										jQuery(this).html("&nbsp;").css('opacity', 1);
									});
								},
								error: function(jqXHR, statusText, errorThrown) {
									tries++;
									if ( tries < 5 ) {
										ajaxCall(id, meta, value);
									} else {
										tries = 0;
										jQuery("#"+id).find(".output").html("Wijzigingen mislukt!").delay(15000).animate({
								    		opacity: 0,
								    	}, 1000, function(){
											jQuery(this).html("&nbsp;").css('opacity', 1);
										});
									}
								},
							});
						}
					});
				</script>
			<?php }
		?>
	</div>
</div>