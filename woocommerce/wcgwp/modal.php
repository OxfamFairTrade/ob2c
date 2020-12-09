<?php 
/**
 * The template for displaying gift wrap modal content in cart/checkout areas
 *
 * @version 4.4
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="giftwrap_header_wrapper">
    <p class="giftwrap_header"><a data-toggle="modal" data-target=".giftwrapper_products_modal<?php echo $label; ?>" class="wcgwp-modal-toggle wcgwp-modal-toggle<?php echo $label; ?> btn" data-label="<?php echo $label; ?>"><?php echo wp_kses_post( apply_filters( 'wcgwp_add_wrap_prompt', __( 'Add gift wrap?', 'woocommerce-gift-wrapper' ) ) ); ?></a></p>
</div>

<div id="giftwrap_modal<?php echo $label; ?>" class="giftwrapper_products_modal giftwrapper_products_modal<?php echo $label; ?> fusion-modal modal" tabindex="-1" role="dialog">
    <div class="modal-dialog <?php echo apply_filters( 'wcgwp_modal_size', 'modal-lg'); ?> modal-dialog-centered" role="document">
        <div class="modal-content fusion-modal-content">
            <style>
                .giftwrap_cancel {
                    position: absolute;
                    height: 30px;
                    width: 30px;
                    right: 5px;
                    top: 5px;
                    background-image: url(/wp-content/themes/oxfam-webshop/images/x.svg);
                    background-position: center center;
                    background-repeat: no-repeat;
                    background-size: 26px 26px;
                    border-radius: 50%;
                    }

                .giftwrap_submit {
                    background-color: #282828;
                    border: 0 none;
                    border-radius: 0;
                    color: #fff;
                    cursor: pointer;
                    display: inline-block;
                    font-size: 16px;
                    font-weight: normal;
                    line-height: 22px;
                    opacity: 1;
                    padding: 10px 14px;
                    transition: opacity 0.2s ease;
                    text-decoration: none;
                    text-align: center;
                    width: auto;
                }

                .wc_giftwrap_notes_container {
                    margin-bottom: 1em;
                }
            </style>

            <a href="#" class="button btn giftwrap_cancel" data-dismiss="modal" aria-label="Close" title="Annuleer"></a>
            
            <form class="giftwrapper_products modal_form wcgwp_form" method="post">
                <div class="modal-body wcgwp_modal_body">
                    <h3>Oxfam pakt (voor) je in!</h3>
                    <?php if ( ! apply_filters( 'wcgwp_hide_details', FALSE ) ) { ?>
                        <p class="giftwrap_details">
                        <?php echo wp_kses_post( apply_filters( 'wcgwp_wrap_offerings', __( $giftwrap_details, 'woocommerce-gift-wrapper' ) ) ); ?>
                        </p>
                    <?php }
                    
                    $list_count = count( $list ) > 1;
                    $product_image = '';
                    $image_output = '';
                    $wrap_count = 0;
                    $show_link = get_option( 'wcgwp_link' );
                    ?>

                    <ul class="giftwrap_ul">
                        <?php foreach ( $list as $giftwrapper_product ) {
                            $checked = $wrap_count == 0 ? 'checked' : '';
                            $product = new WC_Product( $giftwrapper_product->ID );
                            $price_html     = $product->get_price_html();
                            $giftwrap_label = strtolower( preg_replace( '/\s*/', '', $product->get_title() ) );
                            $show_thumbs_class = ' no_giftwrap_thumbs';

                            // Dit wordt bepaald door de verwarrende 'Toon cadeauverpakking in winkelwagen'-instelling
                            if ( $show_thumbs == TRUE ) {
                                // GEWIJZIGD: Vervangen door standaard WooCommerce-call, zodat de fotologica gerespecteerd wordt 
                                $product_image = $product->get_image('thumbnail');
                                $image_output = '<div class="giftwrap_thumb">';
                                if ( $show_link == 'yes' ) {
                                    $giftwrapper_product_URL = get_permalink( $giftwrapper_product );
                                    $image_output .= '<a href="' . esc_url( $giftwrapper_product_URL ) . '">';
                                }
                                $image_output .= $product_image;
                                if ( $show_link == 'yes' ) {
                                    $image_output .= '</a>';
                                }
                                $image_output .= '</div>';
                                $show_thumbs_class = ' show_thumb';
                            }
                            if ( $list_count ) { 
                                echo '<li class="giftwrap_li' . $show_thumbs_class . '"><input type="radio" name="wcgwp_product' . $label . '" id="' . $giftwrap_label . $label . '" value="' . $giftwrapper_product->ID . '"' . $checked . ' class="wcgwp_product_input">';
                                echo '<label for="' . $giftwrap_label . $label . '" class="giftwrap_desc"><span class="giftwrap_title"> ' . $giftwrapper_product->post_title . '</span> ' . $price_html;
                                if ( $show_link == 'yes' ) {
                                    echo '</label>' . $image_output . '</li>';
                                } else {
                                    echo $image_output . '</label></li>';
                                }
                            } else {
                                echo '<li class="giftwrap_li' . $show_thumbs_class . '"><label for="' . $giftwrap_label . $label . '" class="giftwrap_desc singular_label"><span class="giftwrap_title"> ' . $giftwrapper_product->post_title . '</span> ' . $price_html . '</label>' . $image_output . '</li>';
                                echo '<input type="hidden" name="wcgwp_product' . $label . '" value="' . $giftwrapper_product->ID . '" id="' . $giftwrap_label . $label . '">';
                            } 
                            ++$wrap_count;
                        } ?>
                    </ul>

                    <div class="wc_giftwrap_notes_container">
                        <label for="wcgwp_notes<?php echo $label; ?>">
                            <?php echo wp_kses_post( apply_filters( 'wcgwp_add_wrap_message', __( 'Add Gift Wrap Message:', 'woocommerce-gift-wrapper' ) ) ); ?>
                        </label>
                        <textarea name="wcgwp_note<?php echo $label; ?>" id="wcgwp_notes<?php echo $label; ?>" cols="30" rows="4" maxlength="<?php echo esc_attr( get_option( 'wcgwp_textarea_limit', '1000' ) ); ?>" class="wc_giftwrap_notes"></textarea>	
                    </div>

                    <button type="submit" class="button btn alt giftwrap_submit replace_wrap fusion-button fusion-button-default fusion-button-default-size" name="wcgwp_submit<?php echo $label; ?>">
                        <?php echo wp_kses_post( apply_filters( 'wcgwp_add_wrap_button_text', __( 'Voeg toe aan bestelling', 'oxfam-webshop' ) ) ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>