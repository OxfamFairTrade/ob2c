<?php
	/**
	 * Copyright (c) 2012, Mollie B.V.
	 * All rights reserved.
	 *
	 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
	 * @author      Mollie B.V. <info@mollie.com>
	 * @copyright   Copyright © 2012 Mollie B.V.
	 * @link        https://www.mollie.com
	 * @category    Mollie
	 * @version     1.10.4
	 *
	 */

	// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
	require_once '../../../wp-load.php';

	if ( $_GET['import_key'] !== IMPORT_KEY ) {
		die( "Access prohibited!" );
	}

	$blog_id_not_wp = 44;
	$login = 'diksmuide';
	$email = 'webshop.diksmuide@oxfamwereldwinkels.be';
	$shop_id = 3386;
	$fname = 'Rik';
	$lname = 'Maekelberg';
	
	switch_to_blog( $blog_id_not_wp );

	// Register autoloader
	require_once WP_PLUGIN_DIR.'/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();

	// Instantiate Mollie class
	$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
	
	try {
		// Parameters op te halen uit site
		// BIJ REGIOWERKINGEN KAN DIT AFWIJKEN (= NAAM REKENINGWINKEL)
		$address = get_oxfam_shop_data('place');
		$zip = get_oxfam_shop_data('zipcode');
		$city = get_oxfam_shop_data('city');
		$phone = '32'.str_replace( '/', '', str_replace( '.', '', substr( get_oxfam_shop_data('telephone'), 1 ) ) );
		$email = get_blog_option( $blog_id_not_wp, 'admin_email' );
		$btw = str_replace( ' ', '', str_replace( '.', '', get_oxfam_shop_data('tax') ) );
		$iban = str_replace( ' ', '', get_oxfam_shop_data('account') );
		$company = get_bloginfo('name');
		$url = get_bloginfo('url');
		
		// HEADQUARTER IS LEEG SINDS NIEUWE OWW-SITE
		// $headquarter = get_oxfam_shop_data('headquarter');
		// $lines = explode( ', ', $headquarter, 2 );
		// $billing_address = trim($lines[0]);
		// $parts = explode( ' ', $lines[1], 2 );
		// $billing_zip = trim($parts[0]);
		// $billing_city = trim($parts[1]);
		
		$login = 'oww'.$login;
		$representative = $fname.' '.$lname;
		$billing_address = $address;
		$billing_zip = $zip;
		$billing_city = $city;
		
		// Check of we deze KBO-parameters niet handmatig moeten overrulen
		// Moet in de praktijk toch opnieuw in Mollie, dus niet zo belangrijk
		// $representative = '';
		// $billing_address = '';
		
		// $bic = 'NICABEBB';
		$bic = 'AXABBE22';
		// $bic = 'GEBABEBB';
		// $bic = 'GKCCBEBB';
		// $bic = 'HBKABE22';
		// $bic = 'KREDBEBB';
		// $bic = 'VDSPBE91';
		// $bic = 'ARSPBE22';

		$parameters = array( 
			'name' => $representative,
			'company_name' => $company,
			'url' => $url,
			'address' => $address,
			'zipcode' => $zip,
			'city' => $city,
			'country' => 'BE',
			'email' => $email,
			'registration_number' => str_replace( 'BE', '', $btw ),
			'legal_form' => 'vzw-be',
			'vat_number' => $btw,
			'representative' => $representative,
			'billing_address' => $billing_address,
			'billing_zipcode' => $billing_zip,
			'billing_city' => $billing_city,
			'billing_country' => 'BE',
			'bankaccount_iban' => $iban,
			'bankaccount_bic' => $bic,
			'locale' => 'nl_BE',
		);

		echo '<pre>'.var_export( $parameters, true ).'</pre>';
		echo '<a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer='.str_replace( 'BE', '', $btw ).'&actionLu=Zoek" target="_blank">KBO-fiche</a><br/>';
		echo '<a href="https://www.ibanbic.be/default.aspx?textboxBBAN='.$iban.'" target="_blank">BIC-code</a>';
		
		// UITSCHAKELEN INDIEN VOOR ECHT!
		$parameters['testmode'] = 1;
		
		$accountxml = $mollie->accountCreate( $login, $parameters );
		echo '<pre>'.var_export( $accountxml, true ).'</pre>';
	} catch (Mollie_Exception $e) {
		die( "An error occurred: ".$e->getMessage() );
	}

	if ( $accountxml->resultcode == '10' and ! array_key_exists( 'testmode', $parameters ) ) {
		echo "<p>".$accountxml->resultmessage."</p>";
		
		echo "Partner-ID: ".$accountxml->partner_id."<br/>";
		// SimpleXML-node expliciet casten naar string indien geen context!
		if ( update_blog_option( $blog_id_not_wp, 'oxfam_mollie_partner_id', (string) $accountxml->partner_id ) ) {
			echo "Partner-ID gewijzigd in webshop!<br/>";
		}

		echo "Wachtwoord: ".$accountxml->password."<br/>";
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_set_password( (string) $accountxml->password, $user->ID );
			echo "Wachtwoord gekopieerd naar lokale beheerder!<br/>";
		}

		echo "<p>&nbsp;</p>";

		$profilexml = $mollie->profileCreateByPartnerId( $accountxml->partner_id, $company, $url, $email, $phone, 5499 );
		
		if ( $profilexml->resultcode == '10' ) {
			echo "<p>".$profilexml->resultmessage."</p>";
			
			echo "LIVE API: ".$profilexml->profile->api_keys->live."<br/>";
			if ( update_blog_option( $blog_id_not_wp, 'mollie-payments-for-woocommerce_live_api_key', (string) $profilexml->profile->api_keys->live ) ) {
				echo "Live API-key gewijzigd in webshop!<br/>";	
			}

			echo "TEST API: ".$profilexml->profile->api_keys->test."<br/>";
			if ( update_blog_option( $blog_id_not_wp, 'mollie-payments-for-woocommerce_test_api_key', (string) $profilexml->profile->api_keys->test ) ) {
				echo "Test API-key gewijzigd in webshop!<br/>";
			}
		} else {
			echo '<pre>'.var_export( $profilexml, true ).'</pre>';
		}
	} else {
		echo '<pre>'.var_export( $accountxml, true ).'</pre>';
	}

	restore_current_blog();
?>