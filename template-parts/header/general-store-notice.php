<?php
	$html = false;
	$single_message = false;

	if ( strlen( get_option('oxfam_sitewide_banner_top') ) > 0 ) {

		$html = get_option('oxfam_sitewide_banner_top');
		$single_message = true;

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
		$html = 'Gratis afhaling in de winkel';

	}
?>

<div class="container general-store-notice">
	<div>
		<ul class="col-row">
			<?php if ( $single_message ) : ?>
				<li class="col-md-12"><span><?php echo $html; ?></span></li>
			<?php else : ?>
				<?php if ( $html and ! is_b2b_customer() ) : ?>
					<li class="col-md-4"><span><?php echo $html; ?></span></li>
				<?php endif; ?>
				<li class="col-md-4"><span>Wij kopen rechtstreeks bij kwetsbare producenten</span></li>
				<li class="col-md-4"><span>Je steunt de strijd voor eerlijke handel</span></li>
			<?php endif; ?>
		</ul>
	</div>
</div>