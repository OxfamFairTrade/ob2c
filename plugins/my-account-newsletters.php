<?php
	/*
	Plugin Name: My Account Newsletters
	Description: Zorg ervoor dat een extra tabblaadje 'Digizine' verschijnt en dat 'Dashboard', 'Downloads' en 'Uitloggen' verborgen worden.
	Version:     1.2.0
	Author:      Le Couperet
	Author URI:  https://www.lecouperet.net/
	Text Domain: my-account-newsletters
	*/

	defined( 'ABSPATH' ) or die( 'Rechtstreekse toegang verboden!' );

	class Custom_My_Account_Endpoint {
		public $endpoint;

		public function __construct( $endpoint ) {
			$this->endpoint = $endpoint;
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
			add_filter( 'the_title', array( $this, 'endpoint_title' ) );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_'.$this->endpoint.'_endpoint', array( $this, 'endpoint_content' ) );
		}

		public function add_endpoints() {
			add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
		}

		public function add_query_vars( $vars ) {
			$vars[] = $this->endpoint;
			return $vars;
		}

		public function endpoint_title( $title ) {
			global $wp_query;
			$is_endpoint = isset( $wp_query->query_vars[$this->endpoint] );
			if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
				if ( $this->endpoint === 'nieuwsbrief' ) {
					$title = 'Beheer je abonnement op ons Digizine';
				} else {
					$title = 'Ook deze site is future proof!';
				}
				remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
			}
			return $title;
		}

		public function new_menu_items( $items ) {
			// Want uitlogknop staat al bovenaan
			unset( $items['customer-logout'] );
			// Schakel ondertussen ook dashboard en downloads uit
			unset( $items['dashboard'] );
			unset( $items['downloads'] );
			$items['nieuwsbrief'] = 'Digizine';
			$items['customer-logout'] = $logout;
			return $items;
		}

		public function endpoint_content() {
			if ( $this->endpoint === 'nieuwsbrief' ) {
				echo get_latest_newsletters();
				echo get_mailchimp_status();
			} else {
				echo 'In de toekomst leuke andere dingen?';
			}
		}

		public static function install() {
			add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
	}

	new Custom_My_Account_Endpoint( 'nieuwsbrief' );
	
	register_activation_hook( __FILE__, array( 'Custom_My_Account_Endpoint', 'install' ) );
	register_deactivation_hook( __FILE__, array( 'Custom_My_Account_Endpoint', 'install' ) );
?>