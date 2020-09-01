<?php
	// Haal de huidig gekozen winkel op
	$current_store = false;
	if ( ! empty( $_COOKIE['latest_shop_id'] ) ) {
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
	<div class="selected-store ok" style="border: 1px solid green; padding: 0.5em;">
		<span class="pointer">Jouw Oxfam-winkel</span>
		<!-- Niet alle ID's zitten in elke shop, beter ophalen op hoofdniveau? -->
		<p><?php echo get_shop_name( array( 'id' => $current_store ) ).'<br/>'.get_shop_address( array( 'id' => $current_store ) ); ?></p>
		<ul class="delivery-options">
			<li class="shipping inactive">​Levering aan huis</li>
			<li class="pickup active">Afhalen in de winkel</li>
		</ul>
		<a href="#" id="open-store-selector">Winkel wijzigen</a>
	</div>
<?php endif; ?>