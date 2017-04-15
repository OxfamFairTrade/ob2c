<?php
	/*
	Plugin Name: My Account Newsletters
	Description: Zorg ervoor dat er een tabblaadje 'Digizine' verschijnt en 'Dashboard', 'Downloads' en 'Uitloggen' verborgen worden.
	Version:     1.0.0
	Author:      Le Couperet
	Author URI:  https://www.lecouperet.net/
	Text Domain: my-account-newsletters
	*/

	defined( 'ABSPATH' ) or die( 'Rechtstreekse toegang verboden!' );

	class Custom_My_Account_Endpoint {
		public static $endpoint = 'digizine';

		public function __construct() {
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
			add_filter( 'the_title', array( $this, 'endpoint_title' ) );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_'.self::$endpoint.'_endpoint', array( $this, 'endpoint_content' ) );
		}

		public function add_endpoints() {
			add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
		}

		public function add_query_vars( $vars ) {
			$vars[] = self::$endpoint;
			return $vars;
		}

		public function endpoint_title( $title ) {
			global $wp_query;
			$is_endpoint = isset( $wp_query->query_vars[self::$endpoint] );
			if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
				$title = 'Digizine';
				remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
			}
			return $title;
		}

		public function new_menu_items( $items ) {
			unset( $items['dashboard'] );
			unset( $items['downloads'] );
			unset( $items['customer-logout'] );
			$items[self::$endpoint] = 'Digizine';
			return $items;
		}

		public function endpoint_content() {
			echo getLatestNewsletters();
			// echo getMailChimpStatus();
		}

		public static function install() {
			add_rewrite_endpoint( 'digizine', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
	}

	new Custom_My_Account_Endpoint();
	
	register_activation_hook( __FILE__, array( 'Custom_My_Account_Endpoint', 'install' ) );
	register_deactivation_hook( __FILE__, array( 'Custom_My_Account_Endpoint', 'install' ) );
?>