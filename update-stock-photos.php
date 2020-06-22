<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Stel de voorraad van je lokale webshop in</h1>

	<p>Vink een product aan om het op de homepage te plaatsen of selecteer de juiste voorraadstatus om het in of uit de online verkoop te halen. Je aanpassing wordt onmiddellijk opgeslagen! Een bevestigingsvenster behoedt je voor onbedoelde wijzigingen. Tip: met Ctrl+F kun je snel zoeken naar een product.</p>

	<p>Nieuwe producten, die in de loop van de voorbije 3 maanden beschikbaar werden op BestelWeb, hebben <span style="background-color: lightskyblue;">een blauwe achtergrond</span>. Ze verschijnen aanvankelijk als 'uitverkocht' in jullie lokale webshop, zodat je alle tijd hebt om te beslissen of je het product zal inkopen en online wil aanbieden. Producten die momenteel onbeschikbaar zijn op BestelWeb krijgen <span style="background-color: gold;">een gele achtergrond</span>, zodat het duidelijk is dat dit product misschien op zijn laatste benen loopt.</p>

	<p>Oude producten die definitief niet meer te bestellen zijn bij Oxfam Fair Trade worden pas na enkele maanden uit de moederdatabank verwijderd (en dus uit jullie webshop), zodat we er zeker kunnen van zijn dat er geen lokale voorraden meer bestaan. Dit zal ook aangekondigd worden op het dashboard.</p>

	<div id="oxfam-products" style="border-spacing: 0 10px;">
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
			$content ="";

			if ( $products->have_posts() ) {
				$i = 0;
				$instock_cnt = 0;
				$featured_cnt = 0;
				while ( $products->have_posts() ) {
					$products->the_post();
					$product = wc_get_product( get_the_ID() );
					// Verhinder dat leeggoed ook opduikt
					if ( is_numeric( $product->get_sku() ) ) {
						if ( $i % 2 === 0 ) $content .= '<div style="display: table-row;">';
						if ( $product->is_in_stock() ) {
							$class = 'border-color-green';
							$instock_cnt++;
						} else {
							$class = 'border-color-red';
						}
						if ( $product->is_featured() ) {
							$featured_cnt++;
						}
						$content .= '<div class="block">';
							// Linkerdeel
							$content .= '<div id="'.get_the_ID().'" class="pane-left';
							if ( get_the_date('U') > strtotime('-3 months') ) $content .= ' new';
							
							// Check voorraadstatus van moederproduct, voeg klasse toe indien niet langer op stock
							$main_product_id = get_post_meta( get_the_ID(), '_woonet_network_is_child_product_id', true );
							switch_to_blog(1);
							// $main_product = wc_get_product( $main_product_id );
							$bestelweb = get_post_meta( $main_product_id, '_in_bestelweb', true );
							if ( $bestelweb === 'nee' ) $content .= ' old';
							restore_current_blog();
							
							$content .= '">';
								$content .= '<p class="title">'.$product->get_sku().': '.$product->get_title();
								if ( has_term( 'Grootverbruik', 'product_cat', get_the_ID() ) ) {
									$content .= '<br/><small>ENKEL ZICHTBAAR VOOR B2B-KLANTEN</small>';
								}
								$content .= '</p>';
								$content .= '<p>';
									$content .= '<input type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'>';
									$content .= '<label for="'.get_the_ID().'-featured" style="margin-left: 5px;">In de kijker?</label>';
								$content .= '</p>';
								$content .= '<p><select id="'.get_the_ID().'-stockstatus">';
									$content .= '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option>';
									$content .= '<option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Uitverkocht</option>';
								$content .= '</select></p>';
								$content .= '<p class="output">&nbsp;</p>';
								if ( $bestelweb === 'ja' and intval( $product->get_meta('oft_product_id') ) > 0 ) {
									$content .= '<p>Bestel dit product op <a href="https://www.oxfamfairtrade.be/?p='.$product->get_meta('oft_product_id').'" target="_blank">BestelWeb</a></p>';
								}
							$content .= '</div>';
							
							// Rechterdeel
							$content .= '<div class="pane-right '.$class.'">';
								// Verhinder dat de (grote) placeholder uitgespuwd wordt indien een product per ongeluk geen foto heeft
								$content .= '<a href="'.get_permalink().'" target="_blank">'.$product->get_image( 'thumbnail', null, false ).'</a>';
							$content .= '</div>';
						$content .= '</div>';

						if ( $i % 2 === 1 ) $content .= '</div>';
						$i++;
					}
				}
				wp_reset_postdata();
				// Extra afsluitende </div> nodig indien oneven aantal producten!
				if ( $i % 2 === 1 ) $content .= '</div>';
				echo '<div style="display: table-row; width: 100%;">';
					echo '<p style="text-align: right; width: 100%;">Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> voorradig</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
				echo '</div>';
				
				echo $content;
				
				echo '<div style="display: table-row; width: 100%;">';
					echo '<p style="text-align: right; width: 100%;">Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> voorradig</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
				echo '</div>';
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
									var value = jQuery(this).find(":selected").val();
									ajaxCall( id, meta, value );
								}
							} else {
								if ( meta == 'featured' ) {
									jQuery(this).prop("checked", !jQuery(this).is(":checked") );
								}
								if ( meta == 'stockstatus' ) {
									if ( value == 'outofstock' ) {
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
									
									if ( value == 'outofstock' ) {
										jQuery("#"+id).next('div').removeClass('border-color-green').addClass('border-color-red');
									} else if ( value == 'instock' ) {
										jQuery("#"+id).next('div').removeClass('border-color-red').addClass('border-color-green');
									}
									jQuery(".instock-cnt").html(jQuery("#oxfam-products").find(".border-color-green").length);
									jQuery(".featured-cnt").html(jQuery("#oxfam-products").find("input[type=checkbox]:checked").length);
							    	
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