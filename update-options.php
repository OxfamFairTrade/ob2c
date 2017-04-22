<div class="wrap">
	<h1>Instellingen voor lokale webshop</h1>
	
	<form method="post" action="options.php"> 
		<?php
			settings_fields( 'oxfam-option-group' );
			do_settings_sections( 'oxfam-option-group' );
		?>
		
		<table class="form-table">
			<tr>
				<td></td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_shop_node">Nodenummer OWW-site:</label>
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
					<label for="oxfam_btw">BTW-nummer winkel:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_btw" style="width: 50%;" value="<?php echo get_oxfam_shop_data('phone'); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_city">Stad winkel:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_city" style="width: 50%;" value="<?php echo get_oxfam_shop_data('city'); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>
			<tr valign="top">
				<th colspan="3">
					<label for="oxfam_city">Telefoon winkel:</label>
				</th>
		  		<td colspan="5">
		  			<input type="text" name="oxfam_phone" style="width: 50%;" value="<?php echo get_oxfam_shop_data('phone'); ?>"<?php if ( ! current_user_can( 'manage_options' ) ) echo ' readonly'; ?>>
		  		</td>
			</tr>

			<?php
				Mollie_Autoloader::register();
				$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
				$partner_id_customer = get_option( 'oxfam_mollie_partner_id' );
				
				// Check of we niet op de hoofdaccount zitten, want dan krijgen we een fatale error bij getLoginLink()
				if ( $partner_id_customer != 2485891 ) {
					$result = $mollie->getLoginLink( $partner_id_customer );
					echo "<tr><th colspan='3'><a href='".$result->redirect_url."' target='_blank'>Log automatisch in op je Mollie-betaalaccount &raquo;</a></th>";
					echo "<td colspan='5'>Opgelet: deze link is slechts enkele minuten geldig! Herlaad desnoods even deze pagina.</td></tr>";

					if ( does_sendcloud_delivery() ) {
						echo "<tr><th colspan='3'><a href='https://panel.sendcloud.sc/' target='_blank'>Log handmatig in op je SendCloud-verzendaccount &raquo;</a></th>";
						echo "<td colspan='5'>Merk op dat het wachtwoord van deze account volledig los staat van de webshop.</td></tr>";
					}
				}

				if ( current_user_can( 'manage_options' ) ) submit_button();
			?>
		</table>

	</form>
</div>