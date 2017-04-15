<h3>Registratie van nieuwe craftsfoto's</h3>
<table>
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
					if ( $i % 2 === 1 ) echo '<tr>';
					$color = $product->is_in_stock() ? '#61a534' : '#e70052'; 
					echo '<th colspan="2" style="border-right: 5px solid '.$color.'">'.$product->get_sku().': '.$product->get_title().'<br><br>';
					echo '<select name="_stock_status">';
					echo '<option value="instock" '.selected( $product->is_in_stock(), true, false ).'>Op voorraad</option><option value="outofstock" '.selected( $product->is_in_stock(), false, false ).'>Uit voorraad</option>';
					echo '</select><br><br>';
					echo '<input type="checkbox" name="_featured" '.checked( $product->is_featured(), true, false ).'> Uitgelicht</th>';
					// Nieuwe functie output ook al <img>-tag
					echo '<td colspan="2" style="text-align: center;">'.$product->get_image( 'thumbnail' ).'</td>';
					if ( $i % 2 === 0 ) echo '<tr>';
					$i++;
				}
			}
			wp_reset_postdata();
		}

		add_action('admin_footer', 'oxfam_action_javascript');

		function oxfam_action_javascript() { ?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					var data = <?php echo json_encode(list_new_images()); ?>;

					if ( data !== null ) {
						dump(data);
						var s = "";
						if ( data.length !== 1 ) s = "'s";
						jQuery(".input").prepend("<pre>We vonden "+data.length+" nieuwe of gewijzigde foto"+s+"!</pre>");
						if ( data.length > 0 ) jQuery(".run").prop('disabled', false);
						
						jQuery(".run").on('click', function() {
							jQuery(".run").prop('disabled', true);
							jQuery(".run").text('Ik ben aan het nadenken ...');
							jQuery('#wpcontent').css('background-color', 'orange');
							jQuery(".output").before("<p>&nbsp;</p>");
							ajaxCall(0);
						});
					} else {
						jQuery(".input").prepend("<pre>We vonden geen enkele nieuwe of gewijzigde foto!</pre>");
					}

					jQuery(".input").prepend("<pre>Uploadtijdstip laatst verwerkte foto: <?php echo date('d/m/Y H:i:s', get_option('laatste_registratie_timestamp', '946681200')); ?></pre>");

					var tries = 0;

					function ajaxCall(i) {
						if ( i < data.length ) {
							var photo = data[i];

							var input = {
								'action': 'oxfam_action',
								'name': photo['name'],
								'timestamp': photo['timestamp'],
								'path': photo['path'],
							};
				    		
				    		jQuery.ajax({
				    			type: 'POST',
	  							url: ajaxurl,
				    			data: input,
				    			dataType: 'html',
				    			success: function(msg) {
							    	tries = 0;
									jQuery(".output").prepend("<p>"+msg+"</p>");
							    	ajaxCall(i+1);
								},
								error: function(jqXHR, statusText, errorThrown) {
									tries++;
									jQuery(".output").prepend("<p>Asynchroon laden van PHP-file mislukt ... (poging: "+tries+" &mdash; "+statusText+": "+errorThrown+")"+"</p>");
									if ( tries < 5 ) {
										ajaxCall(i);
									} else {
										tries = 0;
										jQuery(".output").prepend("<p>Skip <i>"+photo['name']+"</i>, we schuiven door naar de volgende foto!</p>");
										ajaxCall(i+1);
									}
								},
							});
				    	} else {
				    		jQuery("#wpcontent").css("background-color", "limegreen");
				    		jQuery(".output").prepend("<p>Klaar, we hebben "+i+" foto's verwerkt!</p>");
				    		jQuery(".run").text("Registreer nieuwe / gewijzigde foto's");
				    	}
					}
					
					function dump(obj) {
					    var out = '';
					    for ( var i in obj ) {
					        if ( typeof obj[i] === 'object' ){
					            dump(obj[i]);
					        } else {
					            if ( i != 'timestamp' ) out += i + ": " + obj[i] + "<br>";
					        }
					    }
					    jQuery(".input").append('<pre>'+out+'</pre>');
					}
				});
			</script>
		<?php }
	?>
</table>