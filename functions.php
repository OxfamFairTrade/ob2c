<?php

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
		
	// Belangrijk voor correcte vertalingen in strftime()
	setlocale( LC_ALL, array('Dutch_Netherlands', 'Dutch', 'nl_NL', 'nl', 'nl_NL.ISO8859-1') );

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

	function theme_enqueue_styles() {
	    wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
	    wp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ) );
	}
	
	############
	# SECURITY #
	############

	add_action( 'woocommerce_order_status_processing_to_claimed', 'register_transition_author' );

	function register_transition_author( $order_id ) {
		$current_user = wp_get_current_user();
		add_post_meta( $order_id, 'owner_of_order', $current_user->user_login, true );
	}

	// Poging om 'geclaimd door winkel' op auteur te filteren
	// add_filter( 'woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_order_total' );

	function woocommerce_shop_order_search_order_total( $search_fields ) {
		$search_fields[] = 'owner_of_order';
		return $search_fields;
	}

	// Creëer bovenaan de orderlijst een dropdown met de deelnemende winkels uit de regio
	// add_action( 'restrict_manage_posts', 'add_meta_value_to_orders' );

	function add_meta_value_to_orders() {
		global $pagenow, $post_type;
	    if( $pagenow === 'edit.php' and $post_type === 'shop_order' ) {
			$meta_values = array( 'oostende', 'brugge' );

			echo '<select name="source" id="source">';
				$all = ( ! empty($_GET['source']) and sanitize_text_field($_GET['source']) === 'all' ) ? ' selected' : '';
				echo '<option value="all" '.$all.'>Alle winkels</option>';

				foreach ( $meta_values as $meta_value ) {
					$selected = ( ! empty($_GET['source']) and sanitize_text_field($_GET['source']) === $meta_value ) ? ' selected' : '';
					echo '<option value="'.$meta_value.'" '.$selected.'>Enkel '.ucwords($meta_value).'</option>';
				}

			echo '</select>';
		}
	}

	// Activeer de metadata-filter tijdens het opzoeken van orders in de lijst
	// add_action( 'pre_get_posts', 'filter_orders_per_meta_value' );

	function filter_orders_per_meta_value( $query ) {
	    global $pagenow, $post_type;
	    $current_user = wp_get_current_user();
	    if ( $pagenow === 'edit.php' and $post_type === 'shop_order' and ! empty($_GET['post_status']) and $_GET['post_status'] === 'wc-claimed' ) {
	    	// VERSTOORT OM GOD WEET WELKE REDEN DE CUSTOM ORDER STATUS
	    	$meta_query_args = array(
	    		array(
	    			'key' => 'owner_of_order',
	    			'value' => $current_user->user_login,
	    			'compare' => '=',
	    		)
	    	);
	    	$query->set( 'meta_query', $meta_query_args );
		}
	}

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

	// Registreer de extra status voor WooCommerce-orders NU VIA PLUGIN
	// add_action( 'init', 'register_claimed_by_member_order_status' );
	
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

	// Zorg ervoor dat slechts bepaalde statussen bewerkbaar zijn
	add_filter( 'wc_order_is_editable', 'limit_editable_orders', 20, 2 );

	function limit_editable_orders( $editable, $order ) {
		// Slugs van alle extra orderstatussen (zonder 'wc'-prefix) die bewerkbaar moeten zijn
		// Opmerking: standaard zijn 'pending', 'on-hold' en 'auto-draft' bewerkbaar
		$editable_custom_statuses = array( 'on-hold' );
		if ( in_array( $order->get_status(), $editable_custom_statuses ) ) {
			$editable = true;
		} else {
			$editable = false;
		}
		return $editable;
	}
	
	// Speel met de volgorde van de statussen
	// add_filter( 'wc_order_statuses', 'rearrange_order_statuses' );

	function rearrange_order_statuses( $order_statuses ) {
	    $new_order_statuses = array();
	    foreach ( $order_statuses as $key => $status ) {
	        $new_order_statuses[ $key ] = $status;
	        // Plaats de status net na 'processing' (= order betaald en ontvangen)
	        if ( 'wc-processing' === $key ) {    
	            $new_order_statuses['wc-claimed'] = 'Geclaimd door mijn winkel';
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
	// THEMA GEBRUIKT GEEN INLINE UPDATES OP WINKELMANDPAGINA DUS VOORLOPIG UITSCHAKELEN
	// add_action( 'wp_footer', 'cart_update_qty_script' );
	
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

			/* Disable allergeenklasses */
			jQuery( '#in-product_allergen-615' ).prop( 'disabled', true );
			jQuery( '#in-product_allergen-616' ).prop( 'disabled', true );

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

	// Geef hint om B2B-klant te worden
	// add_action( 'woocommerce_before_checkout_form', 'action_woocommerce_before_checkout_form', 10, 1 );

	function action_woocommerce_before_checkout_form( $wccm_autocreate_account ) {
		wc_add_notice( 'Heb je een factuur nodig? Vraag de winkel om een B2B-account.', 'notice' );
    };
	
	// Schakel BTW-berekeningen op productniveau uit voor geverifieerde bedrijfsklanten MAG ENKEL VOOR BUITENLANDSE KLANTEN
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

	// Toon overschrijving indien B2B-klant
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

	// Print de geschatte leverdatums onder de beschikbare verzendmethodes 
	add_filter( 'woocommerce_cart_shipping_method_full_label', 'printEstimatedDelivery', 10, 2 );
	
	function printEstimatedDelivery( $label, $method ) {
		global $user_ID;
		$label = '&nbsp;'.$label;
		$label = str_replace( '(Gratis)', '', $label );
		$label .= '<br><small class="blauw">';
		// $timestamp = estimateDelivery( $user_ID, $method->id );
		$timestamp = strtotime('+4 days');
		
		switch ( $method->id ) {
			// Nummers achter method_id slaan op de (unieke) instance_id binnen DEZE subsite?
			// Alle instances van de 'Gratis afhaling in de winkel'-methode
			case stristr( $method->id, 'local_pickup' ):
				$timestamp = strtotime('+2 days');
				$label .= 'Vanaf '.strftime('%A %d/%m/%Y', $timestamp);
				break;
			// Alle instances van thuislevering
			case stristr( $method->id, 'flat_rate' ):
				$label .= 'Ten laatste op '.strftime('%A %d/%m/%Y', $timestamp);
				break;
			default:
				$label .= 'Geen schatting beschikbaar';
		}
		$label .= '</small>';
		return $label;
	}

	// Disable verzending met externe partijen indien totale brutogewicht > 30 kg
	add_filter( 'woocommerce_package_rates', 'hide_shipping_when_too_heavy', 10, 2 );
	
	function hide_shipping_when_too_heavy( $rates, $package ) {
		global $woocommerce;
		
		// DEZE CHECK DIENT TE GEBEUREN NA HET INVULLEN VAN DE VERZENDCALCULATOR OF BIJ EEN GEWIJZIGDE POSTCODE IN DE CHECKOUT
		$zip = intval( $woocommerce->customer->get_shipping_postcode() );
		if ( $zip >= 1000 and $zip < 8400 ) {
			wc_add_notice( __( 'Deze winkel doet geen thuisleveringen naar deze postcode. Keer terug naar '.network_site_url().'.', 'woocommerce' ), 'error' );
		}
		
		if ( $woocommerce->cart->cart_contents_weight > 30000 ) {
	  		unset( $rates['flat_rate:2'] );
	  		unset( $rates['flat_rate:4'] );
	  		// wc_add_notice( __( 'Je bestelling is te zwaar voor thuislevering.', 'woocommerce' ), 'error' );
	  	}

	  	return $rates;
	}


	// Check of de persoon moet worden ingeschreven op het digizine 
	add_action( 'woocommerce_after_checkout_validation', 'check_subscription_preference', 10, 1 );

	function check_subscription_preference( $posted ) {
		global $user_ID;
		$checkout = WC()->checkout;
		if ( ! empty($posted['subscribe_digizine']) ) {
			if ( $posted['subscribe_digizine'] !== 1 ) {
				// wc_add_notice( __( 'Oei, je hebt ervoor gekozen om je niet te abonneren op het digizine?', 'woocommerce' ), 'error' );
				wc_add_notice( __( 'Ik ben een blokkerende melding die verhindert dat je nu al afrekent, '.get_user_meta( $user_ID, 'first_name', true ).'.', 'woocommerce' ), 'error' );
			}
		}
	}

	// Verberg de 'kortingsbon invoeren'-boodschap bij het afrekenen NOPE, WERKT NIET
	// add_filter( 'woocommerce_coupon_message', 'remove_msg_filter', 10, 3 );

	function remove_msg_filter( $msg, $msg_code, $this ) {
		write_log("COUPON: ".$msg_code);
		if ( is_checkout() ) {
		    return "";
		}
		return $msg;
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
		$taxonomy_name = 'product_partner';
		
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
			'description' => 'Ken het product toe aan een partner/land',
			'public' => true,
			'publicly_queryable' => true,
			'hierarchical' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => true,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'show_in_quick_edit' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'capabilities' => array( 'manage_terms' => 'create_sites', 'edit_terms' => 'create_sites', 'delete_terms' => 'create_sites', 'assign_terms' => 'edit_products' ),
			'rewrite' => array( 'slug' => 'partner', 'with_front' => false, 'ep_mask' => 'test' ),
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

	// Creëer een custom hiërarchische taxonomie op producten om allergeneninfo in op te slaan
	add_action( 'init', 'register_allergen_taxonomy', 0 );

	function register_allergen_taxonomy() {
		$taxonomy_name = 'product_allergen';
		
		$labels = array(
			'name' => 'Allergenen',
			'singular_name' => 'Allergeen',
			'all_items' => 'Alle allergenen',
			'parent_item' => 'Allergeen',
			'parent_item_colon' => 'Allergeen:',
			'new_item_name' => 'Nieuw allergeen',
			'add_new_item' => 'Voeg nieuw allergeen toe',
		);

		$args = array(
			'labels' => $labels,
			'description' => 'Markeer dat het product dit allergeen bevat',
			'public' => true,
			'publicly_queryable' => true,
			'hierarchical' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => true,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'show_in_quick_edit' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'capabilities' => array( 'manage_terms' => 'create_sites', 'edit_terms' => 'create_sites', 'delete_terms' => 'create_sites', 'assign_terms' => 'edit_products' ),
			'rewrite' => array( 'slug' => 'allergen', 'with_front' => false, 'ep_mask' => 'test' ),
		);

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

	// Registreer een extra tabje op de productdetailpagina voor de voedingswaardes
	add_filter( 'woocommerce_product_tabs', 'add_energy_allergen_tab' );
	
	function add_energy_allergen_tab( $tabs ) {
		global $product;
		$tabs['additional_information']['title'] = 'Eigenschappen';
		$tabs['allergen_info'] = array(
			'title' 	=> 'Allergeneninfo',
			'priority' 	=> 75,
			'callback' 	=> 'allergen_tab_content',
		);
		$parts = explode( ' ', $product->get_attribute( 'pa_inhoud' ) );
		if ( $parts[1] === 'cl' ) {
			// Ofwel op basis van categorie: Wijn (hoofdcategorie) of +/- Spirits, Fruitsap, Sauzen en Olie & azijn (subcategorie)
			$suffix = 'ml';
		} else {
			$suffix = 'g';
		}
		$tabs['food_info'] = array(
			'title' 	=> 'Voedingswaarde per 100 '.$suffix,
			'priority' 	=> 100,
			'callback' 	=> 'food_tab_content',
		);
		return $tabs;
	}

	// Output de info voor de allergenen
	function allergen_tab_content() {
		global $product;
		echo '<div class="nm-additional-information-inner">';
			$has_row    = false;
			$alt        = 1;
			$allergens = get_the_terms( $product->get_id(), 'product_allergen' );
			$label_c = get_term_by( 'id', '615', 'product_allergen' )->name;
			$label_mc = get_term_by( 'id', '616', 'product_allergen' )->name;
			foreach ( $allergens as $allergen ) {
				if ( $allergen->parent === 615 ) {
					$contains[] = $allergen;
				} else {
					$traces[] = $allergen;
				}
			}
			?>
			<table class="shop_attributes">
				
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th><?php echo $label_c; ?></th>
					<td><?php
						$i = 0;
						$str = '/';
						if ( count( $contains ) > 0 ) {
							foreach ( $contains as $substance ) {
								$i++;
								if ( $i === 1 ) {
									$str = $substance->name;
								} else {
									$str .= ', '.$substance->name;
								}
							}
						}
						echo $str;
					?></td>
				</tr>

				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th><?php echo $label_mc; ?></th>
					<td><?php
						$i = 0;
						$str = '/';
						if ( count( $traces ) > 0 ) {
							foreach ( $traces as $substance ) {
								$i++;
								if ( $i === 1 ) {
									$str = $substance->name;
								} else {
									$str .= ', '.$substance->name;
								}
							}
						}
						echo $str;
					?></td>
				</tr>
				
			</table>
			<?php
		echo '</div>';
	}

	// Output de info voor de voedingswaarden
	function food_tab_content() {
		global $product;
		echo '<div class="nm-additional-information-inner">';
			$has_row    = false;
			$alt        = 1;
			$attributes = $product->get_attributes();

			ob_start();
			?>
			<table class="shop_attributes">

				<?php foreach ( $attributes as $attribute ) :
					// Logica omkeren: nu tonen we enkel de 'verborgen' attributen
					if ( empty( $attribute['is_visible'] ) ) {
						$has_row = true;
					} else {
						continue;
					}
					?>
					<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
						<th><?php
							$subattributes = array( 'pa_fapucis', 'pa_famscis', 'pa_fasat', 'pa_polyl', 'pa_starch', 'pa_sugar' );
							if ( in_array( $attribute['name'], $subattributes ) ) {
								echo '<i style="padding-left: 20px;">waarvan '.lcfirst( wc_attribute_label( $attribute['name'] ) ).'</i>';
							} else {
								echo wc_attribute_label( $attribute['name'] );
							}
						?></th>
						<td><?php
							$values = wc_get_product_terms( $product->get_id(), $attribute['name'], array( 'fields' => 'names' ) );
							if ( in_array( $attribute['name'], $subattributes ) ) {
								echo '<i>'.apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values ).'</i>';
							} else {
								echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );
							}
						?></td>
					</tr>
				<?php endforeach; ?>
				
			</table>
			<?php
			if ( $has_row ) {
				echo ob_get_clean();
			} else {
				ob_end_clean();
			}
		echo '</div>';
	}

	// Formatteer de gewichten in de attributen
	add_filter( 'woocommerce_attribute', 'add_weight_suffix', 10, 3 );

	function add_weight_suffix( $wpautop, $attribute, $values ) {
		$weighty_attributes = array( 'pa_choavl', 'pa_famscis', 'pa_fapucis', 'pa_fasat', 'pa_fat', 'pa_fibtg', 'pa_polyl', 'pa_pro', 'pa_salteq', 'pa_starch', 'pa_sugar' );
		$percenty_attributes = array( 'pa_alcohol', 'pa_fairtrade' );
		$energy_attributes = array( 'pa_ener' );

		// HOE BEPALEN WE MET WELKE PRODUCT-ID WE HIER BEZIG ZIJN?
		$product = wc_get_product( 1270 );
		$parts = explode( ' ', $product->get_attribute( 'pa_inhoud' ) );
		if ( $parts[1] === 'cl' ) {
			// Ofwel op basis van categorie: Wijn (hoofdcategorie) of +/- Spirits, Fruitsap, Sauzen en Olie & azijn (subcategorie)
			$suffix = 'liter';
		} else {
			$suffix = 'kilogram';
		}

		if ( in_array( $attribute['name'], $weighty_attributes ) ) {
			$values[0] = number_format( str_replace( ',', '.', $values[0] ), 1, ',', '.' ).' g';
		} elseif ( in_array( $attribute['name'], $percenty_attributes ) ) {
			$values[0] = number_format( str_replace( ',', '.', $values[0] ), 1, ',', '.' ).' %';
		} elseif ( in_array( $attribute['name'], $energy_attributes ) ) {
			$values[0] = number_format( $values[0], 0, ',', '.' ).' kJ';
		} elseif ( $attribute['name'] === 'pa_eenheidsprijs' ) {
			$values[0] = '&euro; '.number_format( str_replace( ',', '.', $values[0] ), 2, ',', '.' ).' per '.$suffix;
		} elseif ( $attribute['name'] === 'pa_ompak' ) {
			$values[0] = $values[0].' stuks';
		}

		$wpautop = wpautop( wptexturize( implode( ', ', $values ) ) );
		return $wpautop;
	}


	#############
	# MULTISITE #
	#############

	// AANGEZIEN WE PROBLEMEN HEBBEN OM BROADCASTING PROGRAMMATORISCH UIT TE LOKKEN BLIJVEN WE VOORLOPIG VIA BULKBEWERKING DE PUBLISH NAAR CHILDS TRIGGEREN
	// Stel de herkomst in op basis van de taxonomie product_partner
	add_action( 'pmxi_saved_post', 'update_calculated_attributes', 10, 1 );

	function set_product_source( $post_id ) {
		if ( get_post_type( $post_id ) === 'product' ) {
			// Hoofdtermen (= landen) ontdubbellen en alfabetisch ordenen!
			$source = 'TEST HERKOMST';
			$term_taxonomy_ids = wp_set_object_terms( $post_id, $source, 'pa_herkomst', true );
			$price = '99';
			$term_taxonomy_ids = wp_set_object_terms( $post_id, $price, 'pa_eenheidsprijs', true );
			$thedata = array(
				'pa_herkomst' 	=>		array(
											'name' => 'pa_herkomst',
											'value' => $source,
											'is_visible' => '1',
											'is_taxonomy' => '0',
										),
				'pa_eenheidsprijs'	=>	array(
											'name' => 'pa_eenheidsprijs',
											'value' => $price,
											'is_visible' => '1',
											'is_taxonomy' => '0',
										),
			);
     		update_post_meta( $post_id, '_product_attributes', $thedata );
		}
	}

	function update_calculated_attributes( $post_id ) {
		if ( get_post_type( $post_id ) === 'product' ) {
			write_log("HALLO");
			// Belangrijk: zorg ervoor dat '_product_attributes' alle attributen bevat in de juiste volgorde
			// De waarden zelf zitten allemaal als termen opgeslagen, maar om te verschijnen bij het product moeten ze wel 'aangekondigd' worden
			$attributes = wc_get_product( 1270 )->get_attributes();
			$product = wc_get_product( $post_id );
			$product->set_attributes( $attributes );

			// REPLACE ALL
			// WORDT AUTOMATISCH ALFABETISCHE GEORDEND EN ONTDUBBELD!

			$term_taxonomy_ids = wp_set_object_terms( $post_id, '88,88', 'pa_eenheidsprijs', false );
			$term_taxonomy_ids = wp_set_object_terms( $post_id, array('Frankrijk', 'België', 'India', 'België'), 'pa_herkomst', false );
		}
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
		echo "<div class='rss-widget'><p>Hier laten we o.a. de rechtstreekse links naar de mailings uit de 'Focusgroep Webshop'-map verschijnen. Rechtstreeks linken naar onze FAQ kan ook <a href='https://github.com/OxfamFairTrade/ob2c/wiki#bestellingen' target='_blank'>heel makkelijk</a>.</p></div>";
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
        global $pagenow, $post_type, $current_user;
	    if ( $pagenow === 'index.php' and current_user_can( 'edit_products' ) ) {
		    echo '<div class="notice notice-info">';
			    if ( get_option( 'mollie-payments-for-woocommerce_test_mode_enabled' ) === 'yes' ) {
			    	echo '<p>Alle betalingen op deze site zijn momenteel fake!</p>';
			    } else {
			    	echo '<p>Alle betalingen op deze site zijn momenteel live!</p>';
			    }
		    echo '</div>';
		} elseif ( $pagenow === 'edit.php' and $post_type === 'product' and current_user_can( 'edit_products' ) ) {
		    echo '<div class="notice notice-warning">';
			    echo '<p>Hou er rekening mee dat alle volumes in g / ml ingegeven worden, zonder eenheid!</p>';
		    echo '</div>';
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

    // Admin reports for custom order status
    add_filter( 'woocommerce_reports_get_order_report_data_args', 'wc_reports_get_order_custom_report_data_args', 100, 1 );

    function wc_reports_get_order_custom_report_data_args( $args ) {
    	$args['order_status'] = array( 'on-hold', 'processing', 'claimed', 'completed' );
    	return $args;
    };

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
		return "Open vanaf ???";
	}

	#############
	# DEBUGGING #
	#############

	// Handig filtertje om het JavaScript-conflict op de checkout te debuggen
	// add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );

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