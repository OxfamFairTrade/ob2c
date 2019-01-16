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

	// Register autoloader
	require_once WP_PLUGIN_DIR.'/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();

	// Instantiate Mollie class
	$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
	
	switch_to_blog( 40 );

	try {
		// Parameters op te halen uit site
		// BIJ REGIOWERKINGEN KAN DIT AFWIJKEN (= NAAM REKENINGWINKEL)
		$company = get_bloginfo('name');
		$address = get_oxfam_shop_data('place');
		$zip = get_oxfam_shop_data('zipcode');
		$city = get_oxfam_shop_data('city');
		$phone = '32'.str_replace( '/', '', str_replace( '.', '', substr( get_oxfam_shop_data('telephone'), 1 ) ) );
		$email = get_bloginfo('admin_email');
		$btw = str_replace( ' ', '', str_replace( '.', '', get_oxfam_shop_data('tax') ) );
		$headquarter = get_oxfam_shop_data('headquarter');
		$lines = explode( ', ', $headquarter, 2 );
		$billing_address = trim($lines[0]);
		$parts = explode( ' ', $lines[1], 2 );
		$billing_zip = trim($parts[0]);
		$billing_city = trim($parts[1]);
		$iban = str_replace( ' ', '', get_oxfam_shop_data('account') );
		$blog = get_bloginfo('name');
		$url = get_bloginfo('url');
		
		// Parameters handmatig in te vullen
		$login = 'owwmechelen';
		$name = '';
		$representative = '';
		// $bic = 'NICABEBB';
		// $bic = 'AXABBE22';
		$bic = 'GEBABEBB';
		// $bic = 'GKCCBEBB';
		// $bic = 'HBKABE22';
		// $bic = 'KREDBEBB';
		// $bic = 'VDSPBE91';

		$parameters = array( 
			'name' => $name, 
			'company_name' => $company, 
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
		);

		echo '<pre>'.var_export( $parameters, true ).'</pre>';

		// UITSCHAKELEN INDIEN VOOR ECHT!
		$parameters['testmode'] = 1;
		$accountxml = $mollie->accountCreate( $login, $parameters );

		$partner_id_customer = '5271984';
		$edit_parameters = array( 
			'registration_number' => str_replace( 'BE', '', $btw ),
			'vat_number' => $btw,
		);
		// $accountxml = $mollie->accountEditByPartnerId( $partner_id_customer, $edit_parameters );
	} catch (Mollie_Exception $e) {
		die( "An error occurred: ".$e->getMessage() );
	}

	if ( $accountxml->resultcode == '10' ) {
		echo "<p>".$accountxml->resultmessage."</p>";
		
		echo "Partner-ID: ".$accountxml->partner_id."<br/>";
		// Let op dat we de SimpleXML-node converteren naar een string!
		if ( update_option( 'oxfam_mollie_partner_id', $accountxml->partner_id->__toString() ) ) {
			echo "Partner-ID gewijzigd in webshop!<br/>";
		}
		
		echo "Wachtwoord: ".$accountxml->password."<br/>";
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_set_password( $accountxml->password->__toString(), $user->ID );
			echo "Wachtwoord gekopieerd naar lokale beheerder!<br/>";
		}

		if ( update_option( 'woocommerce_email_from_name', $company ) ) {
			echo "Afzender gewijzigd naar ".$company."!<br/>";
		}

		if ( update_option( 'woocommerce_email_from_address', $email ) ) {
			echo "Afzendadres gewijzigd naar ".$email."!<br/>";
		}

		echo "<p>&nbsp;</p>";

		$profilexml = $mollie->profileCreateByPartnerId( $accountxml->partner_id, $blog, $url, $email, $phone, 5499 );
		
		if ( $profilexml->resultcode == '10' ) {
			echo "<p>".$profilexml->resultmessage."</p>";
			
			echo "LIVE API: ".$profilexml->profile->api_keys->live."<br/>";
			if ( update_option( 'mollie-payments-for-woocommerce_live_api_key', $profilexml->profile->api_keys->live->__toString() ) ) {
				echo "Live API-key gewijzigd in webshop!<br/>";	
			}

			echo "TEST API: ".$profilexml->profile->api_keys->test."<br/>";
			if ( update_option( 'mollie-payments-for-woocommerce_test_api_key', $profilexml->profile->api_keys->test->__toString() ) ) {
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