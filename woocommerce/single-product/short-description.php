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
		// Toon bij wijnen ook altijd de lange (sommeliers)beschrijving
		if ( stripos( get_permalink(), '/wijn/' ) > 0 ) {
			the_content();
		}
	?>
	<?php echo $short_description; // WPCS: XSS ok. ?>
</div>
