<?php

/*
 * WooCommerce - Template functions
=============================================================== */

global $nm_theme_options, $nm_globals;



/*
 * Show WooCommerce notices
 */
function nm_print_shop_notices() {
    echo '<div id="nm-shop-notices-wrap">';
      wc_print_notices();
    echo '</div>';
}



/*
 * My-account/Login: Get link
 */
function nm_get_myaccount_link( $allow_icon = true ) {
    global $nm_theme_options;

    $myaccount_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

    // Link title/icon
    if ( $allow_icon && $nm_theme_options['menu_login_icon'] ) {
        $link_title = apply_filters( 'nm_myaccount_icon', $nm_theme_options['menu_login_icon_html'], 'nm-font nm-font-user' );
    } else {
        $link_title = ( is_user_logged_in() ) ? esc_html__( 'My account', 'woocommerce' ) : esc_html__( 'Login', 'woocommerce' );
    }

    return '<a href="' . esc_url( $myaccount_url ) . '" id="nm-menu-account-btn">' . apply_filters( 'nm_myaccount_title', $link_title ) . '</a>';
}



/*
 * Cart: Get title/icon
 */
function nm_get_cart_title( $allow_icon = true ) {
    global $nm_theme_options;

    if ( $allow_icon && $nm_theme_options['menu_cart_icon'] ) {
        $cart_title = apply_filters( 'nm_cart_icon', $nm_theme_options['menu_cart_icon_html'], 'nm-font nm-font-shopping-cart' );
    } else {
        $cart_title = '<span class="nm-menu-cart-title">' . esc_html__( 'Cart', 'woocommerce' ) . '</span>';
    }

    return $cart_title;
}



/*
 * Category menu
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
 * Category menu: Output
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
            $current_cat_direct_children = get_terms( 'product_cat',
                array(
                    'fields'        => 'ids',
                    'parent'        => $current_cat_id,
                    'hierarchical'  => true,
                    'hide_empty'    => $hide_empty
                )
            );
            $category_has_children = ( empty( $current_cat_direct_children ) ) ? false : true;
        } else {
            // No current category, set "All" as current (if not product tag archive or search)
            if ( ! is_product_tag() && ! isset( $_REQUEST['s'] ) ) {
                $all_categories_class = ' class="current-cat"';
            }
        }

        $output_categories = '<li' . $all_categories_class . '><a href="' . esc_url ( $page_url ) . '">' . esc_html__( 'All', 'nm-framework' ) . '</a></li>';

        // Categories order
        $orderby = 'slug';
        $order = 'ASC';
        if ( isset( $nm_theme_options['shop_categories_orderby'] ) ) {
            $orderby = $nm_theme_options['shop_categories_orderby'];
            $order = $nm_theme_options['shop_categories_order'];
        }
        
        $args = array(
            'taxonomy'      => 'product_cat',
            'type'          => 'post',
            'orderby'       => $orderby, // Note: 'name' sorts by product category "menu/sort order"
            'order'         => strtoupper( $order ),
            'hide_empty'    => $hide_empty,
            'hierarchical'  => 0
        );
        // Note: The "force_menu_order_sort" parameter added in WooCommerce 3.6 must be set to make "orderby" work (the "name" option doesn't work otherwise)
        // - See the "../woocommerce/includes/wc-term-functions.php" file
        $args['force_menu_order_sort'] = ( $orderby == 'name' ) ? true : false;
            
        $categories = get_categories( $args );
        
        $output_sub_categories = '';
        $output_current_sub_category = '';
        
        // Sub-categories:
        if ( ! $hide_sub ) { // Should sub-categories be included?
            foreach( $categories as $key => $category ) {
                // Is this a sub-category?
                if ( $category->parent != '0' ) {
                    if ( 
                        $category->parent == $current_cat_id || // Include current sub-category's children
                        ! $category_has_children && $category->parent == $wp_query->queried_object->parent // Include categories with the same parent (if current sub-category doesn't have children)
                    ) {
                        $output_sub_categories .= nm_category_menu_create_list( $category, $current_cat_id );
                    } else if ( 
                        $category->term_id == $current_cat_id // Include current sub-category (save in a separate variable so it can be appended to the start of the category list)
                    ) {
                        $output_current_sub_category = nm_category_menu_create_list( $category, $current_cat_id );
                    }
                    
                    // Remove sub-category from array to avoid looping it below
                    unset( $categories[$key] );
                }
            }
            
            $output_sub_categories = $output_current_sub_category . $output_sub_categories;
        }
        
        // Parent categories:
        foreach( $categories as $category ) {
            // Is this a parent category?
            if ( $category->parent == 0 ) {
                $output_categories .= nm_category_menu_create_list( $category, $current_cat_id, $current_top_cat_id, $output_sub_categories );
            }
        }
        
        if ( strlen( $output_sub_categories ) > 0 ) {
            $output_sub_categories = '<ul class="nm-shop-sub-categories">' . $output_sub_categories . '</ul>';
        }
        
        $output = $output_categories . $output_sub_categories;

        echo $output;
    }
}



/*
 * Category menu: Output sub-categories
 */
