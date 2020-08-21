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
		<!-- GEWIJZIGD: Store selector toevoegen -->
		<div class="store-selector catalogue">
			<span>Jouw Oxfam-winkel</span>
			<!-- To do Frederik: Dynamisch maken (winkel vs. webshop!) -->
			<p><?php echo get_company_address(); ?></p>
			<ul>
				<li class="inactive">​Levering aan huis</li>
				<li class="active">Afhalen in de winkel</li>
			</ul>
			<!-- To do Pieter: Toggle modal die verborgen zit in footer -->
			<a href="">Winkel wijzigen</a>
		</div>
			
		<?php
			// GEWIJZIGD: Link naar promoties toevoegen
			$args = array(
				'stock_status' => 'instock',
				'include' => wc_get_product_ids_on_sale(),
			);
			$sale_products = wc_get_products( $args );
			if ( count( $sale_products ) > 0 ) {
				echo '<a href="'.get_site_url().'/tag/promotie/#shop"><p class="ob2c-sale-banner">Bekijk onze promoties</p></a>';
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
