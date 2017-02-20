<?php

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
		
	// Belangrijk voor correcte vertalingen in strftime()
	setlocale( LC_ALL, array('Dutch_Netherlands', 'Dutch', 'nl_NL', 'nl', 'nl_NL.ISO8859-1') );

	// Laad het child theme LIJKT WEINIG PRIORITEIT TE HEBBEN
	add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles', 1000 );

	function theme_enqueue_styles() {
	    wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
	}

	
	############
	# SECURITY #
	############

	// Voer shortcodes ook uit in widgets en titels
	add_filter( 'widget_text', 'do_shortcode' );
	add_filter( 'the_title', 'do_shortcode' );
	
	// Verstop enkele hardnekkige adminlinks voor de shopmanagers
	// Omgekeerd: WP All Export toelaten door rol aan te passen in wp-all-export-pro.php
	// Alle andere beperkingen via User Role Editor
	add_action( 'admin_menu', 'my_remove_menu_pages', 100, 0 );

	function my_remove_menu_pages() {
    	if ( ! current_user_can( 'update_core' ) ) {
    		remove_menu_page( 'vc-welcome' );
    		remove_submenu_page( 'woocommerce', 'wc-settings' );
    		remove_submenu_page( 'woocommerce', 'wc-status' );
    		remove_submenu_page( 'woocommerce', 'wc-addons' );
    	}
	}

	// Haal de hardnekkige pagina's niet enkel uit het menu, maak ze ook effectief ontoegankelijk
	add_action( 'current_screen', 'restrict_menus' );

	function restrict_menus() {
		$screen = get_current_screen();
		if ( ! current_user_can( 'create_sites' ) ) {
			$forbidden_strings = array(
				'vc-welcome',
				'wc-settings',
				'wc-status',
				'wc-addons',
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

	// Voeg sorteren op artikelnummer toe aan de opties op cataloguspagina's
	add_filter( 'woocommerce_get_catalog_ordering_args', 'add_sku_sorting' );

	function add_sku_sorting( $args ) {
		$orderby_value = isset( $_GET['orderby'] ) ? wc_clean( $_GET['orderby'] ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );

		if ( 'alpha' === $orderby_value ) {
			$args['orderby'] = 'title';
	    	$args['order'] = 'ASC';
		}

		if ( 'alpha-desc' === $orderby_value ) {
			$args['orderby'] = 'title';
	    	$args['order'] = 'DESC';
		}

		return $args;
	}
	
	add_filter( 'woocommerce_catalog_orderby', 'sku_sorting_orderby' );
	add_filter( 'woocommerce_default_catalog_orderby_options', 'sku_sorting_orderby' );

	function sku_sorting_orderby( $sortby ) {
		unset($sortby['menu_order']);
		unset($sortby['rating']);
		$sortby['popularity'] = 'Best verkocht';
		$sortby['date'] = 'Laatst toegevoegd';
		$sortby['alpha'] = 'Van A tot Z';
		$sortby['alpha-desc'] = 'Van Z tot A';
		$sortby['price'] = 'Stijgende prijs';
		$sortby['price-desc'] = 'Dalende prijs';
		// $sortby['sku'] = 'Stijgend artikelnummer';
		// $sortby['reverse_sku'] = 'Dalend artikelnummer';
		return $sortby;
	}

	// Herlaad winkelmandje automatisch na aanpassing en activeer live search (indien plugin geactiveerd)
	add_action( 'wp_footer', 'cart_update_qty_script' );
	
	function cart_update_qty_script() {
		if ( is_cart() ) :
		?>
			<script>
				var wto;
				jQuery( 'div.woocommerce' ).on( 'change', '.qty', function() {
					clearTimeout(wto);
					// Time-out net iets groter dan buffertijd zodat we bij ingedrukt houden van de spinner niet gewoon +1/-1 doen
					wto = setTimeout(function() {
						jQuery( "[name='update_cart']" ).trigger( 'click' );
					}, 500);

				});
			</script>
		<?php
		endif;
		?>
		<script>
			jQuery( '.site-header' ).find( '.search-field' ).attr( 'data-swplive', 'true' );
		</script>
		<?php
	}

	// Verhinder bepaalde selecties in de back-end
	add_action( 'admin_footer', 'disable_custom_checkboxes' );

	function disable_custom_checkboxes() {
		?>
		<script>
			/* Disable hoofdcategorieën */
			jQuery( '#in-product_cat-447' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-477' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-420' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-550' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-407' ).prop( 'disabled', true );

			/* Disable rode en witte druiven */
			jQuery( '#in-product_grape-575' ).prop( 'disabled', true );
			jQuery( '#in-product_grape-574' ).prop( 'disabled', true );
		</script>
		<?php
	}

	add_action( 'admin_init', 'hide_wine_taxonomies' );

	function hide_wine_taxonomies() {
		if ( isset($_GET['action']) and $_GET['action'] === 'edit' ) {
			$post_id = isset( $_GET['post'] ) ? $_GET['post'] : $_POST['post_ID'];
			$categories = get_the_terms( $post_id, 'product_cat' );
			if ( is_array( $categories ) ) {
				foreach ( $categories as $category ) {
					while ( $category->parent !== 0 ) {
						$parent = get_term( $category->parent, 'product_cat' );
						write_log($parent);
						$category = $parent;
					}
				}
			}
			if ( $parent->slug !== 'wijn' ) {
				remove_meta_box('product_grapediv', 'product', 'normal');
				remove_meta_box('product_recipediv', 'product', 'normal');
				remove_meta_box('product_tastediv', 'product', 'normal');
			}
		}
	}

	// Label en layout de factuurgegevens
    add_filter( 'woocommerce_billing_fields', 'format_checkout_billing', 10, 1 );
	
	function format_checkout_billing( $address_fields ) {
		$address_fields['billing_first_name']['label'] = "Voornaam";
		$address_fields['billing_first_name']['placeholder'] = "Jan";
		$address_fields['billing_last_name']['label'] = "Familienaam";
		$address_fields['billing_last_name']['placeholder'] = "Peeters";
		$address_fields['billing_phone']['label'] = "Telefoonnummer";
		$address_fields['billing_email']['label'] = "E-mailadres";
		$address_fields['billing_first_name']['class'] = array('form-row-first');
		$address_fields['billing_last_name']['class'] = array('form-row-last');
		$address_fields['billing_phone']['class'] = array('form-row-first');
		$address_fields['billing_phone']['clear'] = false;
		$address_fields['billing_email']['class'] = array('form-row-last');
		$address_fields['billing_email']['clear'] = true;
		$address_fields['billing_email']['required'] = true;
		
		$order = array(
        	"billing_first_name",
        	"billing_last_name",
        	"billing_address_1",
        	"billing_postcode",
        	"billing_city",
        	"billing_phone",
        	"billing_email",
    	);

    	foreach($order as $field) {
        	$ordered_fields[$field] = $address_fields[$field];
        }

        $address_fields = $ordered_fields;
	    
        return $address_fields;
    }

    // Verduidelijk de labels en layout
	add_filter( 'woocommerce_default_address_fields', 'make_addresses_readonly', 10, 1 );

    function make_addresses_readonly( $address_fields ) {
		$address_fields['address_1']['label'] = "Straat en nummer";
		$address_fields['address_1']['placeholder'] = '';
		$address_fields['address_1']['required'] = true;
		
		$address_fields['postcode']['label'] = "Postcode";
		$address_fields['postcode']['placeholder'] = '';
		$address_fields['postcode']['required'] = true;
		$address_fields['postcode']['class'] = array('form-row-first');
		$address_fields['postcode']['clear'] = false;

		$address_fields['city']['label'] = "Gemeente";
		// $address_fields['city']['type'] = 'select';
		// $address_fields['city']['options'] = array(
		// 	'' => '(selecteer)',
		// 	'1000' => 'Brussel',
		// 	'8400' => 'Oostende',
		// 	'8450' => 'Bredene',
		// );
		$address_fields['city']['required'] = true;
		$address_fields['city']['class'] = array('form-row-last');
		$address_fields['city']['clear'] = true;

		return $address_fields;
	}

	// Geef B2B-hint 
	// add_action( 'woocommerce_before_checkout_form', 'action_woocommerce_before_checkout_form', 10, 1 );

	function action_woocommerce_before_checkout_form( $wccm_autocreate_account ) {
		wc_add_notice( 'Heb je een factuur nodig? Vraag de winkel om een B2B-account.', 'notice' );
    };
	
	// Ship to a different address closed by default JAVASCRIPT CONFLICT?
	// add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );

	// Schakel BTW-berekeningen op productniveau uit voor geverifieerde bedrijfsklanten
	add_filter( 'woocommerce_product_get_tax_class', 'zero_rate_for_companies', 1, 2 );

	function zero_rate_for_companies( $tax_class, $product ) {
	    $current_user = wp_get_current_user();
	    if ( ! empty( get_user_meta( $current_user->ID, 'is_vat_exempt', true ) ) ) {
	    	$tax_class = 'vrijgesteld';
	    }
	    return $tax_class;
	}

	// Vervang de prijssuffix indien het om een ingelogde B2B-klant gaat
	add_filter( 'woocommerce_get_price_suffix', 'b2b_price_suffix', 10, 2 );
 
	function b2b_price_suffix( $suffix, $product ) {
	    $current_user = wp_get_current_user();
	    if ( ! empty( get_user_meta( $current_user->ID, 'is_vat_exempt', true ) ) ) {
	    	$suffix = str_replace( 'incl', 'excl', $suffix );
	    }
	    return $suffix;
	}

	// Toon overschrijving indien B2B
	add_filter( 'woocommerce_available_payment_gateways', 'b2b_restrict_to_bank_transfer' );

	function b2b_restrict_to_bank_transfer( $gateways ) {
		$current_user = wp_get_current_user();
	    if ( ! empty( get_user_meta( $current_user->ID, 'is_vat_exempt', true ) ) ) {
			unset( $gateways['mollie_wc_gateway_mistercash'] );
			unset( $gateways['mollie_wc_gateway_creditcard'] );
			unset( $gateways['mollie_wc_gateway_kbc'] );
			unset( $gateways['mollie_wc_gateway_belfius'] );
		} else {
			unset( $gateways['mollie_wc_gateway_banktransfer'] );	
		}
		return $gateways;
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
				$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
				
				// Vervang door (niet bewerkbare) site_option!
				$partner_id_customer = '2842281';

				$simplexml = $mollie->getLoginLink( $partner_id_customer );
				echo "<p><a href='".$simplexml->redirect_url."' target='_blank'>Ga zonder wachtwoord naar je Mollie-betaalaccount!</a> Opgelet: deze link is slechts tijdelijk geldig. Herlaad desnoods even deze pagina.</p>";

				// Query alle gepubliceerde producten en stel voorraadstatus + uitlichting in
				// Ordenen op artikelnummer, nieuwe producten van de afgelopen maand rood markeren?
			?>
				</form>
			</div>
		<?php
	}

	// Creëer een custom hiërarchische taxonomie op producten om partner/landinfo in op te slaan
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
			'rewrite' => array( 'slug' => 'partner', 'with_front' => false, 'ep_mask' => 'test' ),
			'hierarchical' => true,
			'public' => true,
			'show_ui' => true,
			'show_in_quick_edit' => true,
			'show_in_nav_menus' => true,
			'show_admin_column' => true,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'query_var' => true,
		);

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );
	}

	// Creëer drie custom hiërarchische taxonomieën op producten om wijninfo in op te slaan
	add_action( 'init', 'register_wine_taxonomy', 0 );
	
	function register_wine_taxonomy() {
		$name = 'druif';
		$taxonomy_name = 'product_grape';
		
		$labels = array(
			'name' => 'Druiven',
			'singular_name' => 'Druif',
			'all_items' => 'Alle druivensoorten',
			'parent_item' => 'Druif',
			'parent_item_colon' => 'Druif:',
			'new_item_name' => 'Nieuwe druivensoort',
			'add_new_item' => 'Voeg nieuwe druivensoort toe',
		);

		$args = array(
			'labels' => $labels,
			'description' => 'Voeg de wijn toe aan een '.$name.' in de wijnkiezer',
			'public' => false,
			'publicly_queryable' => true,
			'hierarchical' => true,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_nav_menus' => false,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'show_in_quick_edit' => false,
			'show_admin_column' => false,
			'capabilities' => array( 'manage_terms' => 'create_sites', 'edit_terms' => 'create_sites', 'delete_terms' => 'create_sites', 'assign_terms' => 'edit_products' ),
			// In de praktijk niet bereikbaar op deze URL want niet publiek!
			'rewrite' => array( 'slug' => $name, 'with_front' => false, 'hierarchical' => false ),
		);

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );

		unset( $labels );
		$name = 'gerecht';
		$taxonomy_name = 'product_recipe';
		
		$labels = array(
			'name' => 'Gerechten',
			'singular_name' => 'Gerecht',
			'all_items' => 'Alle gerechten',
			'parent_item' => 'Gerecht',
			'parent_item_colon' => 'Gerecht:',
			'new_item_name' => 'Nieuw gerecht',
			'add_new_item' => 'Voeg nieuw gerecht toe',
		);

		$args['labels'] = $labels;
		$args['description'] = 'Voeg de wijn toe aan een '.$name.' in de wijnkiezer';
		$args['rewrite']['slug'] = $name;

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );

		unset( $labels );
		$name = 'smaak';
		$taxonomy_name = 'product_taste';
		
		$labels = array(
			'name' => 'Smaken',
			'singular_name' => 'Smaak',
			'all_items' => 'Alle smaken',
			'parent_item' => 'Smaak',
			'parent_item_colon' => 'Smaak:',
			'new_item_name' => 'Nieuwe smaak',
			'add_new_item' => 'Voeg nieuwe smaak toe',
		);

		$args['labels'] = $labels;
		$args['description'] = 'Voeg de wijn toe aan een '.$name.' in de wijnkiezer';
		$args['rewrite']['slug'] = $name;

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );
	}

	// Vermijd dat geselecteerde termen in hiërarchische taxonomieën naar boven springen
	add_filter( 'wp_terms_checklist_args', 'do_not_jump_to_top', 10, 2 );

	function do_not_jump_to_top( $args, $post_id ) {
		if ( is_admin() ) {
			$args['checked_ontop'] = false;
		}
		return $args;
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

    function getLatestNewsletters() {
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '5cce3040aa';
		$folder_id = 'bbc1d65c43';

	    $args = array(
		 	'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY)
			)
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date( 'Y-m-d', strtotime('-3 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id, $args );
		
		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			$mailings .= "<p>Dit zijn de nieuwsbrieven van de afgelopen drie maanden:</p><ul>";

			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a href="'.$campaign->long_archive_url.'" target="_blank">'.$campaign->settings->subject_line.'</a> ('.trim( strftime( '%e %B %G', strtotime($campaign->send_time) ) ).')</li>';
			}

			$mailings .= "</ul>";
		}		

		return $mailings;
	}

	function getMailChimpStatus() {
		$cur_user = wp_get_current_user();
	    $server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '5cce3040aa';
		$email = $cur_user->user_email;
		$member = md5(strtolower($email));
		
	    $args = array(
		 	'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY)
			)
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/lists/'.$list_id.'/members/'.$member, $args );
		 
		$msg = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);

			if ( $body->status === "subscribed" ) {
				$msg .= "is ".$status." geabonneerd op het digizine. ".$actie;
			} else {
				$msg .= "is niet langer geabonneerd op het digizine. <a href='http://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$cur_user->user_firstname."&LNAME=".$cur_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>Vul het formulier in</a> om je weer te abonneren.";
			}
		} else {
			$msg .= "was nog nooit ingeschreven op het digzine. <a href='http://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$cur_user->user_firstname."&LNAME=".$cur_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>Vul het formulier in</a> om je te abonneren.";
		}

		return "<p>Het e-mailadres van de accounteigenaar (<a href='mailto:".$email."' target='_blank'>".$email."</a>) ".$msg."</p>";
	}


	##############
	# SHORTCODES #
	##############

	// Personaliseer de begroeting op de startpagina
	add_shortcode ( 'topbar', 'print_welcome' );
	add_shortcode ( 'bezoeker', 'print_customer' );
	add_shortcode ( 'winkelnaam', 'print_business' );
	add_shortcode ( 'copyright', 'print_copyright' );
	add_shortcode ( 'openingsuren', 'print_office_hours' );

	function print_welcome() {
		return "Welkom ".print_customer()."! Lekker weertje, niet?";
	}

	function print_customer() {
		global $current_user;
		return ( is_user_logged_in() and strlen($current_user->user_firstname) > 1 ) ? $current_user->user_firstname : "bezoeker";
	}

	function print_business() {
		return get_bloginfo('name');
	}

	function print_copyright() {
		if ( get_option('oxfam_shop_node') ) {
			$node = 'node/'.get_option('oxfam_shop_node');
		} else {
			$node = 'nl';
		}
		return "<a href='https://www.oxfamwereldwinkels.be/".$node."' target='_blank'>".print_business()." &copy; 2016-".date('Y')."</a>";
	}

	function print_office_hours() {
		$node = get_option( 'oxfam_shop_node' );
		
		$msg = shell_exec( "ssh -f -L 3307:127.0.0.1:3306 oxfam_ro@web4.xio.be" );  
		$msg .= shell_exec( "mysql -h 127.0.0.1 -P 3307 -u oxfam_ro" );  
		
		$mysqli = new mysqli( '127.0.0.1:3306', 'oxfam_ro', XIO_MYSQL, 'oxfamDb', 3306 );
		
		if ( $mysqli->connect_errno ) {
		    $msg .= "Failed to connect to MySQL: (".$mysqli->connect_errno.") ".$mysqli->connect_error;
		}
		$msg .= $mysqli->host_info;

		// $connection = ssh2_connect('web4.xio.be', 51234);
		// if ( ssh2_auth_password($connection, 'oxfam_ro', XIO_FTP) ) {
		// 	$msg = "Verbinding met OWW-site mislukt";
		// } else {
		// 	$tunnel = ssh2_tunnel($connection, '127.0.0.1', 3306);
		// 	// shell_exec("ssh -f -L 3307:127.0.0.1:3306 oxfam_ro@web4.xio.be sleep 60 >> logfile");
		// 	$db = mysqli_connect('127.0.0.1', 'oxfam_ro', XIO_MYSQL, 'oxfamDb', 3306);
		// 	$msg = var_dump($db);
		// 	$msg = "op te halen uit OWW-site (node ".$node.")";
		// }
		return $msg;
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