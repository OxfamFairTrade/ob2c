<?php
	$html = false;

	if ( strlen( get_option('oxfam_sitewide_banner_top') ) > 0 ) {

		$html = get_option('oxfam_sitewide_banner_top');

	} elseif ( is_main_site() or does_home_delivery() ) {

		// Neem netwerkinstelling als defaultwaarde
		$min_amount = get_option( 'oxfam_minimum_free_delivery', get_site_option('oxfam_minimum_free_delivery') );

		if ( $min_amount > 0 ) {
			$html = 'Gratis verzending vanaf '.$min_amount.' euro';
		} else {
			$html = 'Gratis thuislevering';
		}

	} elseif ( ! is_main_site() and ! does_home_delivery() ) {

		// Standaardboodschap voor winkels die geen thuislevering aanbieden
		// $html = 'Omwille van het coronavirus kun je je bestelling momenteel enkel <b><u>op afspraak</u></b> afhalen in de winkel.';

	}
?>

<div class="container general-store-notice">
	<div>
		<ul class="col-row">
			<?php if ( $html ) : ?>
				<li class="col-md-4"><span><?php echo $html; ?></span></li>
			<?php endif; ?>
			<li class="col-md-4"><span>Wij kopen rechtreeks bij kwetsbare producenten</span></li>
			<li class="col-md-4"><span>Je steunt de strijd voor eerlijke handel</span></li>
		</ul>
	</div>
</div>