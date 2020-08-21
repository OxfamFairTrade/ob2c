<?php
	// Haal de huidige keuze op (nog aan te passen naar individuele winkels i.p.v. webshops)
	$current_store = false;
	if ( isset( $_COOKIE['latest_subsite'] ) ) {
		$current_store = get_blog_details( $_COOKIE['latest_subsite'], false );
	}
?>
<div class="store-selector">
	<?php var_dump_pre( $current_store ); ?>
</div>