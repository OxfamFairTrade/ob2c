<div class="wrap">
	<h1>Voorraadbeheer van lokale producten</h1>

	<p>Vink een product aan om het op de homepage te plaatsen of selecteer de juiste voorraadstatus om het in of uit de online verkoop te halen. Je aanpassing wordt onmiddellijk opgeslagen! Een bevestigingsvenster behoedt je voor onbedoelde wijzigingen.</p>

	<p>Nieuwe producten, die in de loop van de twee voorbije maanden beschikbaar werden op <a href="http://www.bestelweb.be" target="_blank">bestelweb.be</a>, hebben een blauwe achtergrond. Ze verschijnen aanvankelijk als 'niet op voorraad' in jullie lokale webshop, zodat je alle tijd hebt om te beslissen of je het product zal inkopen en online wil aanbieden.</p>

	<p>Oude producten die niet langer bestelbaar zijn via <a href="http://www.bestelweb.be" target="_blank">bestelweb.be</a> krijgen een gele achtergrond, zodat het duidelijk is dat dit product op zijn laatste benen loopt. Het product wordt pas na 6 maanden definitief uit de catalogus verwijderd, zodat we er vrij zeker kunnen van zijn dat er geen lokale voorraden meer bestaan.</p>

	<div id="oxfam-products">
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
						
						echo '<div class="block">';
							// Linkerdeel
							echo '<div id="'.get_the_ID().'" class="pane-left';
							if ( get_the_date('U') > strtotime('-2 months') ) echo ' new';
							// Leeggoed is ook verborgen (en daardoor 'oud') maar reeds hogerop uit deze lijst gefilterd
							// CHECK VISIBILITY VAN MOEDER PRODUCT, NIET VAN DEZE!
							if ( $product->get_catalog_visibility() !== 'visible' ) echo ' old';
							echo '">';
								echo '<p class="title">'.$product->get_sku().': '.$product->get_title().'</p>';
								echo '<p>';
									echo '<input type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'>';
									echo '<label for="'.get_the_ID().'-featured" style="margin-left: 5px;">In de kijker?</label>';
								echo '</p>';
								echo '<p><select id="'.get_the_ID().'-stockstatus">';
									echo '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option>';
									echo '<option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Niet op voorraad</option>';
								echo '</select></p>';
								echo '<p class="output">&nbsp;</p>';
							echo '</div>';
							
							// Rechterdeel
							echo '<div class="pane-right" style="border-color: '.$color.'">';
								// Verhinder dat de (grote) placeholder uitgespuwd wordt indien een product per ongeluk geen foto heeft
								echo '<a href="'.get_permalink().'" target="_blank">'.$product->get_image( 'thumbnail', $attr, false ).'</a>';
							echo '</div>';
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
							var name = jQuery(this).parents('div').first().children(".title").first().html();
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
								'action': 'oxfam_stock_action',
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