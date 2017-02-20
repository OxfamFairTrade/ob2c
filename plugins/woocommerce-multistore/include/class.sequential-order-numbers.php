<?php

    if ( ! defined( 'ABSPATH' ) ) { exit;}
       
    class WOO_SON 
        {
            
            function __construct() 
                {
                    //add _order_number meta data to order
                    add_action( 'woocommerce_process_shop_order_meta',              'WOO_SON::woocommerce_process_shop_order_meta' , 10, 2 );
                    add_action( 'wp_insert_post',                                   'WOO_SON::wp_insert_post' , 10, 2 );
                    
                    // retrieve _order_number
                    add_filter( 'woocommerce_order_number',                         'WOO_SON::get_order_number' , 10, 2 );

                    add_filter( 'woocommerce_shortcode_order_tracking_order_id',    'WOO_SON::woocommerce_shortcode_order_tracking_order_id' );   
                    
                }
                
                
            static function network_update_order_numbers()
                {
                    global $wpdb;
                    
                    $orders_to_process      =   200;
                    $highest_order_number   =   1;
                    
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            switch_to_blog( $network_site->blog_id );
   
                            do {
                                    
                                    $mysql_query    =   "SELECT DISTINCT ID FROM "   .   $wpdb->posts    .   " as P
                                                            JOIN ". $wpdb->postmeta ." AS PM ON PM.post_id = P.ID
                                                            WHERE  P.post_type = 'shop_order' AND ID NOT IN 
                                                                        ( SELECT post_id FROM ". $wpdb->postmeta ."
                                                                               WHERE ". $wpdb->postmeta .".`meta_key` = '_order_number'
                                                                                AND ". $wpdb->postmeta .".`post_id` =   P.ID
                                                                        )
                                                            ORDER BY ID ASC
                                                            LIMIT ". $orders_to_process;
                                    
                                    $results    =   $wpdb->get_results($mysql_query);
                                    
                                    if(count($results)  >   0)
                                        {
                                            foreach( $results as $result ) 
                                                {
                                                    add_post_meta( $result->ID, '_order_number', $result->ID );
                                                }   
                                        }

                                } while ( count( $results ) >   0 );
                            
                            
                            //get the highest _order_number on current blog
                            $mysql_query    =   "SELECT MAX(PM.meta_value) as highest FROM "   .   $wpdb->posts    .   " as P
                                                    JOIN ". $wpdb->postmeta ." AS PM ON PM.post_id = P.ID
                                                    WHERE  P.post_type = 'shop_order' AND PM.meta_key = '_order_number'";
                            
                            $highest    =   $wpdb->get_var($mysql_query);
                            
                            if($highest_order_number    <   $highest)
                                $highest_order_number   =   $highest; 
                            
                            restore_current_blog();
                            

                            self::update_network_order_number($highest_order_number);
                            
                        }
                                            
                }
            
            
            /**
            * retirve next order_number
            * 
            */
            static function get_next_network_order_number()
                {
                    
                    $network_order_number   =   get_site_option('mstore_current_network_order_number');
                    
                    $network_order_number++;
                    
                    return $network_order_number;
                    
                }
                
            
            /**
            * set next order_number
            * 
            */
            static function update_network_order_number($order_number)
                {
                    
                    update_site_option('mstore_current_network_order_number', $order_number);
                    
                }
            
                
            static function add_order_number($post_id)
                {
                    //check if there's already an order_number
                    $order_number = get_post_meta( $post_id, '_order_number', true );

                    if (    $order_number   >   0)
                        return $order_number;
     
                    
                    $network_order_number   =   self::get_next_network_order_number(); 
                    
                    update_post_meta( $post_id, '_order_number', $network_order_number );
                    
                    self::update_network_order_number( $network_order_number );
                    
                    return $network_order_number;
                    
                }
                
            
            static function woocommerce_process_shop_order_meta($post_id, $post)
                {
                    
                    if ( $post->post_type   !=  'shop_order')
                        return;
                    
                    // If this is just a revision, don't send the email.
                    if ( wp_is_post_revision( $post_id ) )
                        return;
  
                    self::add_order_number($post_id);    
                    
                }
            
            static function wp_insert_post($post_id, $post)
                {
                    
                    if ( $post->post_type   !=  'shop_order')
                        return;
                    
                    // If this is just a revision, don't send the email.
                    if ( wp_is_post_revision( $post_id ) )
                        return;
  
                    self::add_order_number($post_id);
            
                }
                
            
            /**
            * Return the order number
            *     
            */
            static function get_order_number($order_number, $order)
                {
                    //if set the order number, return
                    if ( $order->order_number ) 
                        {
                            return $order->order_number;
                        }

                    // GEWIJZIGD: Voeg prefix en leading zero's toe (+ fix voor het oproepen van $order->order_number: query '_order_number' rechtstreeks)
                    return "OWW".sprintf( '%05d', get_post_meta($order->id, '_order_number', true) );
                    
                }
                   
             
        }




?>