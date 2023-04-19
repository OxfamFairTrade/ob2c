<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Zie https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/admin/class-wc-admin-dashboard.php
	add_action( 'woocommerce_after_dashboard_status_widget', 'add_custom_dashboard_widgets', 10, 1 );
	
	function add_custom_dashboard_widgets( $reports ) {
		$orders_processing = 0;
		$orders_claimed = 0;
		$orders_on_hold = 0;
		
		foreach ( wc_get_order_types('order-count') as $type ) {
			$counts = (array) wp_count_posts( $type );
			$orders_processing += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
			$orders_claimed += isset( $counts['wc-claimed'] ) ? $counts['wc-claimed'] : 0;
			$orders_on_hold += isset( $counts['wc-on-hold'] ) ? $counts['wc-on-hold'] : 0;
		}
		?>
		<?php if ( is_regional_webshop() ) : ?>
			<!-- Vervang 'on-hold' door interessantere status bij regiowerkingen -->
			<li class="orders-processing">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s bestelling</strong> wacht op bevestiging', '<strong>%s bestellingen</strong> wachten op bevestiging', $orders_processing, 'woocommerce' ),
						$orders_processing
					);
				?>
				</a>
			</li>
			<li class="orders-claimed">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-claimed&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $orders_claimed, 'woocommerce' ),
						$orders_claimed
					);
				?>
				</a>
			</li>
		<?php else : ?>
			<li class="orders-processing">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $orders_processing, 'woocommerce' ),
						$orders_processing
					);
				?>
				</a>
			</li>
			<li class="orders-on-hold">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-on-hold&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> on-hold', '<strong>%s orders</strong> on-hold', $orders_on_hold, 'woocommerce' ),
						$orders_on_hold
					);
				?>
				</a>
			</li>
		<?php endif; ?>
		<?php
	}