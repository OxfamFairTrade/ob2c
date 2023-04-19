<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Schakel Gutenberg-editor uit (ook voor widgets)
	add_filter( 'use_block_editor_for_post', '__return_false', 100 );
	add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
	add_filter( 'use_widgets_block_editor', '__return_false', 100 );
	add_filter( 'wp_is_application_passwords_available', '__return_false' );
	
	// Verberg updates van plugins die we gehackt hebben
	add_filter( 'site_transient_update_plugins', 'disable_plugin_updates' );
	
	function disable_plugin_updates( $value ) {
		if ( wp_get_environment_type() === 'production' ) {
			if ( isset( $value ) and is_object( $value ) ) {
				$disabled_plugin_updates = array(
					'woocommerce',
					'woocommerce-force-sells',
					'woocommerce-gift-wrapper',
					'woocommerce-multistore',
					'woocommerce-shipping-local-pickup-plus',
					'wp-store-locator',
				);
				foreach ( $disabled_plugin_updates as $slug ) {
					if ( isset( $value->response[ $slug.'/'.$slug.'.php' ] ) ) {
						unset( $value->response[ $slug.'/'.$slug.'.php' ] );
					}
				}
			}
		}
		return $value;
	}
	
	// Verhinder het lekken van gegevens uit de API aan niet-ingelogde gebruikers
	add_filter( 'rest_authentication_errors', 'only_allow_administrator_rest_access' );
	
	function only_allow_administrator_rest_access( $access ) {
		if ( ! is_user_logged_in() or ! current_user_can('manage_options') ) {
			return new WP_Error( 'rest_cannot_access', 'Access prohibited!', array( 'status' => rest_authorization_required_code() ) );
		}
		return $access;
	}
	
	// Aanpassingen voor iPhone 10+
	add_filter( 'admin_viewport_meta', 'oft_change_admin_viewport', 10, 1 );
	
	function oft_change_admin_viewport( $value ) {
		return 'width=device-width, initial-scale=1, viewport-fit=cover';
	}
	
	// Toon breadcrumbs wél op shoppagina's
	add_filter( 'nm_shop_breadcrumbs_hide', '__return_false' );
	// Laad géén extra NM-stijlen rechtstreeks in de pagina!
	add_filter( 'nm_include_custom_styles', '__return_false' );
	
	// Sta HTML-attribuut 'target' toe in beschrijvingen van taxonomieën
	add_action( 'init', 'allow_target_tag', 20 );
	
	function allow_target_tag() {
		global $allowedtags;
		$allowedtags['a']['target'] = 1;
	}
	
	// Voeg extra CSS-klasses toe aan body (front-end)
	add_filter( 'body_class', 'add_main_site_class' );
	
	function add_main_site_class( $classes ) {
		if ( is_b2b_customer() ) {
			$classes[] = 'is_b2b_customer';
		}
		return $classes;
	}
	
	// Voeg extra CSS-klasses toe aan body (back-end)
	add_filter( 'admin_body_class', 'add_user_role_class' );
	
	function add_user_role_class( $class_string ) {
		if ( ! current_user_can('update_core') ) {
			$class_string .= ' local_manager ';
		}
		return $class_string;
	}
	
	// Herbenoem de default voorraadstatussen
	add_filter( 'woocommerce_product_stock_status_options', function( $statuses ) {
		$statuses['instock'] = 'Op voorraad';
		$statuses['onbackorder'] = 'Tijdelijk uit voorraad';
		$statuses['outofstock'] = 'Niet in assortiment';
		// Dit is toevallig de gewenste volgorde
		ksort( $statuses );
		return $statuses;
	}, 10, 1 );
	
	add_filter( 'woocommerce_admin_stock_html', function( $stock_html ) {
		$stock_html = str_replace( 'In nabestelling', 'Tijdelijk uit voorraad', $stock_html );
		$stock_html = str_replace( 'Uitverkocht', 'Niet in assortiment', $stock_html );
		return $stock_html;
	}, 10, 1 );
	
	add_filter( 'woocommerce_get_availability_text', 'modify_backorder_text', 10, 2 );
	
	function modify_backorder_text( $availability, $product ) {
		if ( $availability === __( 'Available on backorder', 'woocommerce' ) ) {
			$availability = 'Tijdelijk uitverkocht in deze webshop';
		} elseif ( $availability === __( 'Out of stock', 'woocommerce' ) ) {
			$availability = 'Niet beschikbaar in deze webshop';
		}
		return $availability;
	}
	
	// Limiteer de afbeeldingsgrootte op subsites
	add_filter( 'big_image_size_threshold', 'reduce_maximum_size_on_subsites', 10, 1 );
	
	function reduce_maximum_size_on_subsites( $threshold ) {
		if ( is_main_site() ) {
			return false;
		} else {
			// Komt overeen met thumbnail 1536x1536
			return 1536;
		}
	}
	
	// Limiteer de afbeeldingsgrootte in de loop
	add_filter( 'woocommerce_product_thumbnails_large_size', function( $size ) {
		return 'medium';
	} );
	
	// Verberg nutteloze Yoast-kolommen in productoverzicht het productoverzicht in de back-end
	add_filter( 'manage_edit-product_columns', 'yoast_seo_admin_remove_columns', 10, 1 );
	
	function yoast_seo_admin_remove_columns( $columns ) {
		unset($columns['pinned_keywords']);
		unset($columns['unpinned_keywords']);
		unset($columns['pin_for_all']);
		unset($columns['exclude_post']);
		unset($columns['ignore_content']);
		
		return $columns;
	}
	
	// Verberg nutteloze filters boven het productoverzicht in de back-end
	add_filter( 'woocommerce_products_admin_list_table_filters', 'ob2c_sort_categories_by_menu_order', 1000, 1 );
	
	function ob2c_sort_categories_by_menu_order( $filters ) {
		// Hierna wordt call_user_func() toegepast, dus voorzie een callback functie
		$filters['product_category'] = 'ob2c_render_products_category_filter';
		
		// Verwijder de filter van WooMultistore
		if ( array_key_exists( 'parent_child', $filters ) ) {
			unset( $filters['parent_child'] );
		}
		unset( $filters['product_type'] );
		
		return $filters;
	}
	
	function ob2c_render_products_category_filter() {
		wc_product_dropdown_categories(
			array(
				'option_select_text' => __( 'Filter by category', 'woocommerce' ),
				'hide_empty' => 0,
				// Sorteer volgens onze custom volgorde
				'orderby' => 'menu_order',
			)
		);
	}