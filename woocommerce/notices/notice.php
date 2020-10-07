<?php
/**
 * Show messages
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @package     WooCommerce/Templates
 * @version     3.98.0
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! $notices ) {
	return;
}

?>

<?php foreach ( $notices as $notice ) : ?>
    <?php if(wc_kses_notice( $notice['notice'] ) != '') : ?>
        <li class="nm-shop-notice woocommerce-info"<?php echo wc_get_notice_data_attr( $notice ); ?>>
            <span><?php echo wc_kses_notice( $notice['notice'] ); ?></span>
        </li>
    <?php endif; ?>
<?php endforeach; ?>
