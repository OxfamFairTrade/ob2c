<?php
    
    if ( ! defined( 'ABSPATH' ) ) { exit;}
       
    class WOO_MSTORE_functions 
        {
            var $options;
                        
            /**
            * 
            * Run on class construct
            * 
            */
            function __construct( ) 
                {                    
                    //add specific classes for list table within the admin
                    add_filter( 'post_class', array($this, 'post_class'), 10, 3);
                }
                
            /**
            * put your comment there...
            * 
            */
            function __destruct()
                {
                    
                }
                
            
            function create_tables()
                {
                    
                }
            
            function fetch_image($url) 
                {
                    if ( function_exists("curl_init") ) 
                        {
                            return $this->curl_fetch_image($url);
                        } 
                    elseif ( ini_get("allow_url_fopen") ) 
                        {
                            return $this->fopen_fetch_image($url);
                        }
                }

            function curl_fetch_image($url) 
                {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $image = curl_exec($ch);
                    curl_close($ch);
                    return $image;
                }

            function fopen_fetch_image($url) 
                {
                    $image = file_get_contents($url, false, $context);
                    return $image;
                }
                
            
            /**
            * Init actions
            * 
            */
            static function init()
                {
                    
                    // GEWIJZIGD: Deze status hebben wij nergens voor nodig!
                    // self::register_custom_post_status();
                    
                    add_filter('wc_order_statuses', array( __CLASS__ , 'wc_order_statuses'), 10);
                    
                    add_filter('woocommerce_reduce_order_stock', array( __CLASS__ , 'woocommerce_reduce_order_stock'), 10);
                    
                }
            
            
            /**
            * Register custom post status
            *     
            */
            static function register_custom_post_status() 
                {
                    
                    register_post_status( 'wc-backorder', array(
                                                                'label'                     => _x( 'Reklamasjon', 'Order status', 'woonet' ),
                                                                'public'                    => true,
                                                                'exclude_from_search'       => false,
                                                                'show_in_admin_all_list'    => true,
                                                                'show_in_admin_status_list' => true,
                                                                'label_count'               => _n_noop( 'Reklamasjon <span class="count">(%s)</span>', 'Reklamasjon <span class="count">(%s)</span>', 'woonet' )
                                                                ) 
                    );

                }
                
            /**
            * Append a new status to woocommerce
            * 
            */
            static function wc_order_statuses($order_statuses)
                {
                    $order_statuses['wc-backorder'] =   _x( 'Reklamasjon', 'Order status', 'woonet' );
                    
                    return $order_statuses;   
                }
                

            /**
            * Reduce stock when new order, to parent (unless is already a parent product) then replicate to all network
            *     
            * @param mixed $order
            */
            static function woocommerce_reduce_order_stock($order)
                {
                    global $blog_id;
                                            
                    if( sizeof( $order->get_items() ) < 1 )
                        return;
                    
                    $options    =   self::get_options();
                    
                    $exclude_blog   =   array($blog_id);
                        
                    foreach ( $order->get_items() as $item ) 
                        {
                            if ( $item['product_id'] > 0 ) 
                                {
                                    //check if is parent or child product
                                    $network_parent_product_id    =   get_post_meta($item['product_id'], '_woonet_network_is_child_product_id', TRUE);
                                    
                                                                        
                                    if($network_parent_product_id   >   0)
                                        {
                                            
                                            //check if need to syncronize or not
                                            $_woonet_child_inherit_updates      =   get_post_meta($item['product_id'] , '_woonet_child_inherit_updates', TRUE);
                                            $_woonet_child_stock_synchronize    =   get_post_meta($item['product_id'] , '_woonet_child_stock_synchronize', TRUE);
                                            if($options['synchronize-stock']    !=  'yes'   &&  ($_woonet_child_inherit_updates    !=  'yes' &&  $_woonet_child_stock_synchronize    !=  'yes'))
                                                continue;
                                            
                                            //this is a child product,
                                            //syncronize the parent 
                                            $network_parent_product_site_id       =   get_post_meta( $item['product_id'], '_woonet_network_is_child_site_id', TRUE );
                                            
                                            
                                            if($item['variation_id']    >   0)
                                                {
                                                    //this is a variable product
                                                    
                                                    $_stock                    =   (int)get_post_meta($item['variation_id'] ,   '_stock',       TRUE);
                                                    $_stock_status             =   get_post_meta($item['variation_id'] ,        '_stock_status', TRUE);
                                                    
                                                    $network_parent_variation_product_id    =   get_post_meta($item['variation_id'], '_woonet_network_is_child_product_id', TRUE);
                                                    
                                                    switch_to_blog( $network_parent_product_site_id );
                                      
                                                    update_post_meta($network_parent_variation_product_id, '_stock', $_stock);
                                                    update_post_meta($network_parent_variation_product_id, '_stock_status', $_stock_status);
                                                    
                                                    restore_current_blog();
                                                    
                                                }
                                                else
                                                {
                                                    //this is a simple product
                                                    $_stock                    =   (int)get_post_meta($item['product_id'] ,   '_stock',       TRUE);
                                                    $_stock_status             =   get_post_meta($item['product_id'] ,        '_stock_status', TRUE);
                                                    
                                                    //update the [arent
                                                    switch_to_blog( $network_parent_product_site_id );
                                      
                                                    update_post_meta($network_parent_product_id, '_stock', $_stock);
                                                    update_post_meta($network_parent_product_id, '_stock_status', $_stock_status);
                                                    
                                                    restore_current_blog();
                                                       
                                                }
                                            
                                        }
                                        else
                                        {
                                            $network_parent_product_id          =   $item['product_id'];
                                            $network_parent_product_site_id     =   $blog_id;
                                        }
                                    
                                   
                                   
                                   WOO_MSTORE_functions::update_stock_across_network( $network_parent_product_id, $network_parent_product_site_id, $exclude_blog );
                                  

                                }
                        } 
                    
                }
                

            
            /**
            * Syncronize stock across network
            * This should be used along with a main product, which replicate to all childs
            * 
            * @param mixed $product_id
            * @param mixed $new_stock
            * @param mixed $exclude_blog
            */
            static function update_stock_across_network($parent_product_id, $parent_blog_id, $exclude_blog = array())
                {
                    global $blog_id;
                    
                    //check for synchronize-stock option
                    $options    =   self::get_options();
                    
                    switch_to_blog( $parent_blog_id );
                    
                    $args =     array(
                                        'fields' => 'names'
                                        );
                    $product_type = wp_get_object_terms( $parent_product_id, 'product_type', $args);
                    
                    //get variations
                    if(in_array('variable', $product_type))
                        {
                            $child_variations = get_children( 'post_parent='.$parent_product_id.'&post_type=product_variation');
                        }
                        else
                        $child_variations  =   array();
                        
                        
                    //create a map of stocks for every variation
                    $_child_variations_stocks   =   array();
                    if(count($child_variations)    >   0)
                        {
                            foreach($child_variations  as  $_child_variation)
                                {
                                    $_stock                    =   (int)get_post_meta($_child_variation->ID , '_stock', TRUE);
                                    $_stock_status             =   get_post_meta($_child_variation->ID , '_stock_status', TRUE);   
                                    
                                    $_child_variations_stocks[$_child_variation->ID]    =   array(
                                                                                                    '_stock'            =>  $_stock,
                                                                                                    '_stock_status'     =>  $_stock_status,
                                                                                                    );
                                }
                        }
                        
                    $stock                    =   (int)get_post_meta($parent_product_id , '_stock', TRUE);
                    $stock_status             =   get_post_meta($parent_product_id , '_stock_status', TRUE);
                    
                    restore_current_blog();
                    
                    
                    //add current parent product site id to exclude list
                    $exclude_blog[] =   $parent_blog_id;
                          
                    //replicate this stock update to other netChilds as well
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            
                            if(in_array($network_site->blog_id, $exclude_blog))
                                continue;
                            
                            switch_to_blog( $parent_blog_id );
                            $publish_to  =   get_post_meta( $parent_product_id, '_woonet_publish_to_'. $network_site->blog_id, true );
                            restore_current_blog();
                            
                            if(empty($publish_to)   ||  $publish_to !=  'yes')
                                continue;
                                    
                            //publish all meta if does not exists
                            switch_to_blog( $network_site->blog_id );
                            
                            
                            //sync basic product
                            $args   =   array(
                                                'post_type'     =>  'any',
                                                'post_status'   =>  'any',
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                    'value'   => $parent_product_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $parent_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                            $custom_query       =   new WP_Query($args);
                            if($custom_query->found_posts   >   0)
                                    {
                                        //product previously created, this is an update
                                        $child_post =   $custom_query->posts[0];
                                        
                                        //check if need for stock update
                                        $_woonet_child_inherit_updates      =   get_post_meta($child_post->ID , '_woonet_child_inherit_updates', TRUE);
                                        $_woonet_child_stock_synchronize    =   get_post_meta($child_post->ID , '_woonet_child_stock_synchronize', TRUE);
                                        
                                        //check if there's a syncronize require for this
                                        if($options['synchronize-stock']    !=  'yes'   &&  ($_woonet_child_inherit_updates    !=  'yes' &&  $_woonet_child_stock_synchronize    !=  'yes'))
                                            {
                                                restore_current_blog();
                                                continue;
                                            }
                                            
                                        update_post_meta($child_post->ID, '_stock',         $stock);
                                        update_post_meta($child_post->ID, '_stock_status',  $stock_status);
                                    }
                            
                            //something went wrong..
                            if (!is_object($child_post))
                                {
                                    restore_current_blog();
                                    continue;   
                                }
                                
                            $args =     array(
                                                'fields' => 'names'
                                                );
                            $product_type = wp_get_object_terms( $child_post->ID, 'product_type', $args);
                            
                            //get variations
                            if(in_array('variable', $product_type))
                                {
                                    $_child_variations = get_children( 'post_parent='.$child_post->ID.'&post_type=product_variation');
                                }
                                else
                                $_child_variations  =   array();
                                    
                            //update any variations this product may have
                            if(count($_child_variations)    >   0)
                                {
                                    foreach($_child_variations  as  $_child_variation)
                                        {
                                            $_woonet_network_is_child_product_id    =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_product_id', TRUE);   
                                            $_woonet_network_is_child_site_id       =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_site_id', TRUE);
                                            
                                            if (!isset($_child_variations_stocks[$_woonet_network_is_child_product_id]))
                                                continue;
                                            
                                            update_post_meta($_child_variation->ID, '_stock', $_child_variations_stocks[$_woonet_network_is_child_product_id]['_stock']);
                                            update_post_meta($_child_variation->ID, '_stock_status', $_child_variations_stocks[$_woonet_network_is_child_product_id]['_stock_status']);
                                        }
                                }        
                             
                            restore_current_blog();    
                        }        
                        
                    
                }
                
                
            
                
            function is_plugin_active( $plugin ) 
                {
                    return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || $this->is_plugin_active_for_network( $plugin );
                }

            function is_plugin_inactive( $plugin ) 
                {
                    return ! is_plugin_active( $plugin );
                }

            function is_plugin_active_for_network( $plugin ) 
                {
                    if ( !is_multisite() )
                        return false;

                    $plugins = get_site_option( 'active_sitewide_plugins');
                    if ( isset($plugins[$plugin]) )
                        return true;

                    return false;
                } 
                
            
            /**
            * return prlugin options
            * 
            */
            static public function get_options()
                {
                    
                    $mstore_options   =   get_site_option('mstore_options');
                    
                    $defaults = array (
                                             'version'                          =>  '',
                                             'db_version'                       =>  '1.0',
                                             
                                             'synchronize-stock'                =>  'no',
                                             'sequential-order-numbers'         =>  'no',
                                             'publish-capability'               =>  'administrator'
                                             
                                       );
                   
                    
                    // Parse incoming $args into an array and merge it with $defaults
                    $options = wp_parse_args( $mstore_options, $defaults );
                    
                    
                    return $options;  
                    
                }
                
                
            /**
            * update prlugin options
            * 
            */
            function update_options($options)
                {
                    
                    update_site_option('mstore_options', $options);
                    
                    
                }
                
                
                
            function post_class($classes, $class, $post_ID)
                {
                    if(!is_admin())
                        return $classes;
                        
                    $post_data  =   get_post($post_ID);
                    
                    if($post_data->post_type    !=  'product')
                        return $classes;
                        
                    //check if it's child product
                    $_woonet_network_is_child_product_id    =   get_post_meta($post_ID, '_woonet_network_is_child_product_id', TRUE);
                    
                    if(!empty($_woonet_network_is_child_product_id))
                        $classes[]  =   'ms-child-product';
                    
                    return $classes;   
                }
                
            
            /**
            * Check if current user can use plugin Publish functionality
            *     
            */
            function publish_capability_user_can()
                {
                    $options    =   $this->get_options();
                    
                    switch($options['publish-capability'])
                        {
                            case 'super-admin'  :
                                                    if(!is_super_admin())
                                                        return FALSE;
                                                        
                                                    break;   
                            
                            case 'administrator'  :
                                                    if(!current_user_can( 'administrator' ))
                                                        return FALSE;
                                                        
                                                    break; 
                            
                            case 'shop_manager'  :
                                                    if(!current_user_can( 'shop_manager' )  &&  !current_user_can( 'administrator' ))
                                                        return FALSE;
                                                        
                                                    break;
                            
                        }
                        
                        
                    return TRUE;
                       
                }
                
                
            /**
            * Return the path of a key within a multidimensional array
            * 
            * @param mixed $arr
            * @param mixed $lookup
            */
            function ArrayGetKeyPath($array, $lookup)
                {
                    if (array_key_exists($lookup, $array))
                    {
                        return array($lookup);
                    }
                    else
                    {
                        foreach ($array as $key => $subarr)
                        {
                            if (is_array($subarr))
                            {
                                $ret = $this->ArrayGetKeyPath($subarr, $lookup);

                                if ($ret)
                                {
                                    $ret[] = $key;
                                    return $ret;
                                }
                            }
                        }
                    }
                    return null;
                }
            
           
        } 
    
    
    
?>