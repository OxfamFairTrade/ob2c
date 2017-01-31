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
	 * @version     1.6
	 *
	 */

	// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
	require_once '../../../wp-blog-header.php';

	// Register autoloader
	require_once '../../plugins/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();

	// Define configuration
	$partner_id = 2485891;
	$profile_key = 'C556F53A';

	// Instantiate Mollie class
	$mollie = new Mollie_Reseller( $partner_id, $profile_key, MOLLIE_APIKEY );

	$partner_id_customer = '2842281';

	try {
		// $simplexml = $mollie->accountCreate( 'owwoostende', array( 'name' => 'Frederik Neirynck', 'company_name' => 'Oxfam-Wereldwinkel Oostende', 'address' => 'Torhoutsesteenweg 25', 'zipcode' => '8400', 'city' => 'Oostende', 'country' => 'BE', 'email' => 'frederik.neirynck@oft.be', 'registration_number' => '0446474568', 'legal_form' => 'vzw-be', 'vat_number' => 'BE0446474568', 'representative' => 'Edwin Vanden Abeele', 'billing_address' => 'Torhoutsesteenweg 25', 'billing_zipcode' => '8400', 'billing_city' => 'Oostende', 'billing_country' => 'BE' ) );
		// $simplexml = $mollie->profileCreateByPartnerId( $partner_id_customer, array( 'name' => 'Oxfam-Wereldwinkel Oostende', 'website' => 'http://shop.oxfamwereldwinkels.be/oostende', 'email' => 'frederik.neirynck@oft.be', 'phone' => '059513700', 'category' => '5499' ) );
		// $simplexml = $mollie->availablePaymentMethodsByPartnerId( $partner_id_customer );
		$simplexml = $mollie->getLoginLink( $partner_id_customer );
	} catch (Mollie_Exception $e) {
		die('An error occurred: '.$e->getMessage());
	}

	if ( $simplexml->success == 'true' ) {
		echo "<a href='".$simplexml->redirect_url."' target='_blank'>Log zonder wachtwoord in op je Mollie-betaalaccount!</a>";
	} else {
		var_dump($simplexml);
	}
?>