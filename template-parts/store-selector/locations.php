<?php
	// Haal de huidig gekozen winkel op
	$current_store = false;
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$current_store = intval( $_COOKIE['latest_shop_id'] );
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
	</div>
</div>