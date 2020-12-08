<?php
/**
 * Mini-cart
 *
 * Contains the markup for the mini-cart, used by the cart widget.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.7.0
 NM: Modified */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $nm_theme_options;

$nm_cart_empty_class_attr_escaped = ( WC()->cart->is_empty() ) ? ' class="nm-cart-panel-empty"' : '';
?>

<div id="nm-cart-panel"<?php echo $nm_cart_empty_class_attr_escaped; ?>>

<form id="nm-cart-panel-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
    <?php
        // Nonce field and cart URL needed for quantity inputs
        wp_nonce_field( 'woocommerce-cart' );
    ?>
</form>
    
<div id="nm-cart-panel-loader">
    <h5 class="nm-loader"><?php esc_html_e( 'Updating&hellip;', 'nm-framework' ); ?></h5>
</div>
    
<?php do_action( 'woocommerce_before_mini_cart' ); ?>

<div class="nm-cart-panel-list-wrap">

<ul class="woocommerce-mini-cart cart_list product_list_widget <?php echo esc_attr( $args['list_class'] ); ?>">
    
    <?php if ( ! WC()->cart->is_empty() ) : ?>

        <?php
            do_action( 'woocommerce_before_mini_cart_contents' );
    
            // GEWIJZIGD: Plaats het laatst toegevoegde product bovenaan door de te doorlopen array om te keren
            foreach ( array_reverse( WC()->cart->get_cart() ) as $cart_item_key => $cart_item ) {
                $_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                $product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                    $product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
                    $thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                    //$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                    $product_price     = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                    
                    // NM
                    if ( empty( $product_permalink ) ) {
                        $product_name = '<span class="nm-cart-panel-product-title">' . $product_name . '</span>';
                    } else {
                        $product_permalink = esc_url( $product_permalink );
                        $thumbnail = '<a href="' . $product_permalink . '">' . $thumbnail . '</a>';
                        $product_name = '<a href="' . $product_permalink . '" class="nm-cart-panel-product-title">' . $product_name . '</a>';
                    }
                    ?>
                    <li id="nm-cart-panel-item-<?php echo esc_attr( $cart_item_key ); ?>" class="woocommerce-mini-cart-item <?php echo esc_attr( apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key ) ); ?>">
                        <div class="nm-cart-panel-item-thumbnail">
                            <div class="nm-cart-panel-thumbnail-wrap">
                                <?php echo $thumbnail; ?>
                                <div class="nm-cart-panel-thumbnail-loader nm-loader"></div>
                            </div>
                        </div>
                        <div class="nm-cart-panel-item-details">
                            <div class="nm-cart-item-loader nm-loader"></div>
                            
                            <?php
                            echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
                                '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s"><i class="nm-font nm-font-close2"></i></a>',
                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                esc_attr__( 'Remove this item', 'woocommerce' ),
                                esc_attr( $product_id ),
                                esc_attr( $cart_item_key ),
                                esc_attr( $_product->get_sku() )
                            ), $cart_item_key );
                            ?>
                            
                            <?php echo $product_name; ?>
                            <!-- GEWIJZIGD: Metadata zoals 'Hoort bij' niét tonen -->
                            <?php // echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                            
                            <div class="nm-cart-panel-quantity-pricing">
                                <?php if ( ! $nm_theme_options['cart_panel_quantity_arrows'] || $_product->is_sold_individually() ) : ?>
                                    <?php
                                        echo apply_filters( 'woocommerce_widget_cart_item_quantity', '<span class="quantity">' . esc_html__( 'Qty', 'woocommerce' ) . ': ' . $cart_item['quantity'] . '</span>', $cart_item, $cart_item_key );
                                    ?>
                                <?php else: ?>
                                    <div class="product-quantity" data-title="<?php esc_html_e( 'Quantity', 'woocommerce' ); ?>">
                                        <?php
                                            $product_quantity = woocommerce_quantity_input( array(
                                                'input_name'  => "cart[{$cart_item_key}][qty]",
                                                'input_value' => $cart_item['quantity'],
                                                'max_value'   => $_product->backorders_allowed() ? '' : $_product->get_stock_quantity(),
                                                'min_value'   => '1',
                                                'nm_mini_cart_quantity' => true // NM: Makes it possible to check if the quantity-input is for the cart-panel when using the "woocommerce_quantity_input_args" filter
                                            ), $_product, false );
                                            
                                            echo apply_filters( 'woocommerce_widget_cart_item_quantity', $product_quantity, $cart_item, $cart_item_key );
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="nm-cart-panel-item-price">
                                    <?php echo $product_price; ?>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php
                }
            }
    
            do_action( 'woocommerce_mini_cart_contents' );
        ?>

    <?php endif; ?>

    <li class="empty">
        <i class="nm-font nm-font-close2"></i>
        <span><?php esc_html_e( 'No products in the cart.', 'woocommerce' ); ?></span>
    </li>

