<?php
	$html = false;
	$single_message = false;

	if ( strlen( get_option('oxfam_sitewide_banner_top') ) > 0 ) {

		$html = get_option('oxfam_sitewide_banner_top');
		$single_message = true;

	} else {

		$html = get_default_local_store_notice();

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