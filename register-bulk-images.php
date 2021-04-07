<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Registratie van nieuwe productfoto's</h1>
	<?php
		function ends_with( $haystack, $needle ) {
			return $needle === "" or ( ( $temp = strlen($haystack) - strlen($needle) ) >= 0 and strpos( $haystack, $needle, $temp ) !== false );
		}

		function sort_by_time( $a, $b ) {
	    	return $a["timestamp"] - $b["timestamp"];
		}

		function list_new_images() {
			$photos = array();

			// Probeer de uploadmap te openen
	    	if ( $handle = opendir(WP_CONTENT_DIR.'/uploads/') ) {
			    // Loop door alle files in de map
			    while ( false !== ($file = readdir($handle)) ) {
			    	$filepath = WP_CONTENT_DIR.'/uploads/'.$file;
			    	// Beschouw enkel de JPG-foto's zonder 'x' (= geen thumbnails) die sinds de vorige bulksessie geüpload werden
			    	if ( ends_with( $file, '.jpg' ) and ! strpos( $file, 'x' ) and filemtime($filepath) > get_option('laatste_registratie_timestamp', '946681200') ) {
			        	// Zet naam, timestamp, datum en pad van de upload in de array
			        	$photos[] = array(
			        		"name" => basename($filepath),
						    "timestamp" => filemtime($filepath),
						    "date" => get_date_from_gmt( date( 'Y-m-d H:i:s', filemtime($filepath) ), 'd/m/Y H:i:s' ),
						    "path" => $filepath,
						);
			        }
			    }
			    closedir($handle);
			}
			
			// Orden chronologisch
			if ( count($photos) > 1 ) {
				usort( $photos, 'sort_by_time' );
			}
			return $photos;
		}

		list_new_images();

		add_action('admin_footer', 'oxfam_photo_action_javascript');

		function oxfam_photo_action_javascript() { ?>
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
							jQuery('#wpcontent').css('background-color', 'gold');
							jQuery(".output").before("<p>&nbsp;</p>");
							ajaxCall(0);
						});
					} else {
						jQuery(".input").prepend("<pre>We vonden geen enkele nieuwe of gewijzigde foto!</pre>");
					}

					jQuery(".input").prepend("<pre>Uploadtijdstip laatst verwerkte foto: <?php echo get_date_from_gmt( date( 'Y-m-d H:i:s', get_option('laatste_registratie_timestamp', '946681200') ), 'd/m/Y H:i:s' ); ?></pre>");

					var tries = 0;

					function ajaxCall(i) {
						if ( i < data.length ) {
							var photo = data[i];

							var input = {
								'action': 'oxfam_photo_action',
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
				    		jQuery("#wpcontent").css("background-color", "lightgreen");
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

			<?php
		}
	?>

	<p>Hieronder zie je een lijstje met alle originelen die sinds de laatste fotoregistratie naar de FTP-server geüpload werden. Hiervoor plaatsen we de packshots uit <a href="file:///\\vmfile\data\1-Vormgeving & Publicaties\OFT-sync (LATEN STAAN)\RGB HI-RES\">F:\1-Vormgeving & Publicaties\OFT-sync (LATEN STAAN)\RGB HI-RES\</a> die sinds de laatste update bijgewerkt werden handmatig in de uploadmap van het netwerk. Om de thumbnails aan te maken en de foto's te registreren in de mediabib dien je daarna nog op onderstaande knop te klikken. Foto's kunnen hierbij niet dubbel geüpload worden.</p>

	<p>Bijgewerkte foto's die al aan een product gelinkt waren, worden daar opnieuw aan gelinkt. In het andere geval is het wachten tot de volgende ERP-import. Opgelet: sinds oktober 2020 worden de packshots in principe automatisch gedownload bij het runnen van de ERP-import (in medium resolutie). Bovendien worden de packshots niet langer gekopieerd naar alle lokale mediabibliotheken maar wordt het beeld uit het hoofdniveau opgehaald via het '_main_thumbnail_id'-metaveld.</p>

	<div class="output"></div>

	<p>&nbsp;</p>

	<button class="run" style="float: right; margin-right: 20px; width: 300px;" disabled>Registreer nieuwe / gewijzigde foto's</button>
	<div class="input"></div>
</div>