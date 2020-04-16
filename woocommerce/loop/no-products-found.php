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
				echo '<p>Crafts- en solidariteitsproducten verkopen we helaas nog niet online.<br/>Maar spring gerust eens binnen om ons volledige assortiment te ontdekken!</p>';
			} else {
				_e( 'No products were found matching your selection.', 'woocommerce' );
				// GEWIJZIGD: Expliciet automatische suggestie toevoegen
				relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );
			}
		?>
	</h3>
	<?php
		// GEWIJZIGD: Toon best sellers
		echo '<p>&nbsp;</p><h3>Mogen we je wat inspiratie geven? Dit kochten andere klanten vaak:</h3><br/>'.do_shortcode('[best_selling_products per_page="8" columns="4" orderby="rand"]');
	?>
</div>
