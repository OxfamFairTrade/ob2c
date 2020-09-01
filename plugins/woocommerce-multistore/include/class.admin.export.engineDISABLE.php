<?php

    class WOO_MSTORE_EXPORT_ENGINE
        {
            
            var $errors     =   FALSE;
            var $errors_log =   array();
            
            var $export_type            =   '';
            var $export_time_after      =   '';
            var $export_time_before     =   '';
            var $site_filter            =   '';
            var $order_status           =   '';
            
            var $orders                 =   array();
            
            var $fields_export          =   array(
                                                    '_site_id' =>  array(
                                                                                'title'     =>  'Site ID',
                                                                                'callback'  =>  'fields__site_id' 
                                                                                ),
                                                    '_order_number' =>  array(
                                                                                'title'     =>  'Order ID',
                                                                                'callback'  =>  'fields__order_number' 
                                                                                ),
                                                    '_order_date'   =>  array(
                                                                                'title'     =>  'Order Date',
                                                                                'callback'  =>  'fields__order_date' 
                                                                                ),
                                                    // '_order_status'   =>  array(
                                                    //                             'title'     =>  'Order Status',
                                                    //                             'callback'  =>  'fields__order_status' 
                                                    //                             ),
                                                    '_order_total'  =>  array(
                                                                                'title'     =>  'Order Total'
                                                                                ),
                                                    '_order_currency'  =>  array(
                                                                                'title'     =>  'Order Currency'
                                                                                ),
                                                    '_cart_products'  =>  array(
                                                                                'title'     =>  'Order Products',
                                                                                'callback'  =>  'fields__order_products' 
                                                                                ),
                                                    // '_order_discount'  =>  array(
                                                    //                             'title'     =>  'Order Discount'
                                                    //                             ),
                                                    '_cart_discount'  =>  array(
                                                                                'title'     =>  'Cart Discount'
                                                                                ),
                                                    '_order_tax'  =>  array(
                                                                                'title'     =>  'Order Tax'
                                                                                ),
                                                    '_order_shipping'  =>  array(
                                                                                'title'     =>  'Order Shipping'
                                                                                ),
                                                    '_order_shipping_tax'  =>  array(
                                                                                'title'     =>  'Shipping Tax'
                                                                                ),
                                                    '_payment_method_title'  =>  array(
                                                                                'title'     =>  'Payment Title'
                                                                                ),
                                                    '_shipping_method'  =>  array(
                                                                                'title'     =>  'Shipping Title',
                                                                                'callback'  =>  'fields__shipping_method' 
                                                                                ),
                                                    '_customer_user'  =>  array(
                                                                                'title'     =>  'Customer ID',
                                                                                'callback'  =>  'fields__customer_username' 
                                                                                ),
                                                    '_customer_email'  =>  array(
                                                                                'title'     =>  'Customer Details',
                                                                                'callback'  =>  'fields__customer_email' 
                                                                                ), 
                                            );
                         
            function process( $settings )
                {
                    
                    ini_set('max_execution_time', 500);
                    
                    //validate $settings
                    $this->validate_settings( $settings );
                    
                    if( $this->errors    === TRUE    )
                        return false;
                    
                    
                    $this->fetch_orders();
                    
                    $this->output();
                    
                }
                
                
            function validate_settings( $settings )
                {
                    
                    if( empty($settings['export_format'])   ||  !in_array($settings['export_format'], array('csv', 'xls')))
                        {
                            $this->errors       =   TRUE;
                            $this->errors_log[] =   "Invalid export format";
                        }
                        else
                        $this->export_type  =   $settings['export_format'];   
                    
                    if( !empty($settings['export_time_after']) )
                        {
                            $this->export_time_after    =   strtotime($settings['export_time_after']);
                            
                            if( $this->export_time_after ===    FALSE)
                                {
                                    $this->errors       =   TRUE;
                                    $this->errors_log[] =   "Invalid time After";   
                                }
                        }
                    
                    if( !empty($settings['export_time_before']) )
                        {
                            $this->export_time_before    =   strtotime($settings['export_time_before']);
                            
                            if( $this->export_time_before ===    FALSE)
                                {
                                    $this->errors       =   TRUE;
                                    $this->errors_log[] =   "Invalid time Before";   
                                }
                        }
                        
                    if( !empty($settings['site_filter']) )
                        {
                            $this->site_filter    =   $settings['site_filter'];
                        }
                        
                    if( !empty($settings['order_status']) )
                        {
                            $this->order_status    =   $settings['order_status'];
                        }
    
                }
                
            
            /**
            * Retrieve the orders
            *    
            */
            function fetch_orders()
                {
                    
                    global $wpdb;
                    global $WOO_MSTORE;
                                         
                    $network_sites  =   get_sites(array('limit'  =>  999));
                    foreach($network_sites as $network_site)
                        {
                            
                            if( !empty($this->site_filter)  &&  $this->site_filter  != $network_site->blog_id )
                                continue;
                            
                            switch_to_blog( $network_site->blog_id );
                            
                            if( ! $WOO_MSTORE->functions->is_plugin_active('woocommerce/woocommerce.php') )
                                {
                                    restore_current_blog();
                                    continue;   
                                }
                            
                            $mysql_site_id =  $network_site->blog_id;
                            if($mysql_site_id < 2)
                                $mysql_site_table  =   '';
                                else
                                $mysql_site_table  =   $network_site->blog_id . '_';
                            
                            
                            $mysql_query    =          "SELECT ID FROM ". $wpdb->base_prefix . $mysql_site_table . "posts  
                                                            WHERE post_type = 'shop_order'";
                                                            
                            if(!empty($this->order_status))
                                {
                                    $mysql_query    .=  " AND post_status = '". $this->order_status ."' ";   
                                }
                                                       
                            $export_time_after  =   !empty($this->export_time_after) ?  $this->export_time_after    :   '1'; 
                            $export_time_before  =   !empty($this->export_time_before) ?  $this->export_time_before    :   '5999999999';
                            
                            $mysql_query    .=  " AND post_date BETWEEN '". date("Y-m-d", $export_time_after) ."' AND '". date("Y-m-d", $export_time_before) ."'
                                                    ORDER BY ID ASC
                                                    ";
                            
                            $results        =   $wpdb->get_results($mysql_query);
                            foreach($results    as  $result)
                                {
                                    $this->orders[]   =   array(
                                                                'blog_id'   =>  $network_site->blog_id,
                                                                'order_id'  =>  $result->ID
                                                                );   
                                }
                                
                            restore_current_blog();
                            
                        }
                    
                }
                
                
            function output()
                {
                    
                    switch($this->export_type)
                        {
                            case 'csv'  :   
                                            $this->output_csv();
                                            break;
                        
                            case 'xls'  :   
                                            $this->output_xls();
                                            break;
                        
                        }   
                    
                }
                
            
            function output_csv()
                {
                    
                    $filename   =   'export';
                    if(!empty($this->export_time_after) ||   !empty($this->export_time_before))
                        {
                            $filename   .=   implode(array_filter(array($this->export_time_after, $this->export_time_before)), '-');
                        }
                       
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=' . $filename . '.csv');

                    // create a file pointer connected to the output stream
                    $output = fopen('php://output', 'w');
                    //fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

                    // output the column headings
                    $header =   $this->get_fields_header_title();
         
                    fputcsv($output, $header);
               
                                
                    foreach($this->orders   as  $order_data)
                        {
                            $row    =   array();
                            
                            switch_to_blog( $order_data['blog_id'] );
                            
                            
                            $order = new WC_Order( $order_data['order_id']);
                           
                            foreach ($this->fields_export   as  $key    =>  $field_export)
                                {
                                    if(isset($field_export['callback']))
                                        $row[]  =   utf8_decode( call_user_func_array(array($this, $field_export['callback']), array($order, $key)) );
                                        else
                                        $row[]  =   utf8_decode( get_post_meta($order_data['order_id'], $key, TRUE) );
                                }
                            
                            fputcsv($output, $row);
                                
                            restore_current_blog();
                            
                        }

                    die();    
                    
                }
                
            
            function output_xls()
                {
                    
                    $filename   =   'export';
                    if(!empty($this->export_time_after) ||   !empty($this->export_time_before))
                        {
                            $filename   .=   implode(array_filter(array($this->export_time_after, $this->export_time_before)), '-');
                        }
                    
                    header('Content-Encoding: UTF-8');
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
                    header('Cache-Control: max-age=0');
                    header('Cache-Control: max-age=1');

                    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                    header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
                    header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
                    header ('Pragma: public'); // HTTP/1.0
                       
                    error_reporting(E_ALL);
                    ini_set('display_errors', TRUE);
                    ini_set('display_startup_errors', TRUE);
                    date_default_timezone_set('Europe/London');

                    define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

                    /** Include PHPExcel */
                    require_once WOO_MSTORE_PATH. '/include/dependencies/PHPExcel.php';
             
                    $objPHPExcel = new PHPExcel();

                    // output the column headings
                    $header =   $this->get_fields_header_title();
         
                    
                    // Set document properties
                    $objPHPExcel->getProperties()->setCreator("WooCommerce EVC Orders Export")
                                                 ->setLastModifiedBy("WooCommerce EVC Orders Export")
                                                 ->setTitle("Office 2007 XLSX Document")
                                                 ->setSubject("Office 2007 XLSX Document")
                                                 ->setKeywords("office 2007 openxml php");

                    // Add some data
                    $objPHPExcel->setActiveSheetIndex(0);
                    
                    $current_row    =   1;
                    //output headers
                    foreach ($header as $value_key => $value)
                        {
                            $_column =  PHPExcel_Cell::stringFromColumnIndex($value_key);
                            $table_location   = $_column . ($current_row);
                            
                            $try_numeric    =   intval($value);
                            if((string)$try_numeric ==  $value)
                                $objPHPExcel->getActiveSheet()->getCell($table_location)->setValueExplicit(utf8_decode($value), PHPExcel_Cell_DataType::TYPE_NUMERIC);
                                else
                                $objPHPExcel->getActiveSheet()->getCell($table_location)->setValueExplicit(utf8_decode($value), PHPExcel_Cell_DataType::TYPE_STRING);
                                
                            $objPHPExcel->getActiveSheet()->getColumnDimension($this->XLS_GetNameFromNumber(($value_key + 1)))->setWidth(18);
                            $objPHPExcel->getActiveSheet()->getStyle($table_location.":".$table_location)->getFont()->setBold(true);
                        }
                    $current_row++;
                        
                    
                    foreach($this->orders   as  $order_data)
                        {
                            $row    =   array();
                            
                            switch_to_blog( $order_data['blog_id'] );
                            
                            
                            $order = new WC_Order( $order_data['order_id']);
                           
                            foreach ($this->fields_export   as  $key    =>  $field_export)
                                {
                                    if(isset($field_export['callback']))
                                        $row[]  =   utf8_decode ( call_user_func_array(array($this, $field_export['callback']), array($order, $key)) );
                                        else
                                        $row[]  =   utf8_decode ( get_post_meta($order_data['order_id'], $key, TRUE) );
                                }
                            
                            foreach ($row as $value_key => $value)
                                {
                                    $_column =  PHPExcel_Cell::stringFromColumnIndex($value_key);
                                    $table_location   = $_column . ($current_row);
                                    
                                    $try_numeric    =   intval($value);
                                    if((string)$try_numeric ==  $value)
                                        $objPHPExcel->getActiveSheet()->getCell($table_location)->setValueExplicit(utf8_encode($value), PHPExcel_Cell_DataType::TYPE_NUMERIC);
                                        else
                                        $objPHPExcel->getActiveSheet()->getCell($table_location)->setValueExplicit(utf8_encode($value), PHPExcel_Cell_DataType::TYPE_STRING);
                                }
                            $current_row++;
                                
                            restore_current_blog();
                            
                        }
                                      
                    $objPHPExcel->setActiveSheetIndex(0);
                    
                          
                    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                    $objWriter->save('php://output');
                                        
                    die();  
                    
                }
                
                
            function XLS_GetNameFromNumber($num) 
                {
                    $numeric = ($num - 1) % 26;
                    $letter = chr(65 + $numeric);
                    $num2 = intval(($num - 1) / 26);
                    if ($num2 > 0) {
                        return getNameFromNumber($num2) . $letter;
                    } else {
                        return $letter;
                    }
                }
                
            
            
            function get_fields_header_title()
                {
                    
                    $header =   array();
                    foreach($this->fields_export    as  $field_data)
                        {
                            $header[]   =   $field_data['title'];
                        }   
                        
                    return $header;
                    
                }
                
                
            /**
            * Callback fields
            */
            function fields__site_id( $order, $key)
                {
                    return get_bloginfo('name');
                }
            
            function fields__order_number( $order, $key )
                {
                    return $order->get_order_number();
                }
                
            function fields__order_date( $order, $key )
                {
                    return date_i18n( 'd/m/Y', strtotime( $order->get_date_created() ) );    
                }
                
            function fields__order_status( $order, $key )
                {  
                    return $order->get_status();
                }
                
            function fields__order_products( $order, $key )
                {
                    $items = $order->get_items();    
                    
                    $row_cel_data = array();
                    foreach( $items as $item_meta_id => $item ) {
                        $row_cel_data[] = $item['qty'] . 'x ' . $item['name'];
                    }
                        
                    return implode( ', ', $row_cel_data );    
                }
                
            function fields__customer_username( $order, $key )
                {
                    
                    $user_id    =   get_post_meta( $order->get_id(), $key, TRUE );
                    // $user = get_user_by( 'ID', $user_id );   
                    // return $user->username;  
                    
                    $user_meta = get_userdata($user_id);
                    $manager = $user_id;
                    if ( $user_meta !== false ) {
                        foreach ( $user_meta->roles as $capability ) {
                            // Check of de gebruiker 'local_helper' of 'local_manager' is in de subsite van het order
                            if ( strpos( $capability, 'local_' ) === 0 ) {
                                $manager .= ' (webshopmedewerker)';
                            }
                        }
                    }
                    return $manager;
                }
                
            function fields__customer_email( $order, $key )
                {
                    return $order->get_billing_email();   
                }

            function fields__shipping_method( $order, $key )
                {   
                    return $order->get_shipping_method();   
                }
           
        }


?>