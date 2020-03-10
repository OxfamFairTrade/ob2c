<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Stel de voorraad van je lokale webshop in</h1>

	<p>Vink een product aan om het op de homepage te plaatsen of selecteer de juiste voorraadstatus om het in of uit de online verkoop te halen. Je aanpassing wordt onmiddellijk opgeslagen! Met de knop onderaan de pagina kun je alle producten in één keer in/uit voorraad halen. Een bevestigingsvenster behoedt je daarbij voor onbedoelde wijzigingen. <b>Tip: met Ctrl+F kun je snel zoeken naar een product.</b></p>

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
			
			if ( $products->have_posts() ) {
				$i = 0;
				$instock_cnt = 0;
				$featured_cnt = 0;
				$content = '<div style="display: table; width: 100%;">';
				while ( $products->have_posts() ) {
					$products->the_post();
					$product = wc_get_product( get_the_ID() );
					
					// Verhinder dat leeggoed ook opduikt
					if ( is_numeric( $product->get_sku() ) ) {
						// Kleur de randen en tel de initiële waarde voor de tellers
						if ( $product->is_on_backorder() ) {
							$class = 'border color-orange';
						} elseif ( $product->is_in_stock() ) {
							$class = 'border color-green';
							$instock_cnt++;
						} else {
							$class = 'border color-red';
						}
						if ( $product->is_featured() ) {
							$featured_cnt++;
						}

						$content .= '<div id="'.get_the_ID().'" class="compact';

						// Voeg klasse toe indien recent gepubliceerd
						if ( get_the_date('U') > strtotime('-3 months') ) $content .= ' new';
						
						// Check voorraadstatus van moederproduct, voeg klasse toe indien niet langer op stock
						// VERTRAAGT DE BOEL NOGAL
						$main_product_id = get_post_meta( get_the_ID(), '_woonet_network_is_child_product_id', true );
						switch_to_blog(1);
						// $main_product = wc_get_product( $main_product_id );
						$bestelweb = get_post_meta( $main_product_id, '_in_bestelweb', true );
						if ( $bestelweb === 'nee' ) $content .= ' old';
						restore_current_blog();

						$content .= '">';
							$content .= '<div class="cell" style="padding: 0.25em; width: 3%; text-align: center;"><a href="'.get_permalink().'" target="_blank">'.$product->get_image( 'wc_order_status_icon', null, false ).'</a></div>';
							$content .= '<div class="cell '.$class.'" style="width: 40%; text-align: left;"><span class="title">'.$product->get_sku().': '.$product->get_title().' ('.$product->get_attribute('pa_shopplus').')</span></div>';
							$content .= '<div class="cell"><select class="toggle" id="'.get_the_ID().'-stockstatus">';
								$content .= '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option>';
								// Nieuwe voorraadstatus!
								$content .= '<option value="onbackorder" '.selected( $product->is_on_backorder(), true, false ).'>Tijdelijk uit voorraad</option>';
								$content .= '<option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Uitverkocht</option>';
							$content .= '</select></div>';
							$content .= '<div class="cell"><input class="toggle" type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'>';
							$content .= ' <label for="'.get_the_ID().'-featured">In de kijker?</label></div>';
						$content .= '<div class="cell output"></div>';
						$content .= '</div>';
						$i++;
					}
				}
				$content .= '</div>';
				wp_reset_postdata();

				echo '<p style="text-align: right; width: 100%;"><br>Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> voorradig</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
				
				echo $content;
				
				echo '<p style="text-align: right; width: 100%;">Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> voorradig</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
				
			}

			add_action('admin_footer', 'oxfam_action_javascript');

			function oxfam_action_javascript() { ?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						// Check wijzigingen op selects (= voorraad) en checkboxes (= in de kijker)
						jQuery("#oxfam-products").find(".toggle").on( 'change', function() {
							var parts = jQuery(this).attr('id').split("-");
							var id = parts[0];
							var meta = parts[1];
							if ( meta == 'featured' ) {
								ajaxCall(id, meta, jQuery(this).is(":checked"));
							}
							if ( meta == 'stockstatus' ) {
								var value = jQuery(this).find(":selected").val();
								ajaxCall( id, meta, value );
							}
						});

						// Reset teller
						var tries = 0;

						// Verwerk de individuele AJAX-call
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
									
									// Pas de gekleurde rand aan na een succesvolle voorraadwijziging
									if ( value == 'onbackorder' ) {
										jQuery("#"+id).find('.border').removeClass().addClass('border color-orange');
									} else if ( value == 'outofstock' ) {
										jQuery("#"+id).find('.border').removeClass().addClass('border color-red');
									} else if ( value == 'instock' ) {
										jQuery("#"+id).find('.border').removeClass().addClass('border color-green');
									}

									// Werk de tellers bij
									jQuery(".instock-cnt").html(jQuery("#oxfam-products").find(".border.color-green").length);
									jQuery(".featured-cnt").html(jQuery("#oxfam-products").find("input[type=checkbox]:checked").length);
							    	
							    	jQuery("#"+id).find(".output").html("Wijzigingen opgeslagen!").delay(5000).animate({
							    		opacity: 0,
							    	}, 1000, function(){
										jQuery(this).html("&nbsp;").css('opacity', 1);
									});
								},
								error: function(jqXHR, statusText, errorThrown) {
									tries++;
									if ( tries < 10 ) {
										ajaxCall(id, meta, value);
									} else {
										// Val terug op de tegengestelde waarde
										if ( value == 'outofstock' ) {
											jQuery(this).val('instock');
										} else if ( value == 'instock' ) {
											jQuery(this).val('outofstock');
										} else {
											jQuery(this).prop("checked", !jQuery(this).is(":checked") );
										}

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

						jQuery("#oxfam-products").find(".global-toggle").on( 'change', function() {
							if ( jQuery(this).find(":selected").val() == 'instock' ) {
								var to_change = jQuery("#oxfam-products").find(".border.color-red").length; 
								var go = confirm("Ben je zeker dat je "+to_change+" producten in voorraad wil zetten?");
								if ( go == true ) {
									jQuery(this).parent().parent().find(".output").html("Aan het verwerken ...");
									jQuery("#oxfam-products").find(".border.color-red").parent().find("select.toggle").val('instock').each( function() {
										jQuery(this).delay(25).trigger('change');	
									});
									// SUCCESBOODSCHAP TONEN NA AFLOOP
									jQuery(this).parent().parent().find(".output").delay(10000).animate({
							    		opacity: 0,
							    	}, 1000, function(){
										jQuery(this).html("&nbsp;").css('opacity', 1);
									});
								} else {
									alert("Begrepen, we wijzigen niets!");
									jQuery(this).val('');
								}
							} else if ( jQuery(this).find(":selected").val() == 'outofstock' ) {
								var to_change = jQuery("#oxfam-products").find(".border.color-green").length; 
								var go = confirm("Ben je zeker dat je "+to_change+" producten op uitverkocht wil zetten?");
								if ( go == true ) {
									jQuery(this).parent().parent().find(".output").html("Aan het verwerken ...");
									jQuery("#oxfam-products").find(".border.color-green").parent().find("select.toggle").val('outofstock').each( function() {
										jQuery(this).delay(25).trigger('change');	
									});
									// SUCCESBOODSCHAP TONEN NA AFLOOP
									jQuery(this).parent().parent().find(".output").delay(10000).animate({
							    		opacity: 0,
							    	}, 1000, function(){
										jQuery(this).html("&nbsp;").css('opacity', 1);
									});
								} else {
									alert("Begrepen, we wijzigen niets!");
									jQuery(this).val('');
								}
							}
						});
					});
				</script>
			<?php }
		?>
		<div style="display: table; width: 100%; border-top: 1px solid black; border-bottom: 1px solid black;">
			<div class="cell" style="width: 3%;"></div>
			<div class="cell" style="width: 40%; text-align: center;">
				<select class="global-toggle">';
					<option value="" selected>(bulkwijziging)</option>
					<option value="instock">Zet ALLE producten op voorraad</option>
					<option value="outofstock">Zet ALLE producten op uitverkocht</option>
				</select>
			</div>
			<div class="cell" style="width: 40%; text-align: left;">
				Opgelet: deze bewerking kan enkele tientallen seconden in beslag nemen! Verlaat deze pagina niet zolang de tellers lopen.
			</div>
			<div class="cell output" style="width: 17%;"></div>
		</div>
	</div>
</div>