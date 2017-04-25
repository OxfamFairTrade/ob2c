<div class="wrap">
	<h1>Instellingen voor lokale webshop</h1>
	
	<form method="post" action="options.php">
		<table class="form-table" id="oxfam-options">
			<?php
				settings_fields( 'oxfam-option-group' );
				do_settings_sections( 'oxfam-option-group' );

				Mollie_Autoloader::register();
				$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
				$partner_id_customer = get_option( 'oxfam_mollie_partner_id' );
				
				// Check of we niet op de hoofdaccount zitten, want dan krijgen we een fatale error bij getLoginLink()
				if ( $partner_id_customer != 2485891 ) {
					echo "<tr>";
						echo "<th class='left'><a href='https://login.microsoftonline.com/' target='_blank'>Log in op je Office 365-mailaccount &raquo;</a></th>";
						echo "<td class='right'>Merk op dat het wachtwoord van deze mailbox volledig los staat van de webshop.</td>";
					echo "</tr>";

					$login_link = $mollie->getLoginLink( $partner_id_customer );
					echo "<pre>".var_export($login_link, true)."</pre>";
					echo "<tr>";
						echo "<th class='left'><a href='".$login_link->redirect_url."' target='_blank'>Log volautomatisch in op je Mollie-betaalaccount &raquo;</a></th>";
						echo "<td class='right'>Opgelet: deze link is slechts enkele minuten geldig! Herlaad desnoods even deze pagina.</td>";
					echo "</tr>";

					$methods = $mollie->availablePaymentMethodsByPartnerId( $partner_id_customer );
					if ( $methods->resultcode == '10' ) {
						$must_be = array( 'mistercash', 'creditcard', 'belfius', 'kbc', 'banktransfer', 'ideal' );
						foreach ( $must_be as $service ) {
							if ( $methods->services->{$service} == 'false' ) {
								$lacking[] = $service;
							}
						}

						if ( count($lacking) > 0 ) {
							echo "<tr>";
								echo "<th class='left' style='color: red;'>Activeer dringend deze verplichte betaalmethodes:</th>";
								echo "<td class='right'>";
									foreach ( $lacking as $service ) {
										echo strtoupper($service)."&nbsp;&nbsp;&nbsp;&nbsp;";
									}
								echo "</td>";
							echo "</tr>";	
						}
					}

					$profiles = $mollie->profilesByPartnerId( $partner_id_customer );
					if ( $profiles->resultcode == '10' ) {
						if ( get_oxfam_shop_data( 'company_name' ) != trim_and_uppercase($profiles->items->profile->name) ) {
							$name_warning = "<br><small style='color: red;'>Opgelet, bij Mollie staat een andere bedrijfsnaam geregistreerd!</small>";
						}
						if ( get_oxfam_shop_data( 'telephone' ) != format_telephone($profiles->items->profile->phone) ) {
							$phone_warning = "<br><small style='color: red;'>Opgelet, bij Mollie staat een ander contactnummer geregistreerd!</small>";
						}
						if ( get_company_email() != $profiles->items->profile->email ) {
							$mail_warning = "<br><small style='color: red;'>Opgelet, bij Mollie staat een ander contactadres geregistreerd!</small>";
						}
					}

					$accounts = $mollie->bankAccountsByPartnerId( $partner_id_customer );
					write_log($accounts);
					
					if ( does_sendcloud_delivery() ) {
						echo "<tr>";
							echo "<th class='left'><a href='https://panel.sendcloud.sc/' target='_blank'>Log in op je SendCloud-verzendaccount &raquo;</a></th>";
							echo "<td class='right'>Merk op dat het wachtwoord van deze account volledig los staat van de webshop.</td>";
						echo "</tr>";
					}
				}
			?>

			<tr valign="top">
				<th class="left">
					<label for="oxfam_shop_node" title="Aan de hand van deze ID halen we adressen en openingsuren op uit de database achter de publieke site van Oxfam-Wereldwinkels.">Nodenummer OWW-site:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_shop_node" class="text-input" value="<?php echo esc_attr( get_option('oxfam_shop_node') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_mollie_partner_id" title="Je betaalaccount valt onder het contract dat Oxfam Fair Trade sloot met Mollie. Aan de hand van deze ID kunnen we de nodige API-keys invullen en in geval van nood inloggen op jullie lokale account.">Partner-ID Mollie:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_mollie_partner_id" class="text-input" value="<?php echo esc_attr( get_option('oxfam_mollie_partner_id') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_zip_codes" title="Om tegenstrijdige data te vermijden toont deze optie in de toekomst best uit alle postcodes uit de ingeschakelde verzendzones op deze site, maar voorlopig stellen we dit handmatig in. (Heeft ook als voordeel dat we de postcodecheck bij het afrekenen minder rigide kunnen maken.)">Postcodes voor thuislevering:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_zip_codes" class="text-input" value="<?php echo implode ( ", ", get_option('oxfam_zip_codes') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>

			<!-- Deze 'instellingen' maken geen deel uit van de geregistreerde opties en worden dus niet automatisch opgeslagen in database!-->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_tax" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">BTW-nummer:<br><small><a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer=<?php echo str_replace( 'BE ', '', get_oxfam_shop_data( 'tax' ) ); ?>&actionlu=zoek" target="_blank">Kloppen onze gegevens in de KBO-databank nog?</a></small></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_tax" class="text-input" value="<?php echo get_oxfam_shop_data( 'tax' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_account" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">IBAN-rekeningnummer:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_account" class="text-input" value="<?php echo get_oxfam_shop_data( 'account' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_company" title="Dit is ook de titel van deze subsite en kan enkel door Frederik gewijzigd worden.">Bedrijfsnaam: <?php echo $name_warning; ?></label>
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
		  			<input type="text" name="oxfam_place" class="text-input" value="<?php echo get_oxfam_shop_data( 'place' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_zipcode" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Postcode:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_zipcode" class="text-input" value="<?php echo get_oxfam_shop_data( 'zipcode' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_city" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Gemeente:</label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_city" class="text-input" value="<?php echo get_oxfam_shop_data( 'city' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_city" title="Zie je een fout staan? Werk je telefoonnummer bij op de publieke site van Oxfam-Wereldwinkels. Als XIO wat wil meewerken verschijnt de aanpassing meteen ook in de lokale webshop.">Telefoonnummer: <?php echo $phone_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_telephone" class="text-input" value="<?php echo get_oxfam_shop_data( 'telephone' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_email" title="Deze Office 365-mailbox wordt ingesteld als het algemene contactadres van deze subsite en is initieel ook ekoppeld aan de lokale beheeraccount. Opgelet: via de profielpagina kun je deze hoofdgebruiker aan een andere mailbox linken (of schakelen we dat uit? niet handig indien we voor meerdere lokale beheerders opteren!) maar het contactadres naar klanten blijft altijd dit e-mailadres!">E-mailadres: <?php echo $mail_warning; ?></label>
				</th>
		  		<td class="right">
		  			<input type="text" name="oxfam_email" class="text-input" value="<?php echo get_company_email(); ?>" readonly>
		  		</td>
			</tr>
			
			<?php
				if ( current_user_can( 'manage_options' ) ) {
					echo "<tr><td>";
					submit_button();
					echo "</td></tr>";
				}
			?>
		</table>
	</form>
</div>