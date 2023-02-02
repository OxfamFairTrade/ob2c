<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Dashboardberichten instellen</h1>
	
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div id="message" class="updated notice is-dismissible">
			<p><?php esc_html_e( 'Instellingen opgeslagen', 'oxfam-webshop' ); ?></p>
		</div>
	<?php endif; ?>
	
	<p>Met behulp van deze instellingen kun je berichten op het dashboard van elke subsite plaatsen. Alle links worden automatisch lokaal gemaakt.</p>
	
	<form action="edit.php?action=dashboard-info-settings-update" method="POST">
		<table class="form-table">
			<?php
				settings_fields('woonet-woocommerce-dashboard-info');
				
				add_settings_field(
					'dashboard-notice-success',
					__( 'Succesboodschap', 'oxfam-webshop' ),
					'oxfam_shop_dashboard_notice_success_callback',
					'woonet-woocommerce-dashboard-info',
					'default',
					array(
						'label_for' => 'oxfam_shop_dashboard_notice_success',
						'class' => 'frederik',
					),
				);
				
				submit_button();
				
				function oxfam_shop_dashboard_notice_success_callback() {
					$value = get_site_option( 'oxfam_shop_dashboard_notice_success', '' );
					echo '<textarea name="oxfam_shop_dashboard_notice_success" value="' . esc_attr( $value ) . '" rows="4"></textarea>';
				}
			?>
		</table>
	</form>
	
</div>