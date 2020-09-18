<?php
	// Haal de huidige postcode op
	$current_location = false;
	if ( ! empty( $_COOKIE['current_location'] ) ) {
		$current_location = intval( $_COOKIE['current_location'] );
	}
?>

<div class="store-selector-modal">
	<div class="store-selector-inner">
        <a href="#" class="store-selector-close"></a>
		<h2>Selecteer jouw Oxfam-winkel</h2>
		<p>Vul de postcode in waar jij de producten wil <b>afhalen</b> of waar ze <b>geleverd</b> moeten worden. Je bestelling wordt opgevolgd door de vrijwilligers van een Oxfam-Wereldwinkel in jouw buurt.</p>
		<?php
			// Door core hack halen we altijd de winkels in het hoofdniveau op, werkt anders niet in subsites!
			// Opgelet: instelling "Zodra een gebruiker op 'route' klikt, open een nieuwe venster en toon de route op google.com/maps" ingeschakeld laten
			// Anders wordt reverseGeocode() niet doorlopen in wpsl-gmap.js, waardoor de huidige postcode van de gebruiker niet ingevuld wordt
			echo do_shortcode('[wpsl template="no_map"]');
		?>
		<div id="default-content">
			<?php if ( $current_location ) : ?>
				<p>Laatst gezocht: <?php echo $current_location; ?></p>
			<?php endif; ?>
			<ul class="benefits">
				<li>Gratis verzending vanaf 50 euro</li>
				<li>Wij kopen rechtreeks bij kwetsbare producenten, met oog voor ecologische duurzaamheid</li>
				<li>Met jouw aankoop steun je onze strijd voor een structureel eerlijk handelssysteem</li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready( function() {
		/* In wpsl-gmap.js staat in searchLocationBtn() een $( "#wpsl-search-btn" ).unbind( "click" ) die dit verhindert */
		/* Voorlopig daar hard verwijderd maar wellicht beter om zelf een custom event te verzinnen en binden? */
		jQuery('#wpsl-search-btn').on( 'click', function() {
			console.log("Executing click binding by OB2C ...");
			jQuery('#default-content').hide();
			/* Maak de resultatenlijst zéker zichtbaar */
			jQuery('#wpsl-result-list').show();
			/* Bewaar de ingave in een cookie voor later gebruik, o.a. in AJAX */
			setCookie( 'current_location', jQuery('#wpsl-search-input').val() );
		});

		jQuery('#wpsl-search-input').keyup( function(event) {
			if ( event.which == 13 ) {
				jQuery('#wpsl-search-btn').trigger('click');
			}
		});

		var wto;
		var zips = <?php echo json_encode( get_site_option('oxfam_flemish_zip_codes') ); ?>;
		
		jQuery('.store-selector-open').on( 'click', function(event) {
			event.preventDefault();
			jQuery('.store-selector-modal').toggleClass('open');
			
			var zip = jQuery('#wpsl-search-input').val();
			if ( zip.length == 4 && /^\d{4}$/.test(zip) && (zip in zips) ) {
				jQuery('#wpsl-search-btn').prop( 'disabled', false ).parent().addClass('is-valid');
			}
		});

		jQuery('.store-selector-close').on( 'click', function(event) {
			event.preventDefault();
			jQuery('.store-selector-modal').toggleClass('open');
		});

		jQuery('.autocomplete-postcodes').autocomplete({
			/* Dit is een licht andere vorm dan var zips! */
			source: <?php echo json_encode( get_flemish_zips_and_cities() ); ?>,
			minLength: 1,
			autoFocus: true,
			position: { my : "right top", at: "right bottom" },
			close: function(event,ui) {
				// Opgelet: dit wordt uitgevoerd vòòr het standaardevent (= invullen van de postcode in het tekstvak)
				jQuery('#wpsl-search-btn').trigger('click');
			}
		});
		
		/* Gebruik event delegation, deze nodes zijn nog niet aanwezig bij DOM load! */
		/* Voorzien we een non-JS back-up via <a href>? */
		jQuery('#wpsl-wrap').on( 'click', '#wpsl-stores > ul > li.available', function() {
			console.log( "Registreer shop-ID "+jQuery(this).data('oxfam-shop-post-id')+" in cookie en doe redirect naar "+jQuery(this).data('webshop-url') );
			setCookie( 'latest_shop_id', jQuery(this).data('oxfam-shop-post-id') );
			/* Of altijd het huidige pad erachter proberen te plakken? */
			window.location.replace( jQuery(this).data('webshop-url')+'producten/' );
		});
	});

	function setCookie(cname, cvalue) {
		var d = new Date();
		d.setTime( d.getTime() + 30*24*60*60*1000 );
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/;domain=<?php echo OXFAM_COOKIE_DOMAIN; ?>";
	}
</script>