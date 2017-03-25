<?php
    
    class WOO_MSTORE_admin_product 
        {
             /**
             * @var array
             */
            public $product_fields;
            
            var $functions;
            
            var $licence;

            /**
             * __construct function.
             *
             * @access public
             * @return void
             */
            public function __construct() 
                {
                    $this->licence              =   new WOO_MSTORE_licence();
                    
                    if( !   $this->licence->licence_key_verify()    ) 
                        return;
                    
                    $this->functions    =   new WOO_MSTORE_functions();
                                                                                         
                                        
                    //check on post delete
                    add_action('before_delete_post',                        array(  $this,  'before_delete_post'), 999, 1);
                    add_action('trashed_post',                              array(  $this,  'trashed_post'), 999, 1);
                    add_action('untrashed_post',                            array(  $this,  'untrashed_post'), 999, 1);
     
                    add_action( 'admin_menu',                               array(  $this, 'custom_menu_page'), 999 );
                    
                    add_action( 'admin_init',                               array(  $this, 'admin_init'), 1);
                    add_action( 'current_screen', array(  $this, 'current_screen'), 1);
                    
                    //add ignore fields
                    add_filter('woo_mstore/save_meta_to_post/ignore_meta_fields', array(  $this, 'on_save_meta_to_post__ignore_meta_fields'), 10, 2);  
                    
                    
                }
            
            function admin_init()
                {
                    ob_start();
                    
                    
                    // Hooks 
                    
                    add_action( 'woocommerce_product_write_panels',         array(  $this, 'product_write_panel' ) );
                    add_filter( 'woocommerce_process_product_meta',         array(  $this, 'product_save_data' ) );
                    
                    add_action( 'woocommerce_process_product_meta',         array(  $this, 'woocommerce_process_product_meta'), 999, 3 );
                    
                    //hide certain menus and forms if don't have enough access
                    if( $this->functions->publish_capability_user_can() )
                        {
                            add_action( 'woocommerce_product_write_panel_tabs',     array(  $this, 'product_write_panel_tab' ) );        
                        }
                    
                }
            
            function current_screen()
                {
                    $current_screen = get_current_screen();
                    
                    //add rquired js for that area
                    if(is_object($current_screen) && $current_screen->id  ==  'product')
                        {
                            wp_register_style( 'woosl-product', WOO_MSTORE_URL . '/assets/css/woosl-product.css');
                            wp_enqueue_style('woosl-product');
                            
                            wp_register_script( 'woosl-product', WOO_MSTORE_URL . '/assets/js/woosl-product.js', array( 'jquery' ) );
                            wp_enqueue_script('woosl-product');   
                            
                        }
                    
                }
                
            function custom_menu_page() 
                {
                    //only if superadmin
                    if(!    current_user_can('manage_sites'))
                        return;
                    
                    $hookID     =   add_submenu_page( 'woocommerce', 'Network Orders', 'Network Orders', 'manage_options', 'network-orders', array(  $this, 'network_orders_page') );
                    
                    //add_action( 'init',                                     array(  $this, 'init',  ) );
                    add_action('admin_head' , array($this, 'admin_head_network_orders_page'));
                    if(isset($_GET['page']) && $_GET['page']    ==  'network-orders')
                        add_action('load', array($this, 'load_network_orders_page'));
                    
                    
                    $hookID     =   add_submenu_page( 'edit.php?post_type=product', 'Network Products', 'Network Products', 'manage_options', 'network-products', array(  $this, 'network_products_page') );

                    add_action('admin_head' , array($this, 'admin_head_network_products_page'));
                    if(isset($_GET['page']) && $_GET['page']    ==  'network-products')
                        add_action('load', array($this, 'load_network_products_page'));
                    
                    
                    
                }
                
            /**
            * reorder the submenus if the case
            * 
            */
            function admin_head_network_orders_page()
                {
                    //relocate the menu in the first position
                    global $submenu;
                    //get the last index
                    $need_submenu       =   $submenu['woocommerce'];
                    
                    end($need_submenu);
                    $key    =   key($need_submenu);
                    $_data   =   $need_submenu[$key];
                    
                    unset($need_submenu[$key]);
                    
                    reset($need_submenu);
                    $first_key  =   key($need_submenu);
                    $first_data =   current($need_submenu);
                    
                    $_updated_submenu    =   array();
                    
                    $_updated_submenu[$first_key]   =   $first_data;
                    $_updated_submenu[$first_key    +    1]   =   $_data;   
                    
                    //reindex the array
                    foreach($need_submenu   as  $key    =>  $data)
                        {
                            if($key <   2)
                                continue;
                                
                            $new_key    =   $key;   
                            $new_key    =   $new_key    +   4;
                                
                            $_updated_submenu[$new_key] =   $data;   
                        }

                    ksort($_updated_submenu);
                    
                    unset($submenu['woocommerce']);
                    $submenu['woocommerce'] =   $_updated_submenu;
                
                }               
            
            function load_network_orders_page()
                {
                     
                }
            
            function network_orders_page()
                {
                    wp_redirect( network_site_url('wp-admin/network/admin.php?page=woonet-woocommerce'));
                    exit;   
                }
            
            
            /**
            * reorder the submenus if the case
            * 
            */
            function admin_head_network_products_page()
                {
                    
                    //relocate the menu in the first position
                    global $submenu;
                    //get the last index
                    $need_submenu       =   $submenu['edit.php?post_type=product'];
                    
                    //search the network-products
                    foreach($need_submenu   as  $key    =>  $data)
                        {
                            if($data['2']   ==  'network-products')
                                break;
                        }
                    
                    unset($need_submenu[$key]);
                    
                    reset($need_submenu);
                    $first_key  =   key($need_submenu);
                    $first_data =   current($need_submenu);
                    
                    unset($need_submenu[$first_key]);
                    
                    $_updated_submenu    =   array();
                    
                    $_updated_submenu[$first_key]   =   $first_data;
                    $_updated_submenu[$first_key    +    1]   =   $data;   
                         
                    //reindex the array
                    foreach($need_submenu   as  $key    =>  $data)
                        {
                            if($key <   2)
                                continue;
                                
                            $new_key    =   $key;   
                            $new_key    =   $new_key    +   4;
                                
                            $_updated_submenu[$new_key] =   $data;   
                        }
                    
                    
                    //$_updated_submenu[$first_key    +    2]   =   $_data;
                    ksort($_updated_submenu);
                    
                    unset($submenu['edit.php?post_type=product']);
                    $submenu['edit.php?post_type=product'] =   $_updated_submenu;
                
                }
            
            function load_network_products_page()
                {
                       
                }
                
            function network_products_page()
                {
                    wp_redirect( network_site_url('wp-admin/network/admin.php?page=woonet-woocommerce-products'));
                    exit;   
                }
                 
               
            /**
            * Define the custom new fields
            * 
            */
            public function define_fields() 
                {
                    global $post, $blog_id;

                    if ( $this->product_fields ) 
                        return;
                    
                    $options    =   $this->functions->get_options();
                        
                    $parent_product_blog_id =   $blog_id;
                    
                    $check_for_child_product = get_post_meta($post->ID, '_woonet_network_is_child_product_id', TRUE);
                    if($check_for_child_product >   0)
                        {
                            $this->product_fields[] =   array(
                                                            'id'                => '_woonet_title',
                                                            'label'             => '&nbsp;',
                                                            'type'              => 'heading',
                                                            'no_save'           =>  TRUE
                                                        );
                            
                            $this->product_fields[] =   array(
                                                                            'class'             => '_woonet_description inline',
                                                                            'label'             => __( 'Child product, can\'t be re-published to other sites', 'woonet' ),
                                                                            'type'              => 'description',
                                                                            'no_save'           =>  TRUE
                                                                        );
                            
                            $this->product_fields[] =   array(
                                                            'id'                => '_woonet_title',
                                                            'label'             => '&nbsp;',
                                                            'type'              => 'heading',
                                                            'no_save'           =>  TRUE
                                                        );
                            
                            $_woonet_child_inherit_updates  =   get_post_meta($post->ID, '_woonet_child_inherit_updates', TRUE);
                            $this->product_fields[] =   array(
                                                                            'id'                => '_woonet_child_inherit_updates',
                                                                            'class'             => '_woonet_child_inherit_updates inline',
                                                                            'label'             => '',
                                                                            'description'       =>  __( 'If checked, this product will inherit any parent updates', 'woonet'),
                                                                            'type'              => 'checkbox',
                                                                            'value'             =>  $_woonet_child_inherit_updates
                                                                        );      
                                                                        
                            $_woonet_child_stock_synchronize  =   get_post_meta($post->ID, '_woonet_child_stock_synchronize', TRUE);
                            $this->product_fields[] =   array(
                                                                            'id'                => '_woonet_child_stock_synchronize',
                                                                            'class'             => '_woonet_child_stock_synchronize inline',
                                                                            'label'             => '',
                                                                            'description'       =>  __( 'If checked, any stock change will syncronize across product tree.', 'woonet'),
                                                                            'type'              => 'checkbox',
                                                                            'value'             =>  'yes',
                                                                            'checked'           =>  ($_woonet_child_stock_synchronize    ==  'yes')  ?   TRUE    :   FALSE,
                                                                            'disabled'          =>  ($options['synchronize-stock']    ==  'yes')  ?   TRUE    :   FALSE
                                                                        ); 
                        }
                        else
                        {
                            $main_blog_id = $blog_id;
                            
                            
                            $this->product_fields[] =   array(
                                                                            'id'                => 'woonet_toggle_all_sites',
                                                                            'class'             => 'woonet_toggle_all_sites inline',
                                                                            'label'             => '',
                                                                            'description'       =>  __( 'Toggle all Sites', 'woonet'),
                                                                            'type'              => 'checkbox',
                                                                            'value'             =>  '',
                                                                            'no_save'           =>  TRUE
                                                                        );
                                                
                            $this->product_fields[] =   array(
                                                                            'id'                => 'woonet_toggle_child_product_inherit_updates',
                                                                            'class'             => '_woonet_child_inherit_updates inline',
                                                                            'label'             => '',
                                                                            'description'       =>  __( 'Toggle all Child product inherit Parent changes', 'woonet'),
                                                                            'type'              => 'checkbox',
                                                                            'value'             =>  '',
                                                                            'no_save'           =>  TRUE
                                                                        );
                            
                            $this->product_fields[] =   array(
                                                            'id'                => '_woonet_title',
                                                            'label'             => '&nbsp;',
                                                            'type'              => 'heading',
                                                            'no_save'           =>  TRUE
                                                            );
                            $this->product_fields[] =   array(
                                                            'id'                => '_woonet_title',
                                                            'label'             => __( 'Publish to', 'woonet' ),
                                                            'type'              => 'heading',
                                                            'no_save'           =>  TRUE
                                                        );
                            
                            $network_sites  =   get_sites(array('limit'  =>  999));
                            foreach($network_sites as $network_site)
                                {
                                    $blog_details   =   get_blog_details($network_site->blog_id);
                                    
                                    $value  =   get_post_meta( $post->ID, '_woonet_publish_to_' . $network_site->blog_id, true );
                                    
                                    switch_to_blog( $blog_details->blog_id );
                                    
                                    //check if plugin active
                                    if( !   $this->functions->is_plugin_active('woocommerce/woocommerce.php') || ! $this->functions->is_plugin_active('woocommerce-multistore/woocommerce-multistore.php'))
                                        {
                                            restore_current_blog();
                                            continue;
                                        }
                                    
                                    $this->product_fields[] =   array(
                                                                            'id'                    => '_woonet_publish_to_' . $network_site->blog_id,
                                                                            'class'                 => '_woonet_publish_to inline',
                                                                            'label'                 => '',
                                                                            'description'           => '<b>'.$blog_details->blogname .'</b><span class="warning">'. __('<b>Warning:</b> By unselecting this shop the product is unasigned, but not deleted from the shop, witch should be done manually.', 'woonet') .'</span>',
                                                                            'type'                  => 'checkbox',
                                                                            'disabled'              =>  ($blog_details->blog_id   ==  $main_blog_id)  ?   TRUE    :   FALSE,
                                                                            'set_default_value'     =>  TRUE,
                                                                            'custom_attribute'  =>  'data-group-id=' . $network_site->blog_id,
                                                                            'save_callback'         =>  array($this, 'field_process_publish_to')
                                                                        );
                                    
                                    if($blog_details->blog_id   !=  $main_blog_id)
                                        {
                                            $class  =   ' ';
                                            if(empty($value))
                                                $class  .=   'default_hide';
                                            
                                            $_woonet_child_inherit_updates      =   '';
                                            $_woonet_child_stock_synchronize    =   '';
                                            
                                            $args   =   array(
                                                                'post_type'     =>  'product',
                                                                'post_status'   =>  'any',
                                                                'meta_query'    => array(
                                                                                            'relation' => 'AND',
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                                    'value'   => $parent_product_blog_id,
                                                                                                    'compare' => '=',
                                                                                                ),
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                                    'value'   => $post->ID,
                                                                                                    'compare' => '=',
                                                                                                ),
                                                                                        ),
                                                                );
                                            $custom_query       =   new WP_Query($args);
                                            if($custom_query->found_posts   >   0)
                                                {
                                                    //product previously created, this is an update
                                                    $child_post =   $custom_query->posts[0];
                                                    
                                                    $_woonet_child_inherit_updates  =   get_post_meta($child_post->ID, '_woonet_child_inherit_updates', TRUE);        
                                                    $_woonet_child_stock_synchronize  =   get_post_meta($child_post->ID, '_woonet_child_stock_synchronize', TRUE);
                                                }
                                            
                                            
                                            $this->product_fields[] =   array(
                                                                                    'id'                => '_woonet_publish_to_' . $network_site->blog_id .'_child_inheir',
                                                                                    'class'             => 'group_'. $blog_details->blog_id .' _woonet_publish_to_child_inheir inline indent' . $class,
                                                                                    'label'             => '',
                                                                                    'description'       =>  __( 'Child product inherit Parent changes', 'woonet' ),
                                                                                    'type'              => 'checkbox',
                                                                                    'value'             =>  'yes',
                                                                                    'checked'           =>  empty($_woonet_child_inherit_updates) ||    $_woonet_child_inherit_updates  !=  'yes'   ?   FALSE   :   TRUE,
                                                                                    'disabled'          =>  '',
                                                                                    'no_save'           =>  TRUE
                                                                                );
                                                                                
                                            
                                            $this->product_fields[] =   array(
                                                                                            'id'                => '_woonet_' . $network_site->blog_id .'_child_stock_synchronize',
                                                                                            'class'             => 'group_'. $blog_details->blog_id .' _woonet_child_stock_synchronize inline indent' . $class,
                                                                                            'label'             => '',
                                                                                            'description'       =>  __( 'If checked, any stock change will syncronize across product tree.', 'woonet'),
                                                                                            'type'              => 'checkbox',
                                                                                            'value'             =>  'yes',
                                                                                            'checked'           =>  ($_woonet_child_stock_synchronize    ==  'yes')  ?   TRUE    :   FALSE,
                                                                                            'disabled'          =>  ($options['synchronize-stock']    ==  'yes')  ?   TRUE    :   FALSE,
                                                                                            'no_save'           =>  TRUE
                                                                                        ); 
                                        }
                                                                        
                                    restore_current_blog();
                                }
                        }
                }
                

            /**
            * adds Licence tab to the product interface
            */
            public function product_write_panel_tab() 
                {

                    ?>
                    <li class="woonet_tab hide_if_grouped"><a href="#woonet_data"><?php _e( 'MultiStore', 'woonet'); ?></a></li>
                    <?php
                }

                   
            /**
             * adds the panel to the product interface
             */
            public function product_write_panel() 
                {
                    global $post, $woocommerce;

                    $this->define_fields();

                    $data = get_post_meta( $post->ID, 'product_data', true );

                    ?>

                    <div id="woonet_data" class="panel woocommerce_options_panel">
                                    
                        <?php

                        foreach ( $this->product_fields as $field ) 
                            {

                                if ( ! is_array( $field ) ) 
                                    {
                                        if ( $field == 'start_group' ) 
                                            {
                                                echo '<div class="options_group">';
                                            } 
                                        elseif ( $field == 'end_group' ) 
                                            {
                        
                                                echo '</div>';
                                            }

                                        continue;    
                                    } 
                                
                                switch($field['type'])
                                    {
                                        case 'heading'  :
                                                            ?><h4><?php echo  $field['label'] ?></h4><?php
                                                            
                                                            break;
                                                            
                                        case 'description':
                                                            
                                                            ?>
                                                                <p class="form-field <?php echo  $field['class'] ?>">
                                                                    <span class="description"><?php echo wp_kses_post( $field['label'] ) ?></span>
                                                                </p>
                                                            <?php
                                                            break;
                                        
                                        case  'woo_custom_select'   :
                                                    ?>
                                                        <p class="form-field <?php echo  $field['class'] ?>">
                                                            <label for="<?php echo  $field['id'] ?>"><?php echo  $field['label'] ?></label>
                                                    
                                                    <?php
                                                        
                                                        $html_select    =   wp_dropdown_pages( array_merge($field, array('echo' =>  FALSE, 'name'   => $field['id'], 'show_option_none'     => ' '  ) )) . '<span class="description">' . $field['description'] . '</span>';
                                                        
                                                        echo str_replace(' id=', 
                                                                    " data-placeholder='" . 'Select a page' . "' class='wc_woonet_chosen' id=", 
                                                                    $html_select);
                                                    ?>
                                                        </p>
                                                    <?php
                                                    break;  
                                                    
                                        case 'checkbox':
                                                    
                                                    $value  =   get_post_meta( $post->ID, $field['id'], true );
                                                    
                                                    ?>
                                                        <p class="form-field no_label <?php echo  $field['class'] ?>"<?php  if(isset($field['custom_attribute'])    &&  !empty($field['custom_attribute'])) { echo $field['custom_attribute']; }   ?>>
                                                            <?php if ($field['label'] != '') { ?>
                                                            <label for="<?php echo  $field['id'] ?>"><?php echo  $field['label'] ?></label>
                                                            <?php } ?>
                                                            <input type="checkbox" class="<?php echo  $field['class'] ?>" name="<?php echo  $field['id'] ?>" id="<?php echo  $field['id'] ?>" <?php
                                                            
                                                                if(isset($field['disabled'])    &&   $field['disabled'] === TRUE)
                                                                    {
                                                                        ?>  disabled="disabled"<?php
                                                                    }
                                                                    ?>value="yes" <?php 
                                                                    
                                                                    if(!isset($field['checked']))
                                                                        checked( 'yes', $value);
                                                                        else
                                                                        {
                                                                            if($field['checked']    === TRUE)
                                                                                checked( 'yes', 'yes');
                                                                                else
                                                                                checked( 'yes', 'no');
                                                                        }
                                                                    
                                                                    ?> <?php
                                                                    
                                                                    
                                                                    if(isset($field['set_default_value'])   &&  $field['set_default_value'] === TRUE)
                                                                        {
                                                                            echo 'data-default-value="'. $value .'"';
                                                                        }
                                                                    
                                                                    
                                                                    ?>/>

                                                            
                                                            <?php
                                                            
                                                                if ( isset( $field['desc_tip'] ) && false !== $field['desc_tip'] ) 
                                                                    {
                                                                        echo '<img class="help_tip" data-tip="' . esc_attr( $field['desc_tip'] ) . '" src="' . esc_url( WC()->plugin_url() ) . '/assets/images/help.png" height="16" width="16" />';
                                                                    }
                                                            
                                                            ?>
                                                            <span class="description"><?php echo wp_kses_post( $field['description'] ) ?></span>
                                                        </p>
                                                    <?php
                                                    break;
                                        
                                        default:
                                                                    
                                                    $func = 'woocommerce_wp_' . $field['type'] . '_input';

                                                    if ( function_exists( $func ) )
                                                        $func( $field );
                                                    break;
                                        
                                    }

                            }
                        
                    ?>
                    
                    </div>
                    
                    <?php

                }

            /**
             * Saves the data for the woonet Tab product writepanel input boxes
             */
            public function product_save_data() 
                {
                    global $post;

                    // woonet Tab writepanel checkboxes
                    /*
                    $checkboxes = array('_woonet_enabled');

                    foreach ( $checkboxes as $key => $checkbox ) 
                        {

                            if ( ! empty( $_POST["$checkbox"] ) ) 
                                {

                                    update_post_meta( $post->ID, "$checkbox", 'yes' );

                                } 
                            else 
                                {

                                    update_post_meta( $post->ID, "$checkbox", 'no' );
                                }

                        }
                    */
  
                    // Create the product_fields variable array
                    $this->define_fields();

                    //Writepanel text fields
                    foreach ( $this->product_fields as $field ) 
                        {
                            if(isset($field['no_save']) &&  $field['no_save']   === TRUE)
                                continue;
                            
                            if(isset($field['save_callback']))
                                {
                                    call_user_func($field['save_callback'], $field);
                                    continue;
                                }
                            
                            if ( is_array( $field ) ) 
                                {
                                    $data = isset( $_POST[ $field['id'] ] ) ? esc_attr( trim( stripslashes( $_POST[ $field['id'] ] ) ) ) : '';
                                    update_post_meta( $post->ID, $field['id'], $data );
                                }
                        }

                }
                
                
            function field_process_publish_to( $field )    
                {
                    
                    global $post, $blog_id;
                    
                    $parent_blog_id    =   $blog_id;
                    
                    if ( is_array( $field ) ) 
                        {
                            $data = isset( $_POST[ $field['id'] ] ) ? esc_attr( trim( stripslashes( $_POST[ $field['id'] ] ) ) ) : '';

                            $child_blog_id    =   str_replace('_woonet_publish_to_', "", $field['id']);
                            
                            //get previous data
                            $previous_data =    get_post_meta($post->ID, $field['id'], TRUE);
                            if($previous_data   ==  'yes'   &&  $data   !=  'yes')
                                {
                                    //a product has been just unnasigned from the tree, make required changes
                                    switch_to_blog( $child_blog_id );
                            
                                    //identify the product which inherited the data
                                    $args   =   array(
                                                        'post_type'     =>  'product',
                                                        'post_status'   =>  'any',
                                                        'meta_query'    => array(
                                                                                    'relation' => 'AND',
                                                                                    array(
                                                                                            'key'     => '_woonet_network_is_child_site_id',
                                                                                            'value'   => $parent_blog_id,
                                                                                            'compare' => '=',
                                                                                        ),
                                                                                    array(
                                                                                            'key'     => '_woonet_network_is_child_product_id',
                                                                                            'value'   => $post->ID,
                                                                                            'compare' => '=',
                                                                                        ),
                                                                                ),
                                                        );
                                    $custom_query       =   new WP_Query($args);
                                    if($custom_query->found_posts   >   0)
                                        {
                                            $child_post =   $custom_query->posts[0];
                                            
                                            //remove the _woonet_network_is_child_product_id and _woonet_network_is_child_site_id fields 
                                            $_woonet_network_is_child_product_id    =   get_post_meta($child_post->ID, '_woonet_network_is_child_product_id', TRUE);
                                            $_woonet_network_is_child_site_id       =   get_post_meta($child_post->ID, '_woonet_network_is_child_site_id', TRUE);
                                         
                                            delete_post_meta($child_post->ID, '_woonet_network_is_child_product_id');
                                            delete_post_meta($child_post->ID, '_woonet_network_is_child_site_id');
                                            
                                            update_post_meta($child_post->ID, '_woonet_network_unassigned_site_id', $_woonet_network_is_child_site_id );
                                            update_post_meta($child_post->ID, '_woonet_network_unassigned_product_id', $_woonet_network_is_child_product_id );
                                            
                                        }
                                        
                                    restore_current_blog();                                    
                                }
                            
                            update_post_meta( $post->ID, $field['id'], $data );
                        }
                }
                         
            
            function woocommerce_process_product_meta($post_ID, $post)
                {
                    
                    //just a revision?
                    if ( wp_is_post_revision( $post_ID ) )
                        return;
                    
                    if($post->post_type !=  'product')
                        return;
                    
                    $this->process_product($post_ID, $post);
           
                }
                
            
            /**
            * Process any actions for a product New/Update
            * 
            * @param mixed $post_ID
            * @param mixed $post
            * @param mixed $_use_child_settings
            */
            function process_product($post_ID, $post, $_use_child_settings = FALSE)
                {
                    global $blog_id, $wpdb;
                    
                    //check if is re-published product and check for syncronize
                    $network_parent_product_id    =   get_post_meta($post_ID, '_woonet_network_is_child_product_id', TRUE);
                    if($network_parent_product_id   >   0)
                        {
                            
                            $options    =   $this->functions->get_options();
                            
                            $_woonet_child_inherit_updates          =   get_post_meta($post_ID , '_woonet_child_inherit_updates', TRUE);
                            $_woonet_child_stock_synchronize        =   get_post_meta($post_ID , '_woonet_child_stock_synchronize', TRUE);
                            
                            //chck for Always maintain stock synchronization;  If set, it also modify any stock change within child product to parent
                            if($options['synchronize-stock']    !=  'yes'   &&  $_woonet_child_inherit_updates  !=  'yes'  &&  $_woonet_child_stock_synchronize    !=  'yes')
                                return;
                            
                            list($_woonet_network_is_child_product_id, $_woonet_network_is_child_site_id, $ignore_blogs) = $this->functions->on_child_product_change__update_parent( $post_ID );
                            
                            //syncronize all network
                            WOO_MSTORE_functions::update_stock_across_network($_woonet_network_is_child_product_id, $_woonet_network_is_child_site_id, $ignore_blogs);
                            
                            return;
                        }
                    
                    $options    =   $this->functions->get_options();
                    
                    $parent_product_blog_id =   $blog_id;
                    
                    //check if is first publish
                    $_woonet_network_main_product           =   get_post_meta($post_ID, '_woonet_network_main_product', TRUE);
                    $_product_just_publish                  =   empty($_woonet_network_main_product)    ?   TRUE    :   FALSE;   
                    
                    //delete any meta for this product in case it has been unnasigned from a tree
                    delete_post_meta($post_ID, '_woonet_network_unassigned_site_id');
                    delete_post_meta($post_ID, '_woonet_network_unassigned_product_id');
                                            
                    //$_woonet_child_products_inherit_updates =   get_post_meta($post_ID, '_woonet_child_products_inherit_updates', TRUE);
                    
                    update_post_meta($post_ID, '_woonet_network_main_product', 'true');
                    
                    $product_data   =   get_post($post_ID);
                    
                    $product_meta   =   get_post_meta($post_ID);
                    $product_meta   =   $this->functions->filter_product_meta($product_meta);
                    
                    //relocate the _Stock_status to end to allow WooCommerce to syncronyze on actual stock value
                    $data =     isset($variation_product_meta['_stock_status']) ?   $variation_product_meta['_stock_status']    :   '';
                    if(!empty($data))
                        {
                            unset($variation_product_meta['_stock_status']);
                            $variation_product_meta['_stock_status'] = $data;
                        }
                    
                    
                    $args =     array(
                                        'fields' => 'names'
                                        );
                    $product_type = wp_get_object_terms( $post_ID, 'product_type', $args);

                    $product_taxonomies_data    =   $this->get_product_taxonomies_terms_data($post_ID);
                    
                    //get stock which will be used later
                    $main_product_stock         =   get_post_meta($post_ID, '_stock', TRUE);
                    $main_product_stock_status  =   get_post_meta($post_ID, '_stock_status', TRUE);
                    
                    //get variations
                    if(in_array('variable', $product_type))
                        {
                            $children_products = get_children( 'post_parent='.$post_ID.'&post_type=product_variation');
                        }
                        else
                        $children_products  =   array();
                        
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    
                    //get all images attachment
                    $args = array(
                                    'post_parent'       => $post_ID,
                                    'post_type'         => 'attachment', 
                                    'posts_per_page'    => -1,
                                    'post_status'       => 'any' );
                    $custom_query       =   new WP_Query($args);
                    $attachments        =   $custom_query->posts; 
                    //add additional details to attachments data for later usage
                    foreach ($attachments   as $key =>  $attachment)
                        {
                            $file_path =    get_attached_file( $attachment->ID);
                            
                            $attachments[$key]->file_path  =   $file_path;
                        }
                                                        
                    //send the fields to other sites
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            $blog_details   =   get_blog_details($network_site->blog_id);
                            
                            $publish_to  =   get_post_meta( $post_ID, '_woonet_publish_to_'. $blog_details->blog_id, true );
                            if(empty($publish_to))
                                continue;
                                    
                            //publish all meta if does not exists
                            switch_to_blog( $blog_details->blog_id );
                            
                            //identify the product which inherited the data
                            $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  'any',
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $parent_product_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                    'value'   => $post_ID,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                            $custom_query       =   new WP_Query($args);
                            
                            //try to restore if available
                            if($custom_query->found_posts   <   1)
                                {
                                    $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  'any',
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_unassigned_site_id',
                                                                                    'value'   => $parent_product_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            array(
                                                                                    'key'     => '_woonet_network_unassigned_product_id',
                                                                                    'value'   => $post_ID,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                                    $custom_query       =   new WP_Query($args);   
                                    
                                    if($custom_query->found_posts   >   0)
                                        {
                                            $child_post =   $custom_query->posts[0];   
                                            
                                            delete_post_meta($child_post->ID, '_woonet_network_unassigned_site_id');
                                            delete_post_meta($child_post->ID, '_woonet_network_unassigned_product_id');
                                            
                                            update_post_meta($child_post->ID , '_woonet_network_is_child_site_id', $parent_product_blog_id);
                                            update_post_meta($child_post->ID , '_woonet_network_is_child_product_id', $post_ID);
                                        }
                                    
                                }
                            
                            if($custom_query->found_posts   >   0)
                                {
                                    //product previously created, this is an update
                                    $child_post =   $custom_query->posts[0];
                                    
                                    //check if the _woonet_child_inherit_updates is set to yes oterwise do not update
                                    if($_use_child_settings === TRUE)
                                        {
                                            $_woonet_child_inherit_updates      =   get_post_meta($child_post->ID , '_woonet_child_inherit_updates', TRUE);
                                            $_woonet_child_stock_synchronize    =   get_post_meta($child_post->ID , '_woonet_child_stock_synchronize', TRUE);
                                        }
                                        else                                                             
                                        {
                                            $_woonet_child_inherit_updates      =   isset($_REQUEST['_woonet_publish_to_'.$blog_id.'_child_inheir'])  ?    $_REQUEST['_woonet_publish_to_'.$blog_id.'_child_inheir']    :   '';
                                            $_woonet_child_stock_synchronize    =   isset($_REQUEST['_woonet_'.$blog_id.'_child_stock_synchronize'])  ?    $_REQUEST['_woonet_'.$blog_id.'_child_stock_synchronize']    :   '';
                                        }
                                        
                                    update_post_meta($child_post->ID, '_woonet_child_inherit_updates', $_woonet_child_inherit_updates);
                                    update_post_meta($child_post->ID, '_woonet_child_stock_synchronize', $_woonet_child_stock_synchronize);
                                    
                                    //check for stock synchronize
                                    if($_woonet_child_inherit_updates != 'yes'  &&  ($_woonet_child_stock_synchronize    ==  'yes'  ||  $options['synchronize-stock']    ==  'yes'))
                                        {
                                            //update the child
                                            update_post_meta($child_post->ID, '_stock', $main_product_stock);
                                            update_post_meta($child_post->ID, '_stock_status', $main_product_stock_status);
                                        }
                                        
                                    
                                    if($_woonet_child_inherit_updates != 'yes')
                                        {
                                            
                                            //check for syncronize the stocks
                                            if($_woonet_child_stock_synchronize    ==  'yes'  ||  $options['synchronize-stock']    ==  'yes')
                                                {
                                                    $_child_variations = get_children( 'post_parent=' . $child_post->ID . '&post_type=product_variation');
                                                    
                                                    if(in_array('variable', $product_type)  &&  count($_child_variations) > 0)
                                                        {
                                                            foreach($_child_variations   as  $_child_variation)
                                                                {
                                                                    
                                                                    $_woonet_network_is_child_product_id    =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_product_id', TRUE);
                                                                    $_woonet_network_is_child_site_id       =   get_post_meta($_child_variation->ID, '_woonet_network_is_child_site_id',    TRUE);
                                                                    
                                                                    if(empty($_woonet_network_is_child_product_id)  ||  empty($_woonet_network_is_child_site_id) )
                                                                        continue;
                                                                        
                                                                    switch_to_blog( $_woonet_network_is_child_site_id);
                                                                    
                                                                    $parent_variation_stock             =   get_post_meta($_woonet_network_is_child_product_id, '_stock', TRUE);
                                                                    $parent_variation_stock_status      =   get_post_meta($_woonet_network_is_child_product_id, '_stock_status', TRUE);
                                                                    
                                                                    restore_current_blog();
                                                            
                                                                    //update the child variation                                                                    
                                                                    update_post_meta($_child_variation->ID, '_stock', $parent_variation_stock);
                                                                    update_post_meta($_child_variation->ID, '_stock_status', $parent_variation_stock_status);
                                                                    
                                                                }   
                                                        }
                                                }
                                            
                                            
                                            restore_current_blog();
                                            continue;
                                        }
                                    
                                    $ignore_meta_fields =   apply_filters('woo_mstore/save_meta_to_post/ignore_meta_fields', array(), $blog_id);
                                        
                                    if(!in_array('post_title', $ignore_meta_fields))
                                        $child_post->post_title     =   $post->post_title;   
                                    
                                    $child_post->post_content   =   $post->post_content;
                                    
                                    //update post status
                                    $child_post->post_status    =   $post->post_status;
                                    
                                    //update the short descriptin
                                    $child_post->post_excerpt   =   $post->post_excerpt;
                                    
                                    //update comment status
                                    $child_post->comment_status =   $post->comment_status;
                                    

                                    wp_update_post( $child_post );

                                                     
                                }
                                else
                                    {
                                        //create the product
                                        $post_data = $post;
                                        unset($post_data->ID);
                                        $post_data->post_parent = 0;

                                        // Insert the post into the database

                                        $child_post_id  =   wp_insert_post( $post_data );

                                        
                                        update_post_meta($child_post_id, '_woonet_network_is_child_product_id', $post_ID);
                                        update_post_meta($child_post_id, '_woonet_network_is_child_site_id', $parent_product_blog_id);
                                        
                                        //set the updates to do not apply on this child, unless parent product setting  _woonet_child_products_inherit_updates is yes
                                        /*
                                        $_woonet_child_inherit_updates  =   'no';
                                        if($_product_just_publish && $_woonet_child_products_inherit_updates    ==  'yes')
                                            $_woonet_child_inherit_updates  =   'yes';
                                        */
                                        
                                        $_woonet_child_inherit_updates      =   isset($_REQUEST['_woonet_publish_to_'.$blog_id.'_child_inheir'])  ?     $_REQUEST['_woonet_publish_to_'.$blog_id.'_child_inheir']    :   '';
                                        $_woonet_child_stock_synchronize    =   isset($_REQUEST['_woonet_'.$blog_id.'_child_stock_synchronize'])  ?    $_REQUEST['_woonet_'.$blog_id.'_child_stock_synchronize']    :   '';
                                            
                                        update_post_meta($child_post_id, '_woonet_child_inherit_updates', $_woonet_child_inherit_updates);
                                        update_post_meta($child_post_id, '_woonet_child_stock_synchronize', $_woonet_child_stock_synchronize);
                                                                                                                                        
                                        $child_post =   get_post($child_post_id);

                                    }
                                    
                             
                            //Create / Update the taxonomies terms on this child product site
                            $this->save_taxonomies_to_post($product_taxonomies_data, $child_post->ID, $blog_details->blog_id);
                                                               
                            $this->functions->save_meta_to_post($product_meta, $attachments, $child_post->ID, $blog_details->blog_id);
                            
                            
                            //check if this belong to a grouped
                            if($product_data->post_parent > 0)
                                {
                                    //check if the child also belong to a group
                                    if($child_post->post_parent > 0)
                                        {
                                            //check if belong to correct group??
                                            // To Check With Specifications
                                            //+++++++++++
                                        
                                        }    
                                        else
                                        {
                                            //check if the group exists, being previouslly created for another product
                                            $args   =   array(
                                                                'post_type'     =>  'product',
                                                                'post_status'   =>  'any',
                                                                'meta_query'    => array(
                                                                                            'relation' => 'AND',
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                                    'value'   => $product_data->post_parent,
                                                                                                    'compare' => '=',
                                                                                                ),
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                                    'value'   => $blog_id,
                                                                                                    'compare' => '=',
                                                                                                ),
                                                                                        ),
                                                                );
                                            $custom_query       =   new WP_Query($args);
                                            if($custom_query->found_posts   >   0)
                                                    {
                                                        //product previously created, this is an update
                                                        $child_group_post =   $custom_query->posts[0];
                                                    }
                                                else
                                                    {
                                                        //there's no parent, replicate the group post too   
                                                        restore_current_blog();
                                                        
                                                        $post_data = get_post($product_data->post_parent);
                                                        unset($post_data->ID);
                                                        
                                                        switch_to_blog( $blog_details->blog_id );
                                                        
                                                        
                                                        $child_group_post_id  =   wp_insert_post( $post_data );
                                                        
                                                        
                                                        update_post_meta($child_group_post_id, '_woonet_network_is_child_product_id', $product_data->post_parent);
                                                        update_post_meta($child_group_post_id, '_woonet_network_is_child_site_id', $blog_id);
                                                        
                                                        $child_group_post   =   get_post($child_group_post_id);
                                                       
                                                    }
                                                    
                                                                                                
                                            restore_current_blog();
                                                        
                                            $group_taxonomies_data  =   $this->get_product_taxonomies_terms_data($product_data->post_parent);
                                            $group_meta             =   get_post_meta($product_data->post_parent);
                                            $group_meta             =   $this->functions->filter_product_meta($product_meta);
                                            
                                            //relocate the _Stock_status to end to allow WooCommerce to syncronyze on actual stock value
                                            $data =     isset($variation_product_meta['_stock_status']) ?   $variation_product_meta['_stock_status']    :   '';
                                            if(!empty($data))
                                                {
                                                    unset($variation_product_meta['_stock_status']);
                                                    $variation_product_meta['_stock_status'] = $data;
                                                }
                                    
                                            switch_to_blog( $blog_details->blog_id );
                                            
                                            //replicate the data to child group
                                            $this->save_taxonomies_to_post($group_taxonomies_data, $child_group_post->ID, $blog_details->blog_id);
                                                               
                                            $this->functions->save_meta_to_post($group_meta, array(), $child_group_post->ID, $blog_details->blog_id);
                            
                                            //pdate the child post, and make it child for group
                                            $child_post->post_parent    =   $child_group_post->ID;
                                            wp_update_post($child_post);
                                            
                                        }
                                }
                              
                            //replicate the variations
                            if(in_array('variable', $product_type))
                                {
                                    if(count($children_products) > 0)
                                        {
                                            //retrieve all current child variations
                                            $_child_children_products_data  = get_children( 'post_parent='.$child_post->ID.'&post_type=product_variation');
                                            $_child_children_products       =   array();
                                            foreach($_child_children_products_data  as  $_child_children_product_data)
                                                {
                                                    $_child_children_products[]   =   $_child_children_product_data->ID;  
                                                }
                                            
                                            $_child_variations_processed = array();
                                                                                        
                                            //process all variations
                                            foreach($children_products   as  $children_product)
                                                {
                                                    restore_current_blog();
                                            
                                                    $variation_product_meta   =   get_post_meta($children_product->ID);
                                                    $variation_product_meta   =   $this->functions->filter_product_meta($variation_product_meta);
                                                    
                                                    //relocate the _Stock_status to end to allow WooCommerce to syncronyze on actual stock value
                                                    $data =     isset($variation_product_meta['_stock_status']) ?   $variation_product_meta['_stock_status']    :   '';
                                                    if(!empty($data))
                                                        {
                                                            unset($variation_product_meta['_stock_status']);
                                                            $variation_product_meta['_stock_status'] = $data;
                                                        }
                                                                                                
                                                    switch_to_blog( $blog_details->blog_id );
                                                    
                                                    //check if the variation previously created
                                                    $child_variation_id = FALSE;
                                                    foreach($_child_children_products   as  $_child_children_product)
                                                        {
                                                            $_woonet_network_is_child_product_id    =   get_post_meta($_child_children_product, '_woonet_network_is_child_product_id', TRUE);
                                                            if($_woonet_network_is_child_product_id == $children_product->ID)
                                                                {
                                                                    $child_variation_id =   $_child_children_product;
                                                                    break;   
                                                                }
                                                        }
                                                        
                                                    //if variation child does not exists, create it
                                                    if($child_variation_id  === FALSE)
                                                        {
                                                            $variation = array(
                                                                                'post_title'   => $children_product->post_title,
                                                                                'post_content' => '',
                                                                                'post_status'  => $children_product->post_status,
                                                                                'post_author'  => get_current_user_id(),
                                                                                'post_parent'  => $child_post->ID,
                                                                                'post_type'    => 'product_variation'
                                                                            );

                                                            $child_variation_id = wp_insert_post( $variation );   
                                                            
                                                            update_post_meta($child_variation_id, '_woonet_network_is_child_product_id', $children_product->ID);
                                                            update_post_meta($child_variation_id, '_woonet_network_is_child_site_id', $parent_product_blog_id);
                                                            
                                                            //set the origin variation post id
                                                            update_post_meta($child_variation_id, '_woonet_network_is_child_product_variation_id', $children_product->ID);
                                                            
                                                        }
                                                        
                                                    //replicate the meta
                                                    $this->functions->save_meta_to_post($variation_product_meta, array(), $child_variation_id, $blog_details->blog_id);
                                                        
                                                    $_child_variations_processed[]  =   $child_variation_id;
                                                    
                                                }
                                                
                                            //delete all other variations
                                            foreach($_child_children_products_data  as  $_child_children_product_data)
                                                {
                                                    
                                                    if(!in_array($_child_children_product_data->ID, $_child_variations_processed))
                                                        wp_delete_post ($_child_children_product_data->ID, TRUE);
                                                        
                                                }  
                                        }
                                        else
                                        {
                                            
                                            //delete all child
                                            $_child_children_products_data  = get_children( 'post_parent='.$child_post->ID.'&post_type=product_variation');
                                            foreach($_child_children_products_data  as  $_child_children_product_data)
                                                {
                                                    wp_delete_post ($_child_children_product_data->ID, TRUE);  
                                                }
                                                
                                        }   
                                }
                        
                            
                            wc_delete_product_transients( $child_post->ID );
                            
                            restore_current_blog();

                        }
                    
                }
                
                
            
            
            
            /**
            * Create a list map of product taxonomies with terms
            *     
            * @param mixed $post_ID
            */
            function get_product_taxonomies_terms_data($post_ID)
                {
                    $product_taxonomies = get_object_taxonomies( 'product' );
                    $product_taxonomies_data = array();
                    //createa  map with all taxonomies terms of this product
                    foreach($product_taxonomies as  $product_taxonomy)
                        {
                            $taxonomy_terms_data    =   array();
                            
                            $args               = array('fields' => 'ids');
                            $terms  = wp_get_object_terms($post_ID, $product_taxonomy, $args);
                            foreach($terms  as  $term_id)
                                {
                                    $term = get_term_by('id', $term_id, $product_taxonomy);
                                    if($term->parent    >   0)
                                        {
                                            $hierarchy      = array();
                                            $c_hierarchy    =   array();
                                            $p_term_data    = $term;
                                            while($p_term_data->parent    >   0)
                                                {
                                                    $c_hierarchy    =   $hierarchy;
                                                    $hierarchy      = array();
                                                    
                                                    $hierarchy[$p_term_data->term_id]   =   array(
                                                                                                    'term_name' =>  $p_term_data->name
                                                                                                        );
                                                    
                                                    //check if current term is in the list and mark as selected
                                                    if(in_array($p_term_data->term_id, $terms))
                                                        $selected = TRUE;
                                                                                       
                                                    $hierarchy[$p_term_data->term_id]['selected']   =   $selected;
                                                    $selected   =   FALSE; 
                                                        
                                                    if(count($c_hierarchy) > 0)
                                                        {
                                                            $hierarchy[$p_term_data->term_id]['childs'] =   $c_hierarchy;
                                                        }
                                                        else
                                                        {
                                                            $hierarchy[$p_term_data->term_id]['childs'] =   array();   
                                                        }
                                                        
                                                    $p_term_data    =   get_term($p_term_data->parent, $product_taxonomy);
                                                }
                                                
                                            //add the last term
                                            $selected   =   FALSE;
                                            if(in_array($p_term_data->term_id, $terms))
                                                $selected = TRUE;
                                            
                                            //add the parent too
                                            $term_hierarchy =   array();
                                            $term_hierarchy[$p_term_data->term_id]   =   array(
                                                                                            'term_name' =>  $p_term_data->name,
                                                                                            'selected'  =>  $selected,
                                                                                            'childs'    =>  $hierarchy  
                                                                                            );
                                            
                                            $path   =   "";
                                            //add to structure
                                            while(count($term_hierarchy) >   0)
                                                {
                                                    
                                                    reset($term_hierarchy);
                                                    
                                                    $current_term   =   current($term_hierarchy);   
                                                    
                                                    $level_data     =   array();
                                                    $level_data['cur_term_id']    =   key($term_hierarchy);
                                                    $level_data['term_name']      =   $current_term['term_name'];
                                                    $level_data['selected']       =   $current_term['selected'];
                                                    $level_data['childs']         =   array();
                                                    
                                                    if(!empty($path))
                                                        $path   .=  ".";
                                                        
                                                    $path   .=  $level_data['cur_term_id'];
                                                    
                                                    $term_hierarchy =   $current_term['childs'];
                                                    
                                                    
                                                    $this->set_nested_array_value($taxonomy_terms_data, $path, $level_data, $level_data['cur_term_id']);  
                                                    
                                                }
                                         
                                            
                                            
                                        }
                                        else
                                        {
                                            if(!isset($taxonomy_terms_data[$term->term_id]))
                                                {
                                                    $taxonomy_terms_data[$term->term_id]   =   array(
                                                                                                            'term_name' =>  $term->name,
                                                                                                            'selected'  =>  TRUE,
                                                                                                            'childs'    =>  array()
                                                                                                                );
                                                }
                                        }
                                    
                                }
                            
                            $product_taxonomies_data[$product_taxonomy] =   $taxonomy_terms_data;
                        }
                        
                    return $product_taxonomies_data;
                    
                }
                    
                
            function set_nested_array_value(&$array, $path, $value, $target_id) 
                {
                    $pathParts = explode(".", $path);
                    
                    $current = &$array;
                    if (!is_array($current)) 
                        { 
                            $current = array(); 
                        }
                        
                    foreach($pathParts as $index    =>  $key) 
                        {

                            if(isset($current[$key]))
                                {
                                    if($target_id   == $key)
                                        $current = &$current[$key];
                                        else
                                        $current = &$current[$key]['childs'];
                                    
                                    if($index < (count($pathParts) - 1 ))
                                        continue;
                                    
                                    $term_id    =   $value['cur_term_id'];
                                    unset($value['cur_term_id']);
                                    
                                    $selected   =   $value['selected'];
                                                                 
                                    if(isset($current[$term_id]) && $current[$term_id]['selected']      ===    TRUE    &&  $value['selected']  === FALSE)
                                        $value['selected']  =   TRUE;
                                        
                                    if($target_id   == $key)
                                        {
                                            //update
                                            $current['selected']    =   $value['selected'];
                                            
                                        }
                                        else
                                        {
                                            //add
                                            $current[$term_id] = $value;
                                        }
                                    
                                }
                                else
                                {
                                    $current = &$current[$key];
                                    unset($value['cur_term_id']);
                                    $current = $value;
                                }
                        }        
                }
                      
            
            function process_taxonomy_term_structure_data($product_id, $taxonomy, $parent_term_id, $group_data)
                {
                    if(count($group_data) > 0)
                        {
                            foreach($group_data as  $network_term_id =>  $term_data)
                                {
                                    //check if the term already exists
                                    $term_id = $this->get_term_id_by_name($taxonomy, $term_data['term_name'], $parent_term_id);
                                    if($term_id === FALSE)
                                        {
                                            //create the term
                                            $argv =     array(
                                                                'parent'    =>  $parent_term_id
                                                                );
                                            $result_data    =   wp_insert_term( (string)$term_data['term_name'], $taxonomy, $argv );
                                            $term_id        =   $result_data['term_id'];
                                        }
                                        
                                    //check if the term is selected
                                    if($term_data['selected']   === TRUE) 
                                        {
                                            wp_set_object_terms( $product_id, array((int)$term_id), $taxonomy, TRUE );   
                                        }
                                    
                                    if (isset($term_data['childs'])   && count($term_data['childs'])    >   0)
                                        {
                                            $this->process_taxonomy_term_structure_data($product_id, $taxonomy, $term_id, $term_data['childs']);
                                        }
                                }    
                        }    
                }
                
            function get_term_id_by_name($taxonomy, $term_name , $term_parent)
                {
                    $argv = array(
                                    'parent'        =>  $term_parent,
                                    'child_of'      =>  $term_parent,
                                    'hide_empty'    =>  FALSE
                                    );
                    $terms = get_terms($taxonomy, $argv);
                    foreach($terms  as  $term)
                        {
                            if( strtolower(trim((string)$term_name ))   ==  strtolower(trim( (string)$term->name)) )
                                return $term->term_id;
                        }
                        
                    return FALSE;
                }
                            
                

            function save_taxonomies_to_post($product_taxonomies_data, $post_ID, $blog_id)
                {
                    global $wpdb; 
                    
                    foreach($product_taxonomies_data    as  $taxonomy   =>  $tax_structure_data)
                        {
                            if(! taxonomy_exists($taxonomy))
                                continue;
                            
                            //unnasign any terms for this taxonomy on post
                            wp_set_object_terms( $post_ID, null, $taxonomy );
                            
                            if(count($tax_structure_data) < 1)
                                continue;
                                
                            $this->process_taxonomy_term_structure_data($post_ID, $taxonomy, 0, $tax_structure_data);
                            
                            //if the taxonomy is hierarhical, then update the child option
                            $taxonomy_info = get_taxonomy($taxonomy); 
                            if($taxonomy_info->hierarchical === TRUE)
                                {
                                    _get_term_hierarchy($taxonomy);   
                                }
                                                                        
                            //check if attribute
                            if(strpos($taxonomy, 'pa_') === 0)
                                {
                                    
                                    //check for attributes (taxonomy)
                                    $child_attribute_taxonomies = wc_get_attribute_taxonomies();
                                    $found_attribute = FALSE;
                                    foreach($child_attribute_taxonomies as  $child_attribute_taxonomy)
                                        {
                                           if(str_replace("pa_", "", $taxonomy)    == $child_attribute_taxonomy->attribute_name)
                                                {
                                                    $found_attribute = TRUE;
                                                    break;
                                                }
                                        }
                                        
                                    //create the attribte if not found
                                    if($found_attribute === FALSE)
                                        {
                                            //restore to original blog and retrieve the attribute data
                                            restore_current_blog();
      
                                            $attribute_taxonomies = wc_get_attribute_taxonomies();
      
                                            switch_to_blog( $blog_id );
                                            
                                            foreach($attribute_taxonomies as $attribute_taxonomy)
                                                {
                                                    if(str_replace("pa_", "", $taxonomy)    ==  $attribute_taxonomy->attribute_name)
                                                        {
                                                            $attribute = (array)$attribute_taxonomy;
                                                            unset($attribute['attribute_id']);

                                                            $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
                                                            break;
                                                        }
                                                }
                                                
                                            delete_transient( 'wc_attribute_taxonomies' );  
                                        }
                                }
                        }
                                        
                    
                }
            
                     
            /**
            * Delete all porduct childs
            * 
            */
            function before_delete_post($post_ID)
                {
                    global $blog_id;
                    
                    $parent_product_blog_id =   $blog_id;
                    
                    $post_data  =   get_post($post_ID);
                    
                    //check if product
                    if($post_data->post_type    !=  'product')
                        return;
                        
                    //check if is parentproduct
                    $_woonet_network_main_product   =   get_post_meta($post_ID, '_woonet_network_main_product', TRUE);
                    if(empty($_woonet_network_main_product))
                        return;
                        
                    
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            $blog_details   =   get_blog_details($network_site->blog_id);
                            
                            $publish_to  =   get_post_meta( $post_ID, '_woonet_publish_to_'. $blog_details->blog_id, true );
                            if(empty($publish_to))
                                continue;
                                    
                            //publish all meta if does not exists
                            switch_to_blog( $blog_details->blog_id );
                            
                            //Identify the product which inherited the data
                            $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  array('any', 'trash'),
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $parent_product_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                    'value'   => $post_ID,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                            $custom_query       =   new WP_Query($args);
                            if($custom_query->found_posts   >   0)
                                {
                                    //product previously created, this is an update
                                    $child_post =   $custom_query->posts[0];
                                    
                                    //check if the _woonet_child_inherit_updates is set to yes oterwise do not update
                                    $_woonet_child_inherit_updates  =   get_post_meta($child_post->ID, '_woonet_child_inherit_updates', TRUE);
                                    if($_woonet_child_inherit_updates != 'yes')
                                        {
                                            restore_current_blog();
                                            continue;
                                        }
                                    
                                    //remove the product                                    

                                    
                                    //delete all child of this post, like variations
                                    $args   =   array(
                                                        'post_type'     =>  array('any', 'product_variation'),
                                                        'post_status'   =>  array('any', 'trash'),
                                                        'post_parent'   =>  $child_post->ID
                                                        );
                                    $custom_query_children       =   new WP_Query($args);
                                    if($custom_query_children->found_posts   >   0)
                                        {
                                            foreach($custom_query_children->posts   as  $child_post_child)
                                                {
                                                    wp_delete_post( $child_post_child->ID, TRUE );
                                                }
                                        }
                                    
                                    wp_delete_post( $child_post->ID, TRUE );

                                                     
                                }
                                
                            restore_current_blog();
                            
                        }
                    
                }
                
            /**
            * Trash all child products
            *     
            * @param mixed $post_ID
            */
            function trashed_post($post_ID)
                {
                    global $blog_id;
                    
                    $parent_product_blog_id =   $blog_id;
                    
                    $post_data  =   get_post($post_ID);
                    
                    //check if product
                    if($post_data->post_type    !=  'product')
                        return;
                        
                    //check if is parentproduct
                    $_woonet_network_main_product   =   get_post_meta($post_ID, '_woonet_network_main_product', TRUE);
                    if(empty($_woonet_network_main_product))
                        return;
                        
                    
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            $blog_details   =   get_blog_details($network_site->blog_id);
                            
                            $publish_to  =   get_post_meta( $post_ID, '_woonet_publish_to_'. $blog_details->blog_id, true );
                            if(empty($publish_to))
                                continue;
                                    
                            //publish all meta if does not exists
                            switch_to_blog( $blog_details->blog_id );
                            
                            //identify the product which inherited the data
                            $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  'any',
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $parent_product_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                    'value'   => $post_ID,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                            $custom_query       =   new WP_Query($args);
                            if($custom_query->found_posts   >   0)
                                {
                                    //product previously created, this is an update
                                    $child_post =   $custom_query->posts[0];
                                    
                                    //check if the _woonet_child_inherit_updates is set to yes oterwise do not update
                                    $_woonet_child_inherit_updates  =   get_post_meta($child_post->ID, '_woonet_child_inherit_updates', TRUE);
                                    if($_woonet_child_inherit_updates != 'yes')
                                        {
                                            restore_current_blog();
                                            continue;
                                        }
                                    
                                    do_action( 'wp_trash_post', $child_post->ID );

                                    add_post_meta($child_post->ID,'_wp_trash_meta_status', $child_post->post_status);
                                    add_post_meta($child_post->ID,'_wp_trash_meta_time', time());

                                    $child_post->post_status = 'trash';
                                    wp_insert_post( wp_slash( (array)$child_post ) );

                                    wp_trash_post_comments($child_post->ID);
                                    
                                    do_action( 'trashed_post', $post_id );
                                                     
                                }
                                
                            restore_current_blog();
                        }   
                    
                }
                
                
            /**
            * Trash all child products
            *     
            * @param mixed $post_ID
            */
            function untrashed_post($post_ID)
                {
                    global $blog_id;
                    
                    $parent_product_blog_id =   $blog_id;
                    
                    $post_data  =   get_post($post_ID);
                    
                    //check if product
                    if($post_data->post_type    !=  'product')
                        return;
                        
                    //check if is parentproduct
                    $_woonet_network_main_product   =   get_post_meta($post_ID, '_woonet_network_main_product', TRUE);
                    if(empty($_woonet_network_main_product))
                        return;
                        
                    
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            $blog_details   =   get_blog_details($network_site->blog_id);
                            
                            $publish_to  =   get_post_meta( $post_ID, '_woonet_publish_to_'. $blog_details->blog_id, true );
                            if(empty($publish_to))
                                continue;
                                    
                            //publish all meta if does not exists
                            switch_to_blog( $blog_details->blog_id );
                            
                            //identify the product which inherited the data
                            $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  array('trash'),
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $parent_product_blog_id,
                                                                                    'compare' => '=',
                                                                                ),
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                    'value'   => $post_ID,
                                                                                    'compare' => '=',
                                                                                ),
                                                                        ),
                                                );
                            $custom_query       =   new WP_Query($args);
                            if($custom_query->found_posts   >   0)
                                {
                                    //product previously created, this is an update
                                    $child_post =   $custom_query->posts[0];
                                    
                                    //check if the _woonet_child_inherit_updates is set to yes oterwise do not update
                                    $_woonet_child_inherit_updates  =   get_post_meta($child_post->ID, '_woonet_child_inherit_updates', TRUE);
                                    if($_woonet_child_inherit_updates != 'yes')
                                        {
                                            restore_current_blog();
                                            continue;
                                        }
                                    
                                    do_action( 'untrash_post', $child_post->ID );

                                    $post_status = get_post_meta($child_post->ID, '_wp_trash_meta_status', true);

                                    $child_post->post_status = $post_data->post_status;

                                    delete_post_meta($child_post->ID, '_wp_trash_meta_status');
                                    delete_post_meta($child_post->ID, '_wp_trash_meta_time');

                                    wp_insert_post( wp_slash( (array)$child_post ) );

                                    wp_untrash_post_comments($child_post->ID);

                                    do_action( 'untrashed_post', $child_post->ID );
                                                     
                                }
                            
                            restore_current_blog();
                        }   
                    
                }
       
       
            /**
            * Set ignore fields
            * This return fields related to blog id
            * 
            */
            function on_save_meta_to_post__ignore_meta_fields( $ignore_meta_fields, $blog_id )
                {
                    $options    =   $this->functions->get_options();
                    
                    if($options['child_inherit_changes_fields_control__title'][$blog_id] ==   'no')
                        {
                            $ignore_meta_fields[]   =   'post_title';
                        }
                    
                    if($options['child_inherit_changes_fields_control__price'][$blog_id] ==   'no')
                        {
                            $ignore_meta_fields[]   =   '_regular_price';
                            $ignore_meta_fields[]   =   '_sale_price';
                            $ignore_meta_fields[]   =   '_sale_price';
                            $ignore_meta_fields[]   =   '_sale_price_dates_from';
                            $ignore_meta_fields[]   =   '_sale_price_dates_to';
                        }

                    $ignore_meta_fields[]   =   '_stock';
                    $ignore_meta_fields[]   =   '_stock_status';
                    
                    return $ignore_meta_fields;   
                    
                }
       
        }
        
?>