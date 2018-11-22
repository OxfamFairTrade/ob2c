<?php
/**
 * Show error messages
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! $messages ) {
	return;
}

?>
<ul class="woocommerce-error">
	<?php foreach ( $messages as $message ) : ?>
		<!-- GEWIJZIGD: Sta HTML toe in boodschap (o.a. doorsturen naar andere webshop) -->
		<li><?php echo $message; ?></li>
	<?php endforeach; ?>
</ul>
