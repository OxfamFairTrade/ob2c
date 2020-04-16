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
			// GEWIJZIGD: Link naar promoties toevoegen
			if ( ! is_product_tag('promotie') ) {
				$args = array(
					'stock_status' => 'instock',
					'include' => wc_get_product_ids_on_sale(),
				);
				$sale_products = wc_get_products( $args );
				if ( count( $sale_products ) > 0 ) {
					echo '<a href="'.get_site_url().'/tag/promotie/#shop"><p class="ob2c-sale-banner">Bekijk onze promoties</p></a>';
				}
			}
		?>
		<ul id="nm-shop-widgets-ul">
			<?php
				if ( is_active_sidebar( 'widgets-shop' ) ) {
					dynamic_sidebar( 'widgets-shop' );
				}
			?>
		</ul>
	</div>
</div>