if ( ! function_exists( 'nm_sub_category_menu_output' ) ) {
    function nm_sub_category_menu_output( $current_cat_id, $hide_empty ) {
        global $wp_query, $nm_theme_options;

        $output_sub_categories = '';

        // Categories order
        $orderby = 'slug';
        $order = 'asc';
        if ( isset( $nm_theme_options['shop_categories_orderby'] ) ) {
            $orderby = $nm_theme_options['shop_categories_orderby'];
            $order = $nm_theme_options['shop_categories_order'];
        }

        $args = array(
            'type'          => 'post',
            'parent'        => $current_cat_id,
            'orderby'       => $orderby, // Note: 'name' sorts by product category "menu/sort order"
            'order'         => $order,
            'hide_empty'    => $hide_empty,
            'hierarchical'  => 1,
            'taxonomy'      => 'product_cat'
        );
        // Note: The "force_menu_order_sort" parameter added in WooCommerce 3.6 must be set to make "orderby" work (the "name" option doesn't work otherwise)
        // - See the "../woocommerce/includes/wc-term-functions.php" file
        $args['force_menu_order_sort'] = ( $orderby == 'name' ) ? true : false;
        
        $sub_categories = get_categories( $args );
        
        $has_sub_categories = ( empty( $sub_categories ) ) ? false : true;

        // Is there any sub-categories available
        if ( $has_sub_categories ) {
            $current_cat_name = apply_filters( 'nm_shop_parent_category_title', $wp_query->queried_object->name );

            foreach( $sub_categories as $sub_category ) {
                $output_sub_categories .= nm_category_menu_create_list( $sub_category, $current_cat_id );
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
                $output_back_link = nm_sub_category_menu_back_link( $parent_cat_url );
            } else if ( $nm_theme_options['shop_categories_back_link'] == '1st' ) {
                // 1st sub-level - Back to top-level (main shop page) link
                $shop_page_id = wc_get_page_id( 'shop' );
                $shop_url = get_permalink( $shop_page_id );
                $output_back_link = nm_sub_category_menu_back_link( $shop_url, ' 1st-level' );
            }
        }

        // Current category link
        $current_cat_url = get_term_link( (int) $current_cat_id, 'product_cat' );
        $output_current_cat = '<li class="current-cat"><a href="' . esc_url( $current_cat_url ) . '">' . esc_html( $current_cat_name ) . '</a></li>';

        echo $output_back_link . $output_current_cat . $output_sub_categories;
    }
}



/*
 * Category menu: Create single category list HTML 
 */
if ( ! function_exists( 'nm_category_menu_create_list' ) ) {
    function nm_category_menu_create_list( $category, $current_cat_id, $current_top_cat_id = null, $output_sub_categories_nested = '' ) {
        $menu_divider = apply_filters( 'nm_shop_categories_divider', '<span>&frasl;</span>' );
        
        $include_nested_categories = false;
        
        $output = '<li class="cat-item-' . $category->term_id;

        // Is this the current category?
        if ( $current_cat_id == $category->term_id ) {
            $include_nested_categories = true;
            
            $output .= ' current-cat';
        }
        // Is this the current top parent-category?
        else if ( $current_top_cat_id && $current_top_cat_id == $category->term_id ) {
            $include_nested_categories = true;
            
            $output .= ' current-parent-cat';
        }
        
        // Thumbnail
        $thumbnail_id = absint( get_term_meta( $category->term_id, 'nm_cat_menu_thumbnail_id', true ) );
        if ( $thumbnail_id ) {
            $thumbnail_url = wp_get_attachment_thumb_url( $thumbnail_id );
            $thumbnail = apply_filters( 'nm_cat_menu_thumbnail', sprintf( '<img src="%s" width="24" height="24" />', $thumbnail_url ), $thumbnail_url, $thumbnail_id );
        } else {
            $thumbnail = '';
        }
        
        $output .=  '">' . $menu_divider . '<a href="' . esc_url( get_term_link( (int) $category->term_id, 'product_cat' ) ) . '">' . $thumbnail . esc_attr( $category->name ) . '</a>';
        
        // Include nested sub-categories
        if ( $include_nested_categories && strlen( $output_sub_categories_nested ) > 0 ) {
            $output .= '<ul class="nm-shop-sub-categories-nested">' . $output_sub_categories_nested . '</ul>';
        }
        
        $output .=  '</li>';
        
        return $output;
    }
}



