<?php
	// Haal de huidige postcode op
	$current_location = false;
	if ( ! empty( $_COOKIE['current_location'] ) ) {
		$current_location = intval( $_COOKIE['current_location'] );
	}

	// Indien we false doorgeven, wordt er niet gefilterd op postcode
	if ( does_home_delivery( $current_location ) ) {
		$home_delivery = 'active';
	} else {
		$home_delivery = 'inactive';
	}

	// Haal de huidige gekozen winkel op
	$current_store = false;
	if ( ! empty( $_COOKIE['latest_shop_id'] ) ) {
		$current_store = intval( $_COOKIE['latest_shop_id'] );
	}

	if ( $locations = get_option('woocommerce_pickup_locations') ) {
		foreach ( $locations as $location ) {
			$parts = explode( 'id=', $location['address_1'] );
			if ( isset( $parts[1] ) ) {
				// Het heeft geen zin om het adres van niet-numerieke ID's op te vragen (= uitzonderingen)
				$shop_post_id = intval( str_replace( ']', '', $parts[1] ) );
				if ( $shop_post_id > 0 ) {
					$shops[ $shop_post_id ] = $location['shipping_company'];
				}
			} else {
				// Geen argument, dus het is de hoofdwinkel, altijd opnemen!
				$shops[ get_option('oxfam_shop_post_id') ] = $location['shipping_company'];
			}
		}
	}

	if ( ! array_key_exists( $current_store, $shops ) ) {
		// De cookie slaat op een winkel uit een andere subsite (bv. door rechtstreeks switchen)
		// Stel de hoofdwinkel van de huidige subsite in als fallback
		$current_store = get_option('oxfam_shop_post_id');
	}
?>

<?php if ( is_main_site() or $current_store === false ) : ?>
	<div class="selected-store not-ok">
		<div class="pointer"></div>
		<p>Online shoppen?</p>
		<!-- Alle #open-store-selector toggelen de modal die verborgen zit in de footer -->
		<a href="#" id="open-store-selector"><button>Selecteer winkel</button></a>
	</div>
<?php else : ?>
	<div class="selected-store ok">
		<div class="pointer">Jouw Oxfam-winkel</div>
		<!-- Niet alle ID's zitten in elke shop, beter ophalen op hoofdniveau? -->
        <div class="shop-name">
            <strong><?php echo get_shop_name( array( 'id' => $current_store ) );  ?></strong>
        </div>
        <div class="shop-address">
		    <p><?php echo get_shop_address( array( 'id' => $current_store ) ); ?></p>
        </div>
		<ul class="delivery-options">
			<li class="pickup active">Afhalen in de winkel</li>
			<li class="shipping <?php echo $home_delivery; ?>">​Levering aan huis</li>
		</ul>
		<a href="#" id="open-store-selector">Winkel wijzigen</a>

		<?php if ( $args['context'] === 'cart' ) : ?>
			<p>Openingsuren</p>
			<?php
				switch_to_blog(1);
				// Te vervangen door reële post-ID van WPSL-object! (= niet gelijk aan $current_store)
				echo do_shortcode('[wpsl_hours id="1660"]');
				restore_current_blog();
			?>
			<p>Je ontvangt een bevestigingsmail zodra je bestelling klaar is voor afhaling of thuislevering.</p>
		<?php endif; ?>
	</div>
<?php endif; ?>