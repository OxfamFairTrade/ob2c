<?php
	// Haal de huidig gekozen winkel op
	$current_store = false;
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$current_store = intval( $_COOKIE['latest_shop_id'] );
	}
	
	$options = '';
	$global_zips = get_shops();
	$all_zips = get_site_option('oxfam_flemish_zip_codes');
	foreach ( $all_zips as $zip => $city ) {
		if ( isset( $global_zips[$zip] ) ) {
			$url = $global_zips[$zip];
		} else {
			$url = '';
		}
		$options .= '<input type="hidden" class="oxfam-zip-value" id="'.$zip.'" value="'.$url.'">';
	}

	// On select: cookie bijwerken en redirecten MOET VANUIT 'INIT'-ACTIE GEBEUREN VÒÒR ENIGE OUTPUT
	if ( $current_store === false ) {
		// setcookie( 'latest_shop_id', get_option('oxfam_shop_post_id'), time() + 30 * DAY_IN_SECONDS, 'oxfamwereldwinkels.be' );
		// wp_safe_redirect( $store_url );
		// exit();
	}
?>

<div class="store-selector-modal" style="display: none; position: fixed; left: 0; right: 0; top: 0; bottom: 0; background-color: rgba(200,200,200,0.9);">
	<div class="store-selector-inner">
		<h2>Selecteer jouw Oxfam-winkel</h2>
		<p>Vul de postcode in waar jij de producten wil <b>afhalen</b> of waar ze <b>geleverd</b> moeten worden. Je bestelling wordt opgevolgd door de vrijwilligers van een Oxfam-Wereldwinkel in jouw buurt.</p>
		<span class="store-selector-zipcode">
			<input type="text" class="" placeholder="Zoek op postcode" id="oxfam-zip-user" autocomplete="off">
			<button type="submit" class="" id="do_oxfam_redirect" disabled></button>
			<?php echo $options; ?>
		</span>
		<?php
			// Winkels zitten enkel op het hoofdniveau, werkt anders niet in subsites!
			switch_to_blog(1);
			echo do_shortcode('[wpsl template="no_map"]');
			restore_current_blog();
		?>
		<div class="store-selector-results">
			<!-- Default content, in afwachting van ingeven postcode -->
			<p>Laatst gezocht: 9000 Gent</p>
			<?php var_dump_pre( $current_store ); ?>
			<ul class="benefits">
				<li>Gratis verzending vanaf 50 euro</li>
				<li>Wij kopen rechtreeks bij kwetsbare producenten, met oog voor ecologische duurzaamheid</li>
				<li>Met jouw aankoop steun je onze strijd voor een structureel eerlijk handelssysteem</li>
			</ul>
		</div>
		<div class="store-selector-results">
			<!-- Voorbeeldcontent, alle winkels binnen een straal van 30 kilometer -->
			<ul class="stores">
				<li class="store active" style="cursor: pointer;">
					<h3>Oxfam-Wereldwinkel Gent-Centrum</h3>
					<p>Lammerstraat 16, 9000 Gent</p>
					<ul class="delivery-options">
						<li class="shipping active">​Levering aan huis</li>
						<li class="pickup active">Afhalen in de winkel</li>
					</ul>
					<button>Online winkelen</button>
				</li>
				<li class="store inactive" style="cursor: not-allowed;">
					<h3>Oxfam-Wereldwinkel Lokeren</h3>
					<p>Kapellestraat 3, 9160 Lokeren</p>
					<p>Online winkelen niet beschikbaar.<br/>Stuur je bestelling <a href="mailto:lokeren@oww.be">per e-mail</a>.</p>
				</li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready( function() {
		jQuery('#open-store-selector').on( 'click', function() {
			jQuery('.store-selector-modal').toggle();
		});

		var wto;
		jQuery('#oxfam-zip-user').on( 'input change', function() {
			clearTimeout(wto);
			var zip = jQuery(this).val();
			var button = jQuery('#do_oxfam_redirect');
			var zips = <?php echo json_encode( get_site_option('oxfam_flemish_zip_codes') ); ?>;
			if ( zip.length == 4 && /^\d{4}$/.test(zip) && (zip in zips) ) {
				button.prop( 'disabled', false ).parent().addClass('is-valid');
				wto = setTimeout( function() {
					button.find('i').addClass('loading');
					wto = setTimeout( function() {
						button.trigger('click');
					}, 750);
				}, 250);
			} else {
				button.prop( 'disabled', true ).parent().removeClass('is-valid');
			}
		});
		
		jQuery('#oxfam-zip-user').keyup( function(event) {
			if ( event.which == 13 ) {
				jQuery('#do_oxfam_redirect').trigger('click');
			}
		});
		
		jQuery('#do_oxfam_redirect').on( 'click', function() {
			jQuery(this).prop( 'disabled', true );
			var input = jQuery('#oxfam-zip-user');
			var zip = input.val();
			var url = jQuery('#'+zip+'.oxfam-zip-value').val();
			var all_cities = <?php echo json_encode( get_site_option('oxfam_flemish_zip_codes') ) ?>;
			// Indien er meerdere plaatsnamen zijn, knippen we ze op en gebruiken we de eerste (= hoofdgemeente)
			var cities_for_zip = all_cities[zip].split(' / ');
			if ( typeof url !== 'undefined' ) {
				if ( url.length > 10 ) {
					var suffix = '';
					<?php if ( isset( $_GET['addSku'] ) ) : ?>
						suffix = '&addSku=<?php echo $_GET['addSku']; ?>';
					<?php endif; ?>
					window.location.href = url+'?referralZip='+zip+'&referralCity='+cities_for_zip[0]+suffix;
				} else {
					alert("<?php _e( 'Foutmelding na het ingeven van een Vlaamse postcode waar Oxfam-Wereldwinkels nog geen thuislevering voorziet.', 'oxfam-webshop' ); ?>");
					jQuery(this).parent().removeClass('is-valid').find('i').removeClass('loading');
					input.val('');
				}
			} else {
				alert("<?php _e( 'Foutmelding na het ingeven van een onbestaande Vlaamse postcode.', 'oxfam-webshop' ); ?> Tip: je kunt ook de naam van je gemeente beginnen te typen en de juiste postcode selecteren uit de suggesties die verschijnen.");
				jQuery(this).parent().removeClass('is-valid').find('i').removeClass('loading');
				input.val('');
			}
		});

		jQuery( function() {
			var zips = <?php echo json_encode( get_flemish_zips_and_cities() ); ?>;
			jQuery( '#oxfam-zip-user' ).autocomplete({
				source: zips,
				minLength: 1,
				autoFocus: true,
				position: { my : "right+20 top", at: "right bottom" },
				close: function(event,ui) {
					// Opgelet: dit wordt uitgevoerd vòòr het standaardevent (= invullen van de postcode in het tekstvak)
					jQuery( '#oxfam-zip-user' ).trigger('change');
				}
			});
		});
	});
</script>