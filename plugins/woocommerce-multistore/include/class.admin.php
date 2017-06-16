<?php
    
    if ( ! defined( 'ABSPATH' ) ) { exit;}
    
    class WOO_MSTORE_admin 
        {
            var $functions;
            
            var $licence;
            
            var $upgrade_require    =   FALSE;

            
            /**
            * 
            * Run on class construct
            * 
            */
            function __construct( ) 
                {

                        
                }
            
            
            function init()
                {
                    $this->licence                          =   new WOO_MSTORE_licence(); 
                       
                    $this->functions                        =   new WOO_MSTORE_functions();
                    //add product fields
                    $this->product_interface                =   new WOO_MSTORE_admin_product();
                    $this->network_orders_interface         =   new WOO_MSTORE_admin_orders();
                    $this->network_products_interface       =   new WOO_MSTORE_admin_products();
                    
                    //check for update require
                    $this->upgrade_require_check();
                                        
                    add_action( 'admin_init',                               array(  $this, 'admin_init'), 1); 
                    
                    //geenral network admin menu
                    add_action( 'network_admin_menu', array($this, 'network_admin_menu'), 999 );               
                }    
            
            
            function admin_init()
                {
                    
                    add_action( 'admin_enqueue_scripts',    array($this, 'wp_enqueue_woocommerce_style') );
                            
                    //add quick options for Products
                    add_action( 'bulk_edit_custom_box',             array( $this, 'bulk_edit' ), 20, 2 );
                    add_action( 'quick_edit_custom_box',            array( $this, 'quick_edit' ), 20, 2 );
                    add_action( 'save_post',                        array( $this, 'bulk_and_quick_edit_save_post' ), 999, 2 );
                    
                    add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_columns' ), 10 );  
                    
                    
                    //hide certain menus and forms if don't have enough access
                    if( ! $this->functions->publish_capability_user_can() )
                        {
                            add_action( 'admin_enqueue_scripts',    array($this, 'wp_enqueue_no_capability') );
                        }
                        
                }
                
                
            function network_admin_menu()
                {
                    global $WOO_MSTORE;
                    
                    //only if superadmin
                    if(!    current_user_can('manage_sites'))
                        return;

                    if( $this->upgrade_require    ===   FALSE)
                        {
                            //set the latest version
                            $options    =   $this->functions->get_options();
                            $options['version'] =   WOO_MSTORE_VERSION;
                            $this->functions->update_options( $options );   
                            
                            return;   
                        }
                    
                    $current_page   =   isset($_GET['page']) ?      $_GET['page']   :   '';
                    if( $current_page   !=  'woonet-upgrade'    )
                        return;
                    
                    include_once(   WOO_MSTORE_PATH . '/include/class.data-update.php' );
                    $data_update    =   new WOO_Date_Update();
                    
                    $menus_hooks    =   array();

                    $menus_hooks[] =    add_submenu_page( 'woonet-woocommerce', __( 'Upgrade', 'woonet' ), __( 'Upgrade', 'woonet' ), 'manage_product_terms', 'woonet-upgrade', array($data_update, 'update_run') ); 
                    
                    foreach($menus_hooks    as  $menus_hook)
                        {
                            
                            /*
                            add_action('load-' . $menus_hook , array($this, 'load_dependencies'));
                            add_action('load-' . $menus_hook , array($this, 'admin_notices'));
                            
                            add_action('admin_print_styles-' . $menus_hook , array($this, 'admin_print_styles'));
                            add_action('admin_print_scripts-' . $menus_hook , array($this, 'admin_print_scripts'));
                            */
                        }     
                    
                }
                
            function wp_enqueue_woocommerce_style()
                {
                    
                    wp_register_style( 'woonet_admin', WOO_MSTORE_URL . '/assets/css/admin.css' );
                    wp_enqueue_style( 'woonet_admin' );
                    
                    $screen = get_current_screen();
                    if( !isset($screen->id) ||   ($screen->id  !== 'edit-product'   &&  $screen->id  !== 'woocommerce_page_woonet-woocommerce-products-network'))
                        return;
                    
                    wp_register_script( 'quick-edit', WOO_MSTORE_URL . '/assets/js/quick-edit.js' );
                    wp_enqueue_script( 'quick-edit' );
                }
                
            
            function wp_enqueue_no_capability()
                {
                    ?>
                    <style>
                        .inline-edit-row #woocommerce-multistore-fields {display: none !important}
                    </style>
                    
                    <?php
                }
                
                
            function bulk_edit($column_name, $post_type)
                {
                    if ( 'price' != $column_name || 'product' != $post_type ) 
                        {
                            return;
                        }
                    
                    include( WOO_MSTORE_PATH . '/include/admin/views/html-bulk-edit-product.php' );
                    
                }
  
  
            function quick_edit($column_name, $post_type)
                {
                    if ( 'price' != $column_name || 'product' != $post_type ) 
                        {
                            return;
                        }
                    
                    include( WOO_MSTORE_PATH . '/include/admin/views/html-quick-edit-product.php' );   
                    
                }
                
                
            public function bulk_and_quick_edit_save_post( $post_id, $post ) 
                {
                  
                    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
                    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                        return $post_id;
                    }

                    // Don't save revisions and autosaves
                    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
                        return $post_id;
                    }

                    // Check post type is product
                    if ( 'product' != $post->post_type ) {
                        return $post_id;
                    }

                    // Check user permission
                    if ( ! current_user_can( 'edit_post', $post_id ) ) {
                        return $post_id;
                    }

                    // Check nonces
                    if ( ! isset( $_REQUEST['woocommerce_multisite_quick_edit_nonce'] ) && ! isset( $_REQUEST['woocommerce_multisite_bulk_edit_nonce'] ) ) {
                        return $post_id;
                    }
                    if ( isset( $_REQUEST['woocommerce_multisite_quick_edit_nonce'] ) && ! wp_verify_nonce( $_REQUEST['woocommerce_multisite_quick_edit_nonce'], 'woocommerce_multisite_quick_edit_nonce' ) ) {
                        return $post_id;
                    }
                    if ( isset( $_REQUEST['woocommerce_multisite_bulk_edit_nonce'] ) && ! wp_verify_nonce( $_REQUEST['woocommerce_multisite_bulk_edit_nonce'], 'woocommerce_multisite_bulk_edit_nonce' ) ) {
                        return $post_id;
                    }

                    
                    $check_for_child_product = get_post_meta($post->ID, '_woonet_network_is_child_product_id', TRUE);
                    if($check_for_child_product >   0)
                        return $post_id;
                        
                    
                    // Get the product and save
                    $product = wc_get_product( $post );
                    
                    //ignore if grouped
                    if( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variable' ))
                        return $post_id;    

                    if ( ! empty( $_REQUEST['woocommerce_multisite_quick_edit'] ) ) 
                        {
                            $this->quick_edit_save( $post_id, $post );
                        } 
                        else 
                        {
                            //$this->bulk_edit_save( $post_id, $product );
                            //use the same as quick edit 
                            $this->quick_edit_save( $post_id, $post );
                        }

                    
                    
                    // Clear transient
                    wc_delete_product_transients( $post_id );

                    return $post_id;
                }
                
                
            /**
             * Quick edit.
             *
             * @param integer $post_id
             * @param WC_Product $product
             */
            public function quick_edit_save( $post_id, $post, $pmxi = false ) 
                {
                    global $blog_id;
                    
                    
                    //check if ignored
                    if(!isset($_POST['WOO_MSTORE_ignore_quick_edit_save']))
                        {
                         
                            $main_blog_id =   $blog_id;
                        
                            $network_sites  =   get_sites(array('limit'  =>  999));
                            foreach($network_sites as $network_site)
                                {
                                    if($network_site->blog_id ==  $main_blog_id)
                                        continue; 
                                    
                                    $blog_details   =   get_blog_details($network_site->blog_id);
                                    
                                    // GEWIJZIGD: Check of we bezig zijn met een WP All Import en het product eerder eerder al gepublishd werd
                                    $go = ( $pmxi and get_post_meta( '_woonet_network_main_product', $post_id, true ) ) ? 'yes' : '';
                                    // GEWIJZIGD: Check of het moederproduct geen concept is
                                    $default = ( get_post_status( $post_id ) === 'publish' ) ? $go : '';
                                    
                                    // GEWIJZIGD: SOWIESO OP 'YES' ZETTEN INDIEN GEPUBLICEERD
                                    $_woonet_publish_to = isset( $_REQUEST['_woonet_publish_to_'.$network_site->blog_id] ) ? $_REQUEST['_woonet_publish_to_'.$network_site->blog_id] : $default;
                                    // GEWIJZIGD: SOWIESO OP 'YES' ZETTEN INDIEN GEPUBLICEERD
                                    $_woonet_publish_to_child_inherit = isset( $_REQUEST['_woonet_publish_to_'.$network_site->blog_id.'_child_inheir'] ) ? $_REQUEST['_woonet_publish_to_'.$network_site->blog_id.'_child_inheir'] : $default;
                                    $_woonet_child_stock_synchronize    =   isset($_REQUEST['_woonet_'. $network_site->blog_id .'_child_stock_synchronize'])  ?    $_REQUEST['_woonet_'. $network_site->blog_id .'_child_stock_synchronize']    :   '';
                                    
                                    //get previous data
                                    $previous_data =    get_post_meta($post_id, '_woonet_publish_to_' . $network_site->blog_id, TRUE);
                                    if($previous_data   ==  'yes'   &&  $_woonet_publish_to   !=  'yes')
                                        {
                                            //a product has been just unnasigned from the tree, make required changes
                                            switch_to_blog( $network_site->blog_id );
                                    
                                            //identify the product which inherited the data
                                            $args   =   array(
                                                                'post_type'     =>  'product',
                                                                'post_status'   =>  'any',
                                                                'meta_query'    => array(
                                                                                            'relation' => 'AND',
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                                    'value'   => $main_blog_id,
                                                                                                    'compare' => '=',
                                                                                                ),
                                                                                            array(
                                                                                                    'key'     => '_woonet_network_is_child_product_id',
                                                                                                    'value'   => $post_id,
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
                                    
                                    
                                    
                                    
                                    update_post_meta( $post_id, '_woonet_publish_to_' . $network_site->blog_id, $_woonet_publish_to );
                                    
                                    //we don't need that field
                                    //delete_post_meta( $post_id, '_woonet_publish_to_'.  $network_site->blog_id .'_child_inheir');
                                    
                                    //update on child product
                                    switch_to_blog( $blog_details->blog_id );
                                    $args   =   array(
                                                        'post_type'     =>  'product',
                                                        'post_status'   =>  'any',
                                                        'meta_query'    => array(
                                                                                    'relation' => 'AND',
                                                                                    array(
                                                                                            'key'     => '_woonet_network_is_child_site_id',
                                                                                            'value'   => $main_blog_id,
                                                                                            'compare' => '=',
                                                                                        ),
                                                                                    array(
                                                                                            'key'     => '_woonet_network_is_child_product_id',
                                                                                            'value'   => $post_id,
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
                                                                                            'value'   => $main_blog_id,
                                                                                            'compare' => '=',
                                                                                        ),
                                                                                    array(
                                                                                            'key'     => '_woonet_network_unassigned_product_id',
                                                                                            'value'   => $post_id,
                                                                                            'compare' => '=',
                                                                                        ),
                                                                                ),
                                                        );
                                            $custom_query       =   new WP_Query($args);   
                                        }
                                    
                                    
                                    if($custom_query->found_posts   >   0)
                                        {
                                            //product previously created, this is an update
                                            $child_post =   $custom_query->posts[0];
                                            
                                            update_post_meta($child_post->ID, '_woonet_child_inherit_updates', $_woonet_publish_to_child_inherit);
                                            update_post_meta($child_post->ID, '_woonet_child_stock_synchronize', $_woonet_child_stock_synchronize);
                                        }
                                    
                                    
                                    restore_current_blog();
                                    
                                }
                        }
                        
                        
                    //unload the save action to avoid infinite loop insert
                    remove_action( 'save_post',                array( $this, 'bulk_and_quick_edit_save_post' ), 999, 2 );
                        
                    //process the product childs in case data has been changed    
                    $this->product_interface->process_product($post_id, $post, TRUE);
                    
                    //put that back
                    add_action( 'save_post',                array( $this, 'bulk_and_quick_edit_save_post' ), 999, 2 );

                    
                }
            
                
                
            /**
            * Ouput custom columns for products.
            *
            * @param string $column
            */
            public function render_product_columns( $column ) 
                {
                    global $post, $the_product;

                    if ( empty( $the_product ) || $the_product->get_id() != $post->ID ) {
                        $the_product = wc_get_product( $post );
                    }

                    if($column  !=  'name')
                        return;
                   
                    $check_for_child_product = get_post_meta($post->ID, '_woonet_network_is_child_product_id', TRUE);
                    if($check_for_child_product >   0)
                        return;
                   
                    $product = wc_get_product( $post );
                    if ( $product->is_type( 'grouped' ) ) 
                        return;
                    
                    ?><div class="hidden" id="woocommerce_multistore_inline_<?php echo $post->ID ?>"><?php
                    
                    
                    
                    global $post, $blog_id;
                     
                    $main_blog_id =   $blog_id;
                
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            $blog_details   =   get_blog_details($network_site->blog_id);
                            
                            $value  =   get_post_meta( $post->ID, '_woonet_publish_to_' . $network_site->blog_id, true );
                            
                            switch_to_blog( $blog_details->blog_id );
                            
                            //check if plugin active
                            if( !   $this->functions->is_plugin_active('woocommerce/woocommerce.php'))
                                {
                                    restore_current_blog();
                                    continue;
                                }
                            
                            if($blog_details->blog_id   ==  $main_blog_id)
                                {
                                    restore_current_blog();
                                    continue;   
                                }
                            
                            $_woonet_child_inherit_updates      =   '';
                            $_woonet_child_stock_synchronize    =   '';
                            $args   =   array(
                                                'post_type'     =>  'product',
                                                'post_status'   =>  'any',
                                                'meta_query'    => array(
                                                                            'relation' => 'AND',
                                                                            array(
                                                                                    'key'     => '_woonet_network_is_child_site_id',
                                                                                    'value'   => $main_blog_id,
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
                                    
                                    $_woonet_child_inherit_updates      =   get_post_meta($child_post->ID, '_woonet_child_inherit_updates', TRUE);        
                                    $_woonet_child_stock_synchronize    =   get_post_meta($child_post->ID , '_woonet_child_stock_synchronize', TRUE);
                                }
                            
                            ?>
                                <div class="data_block" data-blog-id="<?php echo $network_site->blog_id ?>">
                                    <div class="publish_to _woonet_publish_to_<?php echo $network_site->blog_id ?>"><?php echo $value ?></div>
                                    <div class="child_inheir _woonet_publish_to_<?php echo $network_site->blog_id ?>_child_inheir"><?php echo $_woonet_child_inherit_updates ?></div>
                                    <div class="stock_synchronize _woonet_<?php echo $network_site->blog_id ?>_child_stock_synchronize"><?php echo $_woonet_child_stock_synchronize ?></div>
                                </div>
                            <?php
                                                                                           
                            restore_current_blog();
                        }
                    
                    
                    ?></div><?php
           

                }
                
                
            function upgrade_require_check()
                {
                    
                    $options    =   $this->functions->get_options();
                    
                    $version        =   isset($options['version']) ?   $options['version'] :   1;
            
                    if (version_compare($version, WOO_MSTORE_VERSION, '<')) 
                        {
                            
                            $update_required    =   FALSE;
                            
                            if(version_compare($version, '1.5', '<'))
                                {
                                    $update_required    =   TRUE;
                                    
                                    $version =   '1.5';
                                }
                                
                            if( $update_required    === TRUE)
                                $this->upgrade_require     =   TRUE;
                                
                        }
                        
                }    
            
              
        } 
    
    
    
?>