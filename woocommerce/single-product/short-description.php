<?php
/**
 * Single product short description
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  Automattic
 * @package WooCommerce/Templates
 * @version 3.3.0
 NM: Modified - Added "entry-content" class */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $post;

$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );

if ( ! $short_description ) {
	// Toon de lange beschrijving als alternatief
	$short_description = get_the_content();
}

?>
<div class="woocommerce-product-details__short-description entry-content">
	<?php
		// Probleem: als het product een uitgelichte partner heeft (+ het géén wijn is) verschijnt de lange producttekst nergens meer ...
		// Logica om $featured_partner te bepalen verhuizen van product-origin.php naar content-single-product.php, zodat die info hier ook al beschikbaar is?
		// global $featured_partner;
		// if ( $featured_partner ) {
		// 	echo the_content();
		// } else {
			echo $short_description;
		// }
	
		// Toon bij wijnen ook altijd de lange (sommeliers)beschrijving bovenaan
		if ( stripos( get_permalink(), '/wijn/' ) > 0 ) {
			the_content();
		}
	?>
</div>
