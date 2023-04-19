<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Zie https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/admin/class-wc-admin-dashboard.php
	add_action( 'woocommerce_after_dashboard_status_widget', 'add_custom_dashboard_widgets', 10, 1 );
	
	function add_custom_dashboard_widgets( $reports ) {
		$on_hold_count = 0;
		$processing_count = 0;
		
		foreach ( wc_get_order_types('order-count') as $type ) {
			$counts = (array) wp_count_posts( $type );
			$processing_count += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
			if ( is_regional_webshop() ) {
				// Vervang 'on-hold' door interessantere status bij regiowerkingen
				$on_hold_count += isset( $counts['wc-claimed'] ) ? $counts['wc-claimed'] : 0;
			} else {
				$on_hold_count += isset( $counts['wc-on-hold'] ) ? $counts['wc-on-hold'] : 0;
			}
		}
		?>
		<?php if ( is_regional_webshop() ) : ?>
			<li class="processing-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s bestelling</strong> wacht op bevestiging', '<strong>%s bestellingen</strong> wachten op bevestiging', $processing_count, 'woocommerce' ),
						$processing_count
					);
				?>
				</a>
			</li>
			<li class="claimed-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-claimed&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $on_hold_count, 'woocommerce' ),
						$on_hold_count
					);
				?>
				</a>
			</li>
		<?php else : ?>
			<li class="processing-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $processing_count, 'woocommerce' ),
						$processing_count
					);
				?>
				</a>
			</li>
			<li class="on-hold-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-on-hold&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> on-hold', '<strong>%s orders</strong> on-hold', $on_hold_count, 'woocommerce' ),
						$on_hold_count
					);
				?>
				</a>
			</li>
		<?php endif; ?>
		<?php
	}