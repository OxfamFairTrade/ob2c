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
	$partner_id_customer = '2842281';

	try {
		
		// Parameters op te halen uit site
		switch_to_blog( 10 );
			// BIJ REGIOWERKINGEN KAN DIT AFWIJKEN (= NAAM REKENINGWINKEL)
			$company = get_bloginfo('name');
			$address = get_oxfam_shop_data('place');
			$zip = get_oxfam_shop_data('zipcode');
			$city = get_oxfam_shop_data('city');
			$phone = get_oxfam_shop_data('telephone');
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
		restore_current_blog();

		// Parameters handmatig in te vullen
		$login = 'owwbrugge';
		$name = 'Wim Spanhove';
		$representative = 'Wim Spanhove';
		$bic = 'GEBABEBB';

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
		
		echo '<pre>'.var_export($parameters, true).'</pre>';

		// UITSCHAKELEN INDIEN VOOR ECHT!
		$parameters['testmode'] = 1;
		$simplexml = $mollie->accountCreate( $login, $parameters );

		unset($parameters['testmode']);
		// $simplexml = $mollie->accountEditByPartnerId( $partner_id_customer, $parameters );

	} catch (Mollie_Exception $e) {
		die('An error occurred: '.$e->getMessage());
	}

	if ( $simplexml->resultcode == '10' ) {
		echo "<p>".$simplexml->resultmessage."</p>";
		echo "<p>Partner-ID: ".$simplexml->partner_id."</p>";
		echo "<p>Wachtwoord: ".$simplexml->password."</p>";
		echo "<p></p>";
		
		// $simplexml = $mollie->profileCreateByPartnerId( $partner_id_customer, $blog, $url, $email, $phone, 5499 );

		if ( $simplexml->resultcode == '10' ) {
			echo "<p>".$simplexml->resultmessage."</p>";
			echo "<p>LIVE API: ".$simplexml->profile->api_keys->live."</p>";
			echo "<p></p>";
		} else {
			echo '<pre>'.var_export($simplexml, true).'</pre>';
		}
	} else {
		echo '<pre>'.var_export($simplexml, true).'</pre>';
	}
?>