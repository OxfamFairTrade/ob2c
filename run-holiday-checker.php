<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		if ( isset( $_GET['import_key'] ) and $_GET['import_key'] === IMPORT_KEY ) {
			// Sluit afgeschermde en gearchiveerde webshops uit
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

					// Sluit hoofdsite uit
					if ( ! is_main_site() ) {
						global $default_holidays;
						// Stel boodschap in (personaliseerbaar maken? eerste werkdag zoeken na vakantie?)
						update_option( 'woocommerce_demo_store_notice', 'We zijn vandaag uitzonderlijk gesloten. Bestellingen worden opnieuw verwerkt vanaf de eerstvolgende openingsdag. De geschatte leverdatum houdt hiermee rekening.' );
						if ( in_array( date_i18n('Y-m-d'), get_option('oxfam_holidays') ) ) {
							if ( update_option( 'woocommerce_demo_store', 'yes' ) ) {
								echo "Vakantiebanner van ".$site->blogname." geactiveerd!<br>";
							} else {
								echo "Vakantiebanner van ".$site->blogname." ongewijzigd!<br>";
							}
						} else {
							if ( update_option( 'woocommerce_demo_store', 'no' ) ) {
								echo "Vakantiebanner van ".$site->blogname." gedeactiveerd!<br>";
							} else {
								echo "Vakantiebanner van ".$site->blogname." ongewijzigd!<br>";
							}
						}
					}

				restore_current_blog();
			}
		} else {
			die("Access prohibited!");
		}
	?>
</body>

</html>