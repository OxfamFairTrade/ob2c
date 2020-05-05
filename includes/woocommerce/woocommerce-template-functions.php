<?php
	
	/* WooCommerce template functins
	=============================================================== */
	
	global $nm_theme_options, $nm_globals;
    
    
    
    /*
	 *	Show shop notices
	 */
	function nm_print_shop_notices() {
		echo '<div id="nm-shop-notices-wrap">';
		  wc_print_notices();
		echo '</div>';
	}
    
    
    
    /*
     * Get my-account/login link
     */
	function nm_get_myaccount_link( $is_header = true ) {
		global $nm_theme_options;
		
		$myaccount_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
		
		// Link title/icon
		if ( $is_header && $nm_theme_options['menu_login_icon'] ) {
			//$icon_class = apply_filters( 'nm_login_icon_class', 'nm-font nm-font-user' );
			//$link_title = sprintf( '<i class="nm-myaccount-icon %s"></i>', $icon_class );
            $link_title = apply_filters( 'nm_myaccount_icon', '<i class="nm-myaccount-icon nm-font nm-font-user"></i>', 'nm-font nm-font-user' );
		} else {
			$link_title = ( is_user_logged_in() ) ? esc_html__( 'My Account', 'nm-framework' ) : esc_html__( 'Login', 'woocommerce' );
		}
		
		return '<a href="' . esc_url( $myaccount_url ) . '" id="nm-menu-account-btn">' . apply_filters( 'nm_myaccount_title', $link_title ) . '</a>';
	}
    
    
    
    /*
     * Get cart title/icon
     */
	function nm_get_cart_title() {
		global $nm_theme_options;
		
		if ( $nm_theme_options['menu_cart_icon'] ) {
			//$cart_icon_class = apply_filters( 'nm_cart_icon_class', 'nm-font nm-font-shopping-cart' );
			//$cart_title = sprintf( '<i class="nm-menu-cart-icon %s"></i>', $cart_icon_class );
            $cart_title = apply_filters( 'nm_cart_icon', '<i class="nm-menu-cart-icon nm-font nm-font-shopping-cart"></i>', 'nm-font nm-font-shopping-cart' );
		} else {
			$cart_title = '<span class="nm-menu-cart-title">' . esc_html__( 'Cart', 'nm-framework' ) . '</span>';
		}
		
		return $cart_title;
	}
    
    
    
    /*
	 *	Display default Shop description
     *
     *  Code from "woocommerce_taxonomy_archive_description()" function
	 */
	if ( ! function_exists( 'nm_shop_description' ) ) {
        function nm_shop_description( $description ) {
            $description = wc_format_content( $description );
            if ( $description ) {
                echo '<div class="nm-shop-default-description term-description">' . $description . '</div>';
            }
        }
    }
    
    
    
    /*
	 *	Category menu: Create single category list HTML 
	 */
	if ( ! function_exists( 'nm_category_menu_create_list' ) ) {
        function nm_category_menu_create_list( $category, $current_cat_id, $categories_menu_divider, $current_top_cat_id = null ) {
            $output = '<li class="cat-item-' . $category->term_id;
            
            // Is this the current category?
            if ( $current_cat_id == $category->term_id ) {
                $output .= ' current-cat';
            }
            // Is this the current top parent-category?
            else if ( $current_top_cat_id && $current_top_cat_id == $category->term_id ) {
                $output .= ' current-parent-cat';
            }

            // GEWIJZIGD: Verwijder divider en voeg categorieslug toe als klasse op link (voor icoontjes)
            $output .=  '"><a href="' . esc_url( get_term_link( (int) $category->term_id, 'product_cat' ) ) . '" class="' . $category->slug . '">' . esc_attr( $category->name ) . '</a></li>';

            return $output;
        }
    }
	
	
	
	/*
	 *	Product category menu
	 */
	if ( ! function_exists( 'nm_category_menu' ) ) {
        function nm_category_menu() {
            global $wp_query, $nm_theme_options;

            $current_cat_id = ( is_tax( 'product_cat' ) ) ? $wp_query->queried_object->term_id : '';
            $is_category = ( strlen( $current_cat_id ) > 0 ) ? true : false;
            $hide_empty = ( $nm_theme_options['shop_categories_hide_empty'] ) ? true : false;
            
            // Should top-level categories be displayed?
            if ( $nm_theme_options['shop_categories_top_level'] == '0' && $is_category ) {
                nm_sub_category_menu_output( $current_cat_id, $hide_empty );
            } else {
                nm_category_menu_output( $is_category, $current_cat_id, $hide_empty );
            }
        }
    }
	
		
	
	/*
	 *	Product category menu: Output
	 */
	if ( ! function_exists( 'nm_category_menu_output' ) ) {
        function nm_category_menu_output( $is_category, $current_cat_id, $hide_empty ) {
            global $wp_query, $nm_theme_options;

            $page_id = wc_get_page_id( 'shop' );
            $page_url = get_permalink( $page_id );
            $hide_sub = true;
            $current_top_cat_id = null;
            $all_categories_class = '';

            // Is this a category page?																
            if ( $is_category ) {
                $hide_sub = false;
                
                // Get current category's top-parent id
                $current_cat_parents = get_ancestors( $current_cat_id, 'product_cat' );
                if ( ! empty( $current_cat_parents ) ) {
                    $current_top_cat_id = end( $current_cat_parents ); // Get last item from array
                }

                // Get current category's direct children
                // HOUDT GEEN REKENING MET VOORRAADSTATUS
                $current_cat_direct_children = get_terms( 'product_cat',
                    array(
                        'fields'       	=> 'ids',
                        'parent'       	=> $current_cat_id,
                        'hierarchical'	=> true,
                        'hide_empty'   	=> $hide_empty
                    )
                );

                if ( 1 == 0 ) {
                    $start = microtime(true);
                    foreach ( $current_cat_direct_children as $key => $category_id ) {
                        $products = wc_get_products( array(
                            // MOET EEN SLUG ZIJN
                            'category' => $category_id,
                            // WERKT NOG NIET IN DEZE WC-VERSIE
                            'stock_status' => 'instock',
                        ) );
                        unset( $current_cat_direct_children[ $key ] );
                    }
                    write_log( number_format( microtime(true)-$start, 4, ',', '.' )." s => EMPTY SUBCATEGORIES DITCHED" );
                }

                $category_has_children = ( empty( $current_cat_direct_children ) ) ? false : true;
            } else {
                // No current category, set "All" as current (if not product tag archive or search)
                if ( ! is_product_tag() && ! isset( $_REQUEST['s'] ) ) {
                    $all_categories_class = ' class="current-cat"';
                }
            }

            $output_cat = '<li' . $all_categories_class . '><a href="' . esc_url ( $page_url ) . '">' . esc_html__( 'All', 'nm-framework' ) . '</a></li>';
            $output_sub_cat = '';
            $output_current_sub_cat = '';

            // Categories order
            $orderby = 'slug';
            $order = 'asc';
            if ( isset( $nm_theme_options['shop_categories_orderby'] ) ) {
                $orderby = $nm_theme_options['shop_categories_orderby'];
                $order = $nm_theme_options['shop_categories_order'];
            }

            $categories = get_categories( array(
                'type'			=> 'post',
                'orderby'		=> $orderby, // Note: 'name' sorts by product category "menu/sort order"
                'order'			=> $order,
                // GEWIJZIGD: Verwijder de 'Varia'-categorie uit het menu
                'exclude'       => get_option( 'giftwrap_category_id', '' ),
                'hide_empty'	=> $hide_empty,
                'hierarchical'	=> 1,
                'taxonomy'		=> 'product_cat'
            ) );
            
            // Categories menu divider
            $categories_menu_divider = apply_filters( 'nm_shop_categories_divider', '<span>&frasl;</span>' );

            foreach( $categories as $category ) {
                // Is this a sub-category?
                if ( $category->parent != '0' ) {
                    // Should sub-categories be included?
                    if ( $hide_sub ) {
                        continue; // Skip to next loop item
                    } else {
                        if ( 
                            $category->parent == $current_cat_id || // Include current sub-category's children
                            ! $category_has_children && $category->parent == $wp_query->queried_object->parent // Include categories with the same parent (if current sub-category doesn't have children)
                        ) {
                            $output_sub_cat .= nm_category_menu_create_list( $category, $current_cat_id, $categories_menu_divider );
                        } else if ( 
                            $category->term_id == $current_cat_id // Include current sub-category (save in a separate variable so it can be appended to the start of the category list)
                        ) {
                            $output_current_sub_cat = nm_category_menu_create_list( $category, $current_cat_id, $categories_menu_divider );
                        }
                    }
                } else {
                    $output_cat .= nm_category_menu_create_list( $category, $current_cat_id, $categories_menu_divider, $current_top_cat_id );
                }
            }

            if ( strlen( $output_sub_cat ) > 0 ) {
                $output_sub_cat = '<ul class="nm-shop-sub-categories">' . $output_current_sub_cat . $output_sub_cat . '</ul>';
            }

            $output = $output_cat . $output_sub_cat;

            echo $output;
        }
    }
	
	
	
	/*
	 *	Product sub-category menu: Get "Back" link
	 */
	if ( ! function_exists( 'nm_sub_category_menu_back_link' ) ) {
        function nm_sub_category_menu_back_link( $url, $categories_menu_divider, $class = '' ) {
            return '<li class="nm-category-back-button' . esc_attr( $class ) . '"><a href="' . esc_url( $url ) . '"><i class="nm-font nm-font-arrow-left"></i> ' . esc_html__( 'Back', 'nm-framework' ) . '</a>' . $categories_menu_divider . '</li>';
        }
    }
	
	
	
	/*
	 *	Product category menu: Output sub-categories
	 */
	if ( ! function_exists( 'nm_sub_category_menu_output' ) ) {
        function nm_sub_category_menu_output( $current_cat_id, $hide_empty ) {
            global $wp_query, $nm_theme_options;

            // Categories menu divider
            $categories_menu_divider = apply_filters( 'nm_shop_categories_divider', '<span>&frasl;</span>' );

            $output_sub_categories = '';

            // Categories order
            $orderby = 'slug';
            $order = 'asc';
            if ( isset( $nm_theme_options['shop_categories_orderby'] ) ) {
                $orderby = $nm_theme_options['shop_categories_orderby'];
                $order = $nm_theme_options['shop_categories_order'];
            }

            $sub_categories = get_categories( array(
                'type'			=> 'post',
                'parent'       	=> $current_cat_id,
                'orderby'		=> $orderby, // Note: 'name' sorts by product category "menu/sort order"
                'order'			=> $order,
                'hide_empty'	=> $hide_empty,
                'hierarchical'	=> 1,
                'taxonomy'		=> 'product_cat'
            ) );

            $has_sub_categories = ( empty( $sub_categories ) ) ? false : true;

            // Is there any sub-categories available
            if ( $has_sub_categories ) {
                //$current_cat_name = __( 'All', 'nm-framework' );
                $current_cat_name = apply_filters( 'nm_shop_parent_category_title', $wp_query->queried_object->name );

                foreach( $sub_categories as $sub_category ) {
                    $output_sub_categories .= nm_category_menu_create_list( $sub_category, $current_cat_id, $categories_menu_divider );
                }
            } else {
                $current_cat_name = $wp_query->queried_object->name;
            }

            // "Back" link
            $output_back_link = '';
            if ( $nm_theme_options['shop_categories_back_link'] ) {
                $parent_cat_id = $wp_query->queried_object->parent;

                if ( $parent_cat_id ) {
                    // Back to parent-category link
                    $parent_cat_url = get_term_link( (int) $parent_cat_id, 'product_cat' );
                    $output_back_link = nm_sub_category_menu_back_link( $parent_cat_url, $categories_menu_divider );
                } else if ( $nm_theme_options['shop_categories_back_link'] == '1st' ) {
                    // 1st sub-level - Back to top-level (main shop page) link
                    $shop_page_id = wc_get_page_id( 'shop' );
                    $shop_url = get_permalink( $shop_page_id );
                    $output_back_link = nm_sub_category_menu_back_link( $shop_url, $categories_menu_divider, ' 1st-level' );
                }
            }

            // Current category link
            $current_cat_url = get_term_link( (int) $current_cat_id, 'product_cat' );
            $output_current_cat = '<li class="current-cat"><a href="' . esc_url( $current_cat_url ) . '">' . esc_html( $current_cat_name ) . '</a></li>';

            echo $output_back_link . $output_current_cat . $output_sub_categories;
        }
    }
    
    
    
    /*
	 * Shop (product loop): Get image
	 */
    if ( ! function_exists( 'nm_product_thumbnail' ) ) {
        function nm_product_thumbnail( $product_thumbnail_id ) {
            global $nm_theme_options, $nm_globals;
            
            // Note: Use to enable lazy-loading on AJAX (this is currently disabled since the "$nm_globals['shop_image_lazy_loading']" value is only set in the "archive-product_nm.php" template)
            //$ajax_image_lazy_loading = ( nm_is_ajax_request() && $nm_theme_options['product_image_lazy_loading'] ) ? true : false;
            
            //if ( $ajax_image_lazy_loading || $nm_globals['shop_image_lazy_loading'] ) {
            if ( $nm_globals['shop_image_lazy_loading'] ) {
                $product_thumbnail_title = get_the_title( $product_thumbnail_id );
                $product_thumbnail 		 = wp_get_attachment_image_src( $product_thumbnail_id, 'shop_catalog' );

                return '<img src="' . esc_url( $nm_globals['product_placeholder_image'] ) . '" data-src="' . esc_url( $product_thumbnail[0] ) . '" width="' . esc_attr( $product_thumbnail[1] ) . '" height="' . esc_attr( $product_thumbnail[2] ) . '" alt="' . esc_attr( $product_thumbnail_title ) . '" class="attachment-shop-catalog unveil-image" />';
            } else {
                return wp_get_attachment_image( $product_thumbnail_id, 'shop_catalog' );
            }
        }
    }
    
    
    
    // Note: Can be used if you need to get the alternative/hover thumbnail-id separately
    /*
	 * Shop (product loop): Get alternative/hover image id
	 */
    /*function nm_product_thumbnail_alt_id( $product ) {
        $product_gallery_thumbnail_ids = $product->get_gallery_image_ids();

        if ( $product_gallery_thumbnail_ids ) {
            $product_thumbnail_alt_id = reset( $product_gallery_thumbnail_ids ); // Get first gallery image id

            return $product_thumbnail_alt_id;
        }

        return null;
    }*/
    
    
    
    /*
	 * Shop (product loop): Get alternative/hover image
	 */
    if ( ! function_exists( 'nm_product_thumbnail_alt' ) ) {
        function nm_product_thumbnail_alt( $product ) {
            //$product_thumbnail_alt_id = nm_product_thumbnail_alt_id( $product );
            $product_gallery_thumbnail_ids = $product->get_gallery_image_ids();
            $product_thumbnail_alt_id = ( $product_gallery_thumbnail_ids ) ? reset( $product_gallery_thumbnail_ids ) : null; // Get first gallery image id

            if ( $product_thumbnail_alt_id ) {
                $product_thumbnail_alt_src = wp_get_attachment_image_src( $product_thumbnail_alt_id, 'shop_catalog' );

                // Make sure the first image is found (deleted image id's can can still be assigned to the gallery)
                if ( $product_thumbnail_alt_src ) {
                    return '<img src="' . esc_url( NM_THEME_URI . '/img/transparent.gif' ) . '" data-src="' . esc_url( $product_thumbnail_alt_src[0] ) . '" width="' . esc_attr( $product_thumbnail_alt_src[1] ) . '" height="' . esc_attr( $product_thumbnail_alt_src[2] ) . '" class="attachment-shop-catalog hover-image" />';
                }
            }

            return '';
        }
    }
    
    
    
    /*
	 *	Output product-variations list
	 */
    function nm_product_variations_list( $product ) {
        // Note: Code from "woocommerce_variable_add_to_cart()" function
        //$get_variations         = sizeof( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );
        //$available_variations   = $get_variations ? $product->get_available_variations() : false;
        $available_variations   = $product->get_available_variations();
        $attributes             = $product->get_variation_attributes();
        
        // Note: Code from "../savoy/woocommerce/single-product/add-to-cart/variable.php" template
        if ( ! empty( $available_variations ) ) :
        ?>
        <ul class="nm-variations-list">
            <?php foreach ( $attributes as $attribute_name => $options ) : ?>
                <li>
                    <div class="label"><?php echo wc_attribute_label( $attribute_name ); ?>:</div>
                    <div class="values">
                        <?php
                            // Note: Code from "wc_dropdown_variation_attribute_options()" function
                            if ( ! empty( $options ) ) {
                                if ( taxonomy_exists( $attribute_name ) ) {
                                    $terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );

                                    foreach ( $terms as $term ) {
                                        if ( in_array( $term->slug, $options ) ) {
                                            echo '<span>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) . '</span>';
                                        }
                                    }
                                }
                            }
                        ?>
                    </div>
                </li>
            <?php endforeach;?>
        </ul>
        <?php
        endif;
    }
    