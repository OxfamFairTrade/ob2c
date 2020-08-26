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
<div class="store-selector" style="display: none; position: fixed; left: 0; right: 0; top: 0; bottom: 0; background-color: rgba(200,200,200,0.75);">
	<div class="store-selector-inner">
		<?php var_dump_pre( $current_store ); ?>
		<h2>Selecteer jouw Oxfam-winkel</h2>
		<p>Vul de postcode in waar jij de producten wil afhalen of waar ze geleverd moeten worden. Je bestelling wordt opgevolgd door de vrijwilligers van een Oxfam-Wereldwinkel in jouw buurt.</p>
		<span class="input-group">
			<input type="text" class="" placeholder="Zoek op postcode" id="oxfam-zip-user" autocomplete="off">
			<button type="submit" class="" id="do_oxfam_redirect" disabled><i class="pe-7s-search"></i></button>
			<?php echo $options; ?>
		</span>
		<ul class="benefits">
			<li>Gratis verzending vanaf 50 euro</li>
			<li>Wij kopen rechtreeks bij kwetsbare producenten, met oog voor ecologische duurzaamheid</li>
			<li>Met jouw aankoop steun je onze strijd voor een structureel eerlijk handelssysteem</li>
		</ul>
	</div>
</div>