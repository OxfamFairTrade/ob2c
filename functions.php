<?php

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
	
	// Vuile truc om te verhinderen dat WordPress de afmeting van 'large'-afbeeldingen verkeerd weergeeft
	$content_width = 2000;

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
	
	// Verstop enkele hardnekkige adminlinks voor de lokale shopmanagers
	// Omgekeerd: WP All Export toelaten door rol aan te passen in wp-all-export-pro.php
	// Alle andere beperkingen via User Role Editor
	add_action( 'admin_menu', 'my_remove_menu_pages', 100, 0 );

	function my_remove_menu_pages() {
		if ( ! current_user_can( 'update_core' ) ) {
			remove_menu_page( 'vc-welcome' );
			remove_menu_page( 'order_delivery_date_lite' );
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

	// Zorg ervoor dat revisies ook bij producten bijgehouden worden op de hoofdsite
	// Log de post_meta op basis van de algemene update_post_metadata-filter (of beter door WC-functies te hacken?)
	if ( is_main_site( get_current_blog_id() ) ) {
		add_filter( 'woocommerce_register_post_type_product', 'add_product_revisions' );
		add_action( 'update_post_metadata', 'log_product_changes', 1, 4 );
	}
	
	function add_product_revisions( $args ) {
		$args['supports'][] = 'revisions';
		return $args;
	}

	function log_product_changes( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Check of er een belangwekkende wijzing was indien het om een stockupdate gaat
		if ( $meta_key === '_stock_status' ) {
			$new = $meta_value;
	    	// Vergelijk nieuwe waarde met de actuele
			$old = get_post_meta( $post_id, '_stock_status', true );
			
			if ( strcmp( $new, $old ) !== 0 ) {
				if ( $meta_value === 'instock' ) {
					$str = "IN STOCK";
				} elseif ( $meta_value === 'outofstock' ) {
					$str = "UIT STOCK";
				}
				
				// Schrijf weg in voorraadlog per weeknummer (zonder leading zero's) 
				$str = date( 'd/m/Y H:i:s' ) . "\t" . $str . "\t" . get_post_meta( $post_id, '_sku', true ) . "\t" . get_the_title( $post_id ) . "\n";
			    file_put_contents(WP_CONTENT_DIR."/stock-week-".intval( date('W') ).".csv", $str, FILE_APPEND);
			}
		}
		// Zet de normale postmeta-functie verder
		update_post_meta($meta_id, $post_id, $meta_key, $meta_value);
	}
	
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

	// Herlaad winkelmandje automatisch na aanpassing en zorg dat postcode altijd gecheckt wordt (en activeer live search indien plugin geactiveerd)
	// THEMA GEBRUIKT HELAAS GEEN INLINE UPDATES!
	add_action( 'wp_footer', 'cart_update_qty_script' );
	
	function cart_update_qty_script() {
		if ( is_cart() ) :
			global $woocommerce;
			validate_zip_code_for_shipping( intval( $woocommerce->customer->get_shipping_postcode() ) );
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

			/* Disable continenten */
			jQuery( '#in-product_partner-828' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-829' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-830' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-831' ).prop( 'disabled', true );

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
		$address_fields['billing_phone']['placeholder'] = "059 32 49 59";
		$address_fields['billing_email']['label'] = "E-mailadres";
		$address_fields['billing_email']['placeholder'] = "jan@peeters.be";
		// $address_fields['billing_company']['label'] = "Bedrijf";
		// $address_fields['billing_company']['placeholder'] = "Oxfam Fair Trade cvba";
		// $address_fields['billing_address_2']['label'] = "BTW-nummer";
		// $address_fields['billing_address_2']['placeholder'] = "BE 0453.066.016";
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
        	// NODIG VOOR SERVICE POINT!
        	"billing_country",
        	"billing_phone",
        	"billing_email",
    	);

    	foreach($order as $field) {
        	$ordered_fields[$field] = $address_fields[$field];
        }

        $address_fields = $ordered_fields;
	    
        return $address_fields;
    }

    // Label en layout de factuurgegevens
	add_filter( 'woocommerce_shipping_fields', 'format_checkout_shipping', 10, 1 );
	
	function format_checkout_shipping( $address_fields ) {
		$address_fields['shipping_first_name']['label'] = "Voornaam";
		$address_fields['shipping_first_name']['placeholder'] = "Jan";
		$address_fields['shipping_last_name']['label'] = "Familienaam";
		$address_fields['shipping_last_name']['placeholder'] = "Peeters";
		$address_fields['shipping_first_name']['class'] = array('form-row-first');
		$address_fields['shipping_last_name']['class'] = array('form-row-last');
		
		$order = array(
        	"shipping_first_name",
        	"shipping_last_name",
        	"shipping_address_1",
        	"shipping_postcode",
        	"shipping_city",
        	// NODIG VOOR SERVICE POINT!
        	"shipping_country",
        );

    	foreach($order as $field) {
        	$ordered_fields[$field] = $address_fields[$field];
        }

        $address_fields = $ordered_fields;
	    
        return $address_fields;
    }

    // Verduidelijk de labels en layout
	add_filter( 'woocommerce_default_address_fields', 'format_addresses_frontend', 10, 1 );

	function format_addresses_frontend( $address_fields ) {
		$address_fields['address_1']['label'] = "Straat en huisnummer";
		$address_fields['address_1']['placeholder'] = '';
		$address_fields['address_1']['required'] = true;

		$address_fields['postcode']['label'] = "Postcode";
		$address_fields['postcode']['placeholder'] = '';
		$address_fields['postcode']['required'] = true;
		// Zorgt ervoor dat de totalen automatisch bijgewerkt worden na aanpassen
		// Werkt enkel indien de voorgaande verplichte velden niet-leeg zijn, zie maybe_update_checkout() in woocommerce/assets/js/frontend/checkout.js 
		$address_fields['postcode']['class'] = array('form-row-first update_totals_on_change');
		$address_fields['postcode']['clear'] = false;

		$address_fields['city']['label'] = "Gemeente";
		$address_fields['city']['required'] = true;
		$address_fields['city']['class'] = array('form-row-last');
		$address_fields['city']['clear'] = true;

		return $address_fields;
	}

	// Herschrijf bepaalde klantendata tijdens het afrekenen naar standaardformaten
	add_filter( 'woocommerce_process_checkout_field_billing_first_name', 'uppercase_words', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_last_name', 'uppercase_words', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_phone', 'format_telephone', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_first_name', 'uppercase_words', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_last_name', 'uppercase_words', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_city', 'format_city', 10, 1 );
	
	function uppercase_words( $value ) {
		return ucwords( trim($value) );
	}

	function format_tax( $value ) {
		$value = str_replace( 'BE', '', $value );
		return 'BE '.ucwords( trim($value) );
	}

	function format_account( $value ) {
		$value = str_replace( 'IBAN', '', $value );
		return ucwords( trim($value) );
	}

	function format_place( $value ) {
		return ucwords( trim($value) );
	}
	
	function format_zipcode( $value ) {
		return ucwords( trim($value) );
	}

	function format_city( $value ) {
		return ucwords( trim($value) );
	}
	
	function format_telephone( $value ) {
		// Wis alle spaties, leestekens en landcodes
		$temp_tel = preg_replace( '/\s+/', '', $value );
		$temp_tel = str_replace( '/', '', $temp_tel );
		$temp_tel = str_replace( '-', '', $temp_tel );
		$temp_tel = str_replace( '.', '', $temp_tel );
		$temp_tel = str_replace( '+32', '0', $temp_tel );
		$temp_tel = str_replace( '0032', '0', $temp_tel );
		
		// Formatteer vaste telefoonnummers
		if ( mb_strlen($temp_tel) === 9 ) {
			if ( intval($temp_tel[1]) === 2 or intval($temp_tel[1]) === 3 or intval($temp_tel[1]) === 4 or intval($temp_tel[1]) === 9 ) {
				$value = substr($temp_tel, 0, 2)."/".substr($temp_tel, 2, 3).".".substr($temp_tel, 5, 2).".".substr($temp_tel, 7, 2);
			} else {
				$value = substr($temp_tel, 0, 3)."/".substr($temp_tel, 3, 2).".".substr($temp_tel, 5, 2).".".substr($temp_tel, 7, 2);
			}
		}

		// Formatteer mobiele telefoonnummers
		if ( mb_strlen($temp_tel) === 10 ) {
			$value = substr($temp_tel, 0, 4)."/".substr($temp_tel, 4, 3).".".substr($temp_tel, 7, 3);
		}
		
		return $value;
	}

	// Verduidelijk de profiellabels in de back-end	
	add_filter( 'woocommerce_customer_meta_fields', 'modify_user_admin_fields', 10, 1 );

	function modify_user_admin_fields( $profile_fields ) {
		$profile_fields['billing']['title'] = 'Klantgegevens';
		$profile_fields['billing']['fields']['billing_first_name']['label'] = 'Voornaam';
		$profile_fields['billing']['fields']['billing_last_name']['label'] = 'Familienaam';
		$profile_fields['billing']['fields']['billing_address_1']['label'] = 'Straat en huisnummer';
		$profile_fields['billing']['fields']['billing_postcode']['label'] = 'Postcode';
		$profile_fields['billing']['fields']['billing_city']['label'] = 'Gemeente';
		$profile_fields['billing']['fields']['billing_phone']['label'] = 'Telefoonnummer';
		$profile_fields['billing']['fields']['billing_email']['label'] = 'Mail bestelcommunicatie naar';
		unset($profile_fields['billing']['fields']['billing_address_2']);
		unset($profile_fields['billing']['fields']['billing_company']);
		unset($profile_fields['billing']['fields']['billing_state']);
		
		$profile_fields['shipping']['title'] = 'Verzendgegevens';
		$profile_fields['shipping']['fields']['shipping_first_name']['label'] = 'Voornaam';
		$profile_fields['shipping']['fields']['shipping_last_name']['label'] = 'Familienaam';
		$profile_fields['shipping']['fields']['shipping_address_1']['label'] = 'Straat en huisnummer';
		$profile_fields['shipping']['fields']['shipping_postcode']['label'] = 'Postcode';
		$profile_fields['shipping']['fields']['shipping_city']['label'] = 'Gemeente';
		unset($profile_fields['shipping']['fields']['shipping_address_2']);
		unset($profile_fields['shipping']['fields']['shipping_company']);
		unset($profile_fields['shipping']['fields']['shipping_state']);

		$profile_fields['billing']['fields'] = array_swap_assoc('billing_city', 'billing_postcode', $profile_fields['billing']['fields']);
		$profile_fields['shipping']['fields'] = array_swap_assoc('shipping_city', 'shipping_postcode', $profile_fields['shipping']['fields']);
		
		return $profile_fields;
	}

	// Verberg bepaalde profielvelden (en niet verwijderen, want dat reset sommige waardes!)
	add_action( 'admin_footer-profile.php', 'hide_own_profile_fields' );
	add_action( 'admin_footer-user-edit.php', 'hide_others_profile_fields' );
	
	function hide_own_profile_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
		?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").hide();
				jQuery("tr.user-comment-shortcuts-wrap").hide();
				jQuery("tr.user-admin-bar-front-wrap").hide();
				jQuery("tr.user-language-wrap").hide();
				/* Zeker niét verwijderen -> breekt opslaan van pagina! */
				jQuery("tr.user-nickname-wrap").hide();
				jQuery("tr.user-url-wrap").hide();
				jQuery("h2:contains('Over jezelf')").next('.form-table').hide();
				jQuery("h2:contains('Over jezelf')").hide();
				jQuery("h2:contains('Over de gebruiker')").next('.form-table').hide();
				jQuery("h2:contains('Over de gebruiker')").hide();
			</script>
		<?php
		}
	}

	function hide_others_profile_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
		?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").hide();
				jQuery("tr.user-admin-color-wrap").hide();
				jQuery("tr.user-comment-shortcuts-wrap").hide();
				jQuery("tr.user-admin-bar-front-wrap").hide();
				jQuery("tr.user-language-wrap").hide();
				/* Kunnen we eventueel verbergen maar is ook bereikbaar via knop op overzichtsscherm dus wacht op User Role Editor Pro */
				jQuery("tr.user-role-wrap").show();
				/* Zeker niét verwijderen -> breekt opslaan van pagina! */
				jQuery("tr.user-nickname-wrap").hide();
				jQuery("tr.user-url-wrap").hide();
				jQuery("h2:contains('Over de gebruiker')").next('.form-table').hide();
				jQuery("h2:contains('Over de gebruiker')").hide();
				jQuery("h3:contains('Aanvullende rechten')").next('.form-table').hide();
				jQuery("h3:contains('Aanvullende rechten')").hide();
			</script>
		<?php
		}
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
		global $woocommerce;
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
		// $label = str_replace( '(Gratis)', '', $label );
		$label .= '<br><small>';
		// $timestamp = estimateDelivery( $user_ID, $method->id );
		$timestamp = strtotime('+4 days');
		
		switch ( $method->id ) {
			// Nummers achter method_id slaan op de (unieke) instance_id binnen DEZE subsite?
			// Alle instances van de 'Gratis afhaling in winkel'-methode
			case stristr( $method->id, 'local_pickup_plus' ):
				$timestamp = strtotime('+2 days');
				$label .= 'Beschikbaar vanaf '.strftime('%Amiddag %d/%m/%Y', $timestamp);
				break;
			// Alle instances van postpuntlevering
			case stristr( $method->id, 'service_point_shipping_method' ):
				$label .= 'Ten laatste geleverd op '.strftime('%A %d/%m/%Y', $timestamp);
				break;
			// Alle instances van thuislevering
			case stristr( $method->id, 'flat_rate' ):
				$label .= 'Ten laatste geleverd op '.strftime('%A %d/%m/%Y', $timestamp);
				break;
			// Alle instances van gratis thuislevering
			case stristr( $method->id, 'free_shipping' ):
				$label .= 'Ten laatste geleverd op '.strftime('%A %d/%m/%Y', $timestamp);
				break;
			default:
				$label .= __( 'Geen schatting beschikbaar', 'wc-oxfam' );
		}
		$label .= '</small>';
		return $label;
	}

	// Verberg het verzendadres ook bij een postpuntlevering
	add_filter( 'woocommerce_order_hide_shipping_address', 'hide_shipping_address_on_service_point', 10, 2 ); 
	
	function hide_shipping_address_on_service_point( $array, $instance ) {
		$array[] = 'service_point';
		return $array; 
	};

	function validate_zip_code( $zip ) {
		if ( does_home_delivery() and $zip !== 0 ) {
			if ( ! in_array( $zip, get_site_option( 'oxfam_flemish_zip_codes' ) ) ) {
				wc_add_notice( __( 'Dit is geen geldige Vlaamse postcode!', 'wc-oxfam' ), 'error' );
			}
		}
	}

	function validate_zip_code_for_shipping( $zip ) {
		if ( does_home_delivery() and $zip !== 0 ) {
			if ( ! in_array( $zip, get_site_option( 'oxfam_flemish_zip_codes' ) ) ) {
				wc_add_notice( __( 'We konden de verzendopties niet berekenen. Dit is geen geldige Vlaamse postcode!', 'wc-oxfam' ), 'error' );
			} elseif ( ! in_array( $zip, get_option( 'oxfam_zip_codes' ) ) ) {
				$str = date('d/m/Y H:i:s')."\t\t".get_home_url()."\t\tPostcode ingevuld waarvoor deze winkel geen verzending organiseert\n";
				file_put_contents("shipping_errors.csv", $str, FILE_APPEND);
				wc_add_notice( __( 'Deze winkel doet geen thuisleveringen naar deze postcode! Keer terug naar het hoofddomein om de juiste webshop te vinden.', 'wc-oxfam' ), 'error' );
			}
		}
	}
	
	// Disable sommige verzendmethoden onder bepaalde voorwaarden
	add_filter( 'woocommerce_package_rates', 'hide_shipping_methods', 10, 2 );
	
	function hide_shipping_methods( $rates, $package ) {
		global $woocommerce;
		validate_zip_code( intval( $woocommerce->customer->get_shipping_postcode() ) );
		
		// Verberg alle betalende methodes indien er een gratis levering beschikbaar is (= per definitie geen afhaling want Local Plus creëert geen methodes)
	  	if ( isset($rates['free_shipping:2']) or isset($rates['free_shipping:4']) or isset($rates['free_shipping:6']) ) {
	  		unset( $rates['flat_rate:1'] );
	  		unset( $rates['flat_rate:3'] );
	  		unset( $rates['flat_rate:5'] );
	  		unset( $rates['service_point_shipping_method:7'] );
	  	} else {
	  		unset( $rates['free_shipping:2'] );
	  		unset( $rates['free_shipping:4'] );
	  		unset( $rates['free_shipping:6'] );
	  		unset( $rates['service_point_shipping_method:8'] );
	  	}

		// Verhinder externe levermethodes indien totale brutogewicht > 30 kg
		if ( $woocommerce->cart->cart_contents_weight > 29000 ) {
	  		unset( $rates['flat_rate:1'] );
	  		unset( $rates['flat_rate:3'] );
	  		unset( $rates['flat_rate:5'] );
	  		unset( $rates['free_shipping:2'] );
	  		unset( $rates['free_shipping:4'] );
	  		unset( $rates['free_shipping:6'] );
	  		unset( $rates['service_point_shipping_method:7'] );
	  		unset( $rates['service_point_shipping_method:8'] );
	  		wc_add_notice( __( 'Je bestelling is te zwaar voor thuislevering.', 'wc-oxfam' ), 'error' );
	  	}

	  	return $rates;
	}

	// Zorg dat afhalingen in de winkel als standaard levermethode geselecteerd worden
	// Nodig omdat Local Pickup Plus geen verzendzones gebruikt maar alles overkoepelt
	// Documentatie in class-wc-shipping.php: "If not set, not available, or available methods have changed, set to the DEFAULT option"
	add_filter( 'woocommerce_shipping_chosen_method', 'set_pickup_as_default_shipping', 10 );

	function set_pickup_as_default_shipping( $method ) {
		$method = 'local_pickup_plus';
		return $method;
	}

	// Check of de persoon moet worden ingeschreven op het digizine 
	add_action( 'woocommerce_checkout_process', 'check_subscription_preference', 10, 1 );

	function check_subscription_preference( $posted ) {
		global $user_ID, $woocommerce;
		if ( ! empty($posted['subscribe_digizine']) ) {
			if ( $posted['subscribe_digizine'] !== 1 ) {
				// wc_add_notice( __( 'Oei, je hebt ervoor gekozen om je niet te abonneren op het digizine?', 'woocommerce' ), 'error' );
				wc_add_notice( __( 'Ik ben een blokkerende melding die verhindert dat je nu al afrekent, '.get_user_meta( $user_ID, 'first_name', true ).'.', 'woocommerce' ), 'error' );
			}
		}

		// Eventueel bestelminimum om te kunnen afrekenen
		if ( round( $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total, 2 ) < 10 ) {
	  		wc_add_notice( __( 'Online bestellingen van minder dan 10 euro kunnen we niet verwerken.', 'woocommerce' ), 'error' );
	  	}
	}

	// Verberg de 'kortingsbon invoeren'-boodschap bij het afrekenen WERKT NIET DUS VIA CSS GEDAAN
	// add_filter( 'woocommerce_coupon_message', 'remove_msg_filter', 10, 3 );

	function remove_msg_filter( $msg, $msg_code, $this ) {
		write_log("COUPON: ".$msg_code);
		if ( is_checkout() ) {
		    return "";
		}
		return $msg;
	}

	// Voeg bakken leeggoed enkel toe per 6 of 24 flessen
	add_filter( 'wc_force_sell_add_to_cart_product', 'check_plastic_empties_quantity', 10, 2 );

	function check_plastic_empties_quantity( $empties, $product_item ) {
		$empties_product = wc_get_product( $empties['id'] );
		switch ( $empties_product->get_sku() ) {
			case 'WLBS6M':
				$empties['quantity'] = floor( intval($product_item['quantity']) / 6 );
				break;
			case 'WLBS24M':
				$empties['quantity'] = floor( intval($product_item['quantity']) / 24 );
				break;
		}

		return $empties;
	}

	// Zorg ervoor dat het basisproduct toch gekocht kan worden als de bak hierboven nog niet toevoegd mag worden NIET ALS OPLOSSING GEBRUIKEN VOOR VOORRAADSTATUS LEEGGOED
	add_filter( 'wc_force_sell_disallow_no_stock', '__return_false' );
	
	// Check bij de bakken leeggoed of we al aan een volledige set van 6 of 24 flessen zitten 
	add_filter( 'wc_force_sell_update_quantity', 'update_plastic_empties_quantity', 10, 2 );

	function update_plastic_empties_quantity( $quantity, $empties_item ) {
		$product_item = WC()->cart->get_cart_item( $empties_item['forced_by'] );
		$empties_product = wc_get_product( $empties_item['product_id'] );
		switch ( $empties_product->get_sku() ) {
			case 'WLBS6M':
				return floor( intval($product_item['quantity']) / 6 );
			case 'WLBS24M':
				return floor( intval($product_item['quantity']) / 24 );
			// PROBLEEM: winkelmandje is forced sell 'vergeten' wanneer hij wél 6/24 flessen
			case 'WLFSG':
				if ( intval($product_item['quantity']) === 6 ) {
					// Zorg dat deze cart_item ook gelinkt is aan het product waaraan de fles al gelinkt was
					$args['forced_by'] = $empties_item['forced_by'];
					$result = WC()->cart->add_to_cart( wc_get_product_id_by_sku('WLBS6M'), 1, $empties_item['variation_id'], $empties_item['variation'], $args );
				}
				return $quantity;
			case 'WLFSK';
				if ( intval($product_item['quantity']) === 24 ) {
					// Zorg dat deze cart_item ook gelinkt is aan het product waaraan de fles al gelinkt was
					$args['forced_by'] = $empties_item['forced_by'];
					$result = WC()->cart->add_to_cart( wc_get_product_id_by_sku('WLBS24M'), 1, $empties_item['variation_id'], $empties_item['variation'], $args );
				}
			default:
				return $quantity;
		}
	}

	// Tel leeggoed niet mee bij aantal items in winkelmandje
	add_filter( 'woocommerce_cart_contents_count',  'so_28359520_cart_contents_count' );
	
	function so_28359520_cart_contents_count( $count ) {
		$cart = WC()->cart->get_cart();
		
		$subtract = 0;
		foreach ( $cart as $key => $value ) {
			if ( isset( $value['forced_by'] ) ) {
				$subtract += $value['quantity'];
				write_log($subtract);
			}
		}

		return $count - $subtract;
	}
	

	############
	# SETTINGS #
	############

	// Voeg optievelden toe
	add_action( 'admin_init', 'register_oxfam_settings' );

	function register_oxfam_settings() {
		// Zie https://developer.wordpress.org/reference/functions/register_setting/ voor meer
		register_setting( 'oxfam-option-group', 'oxfam_shop_node', 'absint' );
		register_setting( 'oxfam-option-group', 'oxfam_mollie_partner_id', 'absint' );
		// Probleem: wordt niet geserialiseerd opgeslagen in database
		// register_setting( 'oxfam-option-group', 'oxfam_zip_codes', 'array' );
		add_settings_field( 'oxfam_mollie_partner_id', 'Partner-ID bij Mollie', 'oxfam_setting_callback_function', 'options-oxfam', 'default', array( 'label_for' => 'oxfam_mollie_partner_id' ) );
	}

	function oxfam_setting_callback_function( $arg ) {
		echo '<p>id: ' . $arg['id'] . '</p>';
		echo '<p>title: ' . $arg['title'] . '</p>';
		echo '<p>callback: ' . $arg['callback'] . '</p>';
	}

	// Voeg een custom pagina toe onder de algemene opties
	add_action( 'admin_menu', 'custom_oxfam_options' );

	function custom_oxfam_options() {
		add_media_page( 'Productfoto\'s', 'Productfoto\'s', 'manage_options', 'oxfam-photos', 'oxfam_photos_callback' );
		add_menu_page( 'Instellingen voor lokale webshop', 'Instellingen', 'local_manager', 'oxfam-options', 'oxfam_options_callback', 'dashicons-visibility', '56' );
		add_submenu_page( 'woocommerce', 'Stel de voorraad in voor je lokale webshop', 'Voorraadbeheer', 'local_manager', 'oxfam-products', 'oxfam_products_callback' );
	}

	function oxfam_photos_callback() {
		include get_stylesheet_directory().'/register-bulk-images.php';
	}

	function oxfam_options_callback() {
		include get_stylesheet_directory().'/update-options.php';
	}

	function oxfam_products_callback() {
		include get_stylesheet_directory().'/update-stock.php';
	}
	
	// Registreer de AJAX-acties
	add_action( 'wp_ajax_oxfam_stock_action', 'oxfam_stock_action_callback' );
	add_action( 'wp_ajax_oxfam_photo_action', 'oxfam_photo_action_callback' );

	function oxfam_stock_action_callback() {
		echo save_local_product_details($_POST['id'], $_POST['meta'], $_POST['value']);
    	wp_die();
	}

	function save_local_product_details($id, $meta, $value) {			
    	$msg = "";
    	$product = wc_get_product($id);
		if ( $meta === 'stockstatus' ) {
			$product->set_stock_status($value);
			$msg .= "Voorraadstatus opgeslagen!";
		} elseif ( $meta === 'featured' ) {
			$product->set_featured($value);
			$msg .= "Uitlichting opgeslagen!";
		}
		// Retourneert product-ID on success?
		$product->save();
		return $msg;
	}

	function oxfam_photo_action_callback() {
		echo register_photo($_POST['name'], $_POST['timestamp'], $_POST['path']);
    	wp_die();
	}

	function wp_get_attachment_id_by_post_name($post_title) {
        $args = array(
            // We gaan ervan uit dat ons proces waterdicht is en er dus maar één foto met dezelfde titel kan bestaan
            'posts_per_page'	=> 1,
            'post_type'			=> 'attachment',
            // Moet er in principe bij, want anders wordt de default 'publish' gebruikt en die bestaat niet voor attachments!
            'post_status'		=> 'inherit',
            // De titel is steeds gelijk aan de bestandsnaam en beter dan de 'name' die uniek moet zijn en door WP automatisch voorzien wordt van volgnummers
            'title'				=> trim($post_title),
        );
        $attachments = new WP_Query($args);
        if ( $attachments->have_posts() ) {
        	$attachments->the_post();
        	$attachment_id = get_the_ID();
        	wp_reset_postdata();
        } else {
        	$attachment_id = false;
        }
        return $attachment_id;
    }

    function register_photo($filename, $filestamp, $filepath) {			
    	// Parse de fototitel
    	$filetitle = explode('.jpg', $filename);
	    $filetitle = $filetitle[0];
    	
    	// Check of er al een vorige versie bestaat
    	$updated = false;
    	$deleted = false;
    	$old_id= wp_get_attachment_id_by_post_name($filetitle);
		if ( $old_id ) {
			// Bewaar de post_parent van het originele attachment
			$product_id = wp_get_post_parent_id($old_id);
			// Check of de uploadlocatie op dit punt al ingegeven is!
			if ( $product_id ) $product = wc_get_product( $product_id );
			
			// Stel het originele bestand veilig
			rename($filepath, WP_CONTENT_DIR.'/uploads/temporary.jpg');
			// Verwijder de versie
			if ( wp_delete_attachment($old_id, true) ) {
				// Extra check op het succesvol verwijderen
				$deleted = true;
			}
			$updated = true;
			// Hernoem opnieuw zodat de links weer naar de juiste file wijzen 
			rename(WP_CONTENT_DIR.'/uploads/temporary.jpg', $filepath);
		}
		
		// Creëer de parameters voor de foto
		$wp_filetype = wp_check_filetype($filename, null);
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => $filetitle,
			'post_content' => '',
			'post_author' => get_current_user_id(),
			'post_status' => 'inherit',
		);

		// Probeer de foto in de mediabibliotheek te stoppen
		$msg = "";
		$attachment_id = wp_insert_attachment( $attachment, $filepath );
		if ( ! is_wp_error($attachment_id) ) {
			if ( isset($product) ) {
				// Voeg de nieuwe attachment-ID weer toe aan het oorspronkelijke product
				$product->set_image_id($attachment_id);
				$product->save();
				// Stel de uploadlocatie van de nieuwe afbeelding in op die van het origineel
				wp_update_post(
					array(
						'ID' => $attachment_id, 
						'post_parent' => $product_id,
					)
				);
			}

			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filepath );
			// Registreer ook de metadata en toon een succesboodschap
			wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			if ($updated) {
				$deleted = $deleted ? "verwijderd en opnieuw aangemaakt" : "bijgewerkt";
				$msg .= "<i>".$filename."</i> ".$deleted." in de mediabibliotheek om ".date('H:i:s')." ...";
			} else {
				$msg .= "<i>".$filename."</i> aangemaakt in de mediabibliotheek om ".date('H:i:s')." ...";
			}
			// Sla het uploadtijdstip van de laatste succesvolle registratie op (kan gebruikt worden als limiet voor nieuwe foto's!)
			update_option('laatste_registratie_timestamp', $filestamp);
			$registered = true;
		} else {
			// Geef een waarschuwing als de aanmaak mislukte
			$msg .= "Opgelet, er liep iets mis met <i>".$filename."</i>!";
		}

		return $msg;
	}

	// Toon een boodschap op de detailpagina indien het product niet thuisgeleverd wordt
	// Icoontje wordt toegevoegd op basis van CSS-klasse .product_shipping_class-fruitsap
	add_action( 'woocommerce_single_product_summary', 'show_delivery_warning', 200 );

	function show_delivery_warning() {
		echo "Opgelet: hier komt een boodschap indien het product niet thuisgeleverd wordt (of enkel per zes verkocht wordt, maar dat idee gaan we wellicht afvoeren).";
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
		// Titel wijzigen van standaardtab kan, maar prioriteit niet
		$tabs['additional_information']['title'] = 'Technisch';
		$tabs['partner_info'] = array(
			'title' 	=> 'Herkomst',
			'priority' 	=> 60,
			'callback' 	=> 'partner_tab_content',
		);
		$tabs['allergen_info'] = array(
			'title' 	=> 'Allergenen',
			'priority' 	=> 100,
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
			'priority' 	=> 80,
			'callback' 	=> 'food_tab_content',
		);
		return $tabs;
	}

	// Output de info voor de allergenen
	function allergen_tab_content() {
		global $product;
		echo '<div class="nm-additional-information-inner">';
			$has_row = false;
			$alt = 1;
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
					$forbidden = array( 'pa_ompak', 'pa_ompak_ean', 'pa_pal_perlaag', 'pa_pal_lagen' );
					// Logica omkeren: nu tonen we enkel de 'verborgen' attributen
					if ( empty( $attribute['is_visible'] ) and ! in_array( $attribute['name'], $forbidden ) ) {
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

	// Output de info over de partners
	function partner_tab_content() {
		global $product, $wpdb;
		echo '<div class="nm-additional-information-inner">';
			$alt = 1;
			$terms = get_the_terms( $product->get_id(), 'product_partner' );

			// ID's van de continenten (integers!)
			$continents = array( 828, 829, 830, 831 );
			foreach ( $terms as $term ) {
				// Check op een strikte manier of we niet met een land bezig zijn
				if ( ! in_array( $term->parent, $continents, true ) ) {
					$partners[] = $term;
					// Voeg het land van deze partner toe aan de landen
					$parent_term = get_term_by( 'id', $term->parent, 'product_partner' );
					// Opgelet, dit moet nog ontdubbeld en geordend worden!
					$countries[] = $parent_term->name;
				} else {
					// Kan geen continent zijn want die selectie wordt niet toegestaan en enkel de laagste taxonomie wordt toegekend
					$countries[] = $term->name;
				}
			}

			$countries = array_unique( $countries );
			asort($countries);
			ob_start();
			?>
			<table class="shop_attributes">
				
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th>Herkomstlanden</th>
					<td><?php
						$i = 0;
						$str = '/';
						if ( count( $countries ) > 0 ) {
							foreach ( $countries as $country ) {
								$i++;
								if ( $i === 1 ) {
									$str = $country;
								} else {
									$str .= '<br>'.$country;
								}
							}
						}
						echo $str;
					?></td>
				</tr>

				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th>Onze partners</th>
					<td><?php
						$i = 0;
						$msg = '<i>(aankoop via andere fairtradeorganisatie)</i>';
						if ( count( $partners ) > 0 ) {
							foreach ( $partners as $partner ) {
								$i++;
								// Let op: de naam is een string, dus er moeten quotes rond!
								$row = $wpdb->get_row( 'SELECT * FROM partners WHERE part_naam = "'.$partner->name.'"' );
								
								if ( $row ) {
									$url = 'https://www.oxfamwereldwinkels.be/'.trim($row->part_website);
								} else {
									// Val terug op de termbeschrijving die misschien toegevoegd is
									$url = explode('href="', $partner->description);
									$parts = explode('"', $url[1]);
									$url = $parts[0];
								}
								
								if ( strlen($url) > 10 ) {
									$text = '<a href="'.$url.'" target="_blank" title="Lees meer info over deze partner op de site van Oxfam-Wereldwinkels">'.$partner->name.'</a>';
								} else {
									$text = $partner->name;
								}
								if ( $i === 1 ) {
									$msg = $text;
								} else {
									$msg .= '<br>'.$text;
								}
							}
						}
						echo $msg;
					?></td>
				</tr>
				
			</table>
			<?php
			echo ob_get_clean();
		echo '</div>';
	}

	// Formatteer de gewichten in de attributen
	add_filter( 'woocommerce_attribute', 'add_weight_suffix', 10, 3 );

	function add_weight_suffix( $wpautop, $attribute, $values ) {
		$weighty_attributes = array( 'pa_choavl', 'pa_famscis', 'pa_fapucis', 'pa_fasat', 'pa_fat', 'pa_fibtg', 'pa_polyl', 'pa_pro', 'pa_salteq', 'pa_starch', 'pa_sugar' );
		$percenty_attributes = array( 'pa_alcohol', 'pa_fairtrade' );
		$energy_attributes = array( 'pa_ener' );

		// HOE BEPALEN WE MET WELKE PRODUCT-ID WE HIER BEZIG ZIJN? => GLOBAL WERKT
		global $product;
		$parts = explode( ' ', $product->get_attribute( 'pa_inhoud' ) );
		if ( $parts[1] === 'cl' ) {
			// Ofwel op basis van categorie: Wijn (hoofdcategorie) of +/- Spirits, Fruitsap, Sauzen en Olie & azijn (subcategorie)
			$suffix = 'liter';
		} else {
			$suffix = 'kilogram';
		}

		if ( in_array( $attribute['name'], $weighty_attributes ) ) {
			$values[0] = str_replace('.', ',', $values[0]).' g';
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

	// Doe leuke dingen na afloop van een WP All Import
	add_action('pmxi_after_xml_import', 'after_xml_import', 10, 1);
	
	function after_xml_import($import_id) {
		if ( $import_id == 2 ) {
			// Trash de hoofdproducten die de waarde 'naar_prullenmand' meekregen tijdens de import (moet lukken in één query zolang het om een paar 100 producten gaat)
			$args = array(
				'post_type'			=> 'product',
				// Want WP_Query vraagt buiten de adminomgeving (zoals een cron) normaal enkel gepubliceerde posts op!
				'post_status'		=> array( 'publish', 'draft' ),
				'posts_per_page'	=> -1,
				'meta_key'			=> 'naar_prullenmand', 
				'meta_value'		=> 'ja',
				'meta_compare'		=> '=',
			);

			$trashers = new WP_Query( $args );
			
			if ( $trashers->have_posts() ) {
				while ( $trashers->have_posts() ) {
					$trashers->the_post();
					// Normale producten verwijzen we eerst naar de prullenmand
					wp_trash_post( get_the_ID() );
					write_log( "ART. NR. ".get_post_meta( get_the_ID(), '_sku', true )." VERPLAATST NAAR PRULLENMAND" );
				}
				wp_reset_postdata();
			}

			// Verwijder de key 'naar_prullenmand' van producten die succesvol getrashed zijn, ook als dat al tijdens een eerdere import gebeurde!
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array( 'trash' ),
				'posts_per_page'	=> -1,
				'meta_key'			=> 'naar_prullenmand', 
				'meta_value'		=> 'ja',
				'meta_compare'		=> '=',
			);

			$trashed = new WP_Query( $args );

			if ( $trashed->have_posts() ) {
				while ( $trashed->have_posts() ) {
					$trashed->the_post();
					delete_post_meta( get_the_ID(), 'naar_prullenmand' );
				}
				wp_reset_postdata();
			}
		}
	}

	// AANGEZIEN WE PROBLEMEN HEBBEN OM BROADCASTING PROGRAMMATORISCH UIT TE LOKKEN BLIJVEN WE VOORLOPIG VIA BULKBEWERKING DE PUBLISH NAAR CHILDS TRIGGEREN
	// Stel de attributen in die berekend moeten worden uit andere waarden
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
			// Belangrijk: zorg ervoor dat '_product_attributes' alle attributen bevat in de juiste volgorde
			// De waarden zelf zitten allemaal als termen opgeslagen, maar om te verschijnen bij het product moeten ze wel 'aangekondigd' worden
			$product = wc_get_product( $post_id );
			$attributes = $product->get_attributes();
			// write_log($attributes);
			
			$price = floatval($product->get_price());
			$parts = explode( ' ', $product->get_attribute( 'pa_inhoud' ) );
			$content = intval($parts[0]);
			if ( $parts[1] === 'g' ) {
				$calc = $price / $content * 1000;
			} elseif ( $parts[1] === 'cl' ) {
				$calc = $price / $content * 100;
			} else {
				$calc = 0;
			}
			$string = number_format( $calc, 2, ',', '.' );
			$calc = round( $calc, 2 );
			
			// REPLACE ALL TERMS
			$term_taxonomy_ids = wp_set_object_terms( $post_id, $string, 'pa_eenheidsprijs', false );
			// WORDT AUTOMATISCH ALFABETISCHE GEORDEND EN ONTDUBBELD!
			// $term_taxonomy_ids = wp_set_object_terms( $post_id, array('Frankrijk', 'België', 'India', 'België'), 'pa_herkomst', false );
			// update_post_meta( $post_id, '_product_attributes', $thedata );

			$attributes[] = array(
				'pa_eenheidsprijs'	=>	array(
											'name' => 'pa_eenheidsprijs',
											'value' => $string,
											'is_visible' => '1',
											'is_taxonomy' => '1',
										),
			);
     		
     		$product->set_attributes( $attributes );
     		$product->save();
		}
	}

	// Zorg ervoor dat we niet met maandfolders werken
	// add_action( 'wpmu_new_blog', function( $blog_id ) {
	// 	switch_to_blog( $blog_id );
	// 	update_option('uploads_use_yearmonth_folders', false);
	// 	restore_current_blog();
	// });

	// Verhinder dat de lokale voorraad- en uitlichtingsinstellingen overschreven worden bij elke update VOOR DE ZEKERHEID INGESCHAKELD HOUDEN?
	add_filter( 'woo_mstore/save_meta_to_post/ignore_meta_fields', 'ignore_featured_and_stock', 10, 2);

	function ignore_featured_and_stock( $ignored_fields, $post_id ) {
		write_log("NEGEER POST META OP POST-ID ".$post_id);
		$ignored_fields[] = '_featured';
		$ignored_fields[] = '_visibility';
		return $ignored_fields;
	}


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
		echo "<div class='rss-widget'>";
		echo "<p>We bouwen momenteel aan <a href='https://github.com/OxfamFairTrade/ob2c/wiki' target='_blank'>een online FAQ voor webshopbeheerders</a> waarin alle mogelijke vragen en problemen beantwoord worden met screenshots. In afwachting kun je <a href='https://demo.oxfamwereldwinkels.be/wp-content/uploads/slides-1ste-opleiding-B2C-webshop.pdf' target='_blank'>de slides van de 1ste opleidingssessie</a> raadplegen.</p>";
		echo "<p>We herhalen nog eens dat de site nog in volle ontwikkeling is. Je zult dagelijks dingen verbeterd zien worden. De verlate release van <a href='https://wordpress.com/read/blogs/96396764/posts/3767' target='_blank'>WooCommerce 3.0</a> speelt ons gedeeltelijk parten maar dat belet niet dat de basisstructuur definitief is en lanceren in mei realistisch blijft.</p>";
		echo "</div>";
		echo '<div class="rss-widget"><ul>'.get_latest_mailings().'</ul></div>';
	}

	function get_latest_mailings() {
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '53ee397c8b';
		$folder_id = '2a64174067';

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date( 'Y-m-d', strtotime('-9 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );
		
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
		$screen = get_current_screen();
		if ( $pagenow === 'index.php' ) {
			echo '<div class="notice notice-info">';
			if ( get_option( 'mollie-payments-for-woocommerce_test_mode_enabled' ) === 'yes' ) {
				echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust bestellingen te plaatsen en te beheren.</p>';
			} else {
				echo '<p>Opgelet: de betalingen op deze site zijn momenteel live! Tip: betaal je bestelling achteraf volledig terug door een refund uit te voeren via het platform.</p>';
			}
			echo '</div>';
			echo '<div class="notice notice-info">';
			echo '<p>Download <a href="http://demo.oxfamwereldwinkels.be/wp-content/uploads/verzendtarieven-B2C-pakketten.pdf" target="_blank">de nota met tarieven en voorwaarden</a> bij externe verzending via Bpost of Bubble Post. Kun je met een lokale duurzame speler samenwerken? Des te beter! Bezorg ons vóór de 2de opleidingssessie een ruwe schatting van de kostprijs zodat we kunnen bekijken hoe we dit in de voorgestelde vergoeding van 5,74 euro excl. BTW voor thuislevering kunnen inpassen.</p>';
			echo '</div>';
		} elseif ( $pagenow === 'edit.php' and $post_type === 'product' and current_user_can( 'edit_products' ) ) {
			// echo '<div class="notice notice-warning">';
			// echo '<p>Hou er rekening mee dat alle volumes in g / ml ingegeven worden, zonder eenheid!</p>';
			// echo '</div>';
		}
		if ( $screen->base == 'woocommerce_page_oxfam-products' ) {
			echo '<div class="notice notice-info">';
			echo '<p>Wijzigingen opslaan op deze pagina is inmiddels mogelijk!</p>';
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
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date( 'Y-m-d', strtotime('-6 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );

		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			$mailings .= "<p>Dit zijn de nieuwsbrieven van de afgelopen zes maanden:</p><ul>";

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
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY),
			),
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

	add_filter( 'widget_text','do_shortcode' );

	// Personaliseer de begroeting op de startpagina
	add_shortcode ( 'topbar', 'print_welcome' );
	add_shortcode ( 'bezoeker', 'print_customer' );
	add_shortcode ( 'winkelnaam', 'print_business' );
	add_shortcode ( 'copyright', 'print_copyright' );
	add_shortcode ( 'openingsuren', 'print_office_hours' );
	add_shortcode ( 'toon_shops', 'print_shop_selection' );
	add_shortcode ( 'toon_kaart', 'print_map' );
	add_shortcode ( 'widget_usp', 'print_widget_usp' );
	add_shortcode ( 'widget_delivery', 'print_widget_delivery' );
	add_shortcode ( 'widget_contact', 'print_widget_contact' );
	add_shortcode ( 'contact_address', 'print_address' );
	add_shortcode ( 'map_address', 'print_map_address' );

	function get_oxfam_shop_data( $key ) {
		global $wpdb;
		if ( $key === 'tax' or $key === 'account' ) {
			$row = $wpdb->get_row( 'SELECT * FROM field_data_field_shop_'.$key.' WHERE entity_id = '.get_oxfam_shop_data( 'shop' ) );
			if ( $row ) {
				return call_user_func( 'format_'.$key, $row->{'field_shop_'.$key.'_value'} );
			} else {
				return "UNKNOWN";
			}
		} else {
			$row = $wpdb->get_row( 'SELECT * FROM field_data_field_sellpoint_'.$key.' WHERE entity_id = '.get_option( 'oxfam_shop_node' ) );
			if ( $row ) {
				if ( $key === 'shop' ) {
					return $row->field_sellpoint_shop_nid;
				} else {
					return call_user_func( 'format_'.$key, $row->{'field_sellpoint_'.$key.'_value'} );
				}
			} else {
				return "UNKNOWN";
			}
		}
	}

	function print_address() {
		global $wpdb;
		$street = get_oxfam_shop_data( 'place' );
		$zip = get_oxfam_shop_data( 'zipcode' );
		$city = get_oxfam_shop_data( 'city' );
		$phone = get_oxfam_shop_data( 'telephone' );
		return $street."<br>".$zip." ".$city."<br>".$phone."<br><a href='mailto:".get_option( 'admin_email' )."'>".get_option( 'admin_email' )."</a>";
	}

	function print_map_address() {
		global $wpdb;
		$street = get_oxfam_shop_data( 'place' );
		$zip = get_oxfam_shop_data( 'zipcode' );
		$city = get_oxfam_shop_data( 'city' );
		echo $street.", ".$zip." ".$city;
	}

	function print_widget_usp() {
		$msg = "Mooie Marcom-uitdaging: vat onze <i>unique selling points</i> samen in één catchy zin.";
		return $msg;
	}

	function print_widget_delivery() {
		if ( does_home_delivery() ) {
			$msg = "Alles wat je vóór 12 uur 's ochtends bestelt, wordt ten laatste drie werkdagen later bij je thuis geleverd. Afhalen in de winkel kan natuurlijk ook!";
		} else {
			$msg = "Alles wat je vóór 12 uur 's ochtends bestelt, kan je de volgende dag 's middags afhalen in de winkel.";
		}
		return $msg;
	}

	function print_widget_contact() {
		return "<a href='mailto:".get_option( 'admin_email' )."'>".get_option( 'admin_email' )."</a><br>".get_oxfam_shop_data( 'telephone' );
	}

	function print_welcome() {
		return "Dag ".print_customer()."! Ben je klaar om de wereld een klein beetje te veranderen?";
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

	function print_office_hours( $atts = [] ) {
		// normalize attribute keys, lowercase
		$atts = array_change_key_case( (array)$atts, CASE_LOWER );
		// override default attributes with user attributes
		$atts = shortcode_atts( array( 'node' => get_option( 'oxfam_shop_node' ) ), $atts );
		$node = $atts['node'];
		
		if ( ( $handle = fopen( WP_CONTENT_DIR."/office-hours.csv", "r" ) ) !== false ) {
			// Loop over alle rijen (indien de datafile geopend kon worden)
			$headers = fgetcsv( $handle, 0, "\t" );
			while ( ( $row2 = fgetcsv( $handle, 0, "\t" ) ) !== false ) {
				$row = array_combine($headers, $row2);
				if ( $row['entity_id'] == $node ) {
					if ( intval($row['field_sellpoint_office_hours_day']) === 1 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($monday) ) {
							$hours .= "<br>Maandag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$monday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					if ( intval($row['field_sellpoint_office_hours_day']) === 2 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($tuesday) ) {
							$hours .= "<br>Dinsdag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$tuesday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					if ( intval($row['field_sellpoint_office_hours_day']) === 3 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($wednesday) ) {
							$hours .= "<br>Woensdag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$wednesday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					if ( intval($row['field_sellpoint_office_hours_day']) === 4 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($thursday) ) {
							$hours .= "<br>Donderdag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$thursday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					if ( intval($row['field_sellpoint_office_hours_day']) === 5 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($friday) ) {
							$hours .= "<br>Vrijdag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$friday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					if ( intval($row['field_sellpoint_office_hours_day']) === 6 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($saturday) ) {
							$hours .= "<br>Zaterdag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$saturday = true;
						} else {
							$hours .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
					// OMDAT DE NUL ALS EERSTE STAAT IN ONZE HUIDIGE TEKSTFILE MOETEN WE EEN TRUCJE DOEN OM DEZE DAG ACHTERAAN TE ZETTEN
					if ( intval($row['field_sellpoint_office_hours_day']) === 0 ) {
						$start = $row['field_sellpoint_office_hours_starthours'];
						$end = $row['field_sellpoint_office_hours_endhours'];
						if ( ! isset($sunday) ) {
							$hours_sunday = "<br>Zondag: ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
							$sunday = true;
						} else {
							$hours_sunday .= " en ".substr($start, 0, -2).":".substr($start, -2)." - ".substr($end, 0, -2).":".substr($end, -2);
						}
					}
				}
			}
			$hours .= $hours_sunday;
			// Knip de eerste <br> er weer af!
			$hours = substr($hours, 4);
		}
		fclose($handle);

		// PROBLEEM: Hierna moeten we XIO_FTP handmatig ingeven (kan niet als parameter met gewone ssh!)
		// $msg = shell_exec( "ssh -p 51234 -f -L 3307:127.0.0.1:3306 oxfam_ro@web4.xio.be sleep 60 >> logfile" );
		
		// DIT WERKT VIA COMMANDLINE IN ANTAGONIST
		// $msg = shell_exec( "mysql -u oxfam_ro -p ".XIO_MYSQL." -h 127.0.0.1 oxfamDb -P 3307" );  

		// MAAR DIT IS DE PHP-FUNCTIE DIE WE UITEINDELIJK WILLEN GEBRUIKEN
		// $mysqli = new mysqli( '127.0.0.1', 'oxfam_ro', XIO_MYSQL, 'oxfamDb', 3307 );
		// if ( $mysqli->connect_errno ) {
		//     $msg = "Failed to connect to MySQL: ".$mysqli->connect_error;
		// } else {
		// 	$msg = $mysqli->host_info;
		// }

		// WERKT ENKEL INDIEN SSH2-MODULE ENABLED IN SETTINGS PHP (ANTAGONIST = OK, COMBELL NIET)
		// $connection = ssh2_connect('web4.xio.be', 51234);
		// if ( ssh2_auth_password($connection, 'oxfam_ro', XIO_FTP) ) {
		// 	$msg .= "Verbinding met OWW-site mislukt";
		// } else {
		// 	$tunnel = ssh2_tunnel($connection, '127.0.0.1', 3307);
		// 	// shell_exec("ssh -f -L 3307:127.0.0.1:3306 oxfam_ro@web4.xio.be sleep 600 >> logfile");
		// 	$mysqli = mysqli_connect('127.0.0.1', 'oxfam_ro', XIO_MYSQL, 'oxfamDb', 3307);
		// 	$msg .= "op te halen uit OWW-site (node ".$node.")";
		// }

		return $hours;
	}

	function set_flemish_zip_codes() {
		$zips = array( 1000, 1020, 1030, 1040, 1050, 1060, 1070, 1080, 1081, 1082, 1083, 1090, 1120, 1130, 1140, 1150, 1160, 1170, 1180, 1190, 1200, 1210, 1500, 1501, 1502, 1540, 1541, 1547, 1560, 1570, 1600, 1601, 1602, 1620, 1630, 1640, 1650, 1651, 1652, 1653, 1654, 1670, 1671, 1673, 1674, 1700, 1701, 1702, 1703, 1730, 1731, 1740, 1741, 1742, 1745, 1750, 1755, 1760, 1761, 1770, 1780, 1785, 1790, 1800, 1820, 1830, 1831, 1840, 1850, 1851, 1852, 1853, 1860, 1861, 1880, 1910, 1930, 1932, 1933, 1950, 1970, 1980, 1981, 1982, 2000, 2018, 2020, 2030, 2040, 2050, 2060, 2070, 2100, 2110, 2140, 2150, 2160, 2170, 2180, 2200, 2220, 2221, 2222, 2223, 2230, 2235, 2240, 2242, 2243, 2250, 2260, 2270, 2275, 2280, 2288, 2290, 2300, 2310, 2320, 2321, 2322, 2323, 2328, 2330, 2340, 2350, 2360, 2370, 2380, 2381, 2382, 2387, 2390, 2400, 2430, 2431, 2440, 2450, 2460, 2470, 2480, 2490, 2491, 2500, 2520, 2530, 2531, 2540, 2547, 2550, 2560, 2570, 2580, 2590, 2600, 2610, 2620, 2627, 2630, 2640, 2650, 2660, 2800, 2801, 2811, 2812, 2820, 2830, 2840, 2845, 2850, 2860, 2861, 2870, 2880, 2890, 2900, 2910, 2920, 2930, 2940, 2950, 2960, 2970, 2980, 2990, 3000, 3001, 3010, 3012, 3018, 3020, 3040, 3050, 3051, 3052, 3053, 3054, 3060, 3061, 3070, 3071, 3078, 3080, 3090, 3110, 3111, 3118, 3120, 3128, 3130, 3140, 3150, 3190, 3191, 3200, 3201, 3202, 3210, 3211, 3212, 3220, 3221, 3270, 3271, 3272, 3290, 3293, 3294, 3300, 3320, 3321, 3350, 3360, 3370, 3380, 3381, 3384, 3390, 3391, 3400, 3401, 3404, 3440, 3450, 3454, 3460, 3461, 3470, 3471, 3472, 3473, 3500, 3501, 3510, 3511, 3512, 3520, 3530, 3540, 3545, 3550, 3560, 3570, 3580, 3581, 3582, 3583, 3590, 3600, 3620, 3621, 3630, 3631, 3640, 3650, 3660, 3665, 3668, 3670, 3680, 3690, 3700, 3717, 3720, 3721, 3722, 3723, 3724, 3730, 3732, 3740, 3742, 3746, 3770, 3790, 3791, 3792, 3793, 3798, 3800, 3803, 3806, 3830, 3831, 3832, 3840, 3850, 3870, 3890, 3891, 3900, 3910, 3920, 3930, 3940, 3941, 3945, 3950, 3960, 3970, 3971, 3980, 3990, 8000, 8020, 8200, 8210, 8211, 8300, 8301, 8310, 8340, 8370, 8377, 8380, 8400, 8420, 8421, 8430, 8431, 8432, 8433, 8434, 8450, 8460, 8470, 8480, 8490, 8500, 8501, 8510, 8511, 8520, 8530, 8531, 8540, 8550, 8551, 8552, 8553, 8554, 8560, 8570, 8572, 8573, 8580, 8581, 8582, 8583, 8587, 8600, 8610, 8620, 8630, 8640, 8647, 8650, 8660, 8670, 8680, 8690, 8691, 8700, 8710, 8720, 8730, 8740, 8750, 8755, 8760, 8770, 8780, 8790, 8791, 8792, 8793, 8800, 8810, 8820, 8830, 8840, 8850, 8851, 8860, 8870, 8880, 8890, 8900, 8902, 8904, 8906, 8908, 8920, 8930, 8940, 8950, 8951, 8952, 8953, 8954, 8956, 8957, 8958, 8970, 8972, 8978, 8980, 9000, 9030, 9031, 9032, 9040, 9041, 9042, 9050, 9051, 9052, 9060, 9070, 9080, 9090, 9100, 9111, 9112, 9120, 9130, 9140, 9150, 9160, 9170, 9180, 9185, 9190, 9200, 9220, 9230, 9240, 9250, 9255, 9260, 9270, 9280, 9290, 9300, 9308, 9310, 9320, 9340, 9400, 9401, 9402, 9403, 9404, 9406, 9420, 9450, 9451, 9470, 9472, 9473, 9500, 9506, 9520, 9521, 9550, 9551, 9552, 9570, 9571, 9572, 9600, 9620, 9630, 9636, 9660, 9661, 9667, 9680, 9681, 9688, 9690, 9700, 9750, 9770, 9771, 9772, 9790, 9800, 9810, 9820, 9830, 9831, 9840, 9850, 9860, 9870, 9880, 9881, 9890, 9900, 9910, 9920, 9921, 9930, 9931, 9932, 9940, 9950, 9960, 9961, 9968, 9970, 9971, 9980, 9981, 9982, 9988, 9990, 9991, 9992 );
		update_site_option( 'oxfam_flemish_zip_codes', $zips );	
	}

	function does_home_delivery() {
		return get_option( 'oxfam_zip_codes' );
	}

	// Voorlopig nog identiek aan vorige functie, maar dat kan nog veranderen!
	function does_sendcloud_delivery() {
		return get_option( 'oxfam_zip_codes' );
	}

	function get_shops() {
		$global_zips = array();
		$sites = get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			$local_zips = get_option( 'oxfam_zip_codes' );
			foreach ( $local_zips as $zip ) {
				if ( isset($global_zips[$zip]) ) {
					write_log("CONSISTENTIEFOUT: Postcode ".$zip." is reeds gelinkt aan ".$global_zips[$zip].'!');
				}
				$global_zips[$zip] = $site->path;
			}
			restore_current_blog();
		}
		ksort($global_zips);
		return $global_zips;
	}

	function print_shop_selection() {
		$msg = "";
		$global_zips = get_shops();
		foreach ( $global_zips as $zip => $path ) {
			$msg .= '<p style="text-align: center;"><a href="'.$path.'">'.$zip.'</a></p>';
		}
		return $msg;
	}

	function print_map() {
		# Header
		$myfile = fopen("newoutput.kml", "w");
		$str = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
		# Styles (upscalen werkt helaas niet ...)
		$str .= "<Style id='1'><IconStyle><scale>1.21875</scale><w>39</w>
          <h>51</h><Icon><href>https://demo.oxfamwereldwinkels.be/wp-content/uploads/google-maps.png</href></Icon></IconStyle></Style>";
		# Placemarks
		$zips = get_shops();
		foreach ( $zips as $zip => $path ) {
			if ( $zip !== 9000 ) {
				$ll = '2.909586000000,51.226419000000';
			} else {
				$ll = '3.728363000000,51.048020000000';
			}
 			$str .= "<Placemark><name>".$path."</name><styleUrl>#1</styleUrl><description><![CDATA[".get_option('oxfam_addresses')."<br><a href=".$path.">Naar de webshop »</a>]]></description><Point><coordinates>".$ll."</coordinates></Point></Placemark>";
 		}
 		# Footer
		$str .= "</Document></kml>";
		fwrite($myfile, $str);
		fclose($myfile);
		return do_shortcode("[flexiblemap src='".site_url()."/newoutput.kml?v=".rand()."' width='100%' height='600px' zoom='9' hidemaptype='true' maptype='light_monochrome']");
	}

	add_filter( 'flexmap_custom_map_types', function($mapTypes, $attrs) {
	    if ( empty($attrs['maptype']) ) {
        	return $mapTypes;
    	}

		if ( $attrs['maptype'] === 'light_monochrome' and empty( $mapTypes['light_monochrome'] ) ) {
			$custom_type = '{ "styles" : [{"stylers":[{"hue":"#ffffff"},{"invert_lightness":false},{"saturation":-100}]}], "options" : { "name" : "Light Monochrome" } }';
	        $mapTypes['light_monochrome'] = json_decode($custom_type);
    	}
    	return $mapTypes;
	}, 10, 2);

	
	##########
	# SEARCH #
	##########

	// Probeert reguliere meervouden en verkleinwoorden automatisch weg te laten uit zoektermen (én index)
	add_filter( 'relevanssi_stemmer', 'relevanssi_dutch_stemmer' );

	function relevanssi_dutch_stemmer( $term ) {
		// De 'synoniemen' die een woord simpelweg verlengen voeren we pas door nu de content opgesplitst is in woorden
		$synonyms = array( 'blauw' => 'blauwe', 'groen' => 'groene', 'wit' => 'witte', 'zwart' => 'zwarte', 'paars' => 'paarse', 'bruin' => 'bruine' );
		foreach ( $synonyms as $search => $replace ) {
			if ( strcmp( $term, $search ) === 0 ) $term = $replace;
		}
		
		$len = strlen($term);
		
		if ( $len > 4 ) {
			$last_3 = substr($term, -3, 3);
			$last_4 = substr($term, -4, 4);
			$vowels = array( "a", "e", "i", "o", "u" );

			// Knip alle meervouden op 's' weg
			if ( substr($term, -2, 2) === "'s" ) {
				$term = substr($term, 0, -2);
			} elseif ( in_array( $last_4, array( "eaus", "eaux" ) ) ) {
				$term = substr($term, 0, -1);
			} elseif ( substr($term, -1, 1) === "s" and ! in_array( substr($term, -2, 1), array( "a", "i", "o", "u" ), true ) and ! ( in_array( substr($term, -2, 1), $vowels, true ) and in_array( substr($term, -3, 1), $vowels, true ) ) ) {
				// Behalve na een klinker (m.u.v. 'e') of een tweeklank!
				$term = substr($term, 0, -1);
			}

			// Knip de speciale meervouden op 'en' met een wisselende eindletter weg
			if ( $last_3 === "'en" ) {
				$term = substr($term, 0, -3);
			} elseif ( $last_3 === "eën" ) {
				$term = substr($term, 0, -3)."e";
			} elseif ( $last_3 === "iën" ) {
				$term = substr($term, 0, -3)."ie";
			} elseif ( $last_4 === "ozen" ) {
				// Andere onregelmatige meervouden vangen we op via de synoniemen!
				$term = substr($term, 0, -3)."os";
			}

			// Knip de gewone meervouden op 'en' weg
			if ( substr($term, -2, 2) === "en" and ! in_array( substr($term, -3, 1), $vowels, true ) ) {
				$term = substr($term, 0, -2);
			}

			// Knip de verkleinende suffixen weg
			if ( substr($term, -4, 4) === "ltje" ) {
				$term = substr($term, 0, -3);
			} elseif ( substr($term, -4, 4) === "mpje" ) {
				$term = substr($term, 0, -3);
			} elseif ( substr($term, -4, 4) === "etje" ) {
				$term = substr($term, 0, -4);
			} elseif ( substr($term, -2, 2) === "je" ) {
				// Moeilijk te achterhalen wanneer de laatste 't' ook weg moet!
				$term = substr($term, 0, -2);
			}

			// Knip de overblijvende verdubbelde eindletters weg
			if ( in_array( substr($term, -2, 2), array( "bb", "dd", "ff", "gg", "kk", "ll", "mm", "nn", "pp", "rr", "ss", "tt" ) ) ) {
				$term = substr($term, 0, -1);
			}
		}

		return $term;
	}

	// Plaats een zoeksuggestie net onder de titel van zoekpagina's als er minder dan 5 resultaten zijn
	add_action( 'woocommerce_archive_description', 'add_didyoumean' );

	function add_didyoumean() {
		if ( is_search() ) relevanssi_didyoumean(get_search_query(), "<p>Bedoelde je misschien <i>", "</i>?</p>", 5);
	}

	// Verhinder dat zeer zeldzame zoektermen in de index de machine learning verstoren
	add_filter('relevanssi_get_words_query', 'limit_suggestions');
	
	function limit_suggestions( $query ) {
	    $query = $query." HAVING COUNT(term) > 1";
	    return $query;
	}

	// Toon de bestsellers op zoekpagina's zonder resultaten 
	add_action( 'woocommerce_after_main_content', 'add_bestsellers' );

	function add_bestsellers() {
		global $wp_query;
		if ( is_search() and $wp_query->found_posts == 0 ) {
			echo "<br><h2 style='text-align: center;''><strong>Of werp een blik op onze bestsellers ...</strong></h2><hr/>".do_shortcode('[best_selling_products per_page="9" columns="3" orderby="rand"]');
		}
	}

	// Voeg ook het artikelnummer en de bovenliggende categorie toe aan de te indexeren content van een product
	add_filter( 'relevanssi_content_to_index', 'add_sku_and_parent_category', 10, 2 );

	function add_sku_and_parent_category( $content, $post ) {
		global $relevanssi_variables;
		$content .= get_post_meta( $post->ID, '_sku', true ).' ';
		$categories = get_the_terms( $post->ID, 'product_cat' );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				if ( ! empty( $category->parent ) ) {
					$parent = get_term( $category->parent, 'product_cat' );
					// Voer de synoniemen ook hierop door
					$search = array_keys($relevanssi_variables['synonyms']);
					$replace = array_values($relevanssi_variables['synonyms']);
					$content .= str_ireplace($search, $replace, $parent->name).' ';
				}
			}
		}
		return $content;
	}
	
	// Verleng de logs tot 90 dagen
	add_filter( 'relevanssi_30days', 'prolong_relevanssi_logs' );

	function prolong_relevanssi_logs() {
		return 90;
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

	// Verwissel twee associatieve keys in een array
	function array_swap_assoc($key1, $key2, $array) {
		$newArray = array ();
		foreach ($array as $key => $value) {
			if ($key == $key1) {
				$newArray[$key2] = $array[$key2];
			} elseif ($key == $key2) {
				$newArray[$key1] = $array[$key1];
			} else {
				$newArray[$key] = $value;
			}
		}
		return $newArray;
	}

	// Voeg CSS toe aan adminomgeving voor Relevanssi en Voorraadbeheer
	add_action( 'admin_head', 'custom_admin_css' );

	function custom_admin_css() {
		?>
		<style>
			.dashboard_page_relevanssi-premium-relevanssi .postbox-container {
				width: 100%;
			}

			.dashboard_page_relevanssi-premium-relevanssi .postbox-container .widefat {
				margin-bottom: 4em;
			}

			.dashboard_page_relevanssi-premium-relevanssi .postbox-container th,
			.dashboard_page_relevanssi-premium-relevanssi .postbox-container tr {
				text-align: center;
			}

			#oxfam-products {
				display: table;
				width: 100%;
				border-collapse: separate;
				border-spacing: 0px 25px;
			}

			#oxfam-products .block {
				display: inline-block;
				box-sizing: border-box;
				width: 50%;
			}

			#oxfam-products .pane-left {
				display: table-cell;
				box-sizing: border-box;
				text-align: center;
				padding: 0px 25px;
				width: 30%;
			}

			#oxfam-products .pane-right {
				display: table-cell;
				box-sizing: border-box;
				text-align: center;
				vertical-align: middle;
				min-height: 204px;
				width: 20%;
				border-left: 5px solid black;
			}

			#oxfam-products .title {
				font-weight: bold;
			}

			#oxfam-products .output {
				color: #f16e22;
			}

			#oxfam-products .new {
				background-color: #0b9cda;
			}

			#oxfam-products .old {
				background-color: #fbc43a;
			}

			#oxfam-products .border-color-green {
				border-color: #61a534;
			}

			#oxfam-products .border-color-red {
				border-color: #e70052;
			}

			@media (max-width: 1024px) {
				#oxfam-products .block {
					display: block;
					width: 100%;
				}
			}
		</style>
		<?php
	}
	
?>