<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once __DIR__ . '/../../../wp-load.php';
		
		// Bied zowel ondersteuning voor wget als php cron jobs!
		if ( ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) or ( isset( $argv ) and $argv[1] === 'import_key='.IMPORT_KEY ) ) {
			// Sluit hoofdsite en gearchiveerde webshops uit
			$sites = get_sites( array( 'site__not_in' => array(1), 'public' => 1, ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

					if ( get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
						// Stel de waarschuwingsbanner in
						if ( in_array( $site->blog_id, get_site_option('oxfam_blocked_sites') ) ) {
							update_option( 'woocommerce_demo_store_notice', 'Deze webshop is nog niet gepubliceerd. Bestellingen worden nog niet uitgeleverd.' );
							$type = 'A';
						} else {
							update_option( 'woocommerce_demo_store_notice', 'Betalingen zijn nog niet geactiveerd. Bestellingen worden nog niet uitgeleverd.' );
							$type = 'B';
						}
						if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
							write_log("Waarschuwingsbanner type ".$type." geactiveerd op ".$site->blogname."!");
						}
					} else {
						if ( $site->blog_id == 40 and date_i18n('Y-m-d') <= '2020-07-31' ) {
							// Uitzondering voor Mechelen
							update_option( 'woocommerce_demo_store_notice', 'Wegens onze jaarlijkse vakantie is de winkel momenteel gesloten, maar onze webshop blijft open!' );
							if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
								write_log("Speciale banner geactiveerd op ".$site->blogname."!");
							}
						} elseif ( in_array( date_i18n('Y-m-d'), get_option( 'oxfam_holidays', get_site_option('oxfam_holidays') ) ) ) {
							// Neem de wettelijke feestdagen indien er geen enkele lokale gedefinieerd is
							// Stel de afwezigheidsboodschap in, op voorwaarde dat er momenteel geen andere boodschap getoond wordt
							if ( get_option('woocommerce_demo_store') === 'no' ) {
								// @toDo: Personaliseerbaar maken? Eerste werkdag zoeken na vakantie?
								update_option( 'woocommerce_demo_store_notice', 'We zijn vandaag uitzonderlijk gesloten. Bestellingen worden opnieuw verwerkt vanaf de eerstvolgende openingsdag. De geschatte leverdatum houdt hiermee rekening.' );
								if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
									write_log("Vakantiebanner geactiveerd op ".$site->blogname."!");
								}
							}
						} else {
							if ( update_option( 'woocommerce_demo_store', 'no' ) ) {
								write_log("Vakantiebanner gedeactiveerd op ".$site->blogname."!");
							}
						}
					}

				restore_current_blog();
			}

			write_log( "Banners ingesteld voor ".count( $sites )." webshops!" );
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>