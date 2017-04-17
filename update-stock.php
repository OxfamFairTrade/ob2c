<div class="wrap">
	<h1>Voorraadbeheer van lokale producten</h1>

	<div style="display: table; border-collapse: separate; border-spacing: 0px 25px;" id="oxfam-products">
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
						echo '<div id="'.get_the_ID().'" style="box-sizing: border-box; text-align: center; padding: 0px 25px; width: 35%; height: 160px; display: table-cell;"><p class="title" style="font-weight: bold;">'.$product->get_sku().': '.$product->get_title().'</p>';
						echo '<input type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'> In de kijker?<br>';
						echo '<select id="'.get_the_ID().'-stockstatus" style="margin-top: 10px;">';
						echo '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option><option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Uit voorraad</option>';
						echo '</select>';
						echo '<p class="output" style="color: orange;">&nbsp;</p></div>';
						// Nieuwe functie output ook al <img>-tag
						echo '<div style="width: 15%; vertical-align: middle; display: table-cell; box-sizing: border-box; text-align: center; border-left: 5px solid '.$color.'">'.$product->get_image( 'thumbnail' ).'</div>';
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
						jQuery("#oxfam-products").find("input[type=checkbox]").on( 'change', function() {
							var name = jQuery(this).parent().children(".title").first().html();
							var field = jQuery(this).attr('id');
							var parts = field.split("-");
							var id = parts[0];
							var meta = parts[1];
							var go = confirm("Ben je zeker dat je de uitlichting wil bijwerken van "+name+"?");
							if ( go == true ) {
								if ( jQuery(this).is(":checked") ) {
									ajaxCall(id, meta, 'yes');
								} else {
									ajaxCall(id, meta, 'no');
								}
							} else {
								alert("Je hebt geannuleerd, er wordt niets gewijzigd!");
								jQuery(this).prop("checked", !jQuery(this).is(":checked") );
							}
						});

						jQuery("#oxfam-products").find("select").on( 'change', function() {
							var name = jQuery(this).parent().children(".title").first().html();
							var field = jQuery(this).attr('id');
							var parts = field.split("-");
							var id = parts[0];
							var meta = parts[1];
							var go = confirm("Ben je zeker dat je de voorraadstatus wil bijwerken van "+name+"?");
							if ( go == true ) {
								ajaxCall( id, meta, jQuery(this).find(":selected").val() );
								/* UPDATE RANDKLEUR VAN FOTO */
								var color = '#61a534';
								if ( jQuery(this).find(":selected").val() == 'outofstock' ) {
									color = '#e70052';
								}
								jQuery("#"+id).next('div').css('border-left-color', color);
							} else {
								alert("Je hebt geannuleerd, er wordt niets gewijzigd!");
								var fallback = 'outofstock';
								if ( jQuery(this).find(":selected").val() == 'outofstock' ) {
									fallback = 'instock';
								}
								jQuery(this).val(fallback);
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
							    	jQuery("#"+id).find(".output").html("Wijzigingen opgeslagen!").delay(5000).animate({
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
										jQuery("#"+id).find(".output").html("Wijzigingen mislukt!").delay(10000).animate({
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