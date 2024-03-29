<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Dashboardberichten instellen</h1>
	
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div id="message" class="updated notice is-dismissible">
			<p><?php esc_html_e( 'Instellingen bewaard!', 'oxfam-webshop' ); ?></p>
		</div>
	<?php endif; ?>
	
	<p>Met behulp van deze instellingen kun je berichten op het dashboard van elke subsite plaatsen. Indien je een veld volledig leeg maakt, verdwijnt de banner in kwestie automatisch.<br/>Via de code kunnen eventueel geavanceerdere berichten toegevoegd worden, bv. enkel zichtbaar voor bepaalde webshops of met een ingebouwde vervaldatum (zie <i>/oxfam-tweaks.php</i>).</p>
	
	<form action="edit.php?action=woonet-woocommerce-dashboard-info-update" method="POST">
		<table class="form-table">
			<?php
				settings_fields('woonet-woocommerce-dashboard-info');
				do_settings_sections('woonet-woocommerce-dashboard-info');
				submit_button();
			?>
		</table>
	</form>
	
</div>