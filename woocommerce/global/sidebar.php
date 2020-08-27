<?php
/**
 * Sidebar
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="nm-shop-sidebar-col col-md-3 col-sm-12">
	<div id="nm-shop-sidebar" class="nm-shop-sidebar" data-sidebar-layout="default">
		<?php
			// Vanaf WP 5.5: eventueel een argument meegeven voor context, zie https://stackoverflow.com/questions/51569217/wordpress-get-template-part-pass-variable
			get_template_part( 'template-parts/store-selector/current' );
		?>

		<ul id="nm-shop-widgets-ul">
			<li id="woocommerce_product_categories-3" class="widget woocommerce widget_product_categories">
				<div class="nm-shop-widget-col">
					<h3 class="nm-widget-title">In de kijker</h3>
				</div>
				<div class="nm-shop-widget-col">
					<?php
						$args = array(
							'stock_status' => 'instock',
							'include' => wc_get_product_ids_on_sale(),
						);
						$sale_products = wc_get_products( $args );
						if ( count( $sale_products ) > 0 ) {
							echo '<a href="'.get_site_url().'/tag/promotie/#shop"><span>In promotie</span></a>';
						}
						echo '<a href="'.get_site_url().'/tag/sinterklaas/#shop"><span>Sinterklaas</span></a>';
					?>
				</div>
			</li>

			<?php
				if ( is_active_sidebar( 'widgets-shop' ) ) {
					dynamic_sidebar( 'widgets-shop' );
				}
			?>
		</ul>
	</div>
</div>
