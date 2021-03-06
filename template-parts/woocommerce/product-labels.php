<?php 
	global $product;
	$labels = array();

	if ( ! is_b2b_customer() and $product->is_on_sale() ) {
		// Neem algemeen label als default
		$labels['promotion'] = 'Promo';

		// Zoek vervolgens de details van de actie op
		// Handmatig beheerde lijst, want simpeler dan afleiden uit de kortingsregels!
		
		$fifty_percent_off_second_products = array( 20060, 20253, 25627, 27008, 27151, 20059, 20257, 25013, 27114 );
		if ( in_array( $product->get_sku(), $fifty_percent_off_second_products ) ) {
			$labels['promotion'] = 'Promo 2de -50%';
		}
		
		$one_plus_one_products = array();
		if ( in_array( $product->get_sku(), $one_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 1+1 gratis';
		}
		
		$two_plus_one_products = array();
		if ( in_array( $product->get_sku(), $two_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 2+1 gratis';
		}
		
		$three_plus_one_products = array();
		if ( in_array( $product->get_sku(), $three_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 3+1 gratis';
		}

		$twentyfive_percent_off_products = array();
		if ( in_array( $product->get_sku(), $twentyfive_percent_off_products ) ) {
			$labels['promotion'] = 'Promo -25%';
		}

		$four_plus_two_products = array();
		if ( in_array( $product->get_sku(), $four_plus_two_products ) ) {
			$labels['promotion'] = 'Promo 4+2 gratis';
		}
		
		$five_plus_one_products = array();
		if ( in_array( $product->get_sku(), $five_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 5+1 gratis';
		}
	}

	if ( $product->get_date_created() !== NULL and $product->get_date_created()->date_i18n('Y-m-d') > date_i18n( 'Y-m-d', strtotime('-3 months') ) ) {
		$labels['newbee'] = 'Nieuw';
	}

	if ( stripos( $product->get_attribute('preferences'), 'biologisch' ) !== false ) {
		$labels['organic'] = 'Bioproduct';
	}

	if ( ! is_main_site() and ! does_risky_delivery() ) {
		if ( $product->get_shipping_class() === 'breekbaar' ) {
			$labels['pickup-only'] = 'Afhaling';
		}
	}
	
	if ( $product->get_sku() === '20263' and date_i18n('Y-m-d') >= '2021-03-01' and date_i18n('Y-m-d') < '2021-09-01' ) {
		$labels['beste-van-de-test'] = '';
	}

	if ( count( $labels ) > 0 ) {
		echo '<ul class="info-labels">';
		foreach ( $labels as $class => $label ) {
			echo '<li class="info-label '.$class.'">'.$label.'</li>';
		}
		echo '</ul>';
	}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
