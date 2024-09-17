<?php
	/*
	Plugin Name: OB2C Plugin Utilities
	Description: Schermt ontwikkelomgevingen af (behalve voor Mollie) en leidt mailcommunicatie naar klanten om naar webmaster. Is enkel actief indien WP_ENVIRONMENT_TYPE in wp-config.php niet op 'production' staat!
	Version:     0.3.0
	Author:      Full Stack Ahead
	Author URI:  https://www.fullstackahead.be
	Network:     true
	*/

	register_activation_hook( __FILE__, 'fsa_on_plugin_activate' );
	
	function fsa_on_plugin_activate() {
		if ( wp_get_environment_type() !== 'production' ) {
			$sites = get_sites( array( 'orderby' => 'path' ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				// Mollie in testmodus plaatsen
				update_option( 'mollie-payments-for-woocommerce_test_mode_enabled', 'yes' );
				// Indexering door zoekrobots ontraden
				update_option( 'blog_public', 0 );
				
				restore_current_blog();
			}
		}
	}
	
	// Online ontwikkelomgevingen beveiligen
	if ( wp_get_environment_type() === 'development' ) {
		// Afschermen met login (i.p.v. via IP-adres)
		add_action( 'template_redirect', 'fsa_force_user_login' );
		
		// Alle WordPress-mails afleiden naar admin
		// Hoeft niet bij lokale ontwikkelomgevingen dankzij Mailpit!
		add_filter( 'wp_mail', 'fsa_divert_and_flag_wordpress_mails' );
	}
	
	function fsa_force_user_login() {
		if ( defined('DOING_AJAX') and DOING_AJAX ) {
			return;
		}
		
		if ( defined('DOING_CRON') and DOING_CRON ) {
			return;
		}
		
		if ( defined('WP_CLI') and WP_CLI ) {
			return;
		}
		
		if ( is_user_logged_in() ) {
			return;
		}
		
		$mollie_ips = array(
			'23.251.137.244',
			'34.89.231.130',
			'34.90.137.245',
			'34.90.10.225',
			'35.204.34.167',
			'35.204.72.248',
			'35.246.254.59',
			'87.233.217.240',
			'87.233.217.241',
			'87.233.217.243',
			'87.233.217.244',
			'87.233.217.245',
			'87.233.217.246',
			'87.233.217.247',
			'87.233.217.248',
			'87.233.217.249',
			'87.233.217.250',
			'87.233.217.251',
			'87.233.217.253',
			'87.233.217.254',
			'87.233.217.255',
			'87.233.229.26',
			'87.233.229.27',
			'146.148.31.21',
		);
		if ( in_array( $_SERVER['REMOTE_ADDR'], $mollie_ips ) ) {
			// Lijkt niet nodig te zijn, fsa_force_user_login() wordt niet doorlopen bij webhooks?
			$logger = wc_get_logger();
			$logger->debug( "Access granted for Mollie IP address ".$_SERVER['REMOTE_ADDR'] );
			return;
		}
		
		$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		// Sta het bekijken van de inlogpagina wél toe, anders belanden we in een oneindige loop
		if ( preg_replace( '/\?.*/', '', wp_login_url() ) === preg_replace( '/\?.*/', '', $url ) ) {
			return;
		}
		
		// Stel headers in om caching te verhinderen
		nocache_headers();
		
		// Redirect verboden bezoekers
		wp_safe_redirect( wp_login_url( $url ), 302 );
		exit;
	}
	
	function fsa_divert_and_flag_wordpress_mails( $args ) {
		if ( array_key_exists( 'to', $args ) ) {
			if ( is_array( $args['to'] ) ) {
				$old_addresses = implode( ', ', $args['to'] );
			} else {
				$old_addresses = $args['to'];
			}
			error_log( "Diverting mail from " . $old_addresses . " to " . implode( ', ', fsa_get_email_addressees() ) );
		}
		
		$args['to'] = fsa_get_email_addressees();
		$args['subject'] = "TEST - " . $args['subject'] . " - NO ACTION REQUIRED";
		
		return $args;
	}
	
	add_action( 'admin_notices', 'fsa_show_admin_notice', 1000 );
	
	function fsa_show_admin_notice() {
		global $pagenow;
		$screen = get_current_screen();
		
		if ( 'index.php' === $pagenow and 'dashboard' === $screen->base ) {
			echo '<div class="notice notice-error">';
				echo '<p>Deze website staat in testmodus. Ze kan enkel bekeken worden door ingelogde admins en alle uitgaande e-mails worden afgeleid naar de admin mailbox (momenteel <i>'.implode( ', ', fsa_get_email_addressees() ).'</i>).</p>';
			echo '</div>';
		}
	}
	
	function fsa_get_email_addressees() {
		if ( is_multisite() ) {
			return array( get_site_option('admin_email') );
		} else {
			return array( get_option('admin_email') );
		}
	}
	
	// Instant search in de lange lijst substies
	// Zie https://github.com/trepmal/my-sites-search
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
		
		$script = <<<SCRIPT
		jQuery(document).ready( function($) {
			$('#wp-admin-bar-my-sites-search.hide-if-no-js').show();
			$('#wp-admin-bar-my-sites-search input').keyup( function( ) {
				var searchValRegex = new RegExp( $(this).val(), 'i');
				$('#wp-admin-bar-my-sites-list > li.menupop').hide().filter(function() {
					return searchValRegex.test( $(this).find('> a').text() );
				}).show();
			});
		});
		SCRIPT;
		
		wp_enqueue_script( 'admin-bar' );
		wp_add_inline_script( 'admin-bar', $script );
	}
	
	// Schakel nieuwe WooCommerce-features uit
	// @toDeprecate bij omschakelen naar WooCommerce Analytics
	add_filter( 'woocommerce_admin_disabled', '__return_true' );
	add_filter( 'woocommerce_marketing_menu_items', '__return_empty_array' );