<?php 
	global $product;
	$labels = array();

	if ( $product->get_date_created()->date_i18n('Y-m-d') > date_i18n( 'Y-m-d', strtotime('-3 months') ) ) {
		$labels['newbee'] = 'Nieuw';
	}

	// Zal nog vervangen worden door een taxonomie i.p.v. attribuut
	if ( $product->get_attribute('bio') === 'Ja' ) {
		$labels['organic'] = 'Bioproduct';
	}

	if ( ! is_b2b_customer() and has_term( 'promotie', 'product_tag', $product->get_id() ) ) {
		$labels['promotion'] = 'Promo';
	}

	if ( ! does_risky_delivery() ) {
		if ( $product->get_shipping_class() === 'breekbaar' ) {
			$labels['pickup-only'] = 'Afhaling';
		}
	}

	// Indien promo reeds actief: toon ook de details van de actie
	// We beheren deze lijst handmatig, omdat het simpeler is dan het proberen af te leiden uit de kortingsregels
	if ( array_key_exists( 'promotion', $labels ) ) {
		$previous_count = count( $labels );

		$one_plus_one_products = array();
		if ( in_array( $product->get_sku(), $one_plus_one_products ) ) {
			$labels['promotion one-plus-one'] = 'Promo 1+1 gratis';
		}
		
		$fifty_percent_off_second_products = array( '21052', '24532', '25404', '25405', '25406', '27151' );
		if ( in_array( $product->get_sku(), $fifty_percent_off_second_products ) ) {
			$labels['promotion fifty-percent-off'] = 'Promo 2de -50%';
		}
		
		$two_plus_one_products = array( '24102', '24117' );
		if ( in_array( $product->get_sku(), $two_plus_one_products ) ) {
			$labels['promotion two-plus-one'] = 'Promo 2+1 gratis';
		}
		
		// Ons testproduct
		$three_plus_one_products = array( '26424' );
		if ( in_array( $product->get_sku(), $three_plus_one_products ) ) {
			$labels['promotion three-plus-one'] = 'Promo 3+1 gratis';
		}
		
		$twentyfive_percent_off_products = array();
		if ( in_array( $product->get_sku(), $twentyfive_percent_off_products ) ) {
			$labels['promotion twenty-five-percent-off'] = 'Promo -25%';
		}

		if ( count( $labels ) > $previous_count ) {
			// De promo werd gespecifieerd, verwijder het algemene label
			unset( $labels['promotion'] );
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
