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

global $post, $featured_partner;
$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );

?>
<div class="woocommerce-product-details__short-description entry-content">
	<?php
		if ( is_national_product( $post->ID ) ) {		
			if ( stripos( get_permalink(), '/wijn/' ) > 0 ) {
				// Wijn: toon eerst korte 'lekker bij'-omschrijving, en vervolgens lange sommeliersbeschrijving
				echo $short_description;
				// Géén get_the_content() gebruiken, want dan wordt 'the_content'-filter niet doorlopen en ontbreken de paragrafen
				the_content();
			} else {
				if ( ! $short_description ) {
					// Toon noodgedwongen lange beschrijving
					the_content();
				} elseif ( strlen( get_the_content() ) < 10 ) {
					// Toon noodgedwongen korte beschrijving
					echo $short_description;
				} else {
					// Beide productteksten bestaan
					if ( $featured_partner ) {
						the_content();
					} else {
						// Als het product géén uitgelichte partner heeft, mag hier enkel de korte beschrijving verschijnen
						// De lange beschrijving verschijnt dan op de plek van de uitgelichte partner 
						echo $short_description;
					}
				}
			}
		} else {
			echo $short_description;
		}
	?>
</div>