/*
 * Category menu: Get "Back" link
 */
if ( ! function_exists( 'nm_sub_category_menu_back_link' ) ) {
    function nm_sub_category_menu_back_link( $url, $class = '' ) {
        $menu_divider = apply_filters( 'nm_shop_categories_divider', '<span>&frasl;</span>' );
        
        return '<li class="nm-category-back-button' . esc_attr( $class ) . '"><a href="' . esc_url( $url ) . '"><i class="nm-font nm-font-arrow-left"></i> ' . esc_html__( 'Back', 'nm-framework' ) . '</a>' . $menu_divider . '</li>';
    }
}



/*
 * Shop: Display default description
 *
 * Code from "woocommerce_taxonomy_archive_description()" function
 */
if ( ! function_exists( 'nm_shop_description' ) ) {
    function nm_shop_description( $description = '' ) {
        global $nm_theme_options;
        
        if ( strlen( $nm_theme_options['shop_default_description'] ) > 0 && ! isset( $_REQUEST['s'] ) ) { // Don't display on search
            $description = wc_format_content( $nm_theme_options['shop_default_description'] );
            
            if ( $description ) {
                echo '<div class="nm-shop-default-description term-description">' . $description . '</div>';
            }
        }
    }
}



/*
 * Shop: Get product thumbnail/image
 * 
 * Note: Modified version of the "woocommerce_get_product_thumbnail()" function in "../wp-content/plugins/woocommerce/includes/wc-template-functions.php"
 */
if ( ! function_exists( 'woocommerce_get_product_thumbnail' ) ) {
    function woocommerce_get_product_thumbnail( $size = 'woocommerce_thumbnail', $deprecated1 = 0, $deprecated2 = 0 ) {
        global $product, $nm_theme_options, $nm_globals;
        
        $image_size = apply_filters( 'single_product_archive_thumbnail_size', $size );

        if ( $nm_theme_options['product_image_lazy_loading'] ) {
            // GEWIJZIGD: Check of we de afbeelding moeten ophalen in hoofdniveau
            $global_image_html = '';
            if ( ! empty ( $product->get_meta('_main_thumbnail_id') ) ) {
                $main_image_id = $product->get_meta('_main_thumbnail_id');
                $global_image_html = nm_product_get_thumbnail( $main_image_id, $image_size, '', $nm_globals['product_placeholder_image'], true );
                $current_blog = get_site();
                $global_image_html = str_replace( $current_blog->path.'wp-content/uploads/', '/wp-content/uploads/', $global_image_html );
                // do_action( 'qm/debug', $global_image_html );
            }

            if ( $global_image_html === '' ) {
                $image_id = get_post_thumbnail_id();
                if ( $image_id ) {
                    $output = nm_product_get_thumbnail( $image_id, $image_size, '', $nm_globals['product_placeholder_image'] );
                } else {
                    $output = wc_placeholder_img();
                }
            } else {
                $output = $global_image_html;
            }
        } else {
            $output = $product ? $product->get_image( $image_size ) : '';
        }
        
        // "Hover" image
        $hover_image = ( $nm_theme_options['product_hover_image_global'] ) ? true : get_post_meta( $product->get_id(), 'nm_product_image_swap', true );
        if ( $hover_image ) {
            $product_gallery_ids = $product->get_gallery_image_ids();
            $hover_image_id = ( $product_gallery_ids ) ? apply_filters( 'nm_product_hover_image_id', reset( $product_gallery_ids ), $product_gallery_ids ) : null; // Get first array element

            if ( $hover_image_id ) {
                $output .= nm_product_get_thumbnail( $hover_image_id, $image_size, 'nm-shop-hover-image', NM_THEME_URI . '/assets/img/transparent.gif' );
            }
        }

        return $output;
    }
}



/*
 * Shop (product loop): Get thumbnail/image
 */
