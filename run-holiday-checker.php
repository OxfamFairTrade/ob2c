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
						update_option( 'woocommerce_demo_store_notice', 'Opgelet: deze webshop is nog niet actief! De betalingen staan in testmodus.' );
						if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
							write_log("Waarschuwingsbanner geactiveerd op ".$site->blogname."!");
						}
					} else {
						if ( in_array( date_i18n('Y-m-d'), get_option('oxfam_holidays') ) ) {
							// Stel de afwezigheidsboodschap in
							// PERSONALISEERBAAR MAKEN? EERSTE WERKDAG ZOEKEN NA VAKANTIE?
							update_option( 'woocommerce_demo_store_notice', 'We zijn vandaag uitzonderlijk gesloten. Bestellingen worden opnieuw verwerkt vanaf de eerstvolgende openingsdag. De geschatte leverdatum houdt hiermee rekening.' );
							if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
								write_log("Vakantiebanner geactiveerd op ".$site->blogname."!");
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