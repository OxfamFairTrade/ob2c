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
			_e( 'No products were found matching your selection.', 'woocommerce' );
			// GEWIJZIGD: Expliciet automatische suggestie toevoegen
			relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );
		?>
	</h3>
</div>