function nm_product_get_thumbnail( $image_id, $image_size, $image_class, $image_placeholder_url, $switch_to_main = false ) {
    $product_thumbnail = '';

    // GEWIJZIGD: Switch naar de Savoy-formaten, die volledig identiek zijn!
    // Als we de standaard WooCommerce-formaten oproepen, krijgen we steeds het originele beeld terug ...
    switch ( $image_size ) {
        case 'woocommerce_gallery_thumbnail':
            $image_size = 'shop_thumbnail';
            break;

        case 'woocommerce_thumbnail':
            $image_size = 'shop_catalog';
            break;

        case 'woocommerce_single':
            $image_size = 'shop_single';
            break;
    }

    $props = array( 'src' => '' );
    
    // GEWIJZIGD: Haal afbeeldingseigenschappen op in hoofdniveau
    if ( $switch_to_main ) {
        switch_to_blog(1);
        $props = nm_product_get_thumbnail_props( $image_id, $image_size );
        restore_current_blog();
    }

    if ( strlen( $props['src'] ) === 0 ) {
        $props = nm_product_get_thumbnail_props( $image_id, $image_size );
    }

    if ( strlen( $props['src'] ) > 0 ) { // Make sure the image isn't deleted
        $product_thumbnail = sprintf( '<img src="%s" data-src="%s" data-srcset="%s" alt="%s" sizes="%s" width="%s" height="%s" class="attachment-woocommerce_thumbnail size-%s wp-post-image %s lazyload" />',
            esc_url( $image_placeholder_url ),
            $props['src'],
            $props['srcset'],
            $props['alt'],
            $props['sizes'],
            esc_attr( $props['src_w'] ),
            esc_attr( $props['src_h'] ),
            $image_size,
            $image_class
        );
    }

    return $product_thumbnail;
}



/*
 * Shop (product loop): Get thumbnail/image properties
 *
 * Note: Modified version of the "wc_get_product_attachment_props()" function in "../wp-content/plugins/woocommerce/includes/wc-product-functions.php"
 */
function nm_product_get_thumbnail_props( $attachment_id = null, $thumbnail_size = 'woocommerce_thumbnail' ) {
    $props = array(
        'title'   => '',
        'alt'     => '',
        'src'     => '',
        'srcset'  => false,
        'sizes'   => false,
    );
    if ( $attachment = get_post( $attachment_id ) ) {
        $props['title']   = trim( strip_tags( $attachment->post_title ) );
        $props['alt']     = trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );

        $src             = wp_get_attachment_image_src( $attachment_id, $thumbnail_size );
        $props['src']    = $src[0];
        $props['src_w']  = $src[1];
        $props['src_h']  = $src[2];
        $props['srcset'] = function_exists( 'wp_get_attachment_image_srcset' ) ? wp_get_attachment_image_srcset( $attachment_id, $thumbnail_size ) : false;
        $props['sizes']  = function_exists( 'wp_get_attachment_image_sizes' ) ? wp_get_attachment_image_sizes( $attachment_id, $thumbnail_size ) : false;
    }
    return $props;
}



/*
 * Shop (product loop): Show the product title
 */
if ( ! function_exists( 'nm_template_loop_product_title' ) ) {
    function nm_template_loop_product_title() {
        echo '<h3 class="woocommerce-loop-product__title"><a href="' . esc_url( get_permalink() ) . '" class="nm-shop-loop-title-link woocommerce-LoopProduct-link">' . get_the_title() . '</a></h3>';
    }
}



/*
 * Product variations: Output list
 */
function nm_product_variations_list( $product ) {
    // Note: Code from "woocommerce_variable_add_to_cart()" function
    //$get_variations         = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );
    //$available_variations   = $get_variations ? $product->get_available_variations() : false;
    $available_variations   = $product->get_available_variations();
    $attributes             = $product->get_variation_attributes();

    // Note: Code from "../savoy/woocommerce/single-product/add-to-cart/variable.php" template
    if ( ! empty( $available_variations ) ) :
    ?>
    <ul class="nm-variations-list">
        <?php
            foreach ( $attributes as $attribute_name => $options ) :
            
            // Note: Code from "wc_dropdown_variation_attribute_options()" function in "../plugins/woocommerce/includes/wc-template-functions.php" template
            if ( empty( $options ) && ! empty( $product ) && ! empty( $attribute_name ) ) {
                $attributes = $product->get_variation_attributes();
                $options    = $attributes[$attribute_name];
            }
        ?>
            <li>
                <div class="label"><?php echo wc_attribute_label( $attribute_name ); ?>:</div>
                <div class="values">
                    <?php
                        if ( ! empty( $options ) ) {
                            if ( taxonomy_exists( $attribute_name ) ) {
                                $terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );

                                foreach ( $terms as $term ) {
                                    if ( in_array( $term->slug, $options ) ) {
                                        echo '<span>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) . '</span>';
                                    }
                                }
                            } else {
                                foreach ( $options as $option ) {
                                    echo '<span>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</span>';
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
    