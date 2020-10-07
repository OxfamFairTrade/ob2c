<?php
/**
 * Show messages
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

?>

<?php foreach ( $notices as $notice ) : ?>
    <?php if(wc_kses_notice( $notice['notice'] ) != '') : ?>
        <div class="nm-shop-notice woocommerce-message"<?php echo wc_get_notice_data_attr( $notice ); ?> role="alert">
            <span><i class="nm-font nm-font-check"></i><?php echo wc_kses_notice( $notice['notice'] ); ?></span>
        </div>
    <?php endif; ?>
<?php endforeach; ?>