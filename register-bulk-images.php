<h3>Registratie van nieuwe productfoto's</h3>
<?php
	function endsWith($haystack, $needle) {
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
	}

	function sortFunction($a, $b) {
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
		    	if ( endsWith($file, '.jpg') and !strpos($file, 'x') and filemtime($filepath) > get_option('laatste_registratie_timestamp', '946681200') ) {
		        	// Zet naam, timestamp, datum en pad van de upload in de array
		        	$photos[] = array(
		        		"name" => basename($filepath),
					    "timestamp" => filemtime($filepath),
					    "date" => date('d/m/Y H:i:s', filemtime($filepath)),
					    "path" => $filepath,
					);
		        }
		    }
		    closedir($handle);
		}
		
		// Orden chronologisch
		if ( count($photos) > 1 ) {
			usort($photos, "sortFunction");
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

		<?php
	}
?>

<p>Hieronder zie je een lijstje met alle originelen die sinds de laatste fotoregistratie naar de FTP-server geüpload werden. De meest recente bestanden staan onderaan. Elke middag worden om 14 uur de foto's uit <a href="file:///\\vmfile\data\Vormgeving & Publicaties\Productfoto's\">F:\Vormgeving & Publicaties\Productfoto's\</a> (mits enkele controles) naar <a href="file:///\\vmfile\data\Crafts\WebshopImport\Webshopfoto's\">F:\Crafts\WebshopImport\Webshopfoto's\</a> verplaatst en vindt er een opwaartse synchronisatie plaats van alle nieuwe of gewijzigde foto's naar de uploadmap van de webshop.</p>

<p>Om de thumbnails aan te maken en de foto's te registreren in de mediabib dient een sitebeheerder daarna nog op onderstaande knop te klikken. Foto's kunnen hierbij niet langer dubbel geüpload worden. Bijgewerkte foto's die al aan een product gelinkt waren, worden daar opnieuw aan gelinkt (op voorwaarde dat de uploadlocatie bekend was). In het andere geval is het wachten tot de volgende ERP-import.</p>

<p>Is er een foutje gebeurd of wil je een bepaalde foto definitief verwijderen? Dan dien je de foto zowel te verwijderen <a href="<?php echo home_url('/wp-admin/upload.php'); ?>">uit de mediabibliotheek</a> als uit <a href="file:///\\vmfile\data\Vormgeving & Publicaties\Productfoto's\">F:\Vormgeving & Publicaties\Productfoto's\</a>. Zo niet zal de foto elke dag weer als 'nieuwe' foto opduiken en geregistreerd worden in de webshop. De synchronisatie werkt immers niet in twee richtingen, maar enkel opwaarts richting webserver!</p>

<div class="output"></div>

<p>&nbsp;</p>

<button class="run" style="float: right; margin-right: 20px; width: 300px;" disabled>Registreer nieuwe / gewijzigde foto's</button>
<div class="input"></div>