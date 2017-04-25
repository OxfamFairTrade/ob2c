<div class="wrap">
	<h1>Instellingen voor lokale webshop</h1>
	
	<form method="post" action="options.php">
		<table class="form-table">
			<?php
				settings_fields( 'oxfam-option-group' );
				do_settings_sections( 'oxfam-option-group' );

				Mollie_Autoloader::register();
				$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
				$partner_id_customer = get_option( 'oxfam_mollie_partner_id' );
				
				// Check of we niet op de hoofdaccount zitten, want dan krijgen we een fatale error bij getLoginLink()
				if ( $partner_id_customer != 2485891 ) {
					echo "<tr>";
						echo "<th colspan='3'><a href='https://login.microsoftonline.com/' target='_blank'>Log in op je Office 365-mailaccount &raquo;</a></th>";
						echo "<td colspan='5'>Merk op dat het wachtwoord van deze mailbox volledig los staat van de webshop.</td>";
					echo "</tr>";

					$result = $mollie->getLoginLink( $partner_id_customer );
					echo "<tr>";
						echo "<th colspan='3'><a href='".$result->redirect_url."' target='_blank'>Log volautomatisch in op je Mollie-betaalaccount &raquo;</a></th>";
						echo "<td colspan='5'>Opgelet: deze link is slechts enkele minuten geldig! Herlaad desnoods even deze pagina.</td>";
					echo "</tr>";

					if ( does_sendcloud_delivery() ) {
						echo "<tr>";
							echo "<th colspan='3'><a href='https://panel.sendcloud.sc/' target='_blank'>Log in op je SendCloud-verzendaccount &raquo;</a></th>";
							echo "<td colspan='5'>Merk op dat het wachtwoord van deze account volledig los staat van de webshop.</td>";
						echo "</tr>";
					}
				}
			?>

			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_shop_node" title="Aan de hand van deze ID halen we openingsuren en adresinfo op in de database achter oxfamwereldwinkels.be">Nodenummer OWW-site:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_shop_node" style="width: 50%;" value="<?php echo esc_attr( get_option('oxfam_shop_node') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_mollie_partner_id">Partner-ID Mollie:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_mollie_partner_id" style="width: 50%;" value="<?php echo esc_attr( get_option('oxfam_mollie_partner_id') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_zip_codes">Postcodes voor thuislevering:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_zip_codes" style="width: 50%;" value="<?php echo implode ( ", ", get_option('oxfam_zip_codes') ); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>

			<!-- Deze 'instellingen' maken geen deel uit van de geregistreerde opties en zouden dus niet automatisch opgeslagen mogen worden!-->
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_tax">BTW-nummer:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_tax" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'tax' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_account">IBAN-rekeningnummer:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_account" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'account' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_place">Straat en huisnummer:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_place" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'place' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_zipcode">Postcode:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_zipcode" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'zipcode' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_city">Gemeente:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_city" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'city' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_city">Telefoonnummer:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_telephone" style="width: 50%;" value="<?php echo get_oxfam_shop_data( 'telephone' ); ?>" readonly>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_email">E-mailadres:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_email" style="width: 50%;" value="<?php echo get_option( 'admin_email' ); ?>" readonly>
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