</ul><!-- end product list -->

</div>
    
<div class="nm-cart-panel-summary">
    
    <div class="nm-cart-panel-summary-inner">
        
        <?php if ( ! WC()->cart->is_empty() ) : ?>
        
        <p class="woocommerce-mini-cart__total total">
            <strong><?php
                $contains_empties = false;
                $empties = get_oxfam_empties_skus_array();
                
                foreach( WC()->cart->get_cart_contents() as $item_key => $item_value ) {
                    // Verzendklasse 'breekbaar' is niet op alle leeggoed geactiveerd, dus check leeggoed o.b.v. SKU
                    if ( in_array( $item_value['data']->get_sku(), $empties ) ) {
                        $contains_empties = true;
                        break;
                    } 
                }
               
                if ( WC()->cart->get_discount_total() > 0 ) {
                    if ( $contains_empties ) {
                        echo 'Subtotaal (incl. leeggoed, excl. korting):';
                    } else {
                        echo 'Subtotaal (excl. korting):';
                    }
                } else {
                    if ( $contains_empties ) {
                        echo 'Subtotaal (incl. leeggoed):';
                    } else {
                        echo 'Subtotaal:';
                    }
                }
            ?></strong>
            <span class="nm-cart-panel-summary-subtotal">
                <?php echo WC()->cart->get_cart_subtotal(); ?>
            </span>
        </p>

        <?php
            // Schakel de extra buttons via deze actie tijdelijk uit
            // do_action( 'woocommerce_widget_shopping_cart_before_buttons' );
        ?>

        <p class="woocommerce-mini-cart__buttons buttons">
            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button border wc-forward"><?php esc_html_e( 'View cart', 'woocommerce' ); ?></a>
            <?php
                if ( class_exists( 'WCGW_Wrapping' ) ) {
                    $wcgw_wrapping = new WCGW_Wrapping();
                    if ( $wcgw_wrapping->count_giftwrapped_products() ) {
                        if ( $wcgw_wrapping->giftwrap_in_cart ) {
                            ?>
                            <a class="button border add-gift wc-forward"><?php esc_html_e( 'We pakken dit in als een cadeautje', 'oxfam-webshop' ); ?></a>
                            <?php
                        } else {
                            ?>
                            <a href="<?php echo esc_url( wc_get_cart_url().'?triggerGiftWrapper' ); ?>" class="button border add-gift wc-forward"><?php esc_html_e( 'Voeg geschenkverpakking toe', 'oxfam-webshop' ); ?></a>
                            <?php
                        }
                    }
                }
            ?>
            <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button checkout wc-forward"><?php esc_html_e( 'Checkout', 'woocommerce' ); ?></a>
        </p>
        
        <?php endif; ?>
        
        <p class="buttons nm-cart-empty-button">
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" id="nm-cart-panel-continue" class="button border"><?php esc_html_e( 'Continue shopping', 'woocommerce' ); ?></a>
        </p>
        
    </div>

</div>

<?php do_action( 'woocommerce_after_mini_cart' ); ?>
    
</div>