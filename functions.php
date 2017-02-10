<?php

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
		
	// Belangrijk voor correcte vertalingen in strftime()
	setlocale( LC_ALL, array('Dutch_Netherlands', 'Dutch', 'nl_NL', 'nl', 'nl_NL.ISO8859-1') );

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

	function theme_enqueue_styles() {
	    wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
	}

	
	############
	# SECURITY #
	############

	// Verstop enkele adminlinks voor de shopmanagers
	add_action( 'admin_menu', 'my_remove_menu_pages', 100, 0 );

	function my_remove_menu_pages() {
    	if ( ! current_user_can( 'create_sites' ) ) {
    		remove_menu_page( 'pmxi-admin-home' );
    	}
	}

	// Haal de pagina's niet enkel uit het menu, maak ze ook effectief ontoegankelijk
	add_action( 'current_screen', 'restrict_menus' );

	function restrict_menus() {
		write_log($author);
		$screen = get_current_screen();
		if ( ! current_user_can( 'create_sites' ) ) {
			$forbidden_strings = array(
				'pmxi',
			);
		    foreach ( $forbidden_strings as $forbidden ) {
		    	if ( strpos( $screen->base, $forbidden ) !== false ) {
		    		wp_die( 'Uit veiligheidsoverwegingen is deze geavanceerde beheerpagina niet toegankelijk voor lokale winkelbeheerders. Ben je er toch van overtuigd dat je deze functionaliteit nodig hebt? Leg je case voor extra rechten aan ons voor via <a href="mailto:'.get_option( 'admin_email' ).'">'.get_option( 'admin_email' ).'</a>!' );
		    	}
		    }
		}
	}

	// Zorg ervoor dat ook bij producten revisies opgeslagen worden
	add_filter( 'woocommerce_register_post_type_product', 'add_product_revisions' );

	function add_product_revisions( $args ) {
		$args['supports'][] = 'revisions';
		return $args;
	}

	// POSTMETA KAN WELLICHT BEST OPGEVOLGD WORDEN IN EEN LOG A LA VOORRAAD BIJ CRAFTS

	
	###############
	# WOOCOMMERCE #
	###############

	// Verhoog het aantal producten per winkelpagina
	add_filter( 'loop_shop_per_page', create_function( '$cols', 'return 20;' ), 20 );

	// Registreer de extra status voor WooCommerce-orders
	add_action( 'init', 'register_claimed_by_member_order_status' );
	
	function register_claimed_by_member_order_status() {
	    register_post_status( 'wc-claimed',
	    	array(
		        'label' => 'Geclaimd door winkel',
		        'public' => true,
		        'internal' => true,
		        'private' => false,
		        'exclude_from_search' => false,
		        'show_in_admin_all_list' => true,
		        'show_in_admin_status_list' => true,
		        'label_count' => _n_noop( 'Geclaimd door winkel <span class="count">(%s)</span>', 'Geclaimd door winkel <span class="count">(%s)</span>' )
	    	)
	    );
	}

	// Zorg ervoor dat we betaalde orders makkelijker kunnen bewerken GAAN WE DIT DOEN?
	add_filter( 'wc_order_is_editable', 'wc_order_is_editable', 20, 2 );

	function wc_order_is_editable( $editable, $order ) {
		// Slugs van alle extra orderstatussen (zonder 'wc'-prefix) die bewerkbaar moeten zijn
		// Opmerking: 'pending', 'on-hold' en 'auto-draft' zijn sowieso al bewerkbaar
		$editable_custom_statuses = array( 'claimed' );
		if ( in_array( $order->get_status(), $editable_custom_statuses ) ) {
			$editable = true;
		}
		return $editable;
	}
	
	//	Voeg de nieuwe status toe aan alle arrays
	add_filter( 'wc_order_statuses', 'add_claimed_by_member' );

	function add_claimed_by_member( $order_statuses ) {
	    $new_order_statuses = array();
	    foreach ( $order_statuses as $key => $status ) {
	        $new_order_statuses[ $key ] = $status;
	        // Plaats de status net na 'processing' (= order betaald en ontvangen)
	        if ( 'wc-processing' === $key ) {    
	            $new_order_statuses['wc-awaiting-shipment'] = 'Geclaimd door winkel';
	        }
	    }
	    return $new_order_statuses;
	}


	############
	# SETTINGS #
	############

	// Voeg optievelden toe
	add_action( 'admin_init', 'register_oxfam_settings' );

	function register_oxfam_settings() {
		register_setting( 'oxfam-option-group', 'mollie_partner_id', 'absint' );
		// add_settings_section( 'mollie_partner_id', 'Partner-ID bij Mollie', 'eg_setting_section_callback_function', 'options-oxfam' );
		add_settings_field( 'mollie_partner_id', 'Partner-ID bij Mollie', 'oxfam_setting_callback_function', 'options-oxfam', 'default', array( 'label_for' => 'mollie_partner_id' ) );
	}

	function oxfam_setting_callback_function( $arg ) {
		echo '<p>id: ' . $arg['id'] . '</p>';
		echo '<p>title: ' . $arg['title'] . '</p>';
		echo '<p>callback: ' . $arg['callback'] . '</p>';
	}

	// Voeg een custom pagina toe onder de algemene opties
	add_action( 'admin_menu', 'custom_oxfam_options' );

	function custom_oxfam_options() {
		add_options_page( 'Instellingen voor lokale webshop', 'Oxfam Fair Trade', 'shop_manager', 'options-oxfam.php', 'options_oxfam' );
	}

	// Output voor de optiepagina
	function options_oxfam() {
		?>
			<div class="wrap">
				<h1>Instellingen voor lokale webshop</h1>
				<form method="post" action="options.php"> 
			<?php
				settings_fields( 'oxfam-option-group' );
				do_settings_sections( 'oxfam-option-group' );
			?>
				<table class="form-table">
        		<tr valign="top">
        			<th scope="row">Test</th>
      	  			<td><input type="text" name="mollie_partner_id" value="<?php echo esc_attr( get_option('partner_id_customer') ); ?>" /></td>
        		</tr>
        	<?php
				submit_button();

				Mollie_Autoloader::register();
				$partner_id = 2485891;
				$profile_key = 'C556F53A';

				$mollie = new Mollie_Reseller( $partner_id, $profile_key, MOLLIE_APIKEY );
				$partner_id_customer = '2842281';

				$simplexml = $mollie->getLoginLink( $partner_id_customer );
				echo "<p><a href='".$simplexml->redirect_url."' target='_blank'>Ga zonder wachtwoord naar je Mollie-betaalaccount!</a> Opgelet: deze link is slechts tijdelijk geldig. Herlaad desnoods even deze pagina.</p>";
			?>
				</form>
			</div>
		<?php
	}

	// Creëer een custom hiërarchische product taxonomie om partner/landinfo in op te slaan 
	add_action( 'init', 'register_partner_taxonomy', 0 );

	function register_partner_taxonomy() {
		$taxonomy_name = 'product_part';
		
		$labels = array(
			'name' => 'Partners',
			'singular_name' => 'Partner',
			'all_items' => 'Alle partners',
			'parent_item' => 'Land',
			'parent_item_colon' => 'Land:',
			'new_item_name' => 'Nieuwe partner',
			'add_new_item' => 'Voeg nieuwe partner toe',
		);

		$args = array(
			'labels' => $labels,
			'rewrite' => array( 'slug' => 'partner' ),
			'hierarchical' => true,
			'public' => true,
			'show_ui' => true,
			'show_in_quick_edit' => true,
			'show_admin_column' => true,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'query_var' => true,
		);

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );
	}


	#############
	# MULTISITE #
	#############

	// NIET NODIG, EN AANGEZIEN WE PROBLEMEN BIJVEN HEBBEN MET DE KOPPELING VAN DE FOTO'S ZULLEN WE GEWOON VIA BULKBEWERKING DE PUBLISH NAAR CHILDS UITLOKKEN
	// add_action( 'pmxi_saved_post', 'resave_for_multistore', 10, 1 );

	function resave_for_multistore( $post_id ) {
		wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => '16u00' ) );
		// switch_to_blog( 2 );
		// process_product( $post_id, get_post( $post_id ) );
		// restore_current_blog();
	}

	// Zorg ervoor dat we niet met maandfolders werken
	// add_action( 'wpmu_new_blog', function( $blog_id ) {
	// 	switch_to_blog( $blog_id );
	// 	update_option('uploads_use_yearmonth_folders', false);
	// 	restore_current_blog();
	// });


	################
	# COMMUNICATIE #
	################

	// Voeg een custom dashboard widget toe met nieuws over het pilootproject
	add_action( 'wp_dashboard_setup', 'add_pilot_widget' );

	function add_pilot_widget() {
		global $wp_meta_boxes;

		wp_add_dashboard_widget(
			'dashboard_pilot_news_widget',
			'Nieuws over het pilootproject',
			'dashboard_pilot_news_widget_function'
		);

		$dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

		$my_widget = array( 'dashboard_pilot_news_widget' => $dashboard['dashboard_pilot_news_widget'] );
	 	unset( $dashboard['dashboard_pilot_news_widget'] );

	 	$sorted_dashboard = array_merge( $my_widget, $dashboard );
	 	$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}
	
	// Stel de inhoud van de widget op
	function dashboard_pilot_news_widget_function() {
		echo "<div class='rss-widget'><p>Hier kunnen we o.a. de rechtstreekse links naar de mailings uit de 'Focusgroep Webshop'-map laten verschijnen. Of rechtstreeks linken naar onze FAQ. Dankzij Markdown gaat dat <a href='https://github.com/OxfamFairTrade/ob2c/wiki/FAQ#bestellingen' target='_blank'>heel makkelijk</a>.</p></div>";
		echo '<div class="rss-widget"><ul>'.get_latest_mailings().'</ul></div>';
	}

	function get_latest_mailings() {
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '53ee397c8b';
		$folder_id = '2a64174067';

	    $args = array(
		 	'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY)
			)
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date( 'Y-m-d', strtotime('-6 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id, $args );
		
		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			
			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a class="rsswidget" href="'.$campaign->long_archive_url.'" target="_blank">'.$campaign->settings->subject_line.'</a> ('.strftime( '%e %B %G', strtotime($campaign->send_time) ).')</li>';
			}
		}		

		return $mailings;
	}

	// Voeg een bericht toe bovenaan alle adminpagina's
	add_action( 'admin_notices', 'sample_admin_notice' );

	function sample_admin_notice() {
        global $pagenow, $current_user;
	    if ( $pagenow === 'index.php' and current_user_can( 'manage_options' ) ) {
	    	if ( ! get_user_meta( $current_user->ID, 'bancontact_20170131' ) ) {
				?>
			    <div class="notice notice-info is-dismissible">
			        <p>Betalingen met Bancontact zijn tijdelijk onmogelijk! We werken aan een oplossing.</p>
			    </div>
			    <?php
			}
		}
	}

	// Schakel onnuttige widgets uit voor iedereen
	add_action( 'admin_init', 'remove_dashboard_meta' );

	function remove_dashboard_meta() {
		// remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		// remove_meta_box( 'dashboard_pilot_news_widget', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		// remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
    }


	#############
	# DEBUGGING #
	#############

	// Print variabelen op een overzichtelijke manier naar debug.log
	if ( ! function_exists( 'write_log' ) ) {
	    function write_log ( $log )  {
	        if ( true === WP_DEBUG ) {
	            if ( is_array( $log ) || is_object( $log ) ) {
	                error_log( print_r( $log, true ) );
	            } else {
	                error_log( $log );
	            }
	        }
	    }
	}
	
?>