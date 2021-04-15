<?php 
/**
 * The template for displaying gift wrap modal button in cart/checkout areas
 *
 * @version 4.4.6
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="giftwrap_header_wrapper">
    <p class="giftwrap_header"><a data-toggle="modal" data-target=".giftwrapper_products_modal<?php echo $label; ?>" class="wcgwp-modal-toggle wcgwp-modal-toggle<?php echo $label; ?> btn" data-label="<?php echo $label; ?>"><?php echo wp_kses_post( apply_filters( 'wcgwp_add_wrap_prompt', __( 'Add gift wrap?', 'woocommerce-gift-wrapper' ) ) ); ?></a></p>
</div>