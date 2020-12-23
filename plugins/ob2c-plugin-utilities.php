<?php
	/*
	Plugin Name: OB2C Plugin Utilities
	Description: Kleine functies die we niet vanuit het WordPress-thema kunnen regelen. Met dank aan Kailey Lampert.
	Version:     0.1.0
	Author:      Full Stack Ahead
	Author URI:  https://www.fullstackahead.be
	TextDomain:  mss
	Network:     true
	*/

	// Kan eventueel ook omgeleid worden m.b.v. 'wp_password_change_notification_email'-filter (WP 4.9+)
	if ( ! function_exists('wp_password_change_notification') ) {
		function wp_password_change_notification( $user ) {
			return;
		}
	}

	// Schakel nieuwe WooCommerce-features uit
	add_filter( 'woocommerce_admin_disabled', '__return_true' );
	add_filter( 'woocommerce_marketing_menu_items', '__return_empty_array' );

	// Instant search in de lange lijst substies
	add_action( 'admin_bar_menu', 'mss_admin_bar_menu' );
	add_action( 'wp_enqueue_scripts', 'mss_enqueue_styles' );
	add_action( 'admin_enqueue_scripts', 'mss_enqueue_styles' );
	add_action( 'wp_enqueue_scripts', 'mss_enqueue_scripts' );
	add_action( 'admin_enqueue_scripts', 'mss_enqueue_scripts' );

	/**
	 * Add search field menu item
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 * @return void
	 */
	function mss_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can('update_core') ) {
			return;
		}

		$total_users_sites = count( $wp_admin_bar->user->blogs );
		$show_if_gt = apply_filters( 'mms_show_search_minimum_sites', 10 );

		if ( $total_users_sites < $show_if_gt ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'my-sites-list',
				'id'     => 'my-sites-search',
				'title'  => sprintf(
					'<label for="my-sites-search-text">%s</label><input type="text" id="my-sites-search-text" placeholder="%s" />',
					esc_html__( 'Filter sites', 'mss' ),
					esc_attr__( 'Zoek site ...', 'mss' )
				),
				'meta'   => array(
					'class' => 'hide-if-no-js'
				),
			)
		);
	}

	/**
	 * Enqueue styles
	 * Inline styles with admin-bar dependency
	 *
	 * @return void
	 */
	function mss_enqueue_styles() {
		if ( ! current_user_can('update_core') ) {
			return;
		}

		ob_start();
		?>
			#wp-admin-bar-my-sites-search.hide-if-no-js {
				display: none;
			}
			#wp-admin-bar-my-sites-search label[for="my-sites-search-text"] {
				clip: rect(1px, 1px, 1px, 1px);
				position: absolute !important;
				height: 1px;
				width: 1px;
				overflow: hidden;
			}
			#wp-admin-bar-my-sites-search {
				height: 38px;
			}
			#wp-admin-bar-my-sites-search .ab-item {
				height: 34px;
			}
			#wp-admin-bar-my-sites-search input {
				padding: 0 1px;
				width: 95%;
				width: calc( 100% - 2px );
				color: white;
				background-color: initial;
				border-width: 0;
				border-bottom: 1px solid rgba(240,245,250,.7);
			}
			#wp-admin-bar-my-sites-search input::placeholder {
				color: rgba(240,245,250,.7);
				opacity: 1;
			}
			#wp-admin-bar-my-sites-search input:focus {
				border-color: white;
			}
			#wp-admin-bar-my-sites-search input:focus::placeholder {
				color: white;
			}
			#wp-admin-bar-my-sites-list {
				min-width: 350px;
			}
		<?php
		wp_enqueue_style('admin-bar');
		wp_add_inline_style( 'admin-bar', ob_get_clean() );
	}

	/**
	 * Enqueue JavaScript
	 * Inline script with jQuery dependency
	 *
	 * @return void
	 */
	function mss_enqueue_scripts() {
		if ( ! current_user_can('update_core') ) {
			return;
		}

		ob_start();
		?>
			jQuery(document).ready( function($) {
				$('#wp-admin-bar-my-sites-search.hide-if-no-js').show();
				$('#wp-admin-bar-my-sites-search input').keyup( function( ) {
					var searchValRegex = new RegExp( $(this).val(), 'i');
					$('#wp-admin-bar-my-sites-list > li.menupop').hide().filter(function() {
						return searchValRegex.test( $(this).find('> a').text() );
					}).show();
				});
			});
		<?php
		wp_enqueue_script('jquery-core');
		wp_add_inline_script( 'jquery-core', ob_get_clean() );
	}
?>