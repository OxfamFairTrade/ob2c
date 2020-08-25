<?php
	// Haal de huidig gekozen winkel op
	$current_store = false;
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$current_store = intval( $_COOKIE['latest_shop_id'] );
	}
?>
<div class="selected-store">
	<span>Jouw Oxfam-winkel</span>
	<!-- To do Frederik: Dynamisch maken (winkel vs. webshop!) -->
	<p><?php echo get_company_name( $current_store ).'<br/>'.get_company_address( $current_store ); ?></p>
	<ul>
		<li class="inactive">​Levering aan huis</li>
		<li class="active">Afhalen in de winkel</li>
	</ul>
	<!-- To do Pieter: Toggle modal die verborgen zit in footer -->
	<a href="#" class="open-store-selector">Winkel wijzigen</a>
</div>