<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			// Sluit hoofdsite en gearchiveerde webshops uit
			$sites = get_sites( array( 'site__not_in' => array(1), 'public' => 1, ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

					if ( get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
						// Stel de waarschuwingsbanner in
						if ( in_array( $site->blog_id, get_site_option('oxfam_blocked_sites') ) {
							update_option( 'woocommerce_demo_store_notice', 'Deze webshop is nog niet gepubliceerd. Bestellingen worden nog niet uitgeleverd.' );
						} else {
							update_option( 'woocommerce_demo_store_notice', 'Betalingen zijn nog niet geactiveerd. Bestellingen worden nog niet uitgeleverd.' );
						}
						if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
							write_log("Waarschuwingsbanner geactiveerd op ".$site->blogname."!");
						}
					} else {
						// Neem de wettelijke feestdagen indien er geen enkele lokale gedefinieerd is (of merge altijd?)
						if ( in_array( date_i18n('Y-m-d'), get_option( 'oxfam_holidays', get_site_option('oxfam_holidays') ) ) ) {
							// Stel de afwezigheidsboodschap in, op voorwaarde dat er momenteel geen andere boodschap getoond wordt
							if ( get_option('woocommerce_demo_store') === 'no' ) {
								// PERSONALISEERBAAR MAKEN? EERSTE WERKDAG ZOEKEN NA VAKANTIE?
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

			write_log("Banners ingesteld voor ".count($sites)." webshops!");
			echo "The end";
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>