<?php 
	global $product;
	$labels = array();

	if ( ! is_b2b_customer() and $product->is_on_sale() ) {
		// Neem algemeen label als default
		$labels['promotion'] = 'Promo';

		// Zoek vervolgens de details van de actie op
		// Handmatig beheerde lijst, want simpeler dan afleiden uit de kortingsregels!
		
		$one_plus_one_products = array();
		if ( in_array( $product->get_sku(), $one_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 1+1 gratis';
		}
		
		$fifty_percent_off_second_products = array( '23201', '23501', '24502', '24532', '24547', '25218', '65224', '65225' );
		if ( in_array( $product->get_sku(), $fifty_percent_off_second_products ) ) {
			$labels['promotion'] = 'Promo 2de -50%';
		}
		
		$two_plus_one_products = array( '24300', '24302', '24303', '87352' );
		if ( in_array( $product->get_sku(), $two_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 2+1 gratis';
		}
		
		$three_plus_one_products = array( '20808', '20810', '20812' );
		if ( in_array( $product->get_sku(), $three_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 3+1 gratis';
		}

		$four_plus_two_products = array( '21498', '21499' );
		if ( in_array( $product->get_sku(), $four_plus_two_products ) ) {
			$labels['promotion'] = 'Promo 4+2 gratis';
		}
		
		$five_plus_one_products = array( '20031', '20060', '20070' );
		if ( in_array( $product->get_sku(), $five_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 5+1 gratis';
		}

		$twentyfive_percent_off_products = array( '20807', '20809', '20811' );
		if ( in_array( $product->get_sku(), $twentyfive_percent_off_products ) ) {
			$labels['promotion'] = 'Promo -25%';
		}
	}

	if ( $product->get_date_created()->date_i18n('Y-m-d') > date_i18n( 'Y-m-d', strtotime('-3 months') ) ) {
		$labels['newbee'] = 'Nieuw';
	}

	// Zal nog vervangen worden door een taxonomie i.p.v. attribuut
	if ( strpos( $product->get_attribute('preferences'), 'biologisch' ) !== false ) {
		$labels['organic'] = 'Bioproduct';
	}

	if ( ! does_risky_delivery() ) {
		if ( $product->get_shipping_class() === 'breekbaar' ) {
			$labels['pickup-only'] = 'Afhaling';
		}
	}

	if ( count( $labels ) > 0 ) {
		echo '<ul class="info-labels">';
		foreach ( $labels as $class => $label ) {
			echo '<li class="info-label '.$class.'">'.$label.'</li>';
		}
		echo '</ul>';
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
