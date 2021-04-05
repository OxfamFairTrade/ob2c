<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCGW_Wrapping' ) ) :

    class WCGW_Wrapping {
    
        var $giftwrap_in_cart = FALSE; 
        var $cart_virtual_products_only = FALSE;

        /**
         * Constructor
        */
        public function __construct() {
 
            add_action( 'init',                                             array( $this, 'init' ) );
            
            add_action( 'wp',                                               array( $this, 'add_giftwrap_to_order' ) );

            // Load session data into Woo array
            add_filter( 'woocommerce_get_cart_item_from_session',           array( $this, 'get_cart_item_from_session' ), 20, 2 );

            // Just a quick check to see if wrap is in cart
            add_action( 'woocommerce_cart_loaded_from_session',             array( $this, 'check_cart_for_wrap' ), 10, 1 );

            // If thumbnail links aren't desired, remove them from cart as well
            add_filter( 'woocommerce_cart_item_permalink',                  array( $this, 'remove_link_in_cart' ), 10, 3 );  
            
            // maybe disable COD if gift wrap is in cart
            add_filter( 'woocommerce_available_payment_gateways',           array( $this, 'available_payment_gateways' ), 10, 1);

            // Unlink giftwrap item in order if desired            
            add_filter( 'woocommerce_order_item_permalink',                 array( $this, 'remove_link_in_order' ), 10, 3 );

             // Add more item data to the item data array
            add_filter( 'woocommerce_get_item_data',                        array( $this, 'get_item_data'), 10, 2 );

            // Filter the item meta display key, such as on order confirmation page
            add_filter( 'woocommerce_order_item_display_meta_key',          array( $this, 'order_item_display_meta_key' ), 10, 3 );
                    
            // Add line items to order - adjust item before saving to order
            // Fires inside class-wc-checkout.php line 422
            add_action( 'woocommerce_checkout_create_order_line_item',      array( $this, 'checkout_create_order_line_item' ), 10, 3 );
                      

        }  
              
        /**
         * Init - hooks
         *
         * @return void
        */
        public function init() {
        
            if ( is_admin() ) return;

            if ( apply_filters( 'giftwrap_exclude_virtual_products', false ) ) {
                $this->cart_virtual_products_only = TRUE;
            }

            if ( $this->count_giftwrapped_products() < 1 || ( $this->cart_virtual_products_only && $this->cart_virtual_products_only() === TRUE ) ) return; 
            
            $before = $collaterals = $after = $before_checkout = $after_checkout = FALSE;
            $giftwrap_display = get_option( 'wcgwp_display', array() );
        
            if ( ! is_array( $giftwrap_display ) ) {
                $giftwrap_display = str_split( $giftwrap_display, 17 );
            }

            if ( in_array( "before_cart", $giftwrap_display ) ) {
                $before = add_action( 'woocommerce_before_cart', array( $this, 'before_cart' ) );           
            }
            if ( in_array( "after_coupon", $giftwrap_display ) ) {
                $collaterals = add_action( 'woocommerce_before_cart_collaterals', array( $this, 'before_cart_collaterals' ) );           
            }
            if ( in_array( "after_cart", $giftwrap_display ) ) {
                $after = add_action( 'woocommerce_after_cart', array( $this, 'after_cart' ) );
            }
            if ( $before === TRUE || $collaterals === TRUE || $after === TRUE ) {
                add_action( 'wp_footer', array( $this, 'cart_footer_js' ) );
            }
            if ( in_array( "before_checkout", $giftwrap_display ) ) {
                $before_checkout = add_action( 'woocommerce_before_checkout_form', array( $this, 'before_checkout_form' ) );
            }
            if ( in_array( "after_checkout", $giftwrap_display ) ) {
                $after_checkout = add_action( 'woocommerce_after_checkout_form', array( $this, 'after_checkout_form' ) );
            }
            if ( $before_checkout === TRUE || $after_checkout === TRUE ) {
                add_action( 'wp_footer', array( $this, 'checkout_footer_js' ) );
            }        

        }
        
        /**
         * Unlink giftwrap item in cart if desired
         *
         * @param string $link Cart item link, whether URL or blank
         * @param object $cart_item Cart item
         * @param string $cart_item_key Cart item key
         * @return bool
        */
        public function remove_link_in_cart( $link, $cart_item, $cart_item_key ) {

            $link = apply_filters( 'wcgwp_filter_link_in_cart', $link, $cart_item );

            if ( get_option( 'wcgwp_link', 'yes' ) == 'yes' ) {
                return $link;
            }
            if ( $this->check_item_for_giftwrap_cat( $cart_item ) ) {
                $link = '';
            }
            return $link;
    
        }
      
        /**
         * Discover gift wrap products in cart
         *
         * @param object $cart_item
         * @return bool
        */	
        public function check_item_for_giftwrap_cat( $cart_item ) {
        
            $product_id = is_a( $cart_item, 'WC_Order_Item_Product' ) ? $cart_item->get_product_id() : $cart_item['data']->get_id();
            
            $giftwrap_cat_id = get_option( 'wcgwp_category_id', '' );	
            // WPML
            $giftwrap_cat_id = apply_filters( 'wpml_object_id', $giftwrap_cat_id, 'product_cat', true );

            $terms = get_the_terms( $product_id, 'product_cat' );
            if ( $terms ) {
                foreach ( $terms as $term ) {
                    if ( $term->term_id == $giftwrap_cat_id ) {
                        return TRUE;
                    }
                }
            }
            return FALSE;
            
        }        

        /**
         * Put JavaScript inline in footer for cart
         *
         * @return void
        */
        public function cart_footer_js() {

            if ( ! is_cart() ) return;

            if ( get_option( 'wcgwp_modal' ) == 'no' && WCGW_Admin_Notices::check_template_outdated( array( 'wcgwp/cart-slideout-js.php' ) ) ) {
                wp_enqueue_script('jquery'); // make sure jquery is ready
                wc_get_template( 'wcgwp/cart-slideout-js.php', array(), '', WCGW_PLUGIN_DIR . 'templates/');
            }

            // if replacing the only giftwrap item allowed in cart
            if ( $this->giftwrap_in_cart === TRUE && get_option( 'wcgwp_number', 'no' ) == 'no' ) {
                wp_enqueue_script('jquery'); // make sure jquery is ready
                wc_get_template( 'wcgwp/replace-wrap-js.php', array(), '', WCGW_PLUGIN_DIR . 'templates/');            
            }

        }

        /**
         * Put JavaScript inline in footer for checkout
         *
         * @return void
        */
        public function checkout_footer_js() {

            if ( ! is_checkout() ) return;

            if ( get_option( 'wcgwp_modal' ) == 'no' && WCGW_Admin_Notices::check_template_outdated( array( 'wcgwp/checkout-slideout-js.php' ) ) ) {
                wp_enqueue_script('jquery'); // make sure jquery is ready
                wc_get_template( 'wcgwp/checkout-slideout-js.php', array(), '', WCGW_PLUGIN_DIR . 'templates/');
            }

            // if replacing the only giftwrap item allowed in cart
            if ( $this->giftwrap_in_cart === TRUE && get_option( 'wcgwp_number', 'no' ) == 'no' ) {
                wp_enqueue_script('jquery'); // make sure jquery is ready
                wc_get_template( 'wcgwp/replace-wrap-js.php', array(), '', WCGW_PLUGIN_DIR . 'templates/');            
            }
        
        }

        /**
         * Add gift wrapping to cart
         *
         * @return void
         * @throws Exception
        */
        public function add_giftwrap_to_order() {
        
            if ( is_admin() || ( ! is_checkout() && ! is_cart() ) ) return;
            
            if ( ! apply_filters( 'wcgwp_add_giftwrap_to_order', TRUE ) ) return;
        
            if ( isset( $_POST['wcgwp_submit_before_cart'] ) ) {
                $product = isset( $_POST['wcgwp_product_before_cart'] ) ? (int) $_POST['wcgwp_product_before_cart'] : FALSE;
                if ( ! $product ) return;
                $notes = $_POST['wcgwp_note_before_cart'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['wcgwp_note_before_cart'] ) ) ) : FALSE;            
                $this->add_giftwrap( $product, $notes );
            }
            if ( isset( $_POST['wcgwp_submit_coupon'] ) ) {
                $product = isset( $_POST['wcgwp_product_coupon'] ) ? (int) $_POST['wcgwp_product_coupon'] : FALSE;
                if ( ! $product ) return;
                $notes = $_POST['wcgwp_note_coupon'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['wcgwp_note_coupon'] ) ) ) : FALSE;
                $this->add_giftwrap( $product, $notes );
            }
            if ( isset( $_POST['wcgwp_submit_after_cart'] ) ) {
                $product = isset( $_POST['wcgwp_product_after_cart'] ) ? (int) $_POST['wcgwp_product_after_cart'] : FALSE;
                if ( ! $product ) return;                    
                $notes = $_POST['wcgwp_note_after_cart'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['wcgwp_note_after_cart'] ) ) ) : FALSE;
                $this->add_giftwrap( $product, $notes );
            }   
            if ( isset( $_POST['wcgwp_submit_checkout'] ) ) {
                $product = isset( $_POST['wcgwp_product_checkout'] ) ? (int) $_POST['wcgwp_product_checkout'] : FALSE;
                if ( ! $product ) return;                                        
                $notes = $_POST['wcgwp_note_checkout'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['wcgwp_note_checkout'] ) ) ) : FALSE;
                $this->add_giftwrap( $product, $notes );
            }
            if ( isset( $_POST['wcgwp_submit_after_checkout'] ) ) {
                $product = isset( $_POST['wcgwp_product_after_checkout'] ) ? (int) $_POST['wcgwp_product_after_checkout'] : FALSE;
                if ( ! $product ) return;                                        
                $notes = $_POST['wcgwp_note_after_checkout'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['wcgwp_note_after_checkout'] ) ) ) : FALSE;
                $this->add_giftwrap( $product, $notes );
            }
            // backward compatibility
            if ( isset( $_POST['giftwrap_btn'] ) ) {
                $product = isset( $_POST['cart_wcgwp_product'] ) ? (int)$_POST['cart_wcgwp_product'] : FALSE;
                if ( ! $product ) return;
                $notes = $_POST['cart_wcgwp_note'] != '' ? array( 'wcgwp_cart_note' => sanitize_text_field( stripslashes( $_POST['cart_wcgwp_note'] ) ) ) : FALSE;
                $this->add_giftwrap( $product, $notes );
            }
            // POST/REDIRECT/GET to prevent wrap from showing back up after delete + refresh
            if ( isset( $_POST['wcgwp_submit_before_cart'] ) || isset( $_POST['wcgwp_submit_coupon'] ) || isset( $_POST['wcgwp_submit_after_cart'] ) ) {
                wp_safe_redirect( wc_get_cart_url(), 303 );
                exit; // not die() because inside hook
            }
            if ( isset( $_POST['wcgwp_submit_checkout'] ) || isset( $_POST['wcgwp_submit_after_checkout'] ) ) {
                wp_safe_redirect( wc_get_checkout_url(), 303 );
                exit; // not die() because inside hook
            }            
                        
        }
        
        /**
         * Use WC add_to_cart method to add cart/checkout wrap to order
         *
         * @param int $product
         * @param array|string $notes
         * @return void
         * @throws Exception
        */ 
        public function add_giftwrap( $product, $notes ) {

            // allow more than one gift wrap to cart
            $giftwrap_num = get_option( 'wcgwp_number', 'no' );
            if ( $giftwrap_num == 'no' && $this->giftwrap_in_cart === TRUE ) {

                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $product_id = $cart_item['product_id'];             
                    $it_matches = FALSE;    
                    $terms = get_the_terms( $product_id , 'product_cat' );
                    if ( $terms ) {
                        $giftwrap_cat_id = get_option( 'wcgwp_category_id', '' );	
                        // WPML
                        $giftwrap_cat_id = apply_filters( 'wpml_object_id', $giftwrap_cat_id, 'product_cat', true );

                        foreach ( $terms as $term ) {
                            if ( $term->term_id == $giftwrap_cat_id ) {
                                $it_matches = TRUE;    
                                break;                    
                            }
                        }
                        if ( $it_matches ) {
                            WC()->cart->remove_cart_item( $cart_item_key );
                        }
                    }
                }
            }
            if ( ! $notes ) {
                $notes = array();
            }
            WC()->cart->add_to_cart( $product, 1, 0, array(), $notes );	

        }
          
        /**
         * Check WooCommerce cart for wrap
         *
         * @param array $cart
         * @return boolean
        */	
        public function check_cart_for_wrap( $cart ) {
        
            foreach ( $cart->cart_contents as $value ) {
        
                $product_id = $value['product_id']; 
      
                $terms = get_the_terms( $product_id, 'product_cat' );
                if ( $terms ) {
                
                    $giftwrap_cat_id = get_option( 'wcgwp_category_id', '' );	
                    // WPML
                    $giftwrap_cat_id = apply_filters( 'wpml_object_id', $giftwrap_cat_id, 'product_cat', true );
                    
                    foreach ( $terms as $term ) {
                        if ( $term->term_id == $giftwrap_cat_id ) {
                            $this->giftwrap_in_cart = TRUE;
                        }
                    }
                    
                }

            }
            
        }

        /**
         * Get cart item from session.
         *
         * @param array $cart_item Cart item data.
         * @param array $values    Cart item values.
         * @return array
         */
        public function get_cart_item_from_session( $cart_item, $values ) {

            // cart/checkout hooked general gift wrapping
            if ( isset( $values['wcgwp_cart_note'] ) ) {
                $cart_item['wcgwp_cart_note'] = $values['wcgwp_cart_note'];
            }
            return $cart_item;

        }

        /**
         * Return array of products in gift wrap category
         *
         * @return null|array
        */ 
        public function get_wcgw_products() {

            $giftwrap_cat_id = get_option( 'wcgwp_category_id' );
            // WPML
            $giftwrap_cat_id = apply_filters( 'wpml_object_id', $giftwrap_cat_id, 'product_cat', true );
            $suppress_filters = TRUE;               
            if ( class_exists( 'SitePress' ) ) { // for WPML
                $suppress_filters = FALSE;
            }
            // admin doesn't have a gift wrap category set!
            if ( empty( $giftwrap_cat_id ) || $giftwrap_cat_id == 'none' ) {
                return array();
            } 
            $orderby = 'date';
            $order = 'DESC';
            $args = apply_filters( 'wcgwp_post_args', array(
                'post_type'         => 'product',
                'post_status'       => 'publish',
                'posts_per_page'    => '-1',
                'orderby'           => apply_filters( 'wcgwp_orderby', $orderby ),
                'order'             => apply_filters( 'wcgwp_order', $order ),
                'suppress_filters'  => $suppress_filters,
                'tax_query'         => array(
                    array(
                        'taxonomy'  => 'product_cat',
                        'field'     => 'id',
                        'terms'     =>  $giftwrap_cat_id
                    )
                ),
                'meta_query'        => array(
                    array(
                        'key'       => '_stock_status',
                        'value'     => 'instock'
                    )
                ),
            ) );
        	return apply_filters( 'wcgwp_wrap_posts', get_posts( $args ) );	

        }  

        /**
         * Count array of products in gift wrap category
         *
         * @return int
        */ 
        public function count_giftwrapped_products() {
    
            return count( $this->get_wcgw_products() );
        
        }
        
        /**
         * Wrapper function for gift_wrap_action()
         *
         * @return void
        */
        public function before_cart() {
        
            $this->gift_wrap_action( '_before_cart' );
        
        }
        
        /**
         * Wrapper function for gift_wrap_action()
         *
         * @return void
        */
        public function before_cart_collaterals() {
        
            $this->gift_wrap_action( '_coupon' );
        
        }
        
        /**
         * Wrapper function for gift_wrap_action()
         *
         * @return void
        */
        public function after_cart() {
        
            $this->gift_wrap_action( '_after_cart' );
        
        }
        
        /**
         * Wrapper function for gift_wrap_action()
         *
         * @return void
        */
        public function before_checkout_form() {
        
            $this->gift_wrap_action( '_checkout' );
        
        }
        
        /**
         * Wrapper function for gift_wrap_action()
         *
         * @return void
        */
        public function after_checkout_form() {
        
            $this->gift_wrap_action( '_after_checkout' );
        
        }

        /**
         * Add gift wrap options to cart/checkout action hooks
         *
         * @param string $label
         * @return void
        */
        public function gift_wrap_action( $label ) {
        
        	$list = $this->get_wcgw_products();
        
        	if ( ! apply_filters( 'wcgwp_continue_gift_wrap_action', TRUE, $list, $label ) ) return;
            
            $giftwrap_details = get_option( 'wcgwp_details', 'We offer the following gift wrap options:' );
            ob_start(); ?>

            <div class="wc-giftwrap giftwrap<?php echo $label; ?> giftwrap-before-cart giftwrap-after-cart <?php echo $this->extra_class(); ?>">

                <?php
                // if modal version
                if ( get_option( 'wcgwp_modal', 'no' ) == 'yes' ) {

                    wc_get_template( 'wcgwp/modal.php', array( 'label' => $label, 'list' => $list, 'giftwrap_details' => $giftwrap_details, 'show_thumbs' => $this->show_thumbs() ), '', WCGW_PLUGIN_DIR . 'templates/');

                // non-modal version
                } else { 
                
                    if ( WCGW_Admin_Notices::check_template_outdated( array( 'wcgwp/giftwrap-list-cart.php' ) ) ) { ?>

                    <div class="giftwrap_header_wrapper gift-wrapper-info">
                        <a href="#" class="show_giftwrap show_giftwrap<?php echo $label; ?>"><?php echo apply_filters( 'wcgwp_add_wrap_prompt', esc_html__( 'Add gift wrap?', 'woocommerce-gift-wrapper' ) ); ?></a>
                    </div>
                    <form method="post" class="giftwrap_products giftwrapper_products non_modal wcgwp_slideout wcgwp_form">
                    <?php if ( ! apply_filters( 'wcgwp_hide_details', FALSE ) ) { ?>
                        <p class="giftwrap_details">
                            <?php echo esc_html__( $giftwrap_details, 'woocommerce-gift-wrapper' ); ?>
                        </p>
                    <?php }
                    wc_get_template( 'wcgwp/giftwrap-list-cart.php', array( 'label' => $label, 'list' => $list, 'show_thumbs' => $this->show_thumbs() ), '', WCGW_PLUGIN_DIR . 'templates/');
                     ?>
                        <button type="submit" id="cart_giftwrap_submit" class="button btn alt giftwrap_submit replace_wrap fusion-button fusion-button-default fusion-button-default-size" name="wcgwp_submit<?php echo $label; ?>"><?php echo apply_filters( 'wcgwp_add_wrap_button_text', esc_html__( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' ) ); ?></button>
                    </form>
                    <?php } else { // new template since version 4.4
                        wc_get_template( 'wcgwp/giftwrap-list.php', array( 'label' => $label, 'list' => $list, 'show_thumbs' => $this->show_thumbs() ), '', WCGW_PLUGIN_DIR . 'templates/');
                    }
                    
                } ?>
            </div>	
            
        <?php echo ob_get_clean();
        
        }
            
        /**
         * Check if the cart contains virtual product
         * via Remi Corson, 10/2013
         *
         * @return bool
        */
        public function cart_virtual_products_only() {

            $has_virtual_products = FALSE;
            $virtual_products = 0;

            $products = WC()->cart->get_cart();
            
            if ( ! $products ) return FALSE;

            foreach ( $products as $product ) {
  
                // Get product ID and '_virtual' post meta
                $product_id = $product['product_id'];
                $is_virtual = get_post_meta( $product_id, '_virtual', TRUE );
              
                // Update $has_virtual_product if product is virtual
                if ( $is_virtual == 'yes' ) {
                    $virtual_products += 1;
                }
            }

            if ( count( $products ) == $virtual_products ) {
                $has_virtual_products = TRUE;
            }
            return apply_filters( 'wcgwp_virtual_products_only', $has_virtual_products );
        
        }

        /**
         * Add conditional classes to giftwrap wrapper div
         *
         * @return string
        */             
        public function extra_class() {
        
            $extra_class = '';
            if ( $this->giftwrap_in_cart === FALSE || ( $this->giftwrap_in_cart === TRUE && get_option( 'wcgwp_number', 'no' ) == 'yes' ) ) {
                 $extra_class = ' wcgwp_could_giftwrap';
            }
            return apply_filters( 'wcgwp_extra_wrapper_class', $extra_class );

        }
                
        /**
         * Whether to show gift wrap product thumbnails...
         * 
         * @return bool
        */	
        public function show_thumbs() {
        
            if ( get_option( 'wcgwp_show_thumb', 'yes' ) == 'yes' ) {
                return TRUE;
            }
            return FALSE;

        }

        /**
         * Maybe disable payment gateways if gift wrap in cart
         *
         * @param array $gateways
         * @return null|array
        */
        public function available_payment_gateways( $gateways ) {
            
            if ( ! $this->giftwrap_in_cart ) return $gateways;
            if ( apply_filters( 'wcgwp_remove_cod_gateway', FALSE ) ) {
                if ( isset( $gateways['cod'] ) ) {
                    unset( $gateways['cod'] );
                }
            }
            $gateways = apply_filters( 'wcgwp_change_gateways', $gateways );
            return $gateways;

        }

        /**
         * Unlink giftwrap item in order if desired
         *
         * @param string $link Order item link, whether URL or blank
         * @param object $item Order item
         * @param object $order Order
         * @return string
        */
        public function remove_link_in_order( $link, $item, $order ) {
            
            // exit if we're not dealing with wrap
            if ( ! $this->check_item_for_giftwrap_cat( $item ) ) return $link;
            if ( get_option( 'wcgwp_link', 'yes' ) == 'no' ) {
                $link = '';
            }
            return $link;
    
        }  

        /**
         * Display user's note on the cart itemization
         *
         * @param array $item_data
         * @param array $cart_item
         * @return array
        */
        public function get_item_data( $item_data, $cart_item ) {

            // cart/checkout hooked general gift wrapping
            if ( ! isset( $cart_item['wcgwp_cart_note'] ) ) return $item_data;

            $note_value = isset( $cart_item['wcgwp_cart_note'] ) ? $cart_item['wcgwp_cart_note'] : '';
            // GEWIJZIGD: Stel expliciete key in zodat de boodschap slechts één keer verschijnt
            $item_data['wcgwp_cart_note'] = array(
                // GEWIJZIGD: Duidelijker label
                'key'   => __( 'Persoonlijke boodschap', 'oxfam-webshop' ),
                'value' => $note_value,
            );
            return $item_data;
      
        }
        
        /**
         * Filter the item meta display key, such as on order confirmation page
         *
         * @param string $display_key   Display key
         * @param object $meta          WC_Meta_Data
         * @param object $order_item    WC_Order_Item_Product
         * @return string
         */        
        public function order_item_display_meta_key( $display_key, $meta, $order_item ) {

            if ( $display_key == 'wcgwp_note' ) {
                // GEWIJZIGD: Duidelijker label
                $display_key = str_replace( 'wcgwp_note', __( 'Persoonlijke boodschap', 'oxfam-webshop' ), $display_key );
            }
            return $display_key;

        }

        /**
         * Include add-ons line item meta.
         *
         * @param object $item             WC_Order_Item_Product
         * @param string $cart_item_key    Cart item key.
         * @param array $values            Order item values.
         * @return object
        */
        public function checkout_create_order_line_item( $item, $cart_item_key, $values ) {

            if ( isset( $values['wcgwp_cart_note'] ) ) {
                // GEWIJZIGD: Voeg de boodschap slechts één keer toe als metadata!
                if ( $item->get_meta('wcgwp_note') === '' ) {
                    $item->add_meta_data( 'wcgwp_note', $values['wcgwp_cart_note'] );
                }
            }
            return $item;

        }    
          
    } // end class
    
endif;