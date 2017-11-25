<?php
/*
 * Plugin Name: Woocommerce Gift Wrapper
 * Description: This plugin shows gift wrap options on the WooCommerce cart and/or checkout page, and adds gift wrapping to the order
 * Tags: woocommerce, e-commerce, ecommerce, gift, holidays, present, giftwrap, wrapping
 * Version: 2.0.6
 * Author: Little Package
 * Text Domain: woocommerce-gift-wrapper
 * Domain path: /lang
 * Donate link: paypal.me/littlepackage
 * 
 * Woocommerce Gift Wrapper
 * Copyright: (c) 2015-2017 Little Package (email: littlepackage@protonmail.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Remember this plugin is free. If you have problems with it, please be
 * nice and contact me for help before leaving negative feedback. Thank you.
 * 
 * Woocommerce Gift Wrapper was originally forked from Woocommerce Gift Wrap by Gema75
 * Copyright: (c) 2014 Gema75 - http://codecanyon.net/user/Gema75
 * 
 * Original changes from Woocommerce Gift Wrapper include: OOP to avoid plugin clashes; removal of the option to
 * hide categories (to avoid unintentional, detrimental bulk database changes; use of the Woo API for the
 * settings page; complete restyling of the front-end view including a modal view to unclutter the cart view
 * and CSS tagging to allow easier customization; option for easy front end language adjustments and/or l18n;
 * addition of order notes regarding wrapping to order emails, thank you page, and order pages for admins; 
 * further options; support for Woo > 2.2 menu sections, support for Woo 3.0, security fixes, major
 * accessibility improvements, etc etc
 *
 * I need your support & encouragement! If you have found this plugin useful, and especially if you
 * benefit commercially from it, please donate a few dollars to support my work/the plugin's future:
 * 
 * paypal.me/littlepackage
 * 
 * I understand you have a budget and might not be able to afford to buy the developer (me) a beer or a slice 
 * of pizza in thanks. Maybe you can leave a positive review?
 * 
 * https://wordpress.org/support/plugin/woocommerce-gift-wrapper/reviews
 *
 * Thank you!
 * 
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Gift_Wrapping' ) ) :

class WC_Gift_Wrapping {

    /*
    * More than one giftwrap item in cart?
    * @var string
    */
    var $giftwrap_number = 'no';
    
    /*
    * Where to show giftwrap options
    * @var array
    */
    var $giftwrap_display = array();

    /*
    * Giftwrapped products
    * @var array
    */
    var $giftwrap_products = array();
    
    /*
    * Current WP active theme
    * @var string
    */
    var $current_theme_name;
    
    /*
    * Show giftwrap options in popup/modal
    * @var string
    */
    var $giftwrap_modal;
	var $giftwrap_details;
	var $giftwrap_show_thumb;
    
	public function __construct() {

		if ( ! defined( 'GIFT_PLUGIN_BASE_FILE' ) ) {
			define( 'GIFT_PLUGIN_BASE_FILE', plugin_basename(__FILE__) );
		}
		if ( ! defined( 'GIFT_PLUGIN_URL' ) ) {
			define( 'GIFT_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		}

        $this->includes();
        $this->fetch_settings();

		add_action( 'init',                         array( $this, 'init' ) );
		add_action( 'wp',                           array( $this, 'add_giftwrap_to_order' ) );
		add_action( 'wp_enqueue_scripts',           array( $this, 'enqueue_scripts' ) );
		add_filter( 'woocommerce_cart_item_name',   array( $this, 'add_user_note_into_cart' ), 1, 3 );

		$this->current_theme_name = '';

    }
    
    /*
    * Include files
    * @param void
    * @return void
    */
    public function includes() {
    
    	include_once( 'classes/class-gift-wrapper-settings.php' );
    	include_once( 'classes/class-gift-wrapper-admin.php' );

    }

    /*
    * Init - l10n & hooks
    * @param void
    * @return void
    */
	public function init() {
	
		load_plugin_textdomain( 'woocommerce-gift-wrapper', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

		if ( ! is_array( $this->giftwrap_display ) ) {
			$this->giftwrap_display = str_split( $this->giftwrap_display, 17 );
		}
		if ( in_array( "after_coupon", $this->giftwrap_display ) ) {
			add_action( 'woocommerce_cart_coupon', array( $this, 'add_gift_wrap_after_coupon' ) );
		}
		if ( in_array( "after_cart", $this->giftwrap_display ) ) {
			// GEWIJZIGD: woocommerce_after_cart -> woocommerce_after_cart_table (veel mooiere plaatsing in Savoy!)
            add_action( 'woocommerce_after_cart_table', array( $this, 'add_gift_wrap_after_cart' ) );
		}
	    if ( in_array( "before_cart", $this->giftwrap_display ) ) {
			add_action( 'woocommerce_before_cart', array( $this, 'add_gift_wrap_before_cart' ) );
		}
		if ( in_array( "before_checkout", $this->giftwrap_display ) ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'add_gift_wrap_at_checkout' ) );
		} 
		
	}

    /*
    * Enqueue scripts
    * @param void
    * @return void
    */
	public function enqueue_scripts() {

        $current_theme = wp_get_theme();
    	$this->current_theme_name = $current_theme->Name;
    	
		if ( $this->giftwrap_modal == 'yes' ) {
			// Avada already enqueues Bootstrap. Let's not do it twice.
    		if ( $this->current_theme_name != 'Avada' ) {
        		wp_enqueue_script( 'wcgiftwrap-js', GIFT_PLUGIN_URL .'/assets/js/wcgiftwrapper.js', 'jquery', null, true );	
			}
			wp_enqueue_style( 'wcgiftwrap-css', GIFT_PLUGIN_URL .'/assets/css/wcgiftwrap_modal.css', array(), null );
		} else {
			wp_enqueue_style( 'wcgiftwrap-css', GIFT_PLUGIN_URL .'/assets/css/wcgiftwrap.css', array(), null );
		}

	}

    /*
    * Add gift wrapping to cart
    * @param void
    * @return void
    */
	public function fetch_settings() {
	    // Allow more than one gift wrap product in cart?
		$this->giftwrap_number      = get_option( 'giftwrap_number' );
		// where to display gift wrapping options
		$this->giftwrap_display     = get_option( 'giftwrap_display' );
		// display gift wrapping options as popup?
		$this->giftwrap_modal       = get_option( 'giftwrap_modal' );
		// show thumbnail in giftwrap listings
		$this->giftwrap_show_thumb  = get_option( 'giftwrap_show_thumb' );
        // non-translatable gift wrap details/explanation 
        $this->giftwrap_details     = esc_html( get_option( 'giftwrap_details' ) );
        
	}
	
	/*
    * Add gift wrapping to cart
    * @param void
    * @return void
    */
	public function add_giftwrap_to_order() {

		// chosen giftwrap product ID
		$giftwrap = isset( $_POST['giftwrapproduct'] ) && ! empty( $_POST['giftwrapproduct'] ) ? (int)$_POST['giftwrapproduct'] : false;

		if ( $giftwrap && isset( $_POST['giftwrap_btn'] ) ) {

			$giftwrap_in_cart = $this->is_gift_wrap_in_cart();
	
			// allow more than one gift wrap to cart
			if ( $this->giftwrap_number == 'yes' ) {
			
                WC()->cart->add_to_cart( $giftwrap );
                WC()->session->set( 'gift_wrap_set', $giftwrap );
                if ( isset( $_POST['wc_gift_wrap_notes'] ) ) {
                    WC()->session->set( 'gift_wrap_notes', $_POST['wc_gift_wrap_notes'] );
                }
    
			// only allow one type of gift wrap in cart
			} else if ( $this->giftwrap_number == 'no' ) { 
			
                // remove old gift wrap
				$old_giftwrap = WC()->session->get( 'gift_wrap_set' );
                $old_giftwrap = WC()->cart->generate_cart_id( $old_giftwrap );
                unset( WC()->cart->cart_contents[ $old_giftwrap ] );
                
                // add new gift wrap
				WC()->cart->add_to_cart( $giftwrap );
				WC()->session->set( 'gift_wrap_set', $giftwrap );
				if ( isset( $_POST['wc_gift_wrap_notes'] ) ) {
					WC()->session->set( 'gift_wrap_notes', $_POST['wc_gift_wrap_notes'] );
				}	
			}		
		}

	}

	/*
	* Discover gift wrap products in cart
    * @param void
	* @return bool
	*/
	public function is_gift_wrap_in_cart() {
	
		$giftwrap_in_cart = false;
		
        foreach ( WC()->session->get( 'cart', null ) as $key => $value ) {
            $product_id = $value['product_id'];
            $terms = get_the_terms( $product_id , 'product_cat' );
            
            if ( $terms ) {
				$giftwrap_category = get_option( 'giftwrap_category_id', true );	

                foreach ( $terms as $term ) {
                    if ( $term->term_id == $giftwrap_category ) {
                        $giftwrap_in_cart = true;
                    }
                }
            }
        }
        		
        return $giftwrap_in_cart;

	}

	/**
 	* Display user's note on the cart itemization
 	* @param array Order
	* @return void
 	*/
 	public function add_user_note_into_cart( $product_title, $cart_item, $cart_item_key ) {

		// giftwrap product ID
		$giftwrap = WC()->session->get( 'gift_wrap_set' );
		// giftwrap note
		$gift_wrap_notes = WC()->session->get( 'gift_wrap_notes' );

 		if ( $gift_wrap_notes !='' && $cart_item['product_id'] == $giftwrap ) {
            $product_title .= '<p class="giftwrap_note"><em>' . $gift_wrap_notes . '</em></p>';
            return $product_title;
        } else {
            return $product_title;
        }
	}
	
	/*
	* Return array of products in gift wrap category
 	* @param void
	* @return array
 	*/ 
	public function get_giftwrapped_products() {

		$giftwrap_category_id       = get_option( 'giftwrap_category_id', true );
		$giftwrap_category_slug     = get_term( $giftwrap_category_id, 'product_cat' );

		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => '-1',
			'tax_query' => array(
				array(
					'taxonomy' => 'product_cat',
					'field' => 'slug',
					'terms' =>  $giftwrap_category_slug->slug
				)
			),
			'meta_query' => array(
    		    array(
	    		    'key' => '_stock_status',
    		    	'value' => 'instock'
	        	)
	        ),
		);
	
		return get_posts( $args );	
	
	}
	
	/*
	* Return HTML output for giftwrap product listing
 	* @param $label
	* @return void
 	*/ 
	public function giftwrap_list( $label = '' ) {
	    
        $show_thumb         = '';
        $product_image      = '';

	    if ( count( $this->get_giftwrapped_products() ) > 1 ) { ?>

            <ul class="giftwrap_ul">
            <?php foreach ( $this->get_giftwrapped_products() as $giftwrap_product ) {
                $get_giftwrap_product = new WC_Product( $giftwrap_product->ID );
                $giftwrap_product_price = $get_giftwrap_product->get_price_html();
                $giftwrap_product_URL = $get_giftwrap_product->get_permalink();
                $giftwrap_label = strtolower( preg_replace( '/\s*/', '', get_the_title( $giftwrap_product->ID ) ) );
                if ( $this->giftwrap_show_thumb == 'yes' ) {
                    $product_image = wp_get_attachment_image( get_post_thumbnail_id( $giftwrap_product->ID ), 'thumbnail' );
                    $product_image = '<div class="giftwrap_thumb"><a href="' . $giftwrap_product_URL . '">' . $product_image . '</a></div>';
                    $show_thumb = ' show_thumb';
                }
                $gift_wrap_set = WC()->session->get( 'gift_wrap_set' );
                $radio_checked = isset( $gift_wrap_set ) && ( $giftwrap_product->ID == $gift_wrap_set ) && ( $this->is_gift_wrap_in_cart() === true ) ? 'checked="checked"' : '';

                echo '<li class="giftwrap_li' . esc_attr( $show_thumb ) . '"><input type="radio" ' . $radio_checked . ' name="giftwrapproduct" id="' . esc_attr( $giftwrap_label ) . esc_attr( $label ) .  '" value="' . $giftwrap_product->ID . '">';
                echo '<label for="' . esc_attr( $giftwrap_label ) . esc_attr( $label ) . '" class="giftwrap_desc"><span class="giftwrap_title"> ' . $giftwrap_product->post_title . '</span> ' . $giftwrap_product_price . '</label>' . $product_image . '</li>';
            } ?>
            </ul>
              
        <?php // only one gift wrap product 
        } else { ?>
            <ul class="giftwrap_ul">
            <?php foreach ( $this->get_giftwrapped_products() as $giftwrap_product ) {
                $get_giftwrap_product = new WC_Product( $giftwrap_product->ID );
                $giftwrap_product_price = $get_giftwrap_product->get_price_html();
                $giftwrap_product_URL = $get_giftwrap_product->get_permalink();
                $giftwrap_label = strtolower( preg_replace( '/\s*/', '', get_the_title( $giftwrap_product->ID ) ) );
                if ( $this->giftwrap_show_thumb == 'yes' ) {
                    $product_image = wp_get_attachment_image( get_post_thumbnail_id( $giftwrap_product->ID ), 'thumbnail' );
                    $product_image = '<div class="giftwrap_thumb"><a href="' . $giftwrap_product_URL . '">' . $product_image . '</a></div>';
                    $show_thumb = ' show_thumb';
                }
                $gift_wrap_set = WC()->session->get( 'gift_wrap_set' );
                echo '<li class="giftwrap_li' . esc_attr( $show_thumb ) . '"><label for="' . $label . $giftwrap_label . '" class="giftwrap_desc"><span class="giftwrap_title"> ' . $giftwrap_product->post_title . '</span> ' . $giftwrap_product_price . '</label>' . $product_image . '</li>';
                echo '<input type="hidden" name="giftwrapproduct" value="' . $giftwrap_product->ID . '">';
            } ?>
            </ul>
        <?php } ?>
        <div class="wc_giftwrap_notes_container">
            <label for="wc_gift_wrap_notes<?php echo $label ?>">
            <?php if ( get_option( 'giftwrap_text_label' ) == 'Add Gift Wrap Message:' || get_option( 'giftwrap_text_label' ) === false ) {
                _e( 'Add Gift Wrap Message:', 'woocommerce-gift-wrapper' );
            } else { // pre version 2.0
                echo esc_html( get_option( 'giftwrap_text_label' ) );
            } ?>
		    </label>
            <textarea name="wc_gift_wrap_notes" id="wc_gift_wrap_notes<?php echo $label ?>" cols="30" rows="4" maxlength="<?php echo get_option( 'giftwrap_textarea_limit' ); ?>" class="wc_giftwrap_notes"><?php if ( isset( WC()->session->gift_wrap_notes) ) { echo esc_textarea( WC()->session->gift_wrap_notes ); } ?></textarea>	
        </div>
	<?php } 
	
	/*
	* Display modal/popup HTML
 	* @param void
	* @return void
 	*/ 
	public function display_giftwrap_modal( $label ) { ?>
	    <div class="giftwrap_header_wrapper">
            <p class="giftwrap_header"><a data-toggle="modal" data-target=".giftwrap_products_modal<?php echo $label; ?>" class="btn">
            <?php if ( get_option( 'giftwrap_header' ) == 'Add gift wrapping?' || get_option( 'giftwrap_header' ) === false ) {
                _e( 'Add gift wrapping?', 'woocommerce-gift-wrapper' );
		    } else { // pre version 2.0
                echo esc_html( get_option( 'giftwrap_header' ) );
		    } ?>
            </a></p>
        </div>
        <div id="giftwrap_modal<?php echo $label; ?>" class="giftwrap_products_modal giftwrap_products_modal<?php echo esc_attr( $label ); ?> fusion-modal modal" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content fusion-modal-content">
                    <div class="modal-header">
                        <button class="button btn close giftwrap_cancel fusion-button fusion-button-default" type="button" data-dismiss="modal"><?php _e( 'Cancel', 'woocommerce-gift-wrapper' ); ?></button>
                    </div><!-- /.modal-header -->
                    <?php if ( $this->current_theme_name == 'Avada' || $label == "_cart" ) { ?>
                        <form class="giftwrap_products" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
                    <?php } ?>
                    <div class="modal-body">
                        <?php if ( $this->giftwrap_details != '' ) { ?>
                            <p class="giftwrap_details"><?php echo esc_html( $this->giftwrap_details ); ?></p>
                        <?php }
                        $this->giftwrap_list( $label ); ?>
                    </div><!-- /.modal-body -->
                    <div class="modal-footer">
                        <button type="submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn">
                        <?php if ( get_option( 'giftwrap_button' ) == 'Add Gift Wrap to Order' || get_option( 'giftwrap_button' ) === false ) {
                            _e( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' );
                        } else { // pre version 2.0
                            echo get_option( 'giftwrap_button' );
                        } ?>
		                </button>
                    </div><!-- /.modal-footer -->
                    <?php if ( $this->current_theme_name == 'Avada' || $label == "_cart" ) { ?>
                        </form>
                    <?php } ?>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->

	<?php } // End display_giftwrap_modal() 
	
    /*
    * Add gift wrap options after coupon
    * @param void
    * @return void
    */
	public function add_gift_wrap_after_coupon() {
			
		if ( count( $this->get_giftwrapped_products() ) > 0 ) { ?>

        <div id="wc-giftwrap" class="wc-giftwrap giftwrap-coupon">

         <?php // if modal version
        if ( $this->giftwrap_modal == 'yes' ) { 

            $this->display_giftwrap_modal( $label = "_coupon" );

        // non-modal version
        } else if ( $this->giftwrap_modal == 'no' ) { ?>
            <div class="giftwrap_header_wrapper"><h3 class="giftwrap_header">
            <?php if ( get_option( 'giftwrap_header' ) == 'Add gift wrapping?' || get_option( 'giftwrap_header' ) === false ) {
                _e( 'Add gift wrapping?', 'woocommerce-gift-wrapper' );
		    } else { // pre version 2.0
                echo esc_html( get_option( 'giftwrap_header' ) );
		    } ?>
		    </h3></div>
   
       		<?php if ( $this->current_theme_name == 'Avada' ) { ?>
                <form method="post" class="giftwrap_products">
            <?php }
            if ( $this->giftwrap_details != '' ) { ?>
                <p class="giftwrap_details"><?php echo $this->giftwrap_details; ?></p>
            <?php }
            
            $this->giftwrap_list( $label = "coupon_" ); ?>           
            <button type="submit" id="coupon_giftwrap_submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn">
            <?php if ( get_option( 'giftwrap_button' ) == 'Add Gift Wrap to Order' || get_option( 'giftwrap_button' ) === false ) {
                _e( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' );
            } else { // pre version 2.0
                echo get_option( 'giftwrap_button' );
            } ?>
            </button>
          	<?php if ( $this->current_theme_name == 'Avada' ) { ?>
                </form>
            <?php }         
        }

        $giftwrap_in_cart = $this->is_gift_wrap_in_cart();

        // if replacing the only giftwrap item allowed in cart
        if ( $giftwrap_in_cart === true && $this->giftwrap_number == 'no' ) { ?>
        
            <script type="text/javascript">
                /* <![CDATA[ */
                    jQuery('.coupon .giftwrap_submit').click( function() {
                        if ( window.confirm( "<?php _e( 'Are you sure you want to replace the gift wrap in your cart?', 'woocommerce-gift-wrapper' ); ?>" ) ) {
                            return true;	
                        }
                        return false;
                    });
                /* ]]> */
                </script>		
                <noscript></noscript>

        <?php } ?>

        </div><!-- /.wc-giftwrap -->		

    <?php }

	} // End add_gift_wrap_after_coupon()

    
    
     /*
    * Add gift wrap options on before cart
    * @param void
    * @return void
    */	
	public function add_gift_wrap_before_cart() {

		if ( count( $this->get_giftwrapped_products() ) > 0 ) { ?>

            <div id="wc-giftwrap" class="wc-giftwrap giftwrap-checkout">
                <script type="text/javascript">
                    /* <![CDATA[ */				
                    jQuery( function( $ ) {
                        var wc_checkout_giftwrap = {
                            init: function() {
                                $( document.body ).on( 'click', 'a.show_giftwrap', this.show_giftwrap_form );
                                $( '.giftwrap-checkout form.giftwrap_products' ).hide();
                            },
                            show_giftwrap_form: function() {
                                $( '.giftwrap-checkout .giftwrap_products' ).slideToggle( 400 );
                                return false;
                            }
                        };
                        wc_checkout_giftwrap.init();
                    });
                /* ]]> */
                </script>
                <noscript></noscript>

                    <div class="woocommerce-info"><a href="#" class="show_giftwrap">
                    <?php if ( get_option( 'giftwrap_header' ) == 'Add gift wrapping?' || get_option( 'giftwrap_header' ) === false ) {
                        _e( 'Add gift wrapping?', 'woocommerce-gift-wrapper' );
                    } else { // pre version 2.0
                        echo esc_html( get_option( 'giftwrap_header' ) );
                    } ?>
		            </a></div>
                    <form method="post" class="giftwrap_products">
                    <?php if ( $this->giftwrap_details != '' ) { ?>
                        <p class="giftwrap_details"><?php echo $this->giftwrap_details; ?></p>
                    <?php }
                    $this->giftwrap_list( $label = 'cart_' ); ?>
                    <button type="submit" id="checkout_giftwrap_submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn">
                    <?php if ( get_option( 'giftwrap_button' ) == 'Add Gift Wrap to Order' || get_option( 'giftwrap_button' ) === false ) {
                        _e( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' );
                    } else { // pre version 2.0
                        echo get_option( 'giftwrap_button' );
                    } ?>
                    </button>
                    </form>
                
                <?php 
                $giftwrap_in_cart = $this->is_gift_wrap_in_cart();

                // if replacing the only giftwrap item allowed in cart
                if ( $giftwrap_in_cart === true && $this->giftwrap_number == 'no' ) { ?>
                    <script type="text/javascript">
                    /* <![CDATA[ */
                        jQuery('.giftwrap_submit').click( function() {
                            if ( window.confirm( "<?php _e( 'Are you sure you want to replace the gift wrap in your cart?', 'woocommerce-gift-wrapper' ); ?>" ) ) {
                                return true;	
                            }
                            return false;
                        });
                    /* ]]> */
                    </script>		
                    <noscript></noscript>
                <?php } ?>
            </div><!-- /.wc-giftwrap -->		
        <?php }
        
	} // End add_gift_wrap_before_cart()
		
    /*
    * Add gift wrap options after cart
    * @param void
    * @return void
    */
	public function add_gift_wrap_after_cart() {

		if ( count( $this->get_giftwrapped_products() ) > 0 ) { ?>

        <div class="wc-giftwrap giftwrap-collaterals">
            <?php
            // if modal version
            if ( $this->giftwrap_modal == 'yes' && is_checkout() != true ) {

                 $this->display_giftwrap_modal( $label = "_cart" );

            // non-modal version
            } else if ( $this->giftwrap_modal == 'no' && is_checkout() != true ) { ?>

            <div class="giftwrap_header_wrapper">
                <h3 class="giftwrap_header">
                <?php if ( get_option( 'giftwrap_header' ) == 'Add gift wrapping?' || get_option( 'giftwrap_header' ) === false ) {
                    _e( 'Add gift wrapping?', 'woocommerce-gift-wrapper' );
		        } else { // pre version 2.0
                    echo esc_html( get_option( 'giftwrap_header' ) );
		        } ?>
		        </h3>
            </div>
            <form method="post" class="giftwrap_products">
            <?php if ( $this->giftwrap_details != '' ) { ?>
                <p class="giftwrap_details"><?php echo $this->giftwrap_details; ?></p>
            <?php }
            $this->giftwrap_list( $label = 'cart_' ); ?>
                <button type="submit" id="cart_giftwrap_submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn">
                <?php if ( get_option( 'giftwrap_button' ) == 'Add Gift Wrap to Order' || get_option( 'giftwrap_button' ) === false ) {
                    _e( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' );
                } else { // pre version 2.0
                    echo get_option( 'giftwrap_button' );
                } ?>
                </button>
            </form>
            <?php }  
            $giftwrap_in_cart = $this->is_gift_wrap_in_cart();
            // if replacing the only giftwrap item allowed in cart
            if ( $giftwrap_in_cart === true && $this->giftwrap_number == 'no' ) { ?>
                <script type="text/javascript">
                /* <![CDATA[ */
                    jQuery('.giftwrap-collaterals .giftwrap_submit').click( function() {
                        if ( window.confirm( "<?php _e( 'Are you sure you want to replace the gift wrap in your cart?', 'woocommerce-gift-wrapper' ); ?>" ) ) {
                            return true;	
                        }
                        return false;
                    });
                /* ]]> */
                </script>		
                <noscript></noscript>
            <?php } ?>
        </div><!-- /.wc-giftwrap -->		

    <?php }

	} // End add_gift_wrap_after_cart()
	
    /*
    * Add gift wrap options on checkout page
    * @param void
    * @return void
    */	
	public function add_gift_wrap_at_checkout() {

		if ( count( $this->get_giftwrapped_products() ) > 0 ) { ?>

            <div id="wc-giftwrap" class="wc-giftwrap giftwrap-checkout">
                <script type="text/javascript">
                    /* <![CDATA[ */				
                    jQuery( function( $ ) {
                        var wc_checkout_giftwrap = {
                            init: function() {
                                $( document.body ).on( 'click', 'a.show_giftwrap', this.show_giftwrap_form );
                                $( 'form.giftwrap_products' ).hide();
                            },
                            show_giftwrap_form: function() {
                                $( '.giftwrap_products' ).slideToggle( 400 );
                                return false;
                            }
                        };
                        wc_checkout_giftwrap.init();
                    });
                /* ]]> */
                </script>
                <noscript></noscript>

                    <div class="woocommerce-info"><a href="#" class="show_giftwrap">
                    <?php if ( get_option( 'giftwrap_header' ) == 'Add gift wrapping?' || get_option( 'giftwrap_header' ) === false ) {
                        _e( 'Add gift wrapping?', 'woocommerce-gift-wrapper' );
                    } else { // pre version 2.0
                        echo esc_html( get_option( 'giftwrap_header' ) );
                    } ?>
		            </a></div>
                    <form method="post" class="giftwrap_products">
                    <?php if ( $this->giftwrap_details != '' ) { ?>
                        <p class="giftwrap_details"><?php echo $this->giftwrap_details; ?></p>
                    <?php }
                    $this->giftwrap_list( $label = 'checkout_' ); ?>
                    <button type="submit" id="checkout_giftwrap_submit" class="button btn alt giftwrap_submit fusion-button fusion-button-default" name="giftwrap_btn">
                    <?php if ( get_option( 'giftwrap_button' ) == 'Add Gift Wrap to Order' || get_option( 'giftwrap_button' ) === false ) {
                        _e( 'Add Gift Wrap to Order', 'woocommerce-gift-wrapper' );
                    } else { // pre version 2.0
                        echo get_option( 'giftwrap_button' );
                    } ?>
                    </button>
                    </form>
                
                <?php 
                $giftwrap_in_cart = $this->is_gift_wrap_in_cart();

                // if replacing the only giftwrap item allowed in cart
                if ( $giftwrap_in_cart === true && $this->giftwrap_number == 'no' ) { ?>
                    <script type="text/javascript">
                    /* <![CDATA[ */
                        jQuery('.giftwrap_submit').click( function() {
                            if ( window.confirm( "<?php _e( 'Are you sure you want to replace the gift wrap in your cart?', 'woocommerce-gift-wrapper' ); ?>" ) ) {
                                return true;	
                            }
                            return false;
                        });
                    /* ]]> */
                    </script>		
                    <noscript></noscript>
                <?php } ?>
            </div><!-- /.wc-giftwrap -->		
        <?php }
        
	} // End add_gift_wrap_at_checkout()
	
}  // End class WC_Gift_Wrapping

endif; // End if ( class_exists() )

new WC_Gift_Wrapping();
// That's a wrap!