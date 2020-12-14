<?php
/*
 * Plugin Name: My Sites Search
 * Plugin URI: trepmal.com
 * Description: https://twitter.com/trepmal/status/443189183478132736
 * Version: 2016.08.12
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: mss
 * DomainPath:
 * Network: true
 */

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
	$show_if_gt = apply_filters( 'mms_show_search_minimum_sites', 1 );

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
				esc_attr__( 'Zoek sites', 'mss' )
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
			color: rgba(240,245,250,.7);
			border-width: 0;
			border-bottom-width: 1px;
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