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
			$crafts_search_terms = array( 'agenda', 'dopper', 'drinkfles' );
			if ( in_array( get_search_query(), $crafts_search_terms ) ) {
				echo '<p>Crafts- en solidariteitsproducten verkopen we helaas nog niet online. Maar spring gerust eens bij ons binnen om ons volledige assortiment te ontdekken!</p>';
			} else {
				_e( 'No products were found matching your selection.', 'woocommerce' );
				// GEWIJZIGD: Expliciet automatische suggestie toevoegen
				relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );

				// GEWIJZIGD: Toon best sellers
				echo '<p></p><h2>Dit kochten andere klanten vaak:</h2><br/>'.do_shortcode('[best_selling_products per_page="8" columns="4" orderby="rand"]');
			}
		?>
	</h3>
</div>
