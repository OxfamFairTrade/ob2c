<?php
	// Haal de huidige keuze op (nog aan te passen naar individuele winkels i.p.v. webshops)
	$current_store = false;
	if ( isset( $_COOKIE['latest_subsite'] ) ) {
		$current_store = get_blog_details( $_COOKIE['latest_subsite'], false );
	}
?>
<div class="store-selector" display="none; position: fixed; left: 0; right: 0: top: 0; bottom: 0; background-color: rgba(200,200,200,0.25);">
	<div class="store-selector-inner">
		<?php var_dump_pre( $current_store ); ?>
	</div>
</div>