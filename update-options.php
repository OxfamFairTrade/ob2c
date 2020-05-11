<?php
	if ( ! defined('ABSPATH') ) exit;

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
?>

<div class="wrap">
	<h1>Handige gegevens voor je lokale webshop</h1>
	
	<form method="post" action="options.php">
		<table class="form-table" id="oxfam-options">
			<tr valign="top">
				<th class='left'><a href='https://outlook.office.com/oww.be?login_hint=<?php echo get_option('admin_email'); ?>' target='_blank'>Log in op je Office 365-mailaccount &raquo;</a></th>
				<td class='right'>Merk op dat het wachtwoord van deze mailbox volledig los staat van de webshop.</td>
			</tr>

			<?php
				settings_errors();
				// Strikter onderscheid maken tussen opties die nationaal of lokaal beheerd worden (want anders toch bereikbaar door HTML te manipuleren)
				if ( current_user_can( 'create_sites' ) ) {
					settings_fields( 'oxfam-options-global' );
				} else {
					// Er lijkt slechts één groep per keer opgeslagen te kunnen worden!
					// BEVAT GEEN OPTIES MEER MAAR LATEN STAAN VOOR DE TOEKOMST
					// settings_fields( 'oxfam-options-local' );
				}

				// IS NIET MEER BESCHIKBAAR
				// Mollie_Autoloader::register();
				// $mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
				$partner_id_customer = get_option( 'oxfam_mollie_partner_id', 2485891 );
				
				// Check of we niet op de hoofdaccount zitten, want anders fatale API-error
				if ( $partner_id_customer != 2485891 and $partner_id_customer > 2000000 and 1 === 2 ) {
					// Verhinder doorklikken naar echte account op demosites
					if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
						$login_link = $mollie->getLoginLink( $partner_id_customer );
						$href = $login_link->redirect_url;
					} else {
						$href = 'https://www.mollie.com/dashboard/';
					}
					echo "<tr>";
						echo "<th class='left'><a href='".$href."' target='_blank'>Log volautomatisch in op je Mollie-betaalaccount &raquo;</a></th>";
						echo "<td class='right'>Opgelet: deze link is slechts 60 seconden geldig! Herlaad desnoods even deze pagina.</td>";
					echo "</tr>";

					$methods = $mollie->availablePaymentMethodsByPartnerId( $partner_id_customer );
					if ( $methods->resultcode == '10' ) {
						$lacking = array();
						$must_be = array( 'mistercash', 'creditcard', 'belfius', 'kbc', 'ideal' );
						foreach ( $must_be as $service ) {
							if ( $methods->services->{$service} == 'false' ) {
								$lacking[] = $service;
							}
						}

						if ( count($lacking) > 0 ) {
							echo "<tr>";
								echo "<th class='left' style='color: red;'>Activeer volgende verplichte betaalmethodes:</th>";
								echo "<td class='right'>";
									foreach ( $lacking as $service ) {
										echo strtoupper($service)."&nbsp;&nbsp;&nbsp;";
									}
								echo "</td>";
							echo "</tr>";	
						}
					}

					$profiles = $mollie->profilesByPartnerId( $partner_id_customer );
					if ( $profiles->resultcode == '10' ) {
						if ( get_company_name() != trim_and_uppercase($profiles->items->profile->name) ) {
							// $name_warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een andere bedrijfsnaam geregistreerd!</small>";
						}
						// Fix voor winkels met twee nummers (bv. Mariakerke)
						$phones = explode( ' of ', get_oxfam_shop_data('telephone') );
						$warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een ander contactnummer geregistreerd!</small>";
						if ( $phones[0] != format_telephone( '0'.substr( $profiles->items->profile->phone, 2 ), '.' ) ) {
							if ( count($phones) === 2 ) {
								if ( $phones[1] != format_telephone( '0'.substr( $profiles->items->profile->phone, 2 ), '.' ) ) {
									$phone_warning = $warning;
								}
							} else {
								$phone_warning = $warning;
							}
						}
						if ( get_company_email() != $profiles->items->profile->email ) {
							$mail_warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een ander contactadres geregistreerd!</small>";
						}
					}

					$accounts = $mollie->bankAccountsByPartnerId( $partner_id_customer );
					if ( $accounts->resultcode == '10' ) {
						if ( get_oxfam_shop_data('account') !== format_account( $accounts->items->bankaccount->iban_number ) ) {
							$account_warning = "<br/><small style='color: red;'>Opgelet, dit rekeningnummer is (nog) niet bij Mollie geverifieerd!</small>";
						}
					}
				} else {
					echo "<tr>";
						echo "<th class='left' style='color: red;'>API ERROR</th>";
						echo "<td class='right'>Gelieve een geldige Mollie-ID op te geven!</td>";
					echo "</tr>";
				}
				if ( does_sendcloud_delivery() ) {
					echo "<tr>";
						echo "<th class='left'><a href='https://panel.sendcloud.sc/' target='_blank'>Log in op je SendCloud-verzendaccount &raquo;</a></th>";
						echo "<td class='right'>Merk op dat het wachtwoord van deze account volledig los staat van de webshop.</td>";
					echo "</tr>";
				}
			?>

			<tr valign="top">
				<th class="left">
					<label for="blog_id">Blog-ID:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="blog_id" class="text-input" value="<?php echo get_current_blog_id(); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_shop_post_id" title="Aan de hand van deze ID halen we adressen en openingsuren op uit de database achter de publieke site van Oxfam-Wereldwinkels.">Post-ID OWW-site:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_shop_post_id" class="text-input" value="<?php echo get_option('oxfam_shop_post_id'); ?>"<?php if ( ! current_user_can( 'create_sites' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_mollie_partner_id" title="Je betaalaccount valt onder het contract dat Oxfam Fair Trade sloot met Mollie. Aan de hand van deze ID kunnen we de nodige API-keys invullen en in geval van nood inloggen op jullie lokale account.">Partner-ID Mollie:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_mollie_partner_id" class="text-input" value="<?php echo esc_attr( get_option('oxfam_mollie_partner_id') ); ?>"<?php if ( ! current_user_can( 'create_sites' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<?php
				if ( is_regional_webshop() ) {
					echo "<tr valign='top'>";
						echo "<th class='left'><label for='oxfam_member_shops' title=''>Regiosamenwerking</label></th>";
						echo "<td class='right'>";
							echo "<input type='text' name='oxfam_member_shops' class='text-input' value='".esc_attr( trim_and_uppercase( implode( ', ', get_option('oxfam_member_shops') ) ) )."'";
							if ( ! current_user_can( 'create_sites' ) ) echo " readonly";
							echo ">";
						echo "</td>";
					echo "</tr>";
				}
				$b2b_shipping_options = get_option('woocommerce_b2b_home_delivery_settings');
				$oww_store_data = get_external_wpsl_store( intval( get_option('oxfam_shop_post_id') ) );
			?>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_zip_codes" title="Om tegenstrijdige data te vermijden toont deze optie in de toekomst best uit alle postcodes uit de ingeschakelde verzendzones op deze site, maar voorlopig stellen we dit handmatig in. (Heeft ook als voordeel dat we de postcodecheck bij het afrekenen minder rigide kunnen maken.)">Postcodes voor thuislevering (<?php echo count( get_option('oxfam_zip_codes') ); ?>):<br/><small>Dit kan omwille van databaseconsistentie enkel vanuit het NS gewijzigd worden.</small></label>
				</th>
		  		<td class="right">
		  			<textarea name="oxfam_zip_codes" rows="3" class="text-input" placeholder="<?php echo implode( ', ', get_oxfam_covered_zips() ); ?>" <?php if ( ! current_user_can( 'create_sites' ) ) echo ' readonly'; ?>><?php echo esc_textarea( implode( ', ', get_option('oxfam_zip_codes') ) ); ?></textarea>
		  		</td>
			</tr>
			<!-- Deze instelling maakt geen deel meer uit van de geregistreerde opties en worden dus niet automatisch bijgewerkt! -->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_holidays" title="Deze dagen tellen niet mee in de berekening van de levertermijn. Bovendien zal op deze dagen onderaan de webshop een banner verschijnen zodat het voor de klanten duidelijk is dat jullie winkel gesloten is.">Uitzonderlijke sluitingsdagen:<br/><small>Deze datums worden vanaf nu overgenomen uit <a href="<?php echo $oww_store_data['link']; ?>" target="_blank">jullie winkelpagina op oxfamwereldwinkels.be</a>. Het algoritme voor de uiterste leverdatum houdt rekening met deze dagen voor <u>alle levermethodes en afhaalpunten</u>.</small></label>
				</th>
		  		<td class="right">
		  			<textarea name="oxfam_holidays" rows="3" class="text-input" readonly><?php echo esc_textarea( implode( ', ', get_option( 'oxfam_holidays', get_site_option('oxfam_holidays') ) ) ); ?></textarea>
		  		</td>
			</tr>
			<!-- tr valign="top">
				<th class="left">
					<label for="woocommerce_local_pickup_plus_enabled">Afhaling in de winkel tijdelijk volledig uitschakelen?<br/><small>Wereldwinkels die in het kader van de maatregelen tegen de verspreiding van het coronavirus hun winkel sluiten, kunnen voor alle duidelijkheid afhaling in de winkel volledig uitschakelen in hun webshop. Opgelet: indien je slechts enkele dagen sluit en/of beperktere openingsuren hanteert, dien je deze aanpassingen gewoon door te geven via je winkelpagina op oxfamwereldwinkels.be!</small></label>
				</th>
		  		<td class="right">
		  			<input type="checkbox" name="woocommerce_local_pickup_plus_enabled" value="yes" <?php // checked( get_option('woocommerce_local_pickup_plus_enabled'), 'yes' ); ?>>
		  		</td>
			</tr -->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_b2b_delivery_enabled">B2B-levering beschikbaar?<br/><small>Standaard is levering op locatie beschikbaar voor alle B2B-klanten (ongeacht de postcode in hun verzendadres), maar op termijn kun je dit hier uitschakelen.</small></label>
				</th>
		  		<td class="right">
		  			<input type="checkbox" name="oxfam_b2b_delivery_enabled" value="yes" <?php checked( $b2b_shipping_options['enabled'], 'yes' ); ?> disabled>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_b2b_delivery_cost" title="">Kostprijs voor B2B-levering:<br/><small>Indien ingeschakeld verschijnt de levering op locatie voor alle B2B-klanten als gratis, maar op termijn kun je hier een uniform tarief instellen (indien gewenst).</small></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_b2b_delivery_cost" class="text-input" value="<?php echo strip_tags( wc_price( $b2b_shipping_options['cost'] ) ).' excl. BTW'; ?>" readonly>
		  		</td>
			</tr>
			<?php
				if ( current_user_can( 'create_sites' ) ) {
					echo "<tr><th class='left'></th><td class='right'>";
					submit_button();
					echo "</td></tr>";
				}
			?>
			<!-- Deze 'instellingen' maken geen deel uit van de geregistreerde opties en worden dus niet automatisch opgeslagen in database! -->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_tax" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">BTW-nummer: <?php if ( isset($tax_warning) ) echo $tax_warning; ?><br/><small><a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer=<?php echo str_replace( 'BE ', '', get_oxfam_shop_data('tax') ); ?>&actionlu=zoek" target="_blank">Kloppen jullie gegevens in de KBO-databank nog?</a></small></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_tax" class="text-input" value="<?php echo get_oxfam_shop_data('tax'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_account" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">IBAN-rekeningnummer: <?php if ( isset($account_warning) ) echo $account_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_account" class="text-input" value="<?php echo get_oxfam_shop_data('account'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_company" title="Dit is ook de titel van deze subsite en kan enkel door Frederik gewijzigd worden.">Bedrijfsnaam: <?php if ( isset($name_warning) ) echo $name_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_company" class="text-input" value="<?php echo get_company_name(); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_place" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Straat en huisnummer:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_place" class="text-input" value="<?php echo get_oxfam_shop_data('place'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_zipcode" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Postcode:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_zipcode" class="text-input" value="<?php echo get_oxfam_shop_data('zipcode'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_city" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Gemeente:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_city" class="text-input" value="<?php echo get_oxfam_shop_data('city'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_city" title="Zie je een fout staan? Werk je telefoonnummer bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Telefoonnummer: <?php if ( isset($phone_warning) ) echo $phone_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_telephone" class="text-input" value="<?php echo get_oxfam_shop_data('telephone'); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_email" title="Deze Office 365-mailbox wordt ingesteld als het algemene contactadres van deze subsite en is initieel ook ekoppeld aan de lokale beheeraccount. Opgelet: via de profielpagina kun je deze hoofdgebruiker aan een andere mailbox linken (of schakelen we dat uit? niet handig indien we voor meerdere lokale beheerders opteren!) maar het contactadres naar klanten blijft altijd dit e-mailadres!">E-mailadres: <?php if ( isset($mail_warning) ) echo $mail_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_email" class="text-input" value="<?php echo get_company_email(); ?>" readonly>
		  		</td>
			</tr>
		</table>
	</form>
</div>