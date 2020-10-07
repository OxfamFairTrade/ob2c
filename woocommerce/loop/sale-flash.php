<?php
/**
 * Product loop sale flash
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $post, $product, $nm_theme_options;

?>
<?php if ( $nm_theme_options['product_sale_flash'] ) : ?>

	<?php
		// GEWIJZIGD: Vervang kortingspercentages door productlabels (waaronder promoties)
		get_template_part( 'template-parts/woocommerce/product-labels' );
	?>

<?php endif;

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
