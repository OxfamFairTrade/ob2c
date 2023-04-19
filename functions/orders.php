<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Zie https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/admin/class-wc-admin-dashboard.php
	add_action( 'woocommerce_after_dashboard_status_widget', 'add_custom_dashboard_widgets', 10, 1 );
	
	function add_custom_dashboard_widgets( $reports ) {
		$orders_to_claim = 0;
		$orders_to_pick = 0;
		$orders_to_supply = 0;
		
		foreach ( wc_get_order_types('order-count') as $type ) {
			$counts = (array) wp_count_posts( $type );
			if ( is_regional_webshop() ) {
				$orders_to_claim += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
				// Vervang 'on-hold' door interessantere status bij regiowerkingen
				$orders_to_pick += isset( $counts['wc-claimed'] ) ? $counts['wc-claimed'] : 0;
			} else {
				$orders_to_pick += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
				$orders_to_supply += isset( $counts['wc-on-hold'] ) ? $counts['wc-on-hold'] : 0;
			}
		}
		?>
		<?php if ( is_regional_webshop() ) : ?>
			<li class="orders-to-claim">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s bestelling</strong> wacht op bevestiging', '<strong>%s bestellingen</strong> wachten op bevestiging', $orders_to_claim, 'woocommerce' ),
						$orders_to_claim
					);
				?>
				</a>
			</li>
			<li class="orders-to-pick">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-claimed&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $orders_to_pick, 'woocommerce' ),
						$orders_to_pick
					);
				?>
				</a>
			</li>
		<?php else : ?>
			<li class="orders-to-pick">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-processing&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $orders_to_pick, 'woocommerce' ),
						$orders_to_pick
					);
				?>
				</a>
			</li>
			<li class="orders-to-supply">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=wc-on-hold&post_type=shop_order' ) ); ?>">
				<?php
					printf(
						_n( '<strong>%s order</strong> on-hold', '<strong>%s orders</strong> on-hold', $orders_to_supply, 'woocommerce' ),
						$orders_to_supply
					);
				?>
				</a>
			</li>
		<?php endif; ?>
		<?php
	}