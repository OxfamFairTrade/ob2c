<?php
	// Haal de huidig gekozen winkel op
	$current_store = false;
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$current_store = intval( $_COOKIE['latest_shop_id'] );
	}
?>

<?php if ( is_main_site() or $current_store === false ) : ?>
	<div class="selected-store not-ok" style="border: 1px solid red; padding: 0.5em;">
		<span class="pointer"></span>
		<p>Online shoppen?</p>
		<!-- Alle #open-store-selector toggelen de modal die verborgen zit in de footer -->
		<a href="#" id="open-store-selector"><button>Selecteer winkel</button></a>
	</div>
<?php else : ?>
	<div class="selected-store not-ok" style="border: 1px solid green; padding: 0.5em;">
		<span class="pointer">Jouw Oxfam-winkel</span>
		<!-- Niet alle ID's zitten in elke shop, beter ophalen op hoofdniveau? -->
		<p><?php echo get_company_name().'<br/>'.get_company_address(); ?></p>
		<ul>
			<li class="inactive">​Levering aan huis</li>
			<li class="active">Afhalen in de winkel</li>
		</ul>
		<a href="#" id="open-store-selector">Winkel wijzigen</a>
	</div>
<?php endif; ?>