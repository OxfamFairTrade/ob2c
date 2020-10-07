<?php
/**
 * Show error messages
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @package     WooCommerce/Templates
 * @version     3.9.0
 NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $notices ) {
	return;
}

$nm_shop_notice_single_class = ( count( $notices ) > 1 ) ? ' nm-shop-notice-multiple' : '';

?>

<ul class="nm-shop-notices woocommerce-error<?php echo esc_attr( $nm_shop_notice_single_class ); ?>" role="alert">
    <?php foreach ( $notices as $notice ) : ?>
        <?php if(wc_kses_notice( $notice['notice'] ) != '') : ?>
            <li class="nm-shop-notice"<?php echo wc_get_notice_data_attr( $notice ); ?>>
                <span><i class="nm-font nm-font-close"></i><?php echo wc_kses_notice( $notice['notice'] ); ?></span>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
