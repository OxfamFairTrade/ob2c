<?php
/**
 * Cart Page
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 4.4.0
 NM: Modified */

defined( 'ABSPATH' ) || exit;

global $nm_theme_options;

// Action: woocommerce_cart_collaterals
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

// Action: woocommerce_after_cart
add_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display' );
?>

<?php do_action( 'woocommerce_before_cart' ); ?>

<!-- GEWIJZIGD: Extra opening wrappers -->
<div class="container">
	<div class="col-row">
		<div class="col-md-8">

			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

				<h3><?php esc_html_e( 'Shopping Cart', 'nm-framework' ); ?></h3>
				
				<!-- GEWIJZIGD: Extra proceed knop bovenaan -->
				<div class="wc-proceed-to-checkout">
					<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
				</div>

				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
					<tbody>
						<?php do_action( 'woocommerce_before_cart_contents' ); ?>

						<?php
						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

							if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
								?>
								<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

									<!-- GEWIJZIGD: Extra opmaakklasse toevoegen bij leeggoed -->
									<td class="product-thumbnail <?php echo ( ! $_product->is_visible() and $_product->get_sku() !== 'GIFT' ) ? 'empties' : ''; ?>"><?php
										$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );

										if ( ! $product_permalink ) {
				                            echo $thumbnail; // PHPCS: XSS ok.
				                        } else {
				                            printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
				                        }
				                    ?></td>

									<td class="nm-product-details" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
										<?php
											if ( ! $product_permalink ) {
				                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', esc_html( $_product->get_name() ), $cart_item, $cart_item_key ) . '&nbsp;' );
											} else {
				                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), esc_html( $_product->get_name() ) ), $cart_item, $cart_item_key ) );
											}
				                            
				                            do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
				                
											// Meta data
				                            echo wc_get_formatted_cart_item_data( $cart_item ); // PHPCS: XSS ok.

											// Backorder notification
				                            if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
				                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
				                            }
										?>
									</td>

									<?php if ( $nm_theme_options['cart_show_item_price'] ) : ?>
				                        <td class="nm-product-quantity" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
				                            <?php
				                                echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
				                            ?>
				                        </td>
				                    <?php endif; ?>

		                            <td class="nm-product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
		                                <?php
		                                    if ( $_product->is_sold_individually() ) {
		                                        $product_quantity = sprintf( '<span>%s</span>: 1 <input type="hidden" name="cart[%s][qty]" value="1" />', esc_html__( 'Qty', 'woocommerce' ), $cart_item_key );
		                                    } else {
		                                        $product_quantity = woocommerce_quantity_input(
		                                            array(
		                                                'input_name'   => "cart[{$cart_item_key}][qty]",
		                                                'input_value'  => $cart_item['quantity'],
		                                                'max_value'    => $_product->get_max_purchase_quantity(),
		                                                'min_value'    => '0',
		                                                'product_name' => $_product->get_name(),
		                                            ),
		                                            $_product,
		                                            false
		                                        );
		                                    }
		                                    
		                                    echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // PHPCS: XSS ok.
		                                ?>
		                            </td>
		                            
		                            <td class="nm-product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
		                                <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
		                            </td>

									<!-- GEWIJZIGD: Verwijderknop niet tonen -->
								</tr>
								<?php
							}
						}

						do_action( 'woocommerce_cart_contents' );
						?>
						<!-- GEWIJZIGD: Laat rij staan om buttons te triggeren maar verberg altijd -->
						<tr style="display: none;">
							<td colspan="3" class="actions">

								<?php if ( wc_coupons_enabled() ) { ?>
									<div class="coupon">
				                        <label for="coupon_code"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label> <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" /> <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?></button>
										<?php //do_action( 'woocommerce_cart_coupon' ); ?>
									</div>
								<?php } ?>
				                
				                <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" id="nm-cart-continue-button" class="button border"><?php esc_attr_e( 'Continue shopping', 'woocommerce' ); ?></a>
				                
				                <button type="submit" class="button border" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

								<?php do_action( 'woocommerce_cart_actions' ); ?>
				                
				                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
							</td>
						</tr>

						<?php do_action( 'woocommerce_after_cart_contents' ); ?>
					</tbody>
				</table>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>

				<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

				<div class="cart-collaterals">
				    
				    <h2><?php _e( 'Cart totals', 'woocommerce' ); ?></h2>
				    
				    <?php if ( ! is_ajax() && wc_coupons_enabled() ) { ?>
				        <div class="nm-coupon-wrap">
				            <div class="nm-coupon-inner">
				                <a href="#" id="nm-coupon-btn"><?php esc_html_e( 'Add coupon', 'woocommerce' ); ?></a>

				                <div class="nm-coupon">
				                    <input type="text" id="nm-coupon-code" class="input-text" name="nm_coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
				                    
				                    <input type="submit" id="nm-apply-coupon-btn" class="button" name="nm_apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>" />

				                    <?php do_action( 'woocommerce_cart_coupon' ); ?>
				                </div>
				            </div>
				        </div>
				    <?php } ?>
				    
					<?php 
				        /**
						 * Cart collaterals hook.
						 *
						 * @hooked woocommerce_cross_sell_display
						 * @hooked woocommerce_cart_totals - 10
						 */
				        do_action( 'woocommerce_cart_collaterals' );
				    ?>

				    <div class="wc-proceed-to-checkout">
						<a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" id="nm-cart-continue-button" class="button border"><?php esc_attr_e( 'Continue shopping', 'woocommerce' ); ?></a>
						<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
					</div>

				</div>
			</form>	

		</div>

		<!-- GEWIJZIGD: Toon store selector in rechterkolom -->
		<div class="col-md-4">
			<div class="storeselector-wrapper">
				<p>Je bestelling wordt verzorgd door:</p>
				<?php get_template_part( 'template-parts/store-selector/current', NULL, array( 'context' => 'cart' ) ); ?>
			</div>
		</div>

	<!-- GEWIJZIGD: Extra closing wrappers -->
	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
