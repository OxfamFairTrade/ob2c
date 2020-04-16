<?php
/**
 * Displayed when no products are found matching the current query
 *
 * @see 		https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version 	2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="nm-shop-no-products">
	<h3 class="woocommerce-info">
		<?php
			// GEWIJZIGD: Verwijs naar fysieke winkel indien er duidelijk naar crafts gezocht werd
			if ( get_search_query() == 'agenda' or get_search_query() == 'dopper' ) {
				echo '<p>Crafts- en solidariteitsproducten verkopen we helaas nog niet online. Spring zeker eens binnen in je wereldwinkel om het volledige assortiment te ontdekken!</p>';
			} else {
				_e( 'No products were found matching your selection.', 'woocommerce' );
				// GEWIJZIGD: Expliciet automatische suggestie toevoegen
				relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );
			}

			// GEWIJZIGD: Toon best sellers
			echo '<h2>Dit kochten andere klanten vaak:</h2><br/>'.do_shortcode('[best_selling_products limit="8" columns="4" orderby="rand"]');
		?>
	</h3>
</div>
