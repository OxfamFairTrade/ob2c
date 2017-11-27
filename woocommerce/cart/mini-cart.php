<?php
/*
 * Mini-cart
 *
 * Contains the markup for the mini-cart, used by the cart widget.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 2.5.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$nm_cart_empty_class_attr = ( WC()->cart->is_empty() ) ? ' class="nm-cart-panel-empty"' : '';
?>

<div id="nm-cart-panel"<?php echo $nm_cart_empty_class_attr; ?>>

<div id="nm-cart-panel-loader">
    <h5 class="nm-loader"><?php esc_html_e( 'Updating&hellip;', 'nm-framework' );//esc_html_e( 'Loading&hellip;', 'woocommerce' ); ?></h5>
</div>
    
<?php do_action( 'woocommerce_before_mini_cart' ); ?>

<div class="nm-cart-panel-list-wrap">

<ul class="cart_list product_list_widget <?php echo esc_attr( $args['list_class'] ); ?>">
    
    <?php if ( ! WC()->cart->is_empty() ) : ?>

        <?php
            // GEWIJZIGD: Plaats het laatst toegevoegde product bovenaan door de te doorlopen array om te keren
            foreach ( array_reverse(WC()->cart->get_cart()) as $cart_item_key => $cart_item ) {
                $_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                $product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                    $product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key );
                    $thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                    //$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                    $product_price     = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );

                    // NM
                    $thumbnail = str_replace( array( 'http:', 'https:' ), '', $thumbnail );
                    if ( ! $_product->is_visible() ) {
                        $product_name = '<span class="nm-cart-panel-product-title">' . $product_name . '</span>';
                    } else {
                        $product_permalink = esc_url( $product_permalink );
                        $thumbnail = '<a href="' . $product_permalink . '">' . $thumbnail . '</a>';
                        $product_name = '<a href="' . $product_permalink . '" class="nm-cart-panel-product-title">' . $product_name . '</a>';
                    }
                    ?>
                    <li id="nm-cart-panel-item-<?php echo esc_attr( $cart_item_key ); ?>" class="<?php echo esc_attr( apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key ) ); ?>">
                        <div class="nm-cart-panel-item-thumbnail">
                            <?php
                            // GEWIJZIGD: Extra opmaakklasse toevoegen bij leeggoed
                            if ( $_product->is_visible() or $_product->get_sku() === 'GIFT' ) {
                                echo '<div class="nm-cart-panel-thumbnail-wrap">';
                            } else {
                                echo '<div class="nm-cart-panel-thumbnail-wrap empties">';
                            }
                            ?>
                                <?php echo $thumbnail; ?>
                                <div class="nm-cart-panel-thumbnail-loader nm-loader"></div>
                            </div>
                        </div>
                        <div class="nm-cart-panel-item-details">
                            <div class="nm-cart-item-loader nm-loader"></div>
                            
                            <?php
                            echo apply_filters( 'woocommerce_cart_item_remove_link', sprintf(
                                '<a href="%s" class="remove" title="%s" data-product_id="%s" data-product_sku="%s" data-cart-item-key="%s"><i class="nm-font nm-font-close2"></i></a>',    
                                esc_url( WC()->cart->get_remove_url( $cart_item_key ) ),
                                __( 'Remove this item', 'woocommerce' ),
                                esc_attr( $product_id ),
                                esc_attr( $_product->get_sku() ),
                                $cart_item_key
                            ), $cart_item_key );
                            ?>
                            
                            <?php echo $product_name; ?>
                            <!-- GEWIJZIGD: Metadata zoals 'Hoort bij' niét tonen -->
                            <?php // echo WC()->cart->get_item_data( $cart_item ); ?>
                            
                            <div class="nm-cart-panel-quantity-pricing">
                                <!-- GEWIJZIGD: Niet tonen bij individueel verkochte cadeauverpakking -->
                                <?php if ( $_product->is_sold_individually() and $_product->get_sku() !== 'GIFT' ) : ?>
                                    <?php
                                        echo apply_filters( 'woocommerce_widget_cart_item_quantity', '<span class="quantity">' . esc_html__( 'Qty', 'woocommerce' ) . ': ' . $cart_item['quantity'] . '</span>', $cart_item, $cart_item_key );
                                    ?>
                                <?php endif; ?>
                                <?php
                                    // GEWIJZIGD: Hoeveelheidsknoppen niet tonen bij onzichtbaar leeggoed
                                    if ( $_product->is_visible() ) :
                                ?>
                                    <div class="product-quantity" data-title="<?php _e( 'Quantity', 'woocommerce' ); ?>">
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
        ?>

    <?php endif; ?>

    <li class="empty"><?php _e( 'No products in the cart.', 'woocommerce' ); ?></li>

</ul><!-- end product list -->

</div>
    
<div class="nm-cart-panel-summary">
    
    <div class="nm-cart-panel-summary-inner">
        
        <?php if ( ! WC()->cart->is_empty() ) : ?>
        
        <p class="total">
            <strong><?php _e( 'Subtotal', 'woocommerce' ); ?>:</strong>
            <span class="nm-cart-panel-summary-subtotal">
                <?php echo WC()->cart->get_cart_subtotal(); ?>
            </span>
        </p>

        <?php do_action( 'woocommerce_widget_shopping_cart_before_buttons' ); ?>

        <p class="buttons">
            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button border wc-forward"><?php _e( 'View cart', 'woocommerce' ); ?></a>
            <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button checkout wc-forward"><?php _e( 'Checkout', 'woocommerce' ); ?></a>
        </p>
        
        <?php endif; ?>
        
        <p class="buttons nm-cart-empty-button">
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" id="nm-cart-panel-continue" class="button border"><?php _e( 'Continue shopping', 'woocommerce' ); ?></a>
        </p>
        
    </div>

</div>

<?php do_action( 'woocommerce_after_mini_cart' ); ?>
    
</div>