<?php
    
    if ( ! defined( 'ABSPATH' ) ) { exit;}
       
    class WOO_MSTORE_functions 
        {
            var $options;
            
            public static $instance;
                        
            /**
            * 
            * Run on class construct
            * 
            */
            function __construct( ) 
                {                    
                    self::$instance = $this;
                    
                    //add specific classes for list table within the admin
                    add_filter( 'post_class', array($this, 'post_class'), 10, 3);
                }
            
            public static function get_instance() 
                {
                    if (self::$instance === null) 
                        {
                            self::$instance = new self();
                        }
                    return self::$instance;
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
                                     
                    add_filter('woocommerce_reduce_order_stock', array( __CLASS__ , 'woocommerce_reduce_order_stock'), 10);
                    
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
                            
                            
                            switch_to_blog( $network_site->blog_id );                                                
                                                
                            if( ! self::is_plugin_active('woocommerce/woocommerce.php') )
                                {
                                    restore_current_blog();
                                    continue;   
                                }

                            restore_current_blog();
                            
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
                                        
                                        // MOGELIJKE BUG: Moeten hier geen OR's staan? Typisch $options['synchronize-stock'] == 'yes' EN $_woonet_child_stock_synchronize == empty MAAR $_woonet_child_inherit_updates == 'yes'
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
                
                
            
            /**
            * Syncronize the parent product
            * 
            * @param mixed $post_ID
            */
            function on_child_product_change__update_parent( $post_ID )
                {
                    global $blog_id;
                    
                    $_woonet_network_is_child_product_id    =   get_post_meta($post_ID, '_woonet_network_is_child_product_id', TRUE);   
                    $_woonet_network_is_child_site_id       =   get_post_meta($post_ID, '_woonet_network_is_child_site_id', TRUE);
                    
                    $args =     array(
                                        'fields' => 'names'
                                        );
                    $product_type = wp_get_object_terms( $post_ID, 'product_type', $args);
                    
                    //get variations
                    if(in_array('variable', $product_type))
                        {
                            $_child_variations = get_children( 'post_parent='.$post_ID.'&post_type=product_variation');
                        }
                        else
                        $_child_variations  =   array();
                    
                    $_stock                     =   (int)get_post_meta($post_ID , '_stock', TRUE);
                    $_stock_status              =   get_post_meta($post_ID , '_stock_status', TRUE);
                    
                    /**
                    * If turned off the stock, no need to change anything on other sites as it will turn the _stock value to zero so overwrite with wrong data
                    * 
                    * @var mixed
                    */
                    $_manage_stock              =   get_post_meta($post_ID , '_manage_stock', TRUE);
                    
                    if($_manage_stock   !=  'no')
                        {
                            switch_to_blog( $_woonet_network_is_child_site_id );
                                      
                            update_post_meta($_woonet_network_is_child_product_id, '_stock', $_stock);
                            update_post_meta($_woonet_network_is_child_product_id, '_stock_status', $_stock_status);

                            restore_current_blog();
                        }
                    
                    //update parent variations
                    if (count ($_child_variations) > 0)
                        {
                            foreach($_child_variations  as  $_child_variation)
                                {
                                    
                                    $_variation_woonet_network_is_child_product_id    =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_product_id', TRUE);
                                    $_variation_woonet_network_is_child_site_id       =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_site_id',    TRUE);
                                    
                                    if(empty($_variation_woonet_network_is_child_product_id)  ||  empty($_variation_woonet_network_is_child_site_id) )
                                        continue;
                                    
                                    $_stock                    =   (int)get_post_meta($_child_variation->ID , '_stock', TRUE);
                                    $_stock_status             =   get_post_meta($_child_variation->ID , '_stock_status', TRUE);
                                    
                                    /**
                                    * If turned off the stock, no need to change anything on other sites as it will turn the _stock value to zero so overwrite with wrong data
                                    * 
                                    * @var mixed
                                    */
                                    $_manage_stock              =   get_post_meta($_child_variation->ID, '_manage_stock', TRUE);
                                                                            
                                    switch_to_blog( $_variation_woonet_network_is_child_site_id);
                                    
                                    
                                    if($_manage_stock   !=  'no')
                                        {
                                            if($_stock  !==  '')
                                                update_post_meta($_variation_woonet_network_is_child_product_id, '_stock',        $_stock);
                                            if($_stock_status   !==  '')
                                                update_post_meta($_variation_woonet_network_is_child_product_id, '_stock_status', $_stock_status);      
                                        }
                                    
                                    restore_current_blog();
                                                                                
                                }
                        }
                        
                    return array( $_woonet_network_is_child_product_id, $_woonet_network_is_child_site_id, array($blog_id) );
                    
                }

            
            /**
            * Filter product meta
            * 
            * @param mixed $product_meta
            */
            function filter_product_meta($product_meta)
                {
                    //filter certain keys
                    unset($product_meta['_edit_lock']);
                    unset($product_meta['_edit_last']);
                    
                    //remove the network mapping
                    unset($product_meta['_network_sites_map']);   
                                        
                    //exclude all _woonet
                    foreach($product_meta   as  $key    =>  $product_meta_item)
                        {
                            if(strpos($key, '_woonet') === 0)
                                unset($product_meta[$key]);
                        }
                    
                    
                    /**
                    * ToDo
                    * Maybe filter our empty fields?
                    */
                    
                    
                    $product_meta    =   apply_filters('woonet/admin_product/filter_product_meta/product_meta', $product_meta);
                    
                    return $product_meta;
                }
            
            
            
            
            
            /**
            * Save the meta data to object
            * 
            * @param mixed $product_meta
            * @param mixed $attachments
            * @param mixed $post_ID
            * @param mixed $blog_id
            */
            function save_meta_to_post($product_meta, $attachments, $post_ID, $blog_id, $ignore_meta_fields =   array())
                {
                                        
                    $ignore_meta_fields =   apply_filters('woo_mstore/save_meta_to_post/ignore_meta_fields', $ignore_meta_fields, $blog_id);
                     
                    //retrieve any mapped images
                    /**
                    * the format is [parent_site_image_id] = to_site_image_id
                    */
                    switch_to_blog( $blog_id );
                    $_woonet_images_mapping =   (array)get_post_meta($post_ID, '_woonet_images_mapping', TRUE);
                    $_woonet_images_mapping =   array_filter($_woonet_images_mapping);
                    restore_current_blog();
                        
                    foreach($product_meta   as  $key    =>  $product_meta_item)
                        {
                            
                            //check if ths field is ignored
                            if (in_array($key, $ignore_meta_fields))
                                continue;
                             
                            foreach($product_meta_item  as  $product_meta_item_row)
                                {
                                    $product_meta_item_row = maybe_unserialize( $product_meta_item_row );
                                    switch($key)
                                        {

                                            // GEWIJZIGD: Vertaal de nationale post-ID's in deze 4 metavelden naar lokale post-ID's
                                            
                                            case '_force_sell_ids'  :
                                                                        translate_main_to_local_ids( $post_ID, $key, $product_meta_item_row );
                                                                        break;

                                            case '_force_sell_synced_ids'  :
                                                                        translate_main_to_local_ids( $post_ID, $key, $product_meta_item_row );
                                                                        break;

                                            case '_upsell_ids'  :
                                                                        translate_main_to_local_ids( $post_ID, $key, $product_meta_item_row );
                                                                        break;

                                            case '_crosssell_ids'  :
                                                                        translate_main_to_local_ids( $post_ID, $key, $product_meta_item_row );
                                                                        break;

                                            case '_thumbnail_id'    :
                                                                        
                                                                        if(empty($product_meta_item_row))
                                                                            continue;
                                                                        
                                                                        //process the image attachments and import to this blog
                                                                        $found_attachment =   '';
                                                                        foreach($attachments as $attachment)
                                                                            {
                                                                                if($product_meta_item_row   !=  $attachment->ID)
                                                                                    continue;
                                                                                
                                                                                $found_attachment   =   $attachment;
                                                                                break;
                                                                            }
                                                                        
                                                                        //this image is not attached to post, retrieve the data from parent blog
                                                                        if($found_attachment  ==  '')
                                                                            {
                                                                                restore_current_blog();
                                                                                
                                                                                $found_attachment =   get_post($product_meta_item_row);
                                                                                
                                                                                switch_to_blog( $blog_id );
                                                                            }    
                                                                            
                                                                        if(!isset($_woonet_images_mapping[$found_attachment->ID]))
                                                                            {
                                                                                restore_current_blog();
                                                                                
                                                                                $file_path = get_attached_file( $found_attachment->ID);
                                                                                
                                                                                switch_to_blog( $blog_id );
                                                                                
                                                                                $image_id = $this->save_image_locally($file_path);
                                                                                
                                                                                //attache the image to this post
                                                                                $image_data = get_post($image_id);
                                                                                $image_data->post_parent    =   $post_ID;
                                                                                wp_update_post($image_data);
                                                                                
                                                                                $_woonet_images_mapping[$found_attachment->ID]    =   $image_id;
                                                                            }
                                                                            else
                                                                            $image_id   =   $_woonet_images_mapping[$found_attachment->ID];
                                                                        
                                                                        $_thumbnail_id  =   $product_meta_item_row;
                                                                        //process the featured image
                                                                        update_post_meta( $post_ID, '_thumbnail_id', $_woonet_images_mapping[ $_thumbnail_id  ] );
                                                                        
                                                                        break;   
                                            
                                            case    '_product_image_gallery':
                                                                            
                                                                        //process the product image gallery
                                                                        $_product_image_gallery =   $product_meta_item_row;
                                                                        $_product_image_gallery =   explode(",", $_product_image_gallery);
                                                                        $_product_image_gallery =   array_filter($_product_image_gallery);
                                                                        $_child_product_image_gallery   =   '';
                                                                        if(count($_product_image_gallery)   >   0)
                                                                            {
                                                                                $_child_product_image_gallery_array   =   array();
                                                                                foreach($_product_image_gallery as  $_product_image)   
                                                                                    {
                                                                                        
                                                                                        $found_attachment =   '';
                                                                                        foreach($attachments as $attachment)
                                                                                            {
                                                                                                if($_product_image   !=  $attachment->ID)
                                                                                                    continue;
                                                                                                    
                                                                                                $found_attachment   =   $attachment;
                                                                                                break;
                                                                                                    
                                                                                            }
                                                                                        
                                                                                        //this image is not attached to post, retrieve the data from parent blog
                                                                                        if($found_attachment  ==  '')
                                                                                            {
                                                                                                restore_current_blog();
                                                                                                
                                                                                                $found_attachment =   get_post($_product_image);
                                                                                                
                                                                                                switch_to_blog( $blog_id );
                                                                                            } 
                                                                                            
                                                                                        if(!isset($_woonet_images_mapping[$found_attachment->ID]))
                                                                                            {
                                                                                                restore_current_blog();
                                                                                
                                                                                                $file_path = get_attached_file( $found_attachment->ID);
                                                                                                
                                                                                                switch_to_blog( $blog_id );
                                                                                                
                                                                                                $image_id = $this->save_image_locally($file_path);
                                                                                                
                                                                                                //attache the image to this post
                                                                                                $image_data = get_post($image_id);
                                                                                                $image_data->post_parent    =   $post_ID;
                                                                                                wp_update_post($image_data);
                                                                                                
                                                                                                $_woonet_images_mapping[$found_attachment->ID]    =   $image_id;
                                                                                            }
                                                                                            else
                                                                                            $image_id   =   $_woonet_images_mapping[$found_attachment->ID];
                                                                                        
                                                                                        $_child_product_image_gallery_array[] =   $_woonet_images_mapping[ $_product_image  ];
                                                                                    }
                                                                                if(count($_child_product_image_gallery_array) > 0)    
                                                                                    $_child_product_image_gallery   =   implode(",", $_child_product_image_gallery_array);
                                                                            }
                                                                        update_post_meta( $post_ID, '_product_image_gallery', $_child_product_image_gallery );
                                            
                                                                        
                                                                        break;
                                            
                                            case (preg_match('/attribute_pa_/', $key) ? true : false);
                                                                    
                                                                        
                                                                        //retrieve the original attribute to ensure we set the correct term on this blog, since terms are mapped using term name
                                                                        restore_current_blog();
                                                                        
                                                                        $taxonomy   =   str_replace("attribute_pa_", "pa_", $key);
                                                                        
                                                                        $term_data  =   get_term_by('slug', $product_meta_item_row, $taxonomy);
                                                                        
                                                                        switch_to_blog( $blog_id );
                                                                        
                                                                        if(!is_object($term_data))
                                                                            continue;
                                                                            
                                                                        //retrieve the term on local blog
                                                                        $child_term_data    =   get_term_by("name", $term_data->name, $taxonomy);
                                                                        update_post_meta( $post_ID, $key, $child_term_data->slug );
                                                                    
                                                                    
                                                                        break;
                                            
                                            default:
                                                        
                                                                    update_post_meta( $post_ID, $key, $product_meta_item_row );                       
                                                                    break;   
                                        }
                                    
                                }
                            
                        }
                           
                    
                    //save theimages mapping data
                    update_post_meta($post_ID, '_woonet_images_mapping', $_woonet_images_mapping);
                }
       
            
            
            /**
            * Save a image locally
            * 
            * @param mixed $image_path
            * @return WP_Error
            */
            function save_image_locally($image_path)
                {
                    $pathinfo           = pathinfo($image_path);
                    
                    $newfilename        = $pathinfo['filename']   .   "." .  $pathinfo['extension'];
                    
                    $uploads            = wp_upload_dir();
                    
                    $filename           = wp_unique_filename( $uploads['path'], $newfilename, $unique_filename_callback = null );
                    $wp_filetype        = wp_check_filetype($filename, null );
                    $fullpathfilename   = $uploads['path'] . "/" . $filename;

                    // GEWIJZIGD: Upload enkel de grootste thumbnail naar de dochtersites
                    $small_image_path = str_replace('.jpg', '-1000x1000.jpg', $image_path);
                    $image_path = file_exists($small_image_path) ?  $small_image_path : $image_path;
   
                    $image_content  = file_get_contents($image_path);
                    $fileSaved      = file_put_contents($uploads['path'] . "/" . $filename, $image_content);
                                      
                    $attachment = array(
                                         'post_mime_type'   => $wp_filetype['type'],
                                         'post_title'       => preg_replace('/\.[^.]+$/', '', $filename),
                                         'post_content'     => '',
                                         'post_status'      => 'inherit',
                                         'guid'             => $uploads['url'] . "/" . $filename
                                    );
                    $attach_id = wp_insert_attachment( $attachment, $fullpathfilename );
                  
                    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename );
                    wp_update_attachment_metadata( $attach_id,  $attach_data );
                    
                    return $attach_id;
                
                    
                }
            
                
                
            /**
            * Check if a plugin is active
            *     
            * @param mixed $plugin
            */
            static public function is_plugin_active( $plugin ) 
                {
                    return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || self::is_plugin_active_for_network( $plugin );
                }

            static public function is_plugin_inactive( $plugin ) 
                {
                    return ! is_plugin_active( $plugin );
                }

            static public function is_plugin_active_for_network( $plugin ) 
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
                    
                    $this_class =   self::get_instance();
                    
                    $mstore_options   =   get_site_option('mstore_options');
                    
                    $defaults = array (
                                             'version'                          =>  '',
                                             'db_version'                       =>  '1.0',
                                             
                                             'synchronize-stock'                =>  'no',
                                             'sequential-order-numbers'         =>  'no',
                                             'publish-capability'               =>  'administrator',
                                             
                                             'child_inherit_changes_fields_control__title'  =>  array()
                                             
                                       );
                    
                    // Parse incoming $args into an array and merge it with $defaults
                    $options = wp_parse_args( $mstore_options, $defaults );
                    
                    //ensure the child_inherit_changes_fields_control__title is available for all sites
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            
                            switch_to_blog( $network_site->blog_id );                                                
                                                
                            if( ! $this_class->is_plugin_active('woocommerce/woocommerce.php') )
                                {
                                    restore_current_blog();
                                    continue;   
                                }

                            restore_current_blog();
                            
                            
                            if(!isset($options['child_inherit_changes_fields_control__title'][ $network_site->blog_id ]))
                                $options['child_inherit_changes_fields_control__title'][ $network_site->blog_id ]   =   'yes';
                            if(!isset($options['child_inherit_changes_fields_control__price'][ $network_site->blog_id ]))
                                $options['child_inherit_changes_fields_control__price'][ $network_site->blog_id ]   =   'yes';
                        }
                    
                    
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
            * Cretae a filed collation 
            * 
            */
            function get_field_collation( $field_name )
                {
                    
                    global $wpdb;
                    
                    $db_collation =   $wpdb->collate;
                    
                    if(empty($db_collation))
                        return $field_name;
                        
                    return $field_name . " COLLATE " . $db_collation . " AS " . $field_name;                    
                    
                }
           
        } 
    
    
    
?>