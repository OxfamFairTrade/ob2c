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
	 * @version     1.8
	 *
	 */

	// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
	require_once '../../../wp-blog-header.php';

	if ( $_GET['import_key'] !== IMPORT_KEY ) {
		die("Helaba, dit mag niet!");
	}

	// Register autoloader
	require_once '../../plugins/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();

	// Instantiate Mollie class
	$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
	
	switch_to_blog( 10 );

	try {
		// Parameters op te halen uit site
		// BIJ REGIOWERKINGEN KAN DIT AFWIJKEN (= NAAM REKENINGWINKEL)
		$company = get_bloginfo('name');
		$address = get_oxfam_shop_data('place');
		$zip = get_oxfam_shop_data('zipcode');
		$city = get_oxfam_shop_data('city');
		// $phone = '32'.substr( get_oxfam_shop_data('telephone'), 1 );
		$phone = '3292822463';
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
		$login = 'owwdepinte';
		$name = 'Jos Dekeukeleire';
		$representative = 'Jos Dekeukeleire';
		$bic = 'AXABBE22';
		// $bic = 'GEBABEBB';
		// $bic = 'GKCCBEBB';

		// Lijken toch niet noodzakelijk te zijn: 'bankaccount_bankname' => 'BNP Paribas Fortis', 'bankaccount_location' => 'Brussel'
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

		// UITSCHAKELEN INDIEN VOOR ECHT!
		$parameters['testmode'] = 1;
		$accountxml = $mollie->accountCreate( $login, $parameters );

		// $partner_id_customer = '3169994';
		$edit_parameters = array( 
			'registration_number' => str_replace( 'BE', '', $btw ),
			'vat_number' => $btw,
		);
		// $accountxml = $mollie->accountEditByPartnerId( $partner_id_customer, $edit_parameters );
	} catch (Mollie_Exception $e) {
		die('An error occurred: '.$e->getMessage());
	}

	if ( $accountxml->resultcode == '10' ) {
		echo "<p>".$accountxml->resultmessage."</p>";
		echo "<p>Partner-ID: ".$accountxml->partner_id."</p>";
		echo "<p>Wachtwoord: ".$accountxml->password."</p>";
		echo "<p>Telefoon: ".$phone."</p>";
		
		$profilexml = $mollie->profileCreateByPartnerId( $accountxml->partner_id, $blog, $url, $email, $phone, 5499 );
		if ( update_option( 'oxfam_mollie_partner_id', $accountxml->partner_id ) ) {
			echo "<p>Partner-ID gewijzigd in webshop!</p>";
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_set_password( $accountxml->password, $user->ID );
			echo "<p>Wachtwoord gekopieerd naar lokale beheerder!</p>";
		}

		echo "<p></p>";

		if ( $profilexml->resultcode == '10' ) {
			echo "<p>".$profilexml->resultmessage."</p>";
			echo "<p>LIVE API: ".$profilexml->profile->api_keys->live."</p>";
			echo "<p>TEST API: ".$profilexml->profile->api_keys->test."</p>";
			if ( update_option( 'mollie-payments-for-woocommerce_live_api_key', $profilexml->profile->api_keys->live ) ) {
				echo "<p>Live API-key gewijzigd in webshop!</p>";
				if ( update_option( 'mollie-payments-for-woocommerce_test_mode_enabled', 'no' ) ) {
					echo "<p>Testbetalingen uitgeschakeld!</p>";
				}
			}
			if ( update_option( 'mollie-payments-for-woocommerce_test_api_key', $profilexml->profile->api_keys->test ) ) {
				echo "<p>Test API-key gewijzigd in webshop!</p>";
			}

			echo "<p></p>";
		} else {
			echo '<pre>'.var_export($profilexml, true).'</pre>';
		}
	} else {
		echo '<pre>'.var_export($accountxml, true).'</pre>';
	}

	restore_current_blog();
?>