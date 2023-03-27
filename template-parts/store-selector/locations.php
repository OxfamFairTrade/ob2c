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
		<p>
			<?php if ( isset( $_GET['triggerStoreLocator'] ) ) echo '<strong>Vooraleer we de ingrediënten in je winkelmandje kunnen leggen, dien je eerst nog een winkel te kiezen.</strong> '; ?>
			Vul de postcode in waar jij de producten wil <b>afhalen</b> of waar ze <b>geleverd</b> moeten worden. Je bestelling wordt opgevolgd door de vrijwilligers van een Oxfam-Wereldwinkel in jouw buurt.
		</p>
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
	/* Verplaatsen naar externe file /js/scripts.js (met cache)? */
	jQuery(document).ready( function() {
		/* In wpsl-gmap.js staat in searchLocationBtn() een $( "#wpsl-search-btn" ).unbind( "click" ) die dit verhindert */
		/* Voorlopig daar hard verwijderd maar wellicht beter om zelf een custom event te verzinnen en binden? */
		jQuery('#wpsl-search-btn').on( 'click', function() {
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

		var zips = <?php echo json_encode( get_site_option('oxfam_flemish_zip_codes') ); ?>;
		/* Licht gewijzigde vorm voor autocomplete (proberen samenvoegen?) */
		var autocomplete_zips = <?php echo json_encode( get_flemish_zips_and_cities() ); ?>;

		/* Gebruik event delegation, de buttons in .nm-shop-products-col zijn niet noodzakelijk al aanwezig bij DOM load! */
		/* Let op dat elementen niet dubbel getarget worden, dan zal preventDefault() roet in het eten gooien! */
		/* Custom pagina voor wijnfestival = #post-4664 */
		jQuery('#header,.nm-shop-products-col,.nm-product-summary-inner-col,#nm-related,.nm-product-slider,.selected-store,.how-does-it-work-helper-text,#post-4664').on( 'click', '.store-selector-open', function(event) {
			event.preventDefault();
			jQuery('.store-selector-modal').toggleClass('open');

			var zip = jQuery('#wpsl-search-input').val();
			if ( zip.length == 4 && /^\d{4}$/.test(zip) && (zip in zips) ) {
				jQuery('#wpsl-search-btn').prop( 'disabled', false ).parent().addClass('is-valid');
			}
		});

		const urlParams = new URLSearchParams( window.location.search );
		if ( urlParams.has('triggerStoreLocator') ) {
			jQuery('.store-selector-modal').toggleClass('open');
		}

		jQuery('.store-selector-close').on( 'click', function(event) {
			event.preventDefault();
			jQuery('.store-selector-modal').toggleClass('open');
		});

		jQuery('.store-selector-erase').on( 'click', function(event) {
			event.preventDefault();
			console.log("Reset cookies en doe redirect naar overeenkomstige URL op hoofdniveau");
			eraseCookie('latest_shop_node');
			eraseCookie('latest_blog_id');
			eraseCookie('latest_blog_path');
			window.location.replace( window.location.href.replace( '<?php echo home_url('/'); ?>', '<?php echo network_site_url(); ?>' ) );
		});

		jQuery('.autocomplete-postcodes').autocomplete({
			source: autocomplete_zips,
			minLength: 1,
			autoFocus: true,
			position: { my : "right top", at: "right bottom" },
			close: function(event,ui) {
				/* Opgelet: dit wordt uitgevoerd vòòr het standaardevent (= invullen van de postcode in het tekstvak) */
				jQuery('#wpsl-search-btn').trigger('click');
			}
		});

		/* Gebruik event delegation, deze nodes zijn nog niet aanwezig bij DOM load! */
		jQuery('#wpsl-wrap').on( 'click', '#wpsl-stores > ul > li.available', function() {
			console.log( "Registreer shop node "+jQuery(this).data('oxfam-shop-node')+" in cookie en doe redirect naar "+jQuery(this).data('webshop-url') );
			setCookie( 'latest_shop_node', jQuery(this).data('oxfam-shop-node') );
			setCookie( 'latest_blog_id', jQuery(this).data('webshop-blog-id') );
			var current_zip = jQuery('#wpsl-search-input').val();
			/* Voorkom dat er een pile-up ontstaat van GET-parameters */
			if ( window.location.search != '' ) {
				/* Bevat reeds een vraagteken indien niet leeg */
				var url_suffix = window.location.search + '&referralZip=' + current_zip;
			} else {
				var url_suffix = '?referralZip=' + current_zip;
			}
			/* Check of de postcode voorkomt in de lijst, anders blokkeert JavaScript op split() */
			if ( current_zip in zips ) {
				/* Geef enkel de hoofdgemeente mee (nog beter zou zijn om de effectief geselecteerde gemeente door te geven!) */
				var cities = zips[current_zip].split( " / ", 1 );
				url_suffix += '&referralCity=' + cities[0];
			}
			if ( urlParams.has('addSkus') ) {
				url_suffix += '&addSkus=' + urlParams.get('addSkus');
			}
			if ( urlParams.has('recipeId') ) {
				url_suffix += '&recipeId=' + urlParams.get('recipeId');
			}
			/* @toDo: Indien we op een productdetailpagina van lokaal assortiment zitten, leidt deze redirect tot een 404-error! */
			window.location.replace( window.location.href.split(/[?#]/)[0].replace( '<?php echo home_url('/'); ?>', jQuery(this).data('webshop-url') ) + url_suffix );
		});

		jQuery(".cat-item.current-cat > a").on( 'click', function(e) {
			e.preventDefault();
			/* Mimic eventueel wat er gebeurt in nm-shop-filters.js! */
			window.location.replace( window.location.href.replace( jQuery(this).attr('href'), '<?php echo get_permalink( wc_get_page_id('shop') ); ?>' ) );
		});
	});

	function setCookie(cname, cvalue) {
		var d = new Date();
		d.setTime( d.getTime() + 30*24*60*60*1000 );
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/;domain=<?php echo OXFAM_COOKIE_DOMAIN; ?>";
	}

	function eraseCookie(cname) {
		var d = new Date();
		d.setTime( d.getTime() - 24*60*60*1000 );
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=;" + expires + ";path=/;domain=<?php echo OXFAM_COOKIE_DOMAIN; ?>";
	}
</script>