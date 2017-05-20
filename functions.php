<?php

	if ( ! defined('ABSPATH') ) exit;

	// Vuile truc om te verhinderen dat WordPress de afmeting van 'large'-afbeeldingen verkeerd weergeeft
	$content_width = 2000;

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'load_child_theme' );

	function load_child_theme() {
		wp_enqueue_style( 'oxfam-webshop', get_stylesheet_uri(), array( 'nm-core' ) );
	}

	// Voeg custom styling toe aan de adminomgeving (voor Relevanssi en Voorraadbeheer)
	add_action( 'admin_enqueue_scripts', 'load_admin_css' );

	function load_admin_css() {
		wp_enqueue_style( 'oxfam-admin', get_stylesheet_directory_uri().'/admin.css' );
	}

	// Fix het conflict met WP All Export bij het connecteren van Jetpack met Wordpress.com
	add_action( 'http_api_curl', 'custom_curl_timeout', 10, 3 );
	
	function custom_curl_timeout( $handle, $r, $url ) {
		curl_setopt( $handle, CURLOPT_TIMEOUT, 30 );
	}
	
	// Beheer alle wettelijke feestdagen uit de testperiode centraal
	$default_holidays = array( '2017-06-04', '2017-06-05', '2017-07-21', '2017-08-15', '2017-11-01', '2017-11-11', '2017-12-25', '2018-01-01', '2018-04-01', '2018-04-02' );
	
	############
	# SECURITY #
	############

	// Toon het blokje 'Additional Capabilities' op de profielpagina nooit
	add_filter( 'ure_show_additional_capabilities_section', '__return_false' );

	// Schakel de sterkte-indicator voor paswoorden uit
	add_action( 'wp_print_scripts', 'remove_password_strength', 100 );
	
	function remove_password_strength() {
		if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
			wp_dequeue_script( 'wc-password-strength-meter' );
		}
	}

	// Zorg ervoor dat lokale beheerders toch al hun gearchiveerde site kunnen bekijken
	add_filter( 'ms_site_check', 'allow_local_manager_on_archived' );

	function allow_local_manager_on_archived() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
	}

	if ( is_regional_webshop() ) {
		// Definieer een profielveld in de back-end waarin we kunnen bijhouden van welke winkel de gebruiker lid is
		add_filter( 'user_contactmethods', 'add_member_of_shop_field', 10, 1 );
		// Zorg ervoor dat het bewaard wordt
		add_action( 'personal_options_update', 'save_extra_user_field' );
		add_action( 'edit_user_profile_update', 'save_extra_user_field' );
		// Vervang het tekstveld door een dropdown (origineel wordt met jQuery verborgen)
		add_action( 'show_user_profile', 'add_extra_user_field' );
		add_action( 'edit_user_profile', 'add_extra_user_field' );
		
		// Voeg de claimende winkel toe aan de ordermetadata van zodra iemand op het winkeltje klikt (en verwijder indien we teruggaan)
		add_action( 'woocommerce_order_status_processing_to_claimed', 'register_claiming_member_shop' );
		add_action( 'woocommerce_order_status_claimed_to_processing', 'delete_claiming_member_shop' );

		// Deze transities zullen in principe niet voorkomen, maar voor alle zekerheid ...
		add_action( 'woocommerce_order_status_on-hold_to_claimed', 'register_claiming_member_shop' );
		add_action( 'woocommerce_order_status_claimed_to_on-hold', 'delete_claiming_member_shop' );

		// Laat afhalingen automatisch claimen door de gekozen winkel
		add_action( 'woocommerce_thankyou', 'auto_claim_local_pickup' );
		
		// Maak zoeken op claimende winkel mogelijk?
		add_filter( 'woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_order_fields' );

		// Creëer bovenaan de orderlijst een dropdown met de deelnemende winkels uit de regio
		add_action( 'restrict_manage_posts', 'add_claimed_by_filtering' );
		
		// Voer de filtering uit tijdens het bekijken van orders in de admin
		add_action( 'pre_get_posts', 'filter_orders_by_owner' );

		// Voeg ook een kolom toe aan het besteloverzicht in de back-end
		add_filter( 'manage_edit-shop_order_columns', 'add_claimed_by_column', 11 );

		// Maak sorteren op deze nieuwe kolom mogelijk
		add_filter( 'manage_edit-shop_order_sortable_columns', 'make_claimed_by_column_sortable' );

		// Toon de data van elk order in de kolom
		add_action( 'manage_shop_order_posts_custom_column' , 'get_claimed_by_value', 10, 2 );

		// Laat de custom statusfilter verschijnen volgens de normale flow van de verwerking
		add_filter( 'views_edit-shop_order', 'put_claimed_after_processing' );

		// Maak de boodschap om te filteren op winkel beschikbaar bij de rapporten
		add_filter( 'woocommerce_reports_get_order_report_data_args', 'limit_reports_to_member_shop', 10, 2 );
	}

	function add_member_of_shop_field( $contactmethods ) {
		$contactmethods['blog_'.get_current_blog_id().'_member_of_shop'] = 'Ik claim orders voor ...';
		return $contactmethods;
	}
	
	function save_extra_user_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) return false;
		// Usermeta is sitewide, dus ID van blog toevoegen aan de key!
		$key = 'blog_'.get_current_blog_id().'_member_of_shop';
		update_usermeta( $user_id, $key, $_POST[$key] );
	}

	function add_extra_user_field( $user ) {
		?>
		<h3 style="color: red;">Regiosamenwerking</h3>
		<table class="form-table" style="color: red;">
			<tr>
				<th><label for="dropdown" style="color: red;">Ik bevestig orders voor ...</label></th>
				<td>
					<?php
						$key = 'blog_'.get_current_blog_id().'_member_of_shop';
						echo '<select name="'.$key.'" id="'.$key.'" style="color: red;">';
							$member_of = get_the_author_meta( $key, $user->ID );
							$shops = get_option( 'oxfam_member_shops' );
							$selected = empty( $member_of ) ? ' selected' : '';
							echo '<option value=""'.$selected.'>(selecteer)</option>';
							foreach ( $shops as $shop ) {
								$selected = ( $shop === $member_of ) ? ' selected' : '';
								echo '<option value="'.$shop.'"'.$selected.'>'.trim_and_uppercase( $shop ).'</option>';
							}
						echo '</select>';
					?>
					<span class="description">Opgelet: deze keuze bepaalt aan welke winkel de bestellingen die jij bevestigt toegekend worden!</span>
				</td>
			</tr>
		</table>
		<?php 
	}

	function auto_claim_local_pickup( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			$order->update_status( 'claimed' );
		}
	}

	function register_claiming_member_shop( $order_id ) {
		$order = wc_get_order( $order_id );
		$blog_id = get_current_blog_id();
		$owner = get_the_author_meta( 'blog_'.$blog_id.'_member_of_shop', get_current_user_id() );
		
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			// Koppel automatisch aan de winkel waar de afhaling zal gebeuren
			$methods = $order->get_shipping_methods();
			$method = reset($methods);
			$meta_data = $method->get_meta_data();
			$pickup_data = reset($meta_data);
			$city = do_shortcode($pickup_data->value['city']);
			if ( in_array( $city, get_option( 'oxfam_member_shops' ) ) ) {
				// Dubbelcheck of deze stad wel tussen de deelnemende winkels zit
				$owner = $city;
			}
		}

		if ( ! $owner ) {
			// Koppel als laatste redmiddel aan de hoofdwinkel (op basis van het nodenummer) 
			$owner = mb_strtolower( get_oxfam_shop_data( 'city' ) );
		}

		update_post_meta( $order_id, 'claimed_by', $owner, true );
	}

	function delete_claiming_member_shop( $order_id ) {
		delete_post_meta( $order_id, 'claimed_by' );
	}

	function woocommerce_shop_order_search_order_fields( $search_fields ) {
		$search_fields[] = 'claimed_by';
		return $search_fields;
	}

	function add_claimed_by_filtering() {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' ) {
			$shops = get_option( 'oxfam_member_shops' );
			echo '<select name="claimed_by" id="claimed_by">';
				$all = ( ! empty($_GET['claimed_by']) and sanitize_text_field($_GET['claimed_by']) === 'all' ) ? ' selected' : '';
				echo '<option value="all" '.$all.'>Alle winkels uit de regio</option>';
				foreach ( $shops as $shop ) {
					$selected = ( ! empty($_GET['claimed_by']) and sanitize_text_field($_GET['claimed_by']) === $shop ) ? ' selected' : '';
					echo '<option value="'.$shop.'" '.$selected.'>Enkel '.trim_and_uppercase( $shop ).'</option>';
				}
			echo '</select>';
		}
	}

	function filter_orders_by_owner( $query ) {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' and $query->query['post_type'] === 'shop_order' ) {
			if ( ! empty( $_GET['claimed_by'] ) and $_GET['claimed_by'] !== 'all' ) {
				$meta_query_args = array(
					'relation' => 'AND',
					array(
						'key' => 'claimed_by',
						'value' => $_GET['claimed_by'],
						'compare' => '=',
					),
				);
				$query->set( 'meta_query', $meta_query_args );
			} elseif ( 1 < 0 ) {
				// Eventueel AUTOMATISCH filteren op eigen winkel (tenzij expliciet anders aangegeven)
				$owner = get_the_author_meta( 'blog_'.get_current_blog_id().'_member_of_shop', get_current_user_id() );
				if ( ! $owner ) {
					$meta_query_args = array(
						'relation' => 'AND',
						array(
							'key' => 'claimed_by',
							'value' => $owner,
							'compare' => '=',
						),
					);
					$query->set( 'meta_query', $meta_query_args );
				}
			}

			// Check of we moeten sorteren op deze kolom
			if ( $query->get( 'orderby' ) === 'claimed_by' ) {
				$query->set( 'meta_key', 'claimed_by' );
				$query->set( 'orderby', 'meta_value' );
			}
		}
	}

	function add_claimed_by_column( $columns ) {
		$columns['claimed_by'] = 'Behandeling door';
		// Eventueel bepaalde kolommen altijd uitschakelen?
		// unset( $columns['order_notes'] );
		return $columns;
	}

	function make_claimed_by_column_sortable( $columns ) {
		$columns['claimed_by'] = 'claimed_by';
		// Eventueel ook sorteren op status toestaan?
		// $columns['order_status'] = 'order_status';
		return $columns;
	}

	function get_claimed_by_value( $column ) {
		global $the_order;
		if ( $column === 'claimed_by' ) {
			$not_claimed = array( 'wc-pending', 'wc-on-hold', 'wc-processing' ); 
			if ( in_array( get_post_status( $the_order->get_id() ), $not_claimed ) ) {
				echo '<i>nog niet bevestigd</i>';
			} else {
				if ( get_post_meta( $the_order->get_id(), 'claimed_by', true ) ) {
					echo 'OWW '.trim_and_uppercase( get_post_meta( $the_order->get_id(), 'claimed_by', true ) );
				} else {
					// Reeds verderop in het verwerkingsproces maar geen winkel? Dat zou niet mogen zijn!
					echo '<i>ERROR</i>';
				}
			}
		}
	}

	function put_claimed_after_processing( $array ) {
		// Check eerst of de statusknop wel aanwezig is op dit moment!
		if ( array_key_exists( 'wc-claimed', $array ) ) {
			$cnt = 1;
			$stored_value = $array['wc-claimed'];
			unset($array['wc-claimed']);
			foreach ( $array as $key => $value ) {
				if ( $key === 'wc-processing' ) {
					$array = array_slice( $array, 0, $cnt ) + array( 'wc-claimed' => $stored_value ) + array_slice( $array, $cnt, count($array) - $cnt );
					break;
				}
				$cnt++;
			}
		}
		return $array;
	}	

	// Global om ervoor te zorgen dat de boodschap enkel in de eerste loop geëchood wordt
	$warning_shown = false;

	function limit_reports_to_member_shop( $args ) {
		global $pagenow, $warning_shown;
		if ( $pagenow === 'admin.php' and $_GET['page'] === 'wc-reports' ) {
			if ( ! empty( $_GET['claimed_by'] ) ) {
				$new_args['where_meta'] = array(
					'relation' => 'AND',
					array(
						'meta_key'   => 'claimed_by',
						'meta_value' => $_GET['claimed_by'],
						'operator'   => '=',
					),
				);

				// Nette manier om twee argumenten te mergen (in het bijzonder voor individuele productraportage, anders blijft enkel de laatste meta query bewaard)
				$args['where_meta'] = array_key_exists( 'where_meta', $args ) ? wp_parse_args( $new_args['where_meta'], $args['where_meta'] ) : $new_args['where_meta'];
				
				if ( ! $warning_shown ) {
					echo "<div style='background-color: red; color: white; padding: 0.25em 1em;'>";
						echo "<p>Opgelet: momenteel bekijk je een gefilterd rapport met enkel de bestellingen die verwerkt werden door <b>OWW ".trim_and_uppercase( $_GET['claimed_by'] )."</b>.</p>";
						echo "<p style='text-align: right;'>";
							$members = get_option( 'oxfam_member_shops' );
							foreach ( $members as $member ) {
								if ( $member !== $_GET['claimed_by'] ) {
									echo "<a href='".esc_url( add_query_arg( 'claimed_by', $member ) )."' style='color: black;'>Bekijk ".trim_and_uppercase( $member )." »</a><br>";
								}
							}
							echo "<br><a href='".esc_url( remove_query_arg( 'claimed_by' ) )."' style='color: black;'>Terug naar volledige regio »</a>";
						echo "</p>";
					echo "</div>";
				}
			} else {
				if ( ! $warning_shown ) {
					echo "<div style='background-color: green; color: white; padding: 0.25em 1em;'>";
						echo "<p>Momenteel bekijk je het rapport met de bestellingen van alle winkels uit de regio. Klik hieronder om de omzet te filteren op een bepaalde winkel.</p>";
						echo "<p style='text-align: right;'>";
							$members = get_option( 'oxfam_member_shops' );
							foreach ( $members as $member ) {
								echo "<a href='".esc_url( add_query_arg( 'claimed_by', $member ) )."' style='color: black;'>Bekijk enkel ".trim_and_uppercase( $member )." »</a><br>";
							}
						echo "</p>";
					echo "</div>";
				}
			}
			$warning_shown = true;
		}
    	return $args;
	}

	// Voer shortcodes ook uit in widgets, titels en e-mailfooters
	add_filter( 'widget_text', 'do_shortcode' );
	add_filter( 'the_title', 'do_shortcode' );
	add_filter( 'woocommerce_email_footer_text', 'do_shortcode' );

	// Adminlinks verstoppen voor lokale shopmanagers GEBEURT VIA USER ROLE EDITOR
	// Toegangrechten voor WP All Export versoepeld door rol aan te passen in wp-all-export-pro.php!
	// Gebruik eventueel deze speciale filter voor het hardleerse Jetpack:
	// add_action( 'jetpack_admin_menu', 'hide_jetpack_from_others' );
	
	function hide_jetpack_from_others() {
    	if ( ! current_user_can( 'create_sites' ) ) {
    		remove_menu_page( 'jetpack' );
    	}
	}

	// Schakel autosaves uit
	add_action( 'wp_print_scripts', 'disable_autosave' );
	
	function disable_autosave() {
		wp_deregister_script( 'autosave' );
	}

	// Zorg ervoor dat revisies ook bij producten bijgehouden worden op de hoofdsite
	// Log de post_meta op basis van de algemene update_post_metadata-filter (of beter door WC-functies te hacken?)
	if ( is_main_site() ) {
		add_filter( 'woocommerce_register_post_type_product', 'add_product_revisions' );
		add_action( 'update_post_metadata', 'log_product_changes', 1, 4 );
	}
	
	function add_product_revisions( $args ) {
		$args['supports'][] = 'revisions';
		return $args;
	}

	function log_product_changes( $meta_id, $post_id, $meta_key, $new_meta_value ) {
		// Alle overige interessante data zitten in het algemene veld '_product_attributes' dus daarvoor best een ander filtertje zoeken
		$watched_metas = array( '_price', '_stock_status', '_tax_class', '_length', '_width', '_height', '_weight', '_thumbnail_id', '_force_sell_synced_ids' );
		// Check of er een belangwekkende wijzing was
		foreach ( $watched_metas as $meta_key ) {
			// Vergelijk nieuwe waarde met de actuele
			$old_meta_value = get_post_meta( $post_id, $meta_key, true );
			
			// Check of er wel al een oude waarde bestond
			if ( ! $old_meta_value ) {
				// Check welk type variabele het is
				if ( is_array( $new_meta_value ) ) {
					if ( count( array_diff( $new_meta_value, $old_meta_value ) ) > 0 ) {
						// Schrijf weg in log per weeknummer (zonder leading zero's) 
						$str = date_i18n('d/m/Y H:i:s') . "\t" . $meta_key . "\t" . serialize($new_meta_value) . "\t" . get_post_meta( $post_id, '_sku', true ) . "\t" . get_the_title( $post_id ) . "\n";
					    file_put_contents(WP_CONTENT_DIR."/changelog-week-".intval( date_i18n('W') ).".csv", $str, FILE_APPEND);
					}
				} else {
					if ( strcmp( $new_meta_value, $old_meta_value ) !== 0 ) {
						// Schrijf weg in log per weeknummer (zonder leading zero's) 
						$str = date_i18n('d/m/Y H:i:s') . "\t" . $meta_key . "\t" . $new_meta_value . "\t" . get_post_meta( $post_id, '_sku', true ) . "\t" . get_the_title( $post_id ) . "\n";
					    file_put_contents(WP_CONTENT_DIR."/changelog-week-".intval( date_i18n('W') ).".csv", $str, FILE_APPEND);
					}
				}
			}
		}
		// Zet de normale postmeta-functie verder
		update_post_meta( $meta_id, $post_id, $meta_key, $new_meta_value );
	}

	// Verberg alle koopknoppen op het hoofddomein (ook reeds geblokkeerd via .htaccess but better be safe than sorry)
	add_filter( 'woocommerce_get_price_html' , 'no_orders_on_main', 10, 2 );
	
	function no_orders_on_main( $price, $product ) {
		if ( is_main_site() and ! is_admin() ) {
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			return "<i>Geen verkoop vanuit nationaal</i>";
		}
		return $price;
	}

	// Zorg ervoor dat winkelbeheerders na het opslaan van feestdagen niet naar het dashboard geleid worden
	add_filter( 'ure_admin_menu_access_allowed_args', 'ure_allow_args_for_oxfam_options', 10, 1 );

	function ure_allow_args_for_oxfam_options( $args ) {
		$args['edit.php'][''][] = 'claimed_by';
		$args['admin.php']['wc-reports'] = array(
			'tab',
			'report',
			'range',
			'claimed_by',
		);
		$args['admin.php']['oxfam-options'] = array(
			'page',
			'settings-updated',
		);
		$args['admin.php']['pmxe-admin-manage'] = array(
			'id',
			'action',
			'pmxe_nt',
		);
		$args['profile.php'][''] = array(
			'updated',
		);
		return $args;
	}
	
	###############
	# WOOCOMMERCE #
	###############

	// Voeg allerlei checks toe net na het inladen van WordPress
	add_action( 'init', 'woocommerce_clear_cart_url' );
	
	function woocommerce_clear_cart_url() {
		if ( isset( $_GET['referralZip'] ) ) {
			// Dit volstaat ook om de variabele te creëren indien nog niet beschikbaar
			WC()->customer->set_billing_postcode( intval( $_GET['referralZip'] ) );
			WC()->customer->set_shipping_postcode( intval( $_GET['referralZip'] ) );
			// Op basis van betere set_flemish_zip_codes() met alle Vlaamse $zip => $city
			// WC()->customer->set_shipping_city( 'Oostende' );
			// WC()->customer->save();
			var_dump(WC()->customer);
		}
		
		if ( isset( $_GET['emptyCart'] ) ) WC()->cart->empty_cart();
		// if ( isset( $_GET['downloadSheet'] ) ) create_product_pdf( wc_get_product( 4621 ) );
		
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 100 );
	}

	// Verhoog het aantal producten per winkelpagina
	add_filter( 'loop_shop_per_page', create_function( '$cols', 'return 20;' ), 20 );

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
		unset( $sortby['menu_order'] );
		unset( $sortby['rating'] );
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
	add_action( 'wp_footer', 'cart_update_qty_script' );
	
	function cart_update_qty_script() {
		if ( is_cart() ) :
			global $woocommerce;
			// validate_zip_code( intval( $woocommerce->customer->get_shipping_postcode() ) );
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

			/* Disable bovenliggende landen/continenten van alle aangevinkte partners/landen */
			jQuery( '#taxonomy-product_partner' ).find( 'input[type=checkbox]:checked' ).closest( 'ul.children' ).siblings( 'label.selectit' ).find( 'input[type=checkbox]' ).prop( 'disabled', true );

			/* Disable/enable het bovenliggende land bij aan/afvinken van een partner */
			jQuery( '#taxonomy-product_partner' ).find( 'input[type=checkbox]' ).on( 'change', function() {
				jQuery(this).closest( 'ul.children' ).siblings( 'label.selectit' ).find( 'input[type=checkbox]' ).prop( 'disabled', jQuery(this).is(":checked") );
			});

			/* Disable allergeenklasses */
			jQuery( '#in-product_allergen-615' ).prop( 'disabled', true );
			jQuery( '#in-product_allergen-616' ).prop( 'disabled', true );

			/* Disable rode en witte druiven */
			jQuery( '#in-product_grape-575' ).prop( 'disabled', true );
			jQuery( '#in-product_grape-574' ).prop( 'disabled', true );

			/* Disbable prijswijzigingen bij terugbetalingen */
			jQuery( '.refund_line_total.wc_input_price' ).prop( 'disabled', true );
			jQuery( '.refund_line_tax.wc_input_price' ).prop( 'disabled', true );
			/* Eventueel ook totaalbedrag onbewerkbaar maken */
			/* jQuery( 'td.total' ).find ( '#refund_amount' ).prop( 'disabled', true ); */
			jQuery( 'label[for=restock_refunded_items]' ).closest( 'tr' ).hide();
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
				if ( $parent->slug !== 'wijn' ) {
					remove_meta_box('product_grapediv', 'product', 'normal');
					remove_meta_box('product_recipediv', 'product', 'normal');
					remove_meta_box('product_tastediv', 'product', 'normal');
				}
			}
		}
	}

	// Label en layout de factuurgegevens
	add_filter( 'woocommerce_billing_fields', 'format_checkout_billing', 10, 1 );
	
	function format_checkout_billing( $address_fields ) {
		$address_fields['billing_first_name']['label'] = "Voornaam";
		$address_fields['billing_first_name']['placeholder'] = "George";
		$address_fields['billing_last_name']['label'] = "Familienaam";
		$address_fields['billing_last_name']['placeholder'] = "Foreman";
		$address_fields['billing_phone']['label'] = "Telefoonnummer";
		$address_fields['billing_phone']['placeholder'] = get_oxfam_shop_data( 'telephone' );
		$address_fields['billing_email']['label'] = "E-mailadres";
		$address_fields['billing_email']['placeholder'] = "george@foreman.com";
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

    // Label en layout de verzendgegevens
	add_filter( 'woocommerce_shipping_fields', 'format_checkout_shipping', 10, 1 );
	
	function format_checkout_shipping( $address_fields ) {
		$address_fields['shipping_first_name']['label'] = "Voornaam";
		$address_fields['shipping_first_name']['placeholder'] = "Muhammad";
		$address_fields['shipping_last_name']['label'] = "Familienaam";
		$address_fields['shipping_last_name']['placeholder'] = "Ali";
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
		$address_fields['address_1']['placeholder'] = 'Stationstraat 16';
		$address_fields['address_1']['required'] = true;

		$address_fields['postcode']['label'] = "Postcode";
		$address_fields['postcode']['placeholder'] = get_oxfam_shop_data( 'zipcode' );
		$address_fields['postcode']['required'] = true;
		// Zorgt ervoor dat de totalen automatisch bijgewerkt worden na aanpassen
		// Werkt enkel indien de voorgaande verplichte velden niet-leeg zijn, zie maybe_update_checkout() in woocommerce/assets/js/frontend/checkout.js 
		$address_fields['postcode']['class'] = array('form-row-first update_totals_on_change');
		$address_fields['postcode']['clear'] = false;

		$address_fields['city']['label'] = "Gemeente";
		$address_fields['city']['placeholder'] = get_oxfam_shop_data( 'city' );
		$address_fields['city']['required'] = true;
		$address_fields['city']['class'] = array('form-row-last');
		$address_fields['city']['clear'] = true;

		return $address_fields;
	}

	// Vul andere placeholders in, naar gelang de gekozen verzendmethode op de winkelwagenpagina (wordt NIET geüpdatet bij verandering in checkout)
    add_filter( 'woocommerce_checkout_fields' , 'format_checkout_notes' );

    function format_checkout_notes( $fields ) {
    	global $user_ID;
    	$shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
    	$shipping_id = reset($shipping_methods);
		switch ( $shipping_id ) {
			case stristr( $shipping_id, 'local_pickup' ):
				$placeholder = "Ik kom de bestelling pas volgende week oppikken in de winkel.";
				break;
			default:
				$placeholder = "'s Middags is er altijd iemand thuis. Geef gerust een belletje wanneer jullie vertrekken.";
				break;
		}
		// $fields['order']['order_comments']['label'] = "Opmerkingen";
		$fields['order']['order_comments']['placeholder'] = $placeholder;
		// $fields['order']['order_comments']['description'] = "Bel ons na het plaatsen van je bestelling eventueel op ".get_oxfam_shop_data( 'telephone' ).".";
		return $fields;
	}

	// Herschrijf bepaalde klantendata naar standaardformaten tijdens afrekenen én bijwerken vanaf accountpagina
	add_filter( 'woocommerce_process_checkout_field_billing_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_phone', 'format_telephone', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_phone', 'format_telephone', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_city', 'format_city', 10, 1 );
	
	function trim_and_uppercase( $value ) {
		return implode( '-', array_map( 'ucwords', explode( '-', trim($value) ) ) );
	}

	function format_tax( $value ) {
		$value = str_replace( 'BE', '', $value );
		$value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( mb_strlen($value) === 10 ) {
			return 'BE '.substr( $value, 0, 4 ).".".substr( $value, 4, 3 ).".".substr( $value, 7, 3 );
		} else {
			return 'BE 0'.substr( $value, 0, 3 ).".".substr( $value, 3, 3 ).".".substr( $value, 6, 3 );
		}
	}

	function format_account( $value ) {
		$value = str_replace( 'BE', '', $value );
		$value = str_replace( 'IBAN', '', $value );
		$value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( mb_strlen($value) === 14 ) {
			return 'BE'.substr( $value, 0, 2 )." ".substr( $value, 2, 4 )." ".substr( $value, 6, 4 )." ".substr( $value, 10, 4 );
		} else {
			return 'ONGELDIG';
		}
	}

	function format_place( $value ) {
		return trim_and_uppercase( $value );
	}
	
	function format_zipcode( $value ) {
		return trim_and_uppercase( $value );
	}

	function format_city( $value ) {
		return trim_and_uppercase( $value );
	}
	
	// Sta een optionele parameter toe om puntjes te zetten in plaats van spaties (maar: wordt omgezet in streepjes door wc_format_phone() dus niet gebruiken in verkoop!)
	function format_telephone( $value, $delim = ' ' ) {
		if ( $delim === '.' ) {
			$slash = '/';
		} else {
			$slash = $delim;
		}
		// Wis alle spaties, leestekens en landcodes
		$temp_tel = preg_replace( '/[\s\-\.\/]/', '', $value );
		$temp_tel = str_replace( '+32', '0', $temp_tel );
		$temp_tel = preg_replace( '/(^|\s)0032/', '0', $temp_tel );
		
		// Formatteer vaste telefoonnummers
		if ( mb_strlen($temp_tel) === 9 ) {
			if ( intval($temp_tel[1]) === 2 or intval($temp_tel[1]) === 3 or intval($temp_tel[1]) === 4 or intval($temp_tel[1]) === 9 ) {
				$value = substr($temp_tel, 0, 2) . $slash . substr($temp_tel, 2, 3).$delim.substr($temp_tel, 5, 2) . $delim . substr($temp_tel, 7, 2);
			} else {
				$value = substr($temp_tel, 0, 3) . $slash . substr($temp_tel, 3, 2).$delim.substr($temp_tel, 5, 2) . $delim . substr($temp_tel, 7, 2);
			}
		}

		// Formatteer mobiele telefoonnummers
		if ( mb_strlen($temp_tel) === 10 ) {
			$value = substr($temp_tel, 0, 4) . $slash . substr($temp_tel, 4, 2) . $delim . substr($temp_tel, 6, 2) . $delim . substr($temp_tel, 8, 2);
		}
		
		return $value;
	}

	function format_hour( $value ) {
		// Is al behoorlijk clean bij XIO (ingesteld via selects)
		return substr($value, 0, 2) . ':' . substr($value, 2, 2);
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
		$profile_fields['billing']['fields']['billing_email']['label'] = 'Bestelcommunicatie naar';
		unset( $profile_fields['billing']['fields']['billing_address_2'] );
		unset( $profile_fields['billing']['fields']['billing_company'] );
		unset( $profile_fields['billing']['fields']['billing_state'] );
		
		$profile_fields['shipping']['title'] = 'Verzendgegevens';
		$profile_fields['shipping']['fields']['shipping_first_name']['label'] = 'Voornaam';
		$profile_fields['shipping']['fields']['shipping_last_name']['label'] = 'Familienaam';
		$profile_fields['shipping']['fields']['shipping_address_1']['label'] = 'Straat en huisnummer';
		$profile_fields['shipping']['fields']['shipping_postcode']['label'] = 'Postcode';
		$profile_fields['shipping']['fields']['shipping_city']['label'] = 'Gemeente';
		unset( $profile_fields['shipping']['fields']['shipping_address_2'] );
		unset( $profile_fields['shipping']['fields']['shipping_company'] );
		unset( $profile_fields['shipping']['fields']['shipping_state'] );

		$profile_fields['billing']['fields'] = array_swap_assoc('billing_city', 'billing_postcode', $profile_fields['billing']['fields']);
		$profile_fields['shipping']['fields'] = array_swap_assoc('shipping_city', 'shipping_postcode', $profile_fields['shipping']['fields']);
		
		return $profile_fields;
	}

	// Verberg bepaalde profielvelden (en niet verwijderen, want dat reset sommige waardes!)
	add_action( 'admin_footer-profile.php', 'hide_own_profile_fields' );
	add_action( 'admin_footer-user-edit.php', 'hide_others_profile_fields' );
	
	function hide_own_profile_fields() {
		?>
		<script type="text/javascript">
			jQuery("tr[class$='member_of_shop-wrap']").remove();
		</script>
		<?php
		if ( ! current_user_can( 'manage_options' ) ) {
			?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").hide();
				jQuery("tr.user-comment-shortcuts-wrap").hide();
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
		
		$current_user = wp_get_current_user();
		$user_meta = get_userdata($current_user->ID);
		$user_roles = $user_meta->roles;
		if ( in_array( 'local_manager', $user_roles) ) {
			?>
			<script type="text/javascript">
				/* Verhinder dat lokale webbeheerders het e-mailadres aanpassen van hun hoofdaccount */
				jQuery("tr.user-email-wrap").find('input[type=email]').prop('readonly', true);
				jQuery("tr.user-email-wrap").find('input[type=email]').after('<span class="description">De lokale beheerder dient altijd gekoppeld te blijven aan de webshopmailbox.</span>');
			</script>
			<?php
		}
	}

	function hide_others_profile_fields() {
		?>
		<script type="text/javascript">
			jQuery("tr[class$='member_of_shop-wrap']").remove();
		</script>
		<?php
		if ( ! current_user_can( 'manage_options' ) ) {
		?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").hide();
				jQuery("tr.user-admin-color-wrap").hide();
				jQuery("tr.user-comment-shortcuts-wrap").hide();
				jQuery("tr.user-admin-bar-front-wrap").hide();
				jQuery("tr.user-language-wrap").hide();
				/* Zeker niét verwijderen -> breekt opslaan van pagina! */
				jQuery("tr.user-nickname-wrap").hide();
				jQuery("tr.user-url-wrap").hide();
				jQuery("h2:contains('Over de gebruiker')").next('.form-table').hide();
				jQuery("h2:contains('Over de gebruiker')").hide();
				/* Let op: als deze string plots vertaald wordt, verschijnt het blokje opnieuw! VERVANGEN DOOR FILTER */
				// jQuery("h3:contains('Additional Capabilities')").next('.form-table').hide();
				// jQuery("h3:contains('Additional Capabilities')").hide();
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
	// add_filter( 'woocommerce_available_payment_gateways', 'b2b_restrict_to_bank_transfer' );

	function b2b_restrict_to_bank_transfer( $gateways ) {
		global $woocommerce;
		$current_user = wp_get_current_user();
		if ( ! empty( get_user_meta( $current_user->ID, 'is_vat_exempt', true ) ) ) {
			unset( $gateways['mollie_wc_gateway_mistercash'] );
			unset( $gateways['mollie_wc_gateway_creditcard'] );
			unset( $gateways['mollie_wc_gateway_kbc'] );
			unset( $gateways['mollie_wc_gateway_belfius'] );
			unset( $gateways['mollie_wc_gateway_ideal'] );
		} else {
			unset( $gateways['cod'] );
			unset( $gateways['mollie_wc_gateway_banktransfer'] );
		}
		return $gateways;
	}

	// Print de geschatte leverdatums onder de beschikbare verzendmethodes 
	add_filter( 'woocommerce_cart_shipping_method_full_label', 'print_estimated_delivery', 10, 2 );
	
	function print_estimated_delivery( $label, $method ) {
		$descr = '<small>';
		$timestamp = estimate_delivery_date( $method->id );
		
		switch ( $method->id ) {
			// Nummers achter method_id slaan op de (unieke) instance_id binnen DEZE subsite!
			// Alle instances van de 'Gratis afhaling in winkel'-methode
			case stristr( $method->id, 'local_pickup' ):
				$descr .= 'Beschikbaar op '.date_i18n( 'l d/m/Y', $timestamp ).' vanaf '.date_i18n( 'G\ui', $timestamp );
				$label .= ':'.wc_price(0);
				break;
			// Alle instances van postpuntlevering
			case stristr( $method->id, 'service_point_shipping_method' ):
				$descr .= 'Ten laatste beschikbaar vanaf '.date_i18n( 'l d/m/Y', $timestamp );
				if ( floatval( $method->cost ) === 0.0 ) {
					$label = str_replace( 'Afhaling', 'Gratis afhaling', $label );
					$label .= ':'.wc_price(0);
				}
				break;
			// Alle instances van thuislevering
			case stristr( $method->id, 'flat_rate' ):
				$descr .= 'Ten laatste geleverd op '.date_i18n( 'l d/m/Y', $timestamp );
				break;
			// Alle instances van gratis thuislevering
			case stristr( $method->id, 'free_shipping' ):
				$descr .= 'Ten laatste geleverd op '.date_i18n( 'l d/m/Y', $timestamp );
				$label .= ':'.wc_price(0);
				break;
			default:
				$descr .= __( 'Geen schatting beschikbaar', 'wc-oxfam' );
				break;
		}
		$descr .= '</small>';
		return $label.'<br>'.$descr;
	}

	// Haal de openingsuren van de node voor een bepaalde dag op
	// WERKT MET DAGINDEXES VAN 0 TOT 6!
	function get_office_hours_for_day( $day, $node = 0 ) {
		global $wpdb;
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		$rows = $wpdb->get_results( 'SELECT * FROM field_data_field_sellpoint_office_hours WHERE entity_id = '.$node.' AND field_sellpoint_office_hours_day = '.$day.' ORDER BY delta ASC' );
		if ( count($rows) > 0 ) {
			$i = 0;
			foreach ( $rows as $row ) {
				$hours[$i]['start'] = format_hour( $row->field_sellpoint_office_hours_starthours );
				$hours[$i]['end'] = format_hour( $row->field_sellpoint_office_hours_endhours );
				$i++;
			}
		} else {
			$hours = false;
		}
		return $hours;
	}

	// Stop de openingsuren in een logische array
	// ANTWOORDT MET DAGINDEXES VAN 1 TOT 7!
	function get_office_hours( $node = 0 ) {
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		for ( $day = 0; $day <= 6; $day++ ) {
			$hours[$day] = get_office_hours_for_day( $day, $node );
		}
		// Forceer 'natuurlijke' nummering
		$hours[7] = $hours[0];
		unset( $hours[0] );
		return $hours;
	}

	// Bereken de eerst mogelijke leverdatum voor de opgegeven verzendmethode (retourneert een timestamp) 
	function estimate_delivery_date( $shipping_id, $order_date = false ) {
		$deadline = get_office_hours();
		
		// We gebruiken het geregistreerde besteltijdstip OF het live tijdstip voor schattingen van de leverdatum
		$from = $order_date ? strtotime($order_date) : current_time( 'timestamp' );
		
		$timestamp = $from;
		write_log($shipping_id);
		write_log( date_i18n( 'd/m/Y H:i', $timestamp ) );
		
		switch ( $shipping_id ) {
			// Alle instances van winkelafhalingen
			case stristr( $shipping_id, 'local_pickup' ):
				// Zoek de eerste werkdag na de volgende middagdeadline
				$timestamp = get_first_working_day( $from );

				// Tel feestdagen die in de verwerkingsperiode vallen erbij
				$timestamp = move_date_on_holidays( $from, $timestamp );
				
				// + ONDERSTEUNING VOOR ALTERNATIEVE WINKELNODES TOEVOEGEN
				
				// Check of de winkel op deze dag effectief nog geopend is na 12u
				$timestamp = find_first_opening_hour( get_office_hours(), $timestamp );

				break;

			// Alle (gratis/betalende) instances van postpuntlevering en thuislevering
			default:
				// Zoek de eerste werkdag na de volgende middagdeadline
				$timestamp = get_first_working_day( $from );

				// Geef nog twee extra werkdagen voor de thuislevering
				$timestamp = strtotime("+2 weekdays", $timestamp);

				// Tel feestdagen die in de verwerkingsperiode vallen erbij
				$timestamp = move_date_on_holidays( $from, $timestamp );

				break;
		}

		write_log( date_i18n( 'd/m/Y H:i', $timestamp ) );		
		return $timestamp;
	}

	// Ontvangt een timestamp en antwoordt met eerste werkdag die er toe doet
	function get_first_working_day( $from ) {
		if ( date_i18n( 'N', $from ) < 6 and date_i18n( 'G', $from ) < 12 ) {
			// Geen actie nodig
		} else {
			// We zitten al na de deadline van een werkdag, begin pas vanaf morgen te tellen
			$from = strtotime( '+1 weekday', $from );
		}

		// Bepaal de eerstvolgende werkdag
		$timestamp = strtotime( '+1 weekday', $from );
		
		return $timestamp;
	}

	// Check of er feestdagen in een bepaalde periode liggen, en zo ja: tel die dagen bij de einddag
	// Neemt een begin- en eindpunt en retourneert het nieuwe eindpunt (allemaal in timestamps)
	function move_date_on_holidays( $from, $till ) {
		global $default_holidays;

		// Check of de startdag ook nog in beschouwing genomen moet worden
		if ( date_i18n( 'N', $from ) < 6 and date_i18n( 'G', $from ) >= 12 ) {
			$first = date_i18n( 'Y-m-d', strtotime( '+1 weekday', $from ) );
		} else {
			$first = date_i18n( 'Y-m-d', $from );
		}
		// In dit formaat zijn datum- en tekstsortering equivalent!
		$last = date_i18n( 'Y-m-d', $till );

		// Vang het niet bestaan van de optie op
		$holidays = get_option( 'oxfam_holidays', $default_holidays );
		
		foreach ( $holidays as $holiday ) {
			// Enkel de feestdagen die niet in het weekend moeten we in beschouwing nemen!
			if ( date_i18n( 'N', strtotime($holiday) ) < 6 and ( $holiday > $first ) and ( $holiday <= $last ) ) {
				$till = strtotime( '+1 weekday', $till );
				$last = date_i18n( 'Y-m-d', $till );
			}
		}

		return $till;
	}

	// Zoek het eerstvolgende openeningsuur op een dag (indien $afternoon: pas vanaf 12u)
	function find_first_opening_hour( $hours, $from, $afternoon = true ) {
		// Argument 'N' want get_office_hours() werkt van 1 tot 7!
		$i = date_i18n( 'N', $from );
		if ( $hours[$i] ) {
			$day_part = $hours[$i][0];
			$start = intval( substr( $day_part['start'], 0, -2 ) );
			$end = intval( substr( $day_part['end'], 0, -2 ) );
			if ( $afternoon ) {
				if ( $end > 12 ) {
					if ( intval( substr( $day_part['start'], 0, -2 ) ) >= 12 ) {
						// Neem het openingsuur van het eerste deel
						$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
					} else {
						// Toon pas mogelijk vanaf 12u
						$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." 12:00" );
					}
				} else {
					unset( $day_part );
					// Ga naar het tweede dagdeel (we gaan er van uit dat er nooit drie zijn!)
					$day_part = $hours[$i][1];
					$start = intval( substr( $day_part['start'], 0, -2 ) );
					$end = intval( substr( $day_part['end'], 0, -2 ) );
					if ( $end > 12 ) {
						if ( intval( substr( $day_part['start'], 0, -2 ) ) >= 12 ) {
							// Neem het openingsuur van dit deel
							$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
						} else {
							// Toon pas mogelijk vanaf 12u
							$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." 12:00" );
						}
					} else {
						// Het mag ook een dag in het weekend zijn, de wachttijd is vervuld!
						$timestamp = find_first_opening_hour( $hours, strtotime('tomorrow midnight'), false );
					}
				}
			} else {
				// Neem sowieso het openingsuur van het eerste dagdeel
				$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
			}
		} else {
			// Vandaag zijn we gesloten, probeer het morgen opnieuw
			// Het mag ook een dag in het weekend zijn, de wachttijd is vervuld!
			$timestamp = find_first_opening_hour( $hours, strtotime('tomorrow midnight'), false );
		}
		return $timestamp;
	}

	// Bewaar het verzendadres niet tijdens het afrekenen indien het om een afhaling gaat EN SERVICE POINT?
	add_filter( 'woocommerce_cart_needs_shipping_address', 'skip_shipping_address_on_pickups' ); 
	
	function skip_shipping_address_on_pickups( $needs_shipping_address ) {
		$chosen_methods = WC()->session->get('chosen_shipping_methods');
		// Deze vergelijking zoekt naar methodes die beginnen met deze string
		if ( strpos( reset($chosen_methods), 'local_pickup' ) !== false or strpos( reset($chosen_methods), 'service_point_shipping_method' ) !== false ) {
			$needs_shipping_address = false;
		}
		return $needs_shipping_address;
	}

	// Verberg het lege verzendadres na het bestellen ook bij een postpuntlevering in de front-end
	add_filter( 'woocommerce_order_hide_shipping_address', 'hide_shipping_address_on_pickups' ); 
	
	function hide_shipping_address_on_pickups( $hide_on_methods, $order ) {
		// Bevat 'local_pickup' reeds via core en 'local_pickup_plus' via filter in plugin
		// Instances worden er afgeknipt bij de check dus achterwege laten
		$hide_on_methods[] = 'service_point_shipping_method';
		return $hide_on_methods;
	}

	function validate_zip_code( $zip ) {
		if ( does_home_delivery() and $zip !== 0 ) {
			if ( ! in_array( $zip, get_site_option( 'oxfam_flemish_zip_codes' ) ) ) {
				wc_add_notice( __( 'Dit is geen geldige Vlaamse postcode!', 'wc-oxfam' ), 'error' );
			} elseif ( ! in_array( $zip, get_option( 'oxfam_zip_codes' ) ) and is_cart() ) {
				// Enkel tonen op de winkelmandpagina, tijdens de checkout gaan we ervan uit dat de klant niet meer radicaal wijzigt (niet afschrikken met error!)
				$str = date_i18n('d/m/Y H:i:s')."\t\t".get_home_url()."\t\tPostcode ingevuld waarvoor deze winkel geen verzending organiseert\n";
				file_put_contents("shipping_errors.csv", $str, FILE_APPEND);
				// Check eventueel of de boodschap al niet in de pijplijn zit door alle values van de array die wc_get_notices( 'error' ) retourneert te checken
				wc_add_notice( __( 'Deze winkel doet geen thuisleveringen naar deze postcode! Kies voor afhaling of keer terug naar het portaal om de webshop te vinden die voor jouw postcode thuislevering organiseert.', 'wc-oxfam' ), 'error' );
			}
		}
	}

	// Zet een maximum op het aantal items dat je kunt toevoegen CHECKT NIET OP REEDS AANWEZIGE ITEMS, NIET INTERESSANT
	// add_action( 'woocommerce_add_to_cart_validation', 'maximum_item_quantity_validation' );

	function maximum_item_quantity_validation( $passed, $product_id, $quantity, $variation_id, $variations ) {
		if ( $quantity > 10 ) {
			wc_add_notice( 'Je kunt maximum 10 exemplaren van een product toevoegen aan je winkelmandje.', 'error' );
		} else {
			return true;
		}
	}

	// Moedig aan om naar 100 euro te gaan (gratis thuislevering)
	add_action( 'woocommerce_before_cart', 'show_almost_free_shipping_notice' );

	function show_almost_free_shipping_notice() {
		if ( is_cart() ) {
			$threshold = 100;
			// Subtotaal = winkelmandje inclusief belasting, exclusief verzending
			$current = WC()->cart->subtotal;
			if ( $current > 80 ) {
				if ( $current < $threshold ) {
					// Probeer de boodschap slechts af en toe te tonen via sessiedata
					$cnt = WC()->session->get( 'go_to_100_message_count', 0 );
					// Opgelet: WooCoomerce moet actief zijn, we moeten in de front-end zitten én er moet al een winkelmandje aangemaakt zijn!
					WC()->session->set( 'go_to_100_message_count', $cnt+1 );
					if ( $cnt % 7 === 0 ) {
						wc_print_notice( 'Tip: als je nog '.wc_price( $threshold - $current ).' toevoegt, kom je in aanmerking voor gratis thuislevering.', 'success' );
					}
				}
			} else {
				WC()->session->set( 'go_to_100_message_count', 0 );
			}
		}
	}
	
	// Disable sommige verzendmethoden onder bepaalde voorwaarden
	add_filter( 'woocommerce_package_rates', 'hide_shipping_recalculate_taxes', 10, 2 );
	
	function hide_shipping_recalculate_taxes( $rates, $package ) {
		global $woocommerce;
		validate_zip_code( intval( $woocommerce->customer->get_shipping_postcode() ) );

		// Check of er een gratis levermethode beschikbaar is => uniform minimaal bestedingsbedrag!
		$free_home_available = false;
		foreach ( $rates as $rate ) {
			if ( $rate->method_id === 'free_shipping' ) {
				$free_home_available = true;
				break;
			}
		}

		if ( $free_home_available ) {
			// Verberg alle betalende methodes indien er een gratis thuislevering beschikbaar is
			foreach ( $rates as $rate_key => $rate ) {
				if ( floatval( $rate->cost ) > 0.0 ) {
					unset( $rates[$rate_key] );
				}
			}
		} else {
			// Verberg alle gratis methodes die geen afhaling zijn
			foreach ( $rates as $rate_key => $rate ) {
				if ( $rate->method_id !== 'local_pickup_plus' and floatval( $rate->cost ) === 0.0 ) {
					// IS DIT WEL NODIG, ZIJ WORDEN TOCH AL VERBORGEN DOOR WOOCOMMERCE?
					// unset( $rates[$rate_key] );
				}
			}
		}

		// Verhinder alle externe levermethodes indien er een product aanwezig is dat niet thuisgeleverd wordt
		$forbidden_cnt = 0;
		foreach( WC()->cart->cart_contents as $item_key => $item_value ) {
			if ( $item_value['data']->get_shipping_class() === 'glas' ) {
				$forbidden_cnt = $forbidden_cnt + $item_value['quantity'];
			} 
		}
		if ( $forbidden_cnt > 0 ) {
			foreach ( $rates as $rate_key => $rate ) {
				// Blokkeer alle methodes behalve afhalingen
				if ( $rate->method_id !== 'local_pickup_plus' ) {
					unset( $rates[$rate_key] );
				}
			}
			wc_add_notice( __( 'Je winkelmandje bevat '.( $forbidden_cnt - floor( $forbidden_cnt / 6 ) ).' grote flessen die te fragiel zijn om te worden verzonden. Kom je bestelling afhalen in de winkel, of verwijder dit fruitsap uit je winkelmandje zodat thuislevering weer beschikbaar wordt.', 'wc-oxfam' ), 'error' );
		}

		// Verhinder alle externe levermethodes indien totale brutogewicht > 29 kg (neem 1 kg marge voor verpakking)
		$cart_weight = wc_get_weight( $woocommerce->cart->cart_contents_weight, 'kg' );
		if ( $cart_weight > 29 ) {
			foreach ( $rates as $rate_key => $rate ) {
				// Blokkeer alle methodes behalve afhalingen
				if ( $rate->method_id !== 'local_pickup_plus' ) {
					unset( $rates[$rate_key] );
				}
			}
			wc_add_notice( __( 'Je bestelling is te zwaar voor thuislevering ('.number_format( $cart_weight, 1, ',', '.' ).' kg). Gelieve ze te komen afhalen in de winkel!', 'wc-oxfam' ), 'error' );
		}

		$low_vat_slug = 'voeding';
		$low_vat_rates = WC_Tax::get_rates_for_tax_class( $low_vat_slug );
		$low_vat_rate = reset( $low_vat_rates );
		
		// Slug voor de 'standard rate' is een lege string!
		$standard_vat_rates = WC_Tax::get_rates_for_tax_class( '' );
		$standard_vat_rate = reset( $standard_vat_rates );
		
		$tax_classes = $woocommerce->cart->get_cart_item_tax_classes();
		if ( ! in_array( $low_vat_slug, $tax_classes ) ) {
			// Brutoprijs verlagen om te compenseren voor hoger BTW-tarief
			$cost = 5.7438;
			// Ook belastingen expliciet herberekenen!
			$taxes = $cost*0.21;
			$tax_id_free = $low_vat_rate->tax_rate_id;
			$tax_id_cost = $standard_vat_rate->tax_rate_id;
		} else {
			$cost = 6.5566;
			// Deze stap doen we vooral omwille van het wispelturige gedrag van deze tax
			$taxes = $cost*0.06;
			$tax_id_free = $standard_vat_rate->tax_rate_id;
			$tax_id_cost = $low_vat_rate->tax_rate_id;
		}
		
		// Overschrijf alle verzendprijzen (dus niet enkel in 'uitsluitend 21%'-geval -> te onzeker) indien betalende thuislevering
		if ( ! $free_home_available ) {
			foreach ( $rates as $rate_key => $rate ) {
				switch ( $rate_key ) {
					case in_array( $rate->method_id, array( 'flat_rate', 'service_point_shipping_method' ) ):
						$rate->cost = $cost;
						$rate->taxes[$tax_id_free] = 0.0;
						$rate->taxes[$tax_id_cost] = $taxes;
						break;
					default:
						// Dit zijn de gratis pick-ups (+ eventueel thuisleveringen), niets mee doen
						break;
				}
			}
		}

		write_log("BIJGEWERKTE RATES");
		write_log($rates);

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

	// Voeg instructietekst toe boven de locaties
	// add_action( 'woocommerce_review_order_before_local_pickup_location', 'add_local_pickup_instructions' ) {
	
	function add_local_pickup_instructions() {
		echo '<p>Je kunt kiezen uit deze winkels ...</p>';
	}

	// Check of de persoon moet worden ingeschreven op het digizine 
	add_action( 'woocommerce_checkout_process', 'check_subscription_preference', 10, 1 );

	function check_subscription_preference( $posted ) {
		global $user_ID, $woocommerce;
		if ( ! empty($posted['subscribe_digizine']) ) {
			if ( $posted['subscribe_digizine'] !== 1 ) {
				// wc_add_notice( __( 'Oei, je hebt ervoor gekozen om je niet te abonneren op het digizine, hoe kan dat nu?', 'woocommerce' ), 'error' );
				// wc_add_notice( __( 'Ik ben een blokkerende melding die verhindert dat je nu al afrekent, '.get_user_meta( $user_ID, 'first_name', true ).'.', 'wc-oxfam' ), 'error' );
			}
		}

		// Eventueel bestelminimum om te kunnen afrekenen
		if ( round( $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total, 2 ) < 10 ) {
	  		wc_add_notice( __( 'Online bestellingen van minder dan 10 euro kunnen we niet verwerken.', 'wc-oxfam' ), 'error' );
	  	} elseif ( round( $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total, 2 ) > 500 ) {
	  		wc_add_notice( __( 'Dit is een wel erg grote bestelling. Neem contact met ons op om te bekijken of we dit wel tijdig kunnen leveren.', 'wc-oxfam' ), 'error' );
	  	}
	}

	// Verberg de 'kortingsbon invoeren'-boodschap bij het afrekenen
	add_filter( 'woocommerce_checkout_coupon_message', 'remove_msg_filter' );

	function remove_msg_filter( $msg ) {
		if ( is_checkout() ) {
		    return "";
		}
		return $msg;
	}

	// Voeg bakken leeggoed enkel toe per 6 of 24 flessen
	add_filter( 'wc_force_sell_add_to_cart_product', 'check_plastic_empties_quantity', 10, 2 );

	function check_plastic_empties_quantity( $empties, $product_item ) {
		$empties_product = wc_get_product( $empties['id'] );
		// Zou niet mogen, maar toch even checken
		if ( $empties_product !== false ) {
			switch ( $empties_product->get_sku() ) {
				case 'WLBS6M':
					$empties['quantity'] = floor( intval($product_item['quantity']) / 6 );
					break;
				case 'WLBS24M':
					$empties['quantity'] = floor( intval($product_item['quantity']) / 24 );
					break;
			}
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
			// PROBLEEM: BAK WORDT ENKEL TOEGEVOEGD BIJ 6/24 IDENTIEKE FLESSEN
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
			}
		}

		return $count - $subtract;
	}
	

	############
	# SETTINGS #
	############

	// Voeg optievelden toe
	add_action( 'admin_init', 'register_oxfam_settings' );

	// Let op: $option_group = $page in de oude documentatie!
	function register_oxfam_settings() {
		register_setting( 'oxfam-options-global', 'oxfam_shop_node', 'absint' );
		register_setting( 'oxfam-options-global', 'oxfam_mollie_partner_id', 'absint' );
		register_setting( 'oxfam-options-global', 'oxfam_zip_codes', 'comma_string_to_array' );
		register_setting( 'oxfam-options-global', 'oxfam_member_shops', 'comma_string_to_array' );
		register_setting( 'oxfam-options-local', 'oxfam_holidays', 'comma_string_to_array' );
	}

	// Zorg ervoor dat je lokale opties ook zonder 'manage_options'-rechten opgeslagen kunnen worden
	add_filter( 'option_page_capability_oxfam-options-local', 'lower_manage_options_capability' );
	
	function lower_manage_options_capability( $cap ) {
    	return 'manage_woocommerce';
    }

	function comma_string_to_array( $values ) {
		$values = preg_replace( "/\s/", "", $values );
		$values = preg_replace( "/\//", "-", $values );
		$array = (array)preg_split( "/(,|;|&)/", $values, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $array as $key => $value ) {
			$array[$key] = mb_strtolower( trim($value) );
			// Verwijder datums uit het verleden (woorden van toevallig 10 tekens kunnen niet voor een datum komen!)
			if ( strlen( $array[$key] ) === 10 and $array[$key] < date_i18n('Y-m-d') ) {
				unset( $array[$key] );
			}
		}
		sort($array, SORT_STRING);
		return $array;
	}

	// Voeg een custom pagina toe onder de algemene opties
	add_action( 'admin_menu', 'custom_oxfam_options' );

	function custom_oxfam_options() {
		add_media_page( 'Productfoto\'s', 'Productfoto\'s', 'create_sites', 'oxfam-photos', 'oxfam_photos_callback' );
		add_menu_page( 'Stel de voorraad van je lokale webshop in', 'Voorraadbeheer', 'manage_woocommerce', 'oxfam-products-photos', 'oxfam_products_photos_callback', 'dashicons-admin-settings', '56' );
		add_submenu_page( 'oxfam-products-photos', 'Stel de voorraad van je lokale webshop in', 'Fotoweergave', 'manage_woocommerce', 'oxfam-products-photos', 'oxfam_products_photos_callback' );
		add_submenu_page( 'oxfam-products-photos', 'Stel de voorraad van je lokale webshop in', 'Lijstweergave', 'create_sites', 'oxfam-product-list', 'oxfam_products_list_callback' );
		add_menu_page( 'Handige gegevens voor je lokale webshop', 'Winkelgegevens', 'manage_woocommerce', 'oxfam-options', 'oxfam_options_callback', 'dashicons-megaphone', '58' );
	}

	function oxfam_photos_callback() {
		include get_stylesheet_directory().'/register-bulk-images.php';
	}

	function oxfam_options_callback() {
		include get_stylesheet_directory().'/update-options.php';
	}

	function oxfam_products_photos_callback() {
		include get_stylesheet_directory().'/update-stock-photos.php';
	}

	function oxfam_products_list_callback() {
		include get_stylesheet_directory().'/update-stock-list.php';
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
				$msg .= "<i>".$filename."</i> ".$deleted." in de mediabibliotheek om ".date_i18n('H:i:s')." ...";
			} else {
				$msg .= "<i>".$filename."</i> aangemaakt in de mediabibliotheek om ".date_i18n('H:i:s')." ...";
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
	// Icoontje wordt toegevoegd op basis van CSS-klasse .product_shipping_class-glas
	add_action( 'woocommerce_single_product_summary', 'show_delivery_warning', 45 );

	function show_delivery_warning() {
		global $product;
		if ( $product->get_shipping_class() === 'glas' ) {
			echo "Opgelet, dit product kan enkel afgehaald worden in de winkel! Tip: kleine glazen flesjes en tetrabrikken zijn wel beschikbaar voor thuislevering.";
		}
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

		// Indien sluitingsdag: toon een banner dat we vandaag uitzonderlijk gesloten zijn
		global $default_holidays;
		// Wordt bij elke paginaweergave uitgevoerd, dus niet echt efficiënt
		// Boodschap personaliseren? Eerste werkdag zoeken na vakantie?
		update_option('woocommerce_demo_store_notice', 'We zijn vandaag uitzonderlijk gesloten. Bestellingen worden opnieuw verwerkt vanaf de eerstvolgende openingsdag. De geschatte leverdatum houdt hiermee rekening.');
		// Wijkt 2 dagen af, maar kom
		if ( in_array( date_i18n('Y-m-d'), get_option('oxfam_holidays', $default_holidays) ) ) {
			update_option('woocommerce_demo_store', 'yes');
		} else {
			update_option('woocommerce_demo_store', 'no');
		}
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
			// Geef catmans rechten om zelf termen toe te kennen / te bewerken / toe te voegen
			'capabilities' => array( 'assign_terms' => 'edit_products', 'edit_terms' => 'edit_products', 'manage_terms' => 'edit_products', 'delete_terms' => 'create_sites' ),
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
			// Allergenen zullen in principe nooit meer toegevoegd moeten worden, dus catmans enkel rechten geven op toekenning
			'capabilities' => array( 'assign_terms' => 'edit_products', 'edit_terms' => 'create_sites', 'manage_terms' => 'create_sites', 'delete_terms' => 'create_sites' ),
			'rewrite' => array( 'slug' => 'allergen', 'with_front' => false ),
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
		$eh = $product->get_attribute( 'pa_eenheid' );
		if ( $eh === 'L' ) {
			$suffix = 'ml';
		} elseif ( $eh === 'KG' ) {
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
					$forbidden = array( 'pa_ompak', 'pa_ompak_ean', 'pa_pal_perlaag', 'pa_pal_lagen', 'pa_eenheid' );
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
								// echo var_dump($values);
								echo '<i>'.apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values ).'</i>';
							} else {
								echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );
								// echo var_dump($values);
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

	// Retourneert een array met strings van landen waaruit dit product afkomstig is
	function get_countries_by_product( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_partner' );
		$args = array( 'taxonomy' => 'product_partner', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' );
		$continents = get_terms( $args );
		
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->parent, $continents, true ) ) {
				// De bovenliggende term is geen continent, dus het is een partner!
				$parent_term = get_term_by( 'id', $term->parent, 'product_partner' );
				// Voeg de naam van de bovenliggende term (= land) toe aan het lijstje
				$countries[] = $parent_term->name;
			} else {
				// In dit geval is het zeker een land (en zeker geen continent zijn want checkboxes uitgeschakeld + enkel gelinkt aan laagste term)
				$countries[] = $term->name;
			}
		}

		// Ontdubbel de landen en sorteer values alfabetisch
		$countries = array_unique( $countries );
		sort($countries, SORT_STRING);
		return $countries;
	}

	// Retourneert een array term_id => name van de partners die bijdragen aan het product
	function get_partner_terms_by_product( $product ) {
		// Vraag alle partnertermen op die gelinkt zijn aan dit product (helaas geen filterargumenten beschikbaar)
		// Producten worden door de import enkel aan de laagste hiërarchische term gelinkt, dus dit zijn per definitie landen of partners!
		$terms = get_the_terms( $product->get_id(), 'product_partner' );
		
		// Vraag de term-ID's van de continenten in deze site op
		$args = array( 'taxonomy' => 'product_partner', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' );
		$continents = get_terms( $args );
		
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->parent, $continents, true ) ) {
				// De bovenliggende term is geen continent, dus het is een partner!
				$partners[$term->term_id] = $term->name;
			}
		}

		// Sorteer alfabetisch op value (= partnernaam) maar bewaar de index (= term-ID)
		asort($partners);
		return $partners;
	}

	// Retourneert zo veel mogelijk beschikbare info bij een partner (enkel naam en land steeds ingesteld!)
	function get_info_by_partner( $partner ) {
		global $wpdb;
		$partner_info['name'] = $partner->name;
		$partner_info['country'] = get_term_by( 'id', $partner->parent, 'product_partner' )->name;
		
		// Let op: de naam is een string, dus er moeten quotes rond!
		// NOGAL ONZEKERE MANIER OM DE JUISTE MATCH TE VINDEN, LIEVER VIA NODE IN TERMBESCHRIJVING
		$row = $wpdb->get_row( 'SELECT * FROM partners WHERE part_naam = "'.$partner_info['name'].'"' );
		
		if ( $row and strlen( $row->part_website ) > 5 ) {
			// Knip het woord 'node/' er af
			$partner_info['node'] = intval( substr( $row->part_website, 5 ) );
			$partner_info['url'] = 'https://www.oxfamwereldwinkels.be/node/'.$partner_info['node'];
			
			$quote = $wpdb->get_row( 'SELECT * FROM field_data_field_manufacturer_quote WHERE entity_id = '.$partner_info['node'] );
			if ( strlen( $quote->field_manufacturer_quote_value ) > 20 ) {
				$partner_info['quote'] = trim($quote->field_manufacturer_quote_value);
				$quote_by = $wpdb->get_row( 'SELECT * FROM field_data_field_manufacturer_hero_name WHERE entity_id = '.$partner_info['node'] );
				if ( strlen( $quote_by->field_manufacturer_hero_name_value ) > 5 ) {
					$partner_info['quote_by'] = trim($quote_by->field_manufacturer_hero_name_value);
				}
			}
		} else {
			// Val terug op de termbeschrijving die misschien toegevoegd is
			// $url = explode('href="', $partner->description);
			// $parts = explode('"', $url[1]);
			// $partner_info['url'] = $parts[0];
		}

		return $partner_info;
	}

	// Output de info over de partners
	function partner_tab_content() {
		global $product;
		echo '<div class="nm-additional-information-inner">';
			$alt = 1;
			ob_start();
			?>
			<table class="shop_attributes">
				
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th>Herkomstlanden</th>
					<td><?php
						$i = 1;
						$str = '/';
						$countries = get_countries_by_product( $product );
						if ( count( $countries ) > 0 ) {
							foreach ( $countries as $country ) {
								if ( $i === 1 ) {
									$str = $country;
								} else {
									$str .= '<br>'.$country;
								}
								$i++;
							}
						}
						echo $str;
					?></td>
				</tr>

				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th>Onze partners</th>
					<td><?php
						$i = 1;
						$msg = '<i>(aankoop via andere fairtradeorganisatie)</i>';

						$partners = get_partner_terms_by_product( $product );
						if ( count( $partners ) > 0 ) {
							foreach ( $partners as $term_id => $partner_name ) {
								$partner_info = get_info_by_partner( get_term_by( 'id', $term_id, 'product_partner' ) );
								
								if ( isset( $partner_info['url'] ) and strlen( $partner_info['url'] ) > 10 ) {
									$text = '<a href="'.$partner_info['url'].'" target="_blank" title="Lees meer info over deze partner op de site van Oxfam-Wereldwinkels">'.$partner_info['name'].'</a>';
								} else {
									$text = $partner_info['name'];
								}
								
								if ( $i === 1 ) {
									$msg = $text;
								} else {
									$msg .= '<br>'.$text;
								}

								$i++;
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

	add_action( 'woocommerce_single_product_summary', 'show_random_partner_quote', 75 );

	function show_random_partner_quote() {
		global $product;
		$partners = get_partner_terms_by_product( $product );
		if ( count( $partners ) > 0 ) {
			// Sla enkel de partners op waarvan de info een ondertekende quote bevat 
			foreach ( $partners as $term_id => $partner_name ) {
				$partner_info = get_info_by_partner( get_term_by( 'id', $term_id, 'product_partner' ) );
				if ( isset( $partner_info['quote'] ) or isset( $partner_info['quote_by'] ) ) {
					$partners_with_quote[] = $partner_info;
				}
			}
			// Toon een random quote
			if ( count( $partners_with_quote ) > 0 ) {
				$i = random_int( 0, count($partners_with_quote) - 1 );
				echo nm_shortcode_nm_testimonial( array( 'signature' => $partners_with_quote[$i]['quote_by'], ), '&laquo; '.$partners_with_quote[$i]['quote'].' &raquo;' );
				// echo '<p>&laquo; '.$partners_with_quote[$i]['quote'].' &raquo;</p>';
				// echo '<p style="font-style: italic; text-align: right;">('.$partners_with_quote[$i]['quote_by'].')</p>';
			}
		}
	}

	function create_product_pdf( $product ) {
		require_once WP_CONTENT_DIR."/plugins/html2pdf/html2pdf.class.php";
		
		$templatelocatie = WP_CONTENT_DIR."/themes/savoy-child/productfiche.html";
		$templatefile = fopen($templatelocatie, "r");
		$templatecontent = fread($templatefile, filesize($templatelocatie));
		
		$sku = $product->get_sku();
		$templatecontent = str_replace("#artikel", $sku, $templatecontent);
		$templatecontent = str_replace("#prijs", wc_price( $product->get_price() ), $templatecontent);
		$templatecontent = str_replace("#merk", $product->get_attribute('pa_merk'), $templatecontent);
		
		$pdffile = new HTML2PDF("P", "A4", "nl");
		$pdffile->pdf->SetAuthor("Oxfam Fair Trade cvba");
		$pdffile->pdf->SetTitle("Productfiche ".$sku);
		$pdffile->WriteHTML($templatecontent);
		$pdffile->Output(WP_CONTENT_DIR."/".$sku.".pdf", "F");
	}

	// Formatteer de gewichten in de attributen
	add_filter( 'woocommerce_attribute', 'add_weight_suffix', 10, 3 );

	function add_weight_suffix( $wpautop, $attribute, $values ) {
		$weighty_attributes = array( 'pa_choavl', 'pa_famscis', 'pa_fapucis', 'pa_fasat', 'pa_fat', 'pa_fibtg', 'pa_polyl', 'pa_pro', 'pa_salteq', 'pa_starch', 'pa_sugar' );
		$percenty_attributes = array( 'pa_alcohol', 'pa_fairtrade' );
		$energy_attributes = array( 'pa_ener' );

		// HOE BEPALEN WE MET WELKE PRODUCT-ID WE HIER BEZIG ZIJN? => GLOBAL WERKT
		global $product;
		$eh = $product->get_attribute( 'pa_eenheid' );
		if ( $eh === 'L' ) {
			$suffix = 'liter';
		} elseif ( $eh === 'KG' ) {
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
	// add_action( 'pmxi_saved_post', 'update_calculated_attributes', 10, 1 );
	add_action( 'pmxi_saved_post', 'call_wp_update_post', 10, 1 );

	function call_wp_update_post( $post_id ) {
		$my_post = array();
        $my_post['ID'] = $post_id;
        $my_post['post_content'] = "FREDTEST";
        // DOET NIETS
        // wp_update_post( $my_post );
        // MOET INGEROEPEN WORDEN OP WOO_MSTORE_ADMIN_PRODUCT KLASSE
        // process_product( $post_id, get_post( $post_id ), true );
        do_action( 'woocommerce_process_product_meta', $post_id, get_post( $post_id ) );
	}

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
	// switch_to_blog( $blog_id );
	// update_option('uploads_use_yearmonth_folders', false);
	// restore_current_blog();

	// Verhinder dat de lokale voorraad- en uitlichtingsinstellingen overschreven worden bij elke update
	add_filter( 'woo_mstore/save_meta_to_post/ignore_meta_fields', 'ignore_featured_and_stock', 10, 2);

	function ignore_featured_and_stock( $ignored_fields, $post_id ) {
		$ignored_fields[] = '_stock';
		$ignored_fields[] = '_stock_status';
		$ignored_fields[] = 'total_sales';
		$ignored_fields[] = '_wc_review_count';
		$ignored_fields[] = '_wc_rating_count';
		$ignored_fields[] = '_wc_average_rating';
		return $ignored_fields;
	}

	// Functie die post-ID's van de hoofdsite vertaalt en het metaveld opslaat in de huidige subsite (op basis van artikelnummer)
	/**
    * @param int $local_product_id
    * @param string $metakey
    * @param array $product_meta_item_row
    */	
    function translate_main_to_local_ids( $local_product_id, $metakey, $product_meta_item_row ) {
        if ( $product_meta_item_row ) {
            foreach ( $product_meta_item_row as $main_product_id ) {
                switch_to_blog( 1 );
                $main_product = wc_get_product( $main_product_id );
                restore_current_blog();
                $local_product_ids[] = wc_get_product_id_by_sku( $main_product->get_sku() );
            }
            update_post_meta( $local_product_id, $metakey, $local_product_ids );
        } else {
        	// Zorg ervoor dat het veld ook bij de child geleegd wordt!
        	update_post_meta( $local_product_id, $metakey, null );
        }
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
		echo "<p>De eerste ruwe versie van de <a href='https://github.com/OxfamFairTrade/ob2c/wiki' target='_blank'>online FAQ voor webshopbeheerders</a> staat inmiddels online. Hierin verzamelen we alle mogelijke vragen die jullie als lokale webshopbeheerders kunnen hebben en beantwoorden we ze punt per punt met tekst en screenshots. Daarnaast kun je nog altijd <a href='https://demo.oxfamwereldwinkels.be/wp-content/uploads/slides-1ste-opleiding-B2C-webshop.pdf' target='_blank'>de slides van de 1ste opleidingssessie</a> raadplegen voor een overzicht van alle afspraken.</p>";
		echo "<p>Met name op het vlak van lay-out (lokale startpagina en nationale portaalpagina) en communicatie (werkwijze, bevestigingsmails en algemene voorwaarden) is er in mei nog werk aan de winkel. De technische basisstructuur is echter voldoende solide, dus bij deze is het definitief: we lanceren op 1 juni! Vanaf 22 mei kun je als deelnemende winkel een mail verwachten met alle toegangsgegevens voor jullie persoonlijke webshop (mailbox, website, Mollie en eventueel SendCloud).</p>";
		echo "<p>Dit zijn <a href='https://demo.oxfamwereldwinkels.be/wp-content/uploads/slides-2de-opleiding-B2C-webshop.pdf' target='_blank'>de slides van de 2de opleidingssessie</a>. Voor onderling overleg (en met ons) kun je vanaf nu ook terecht op <a href='https://oxfamfairtrade.slack.com' target='_blank'>Slack</a>. Wie zich hiervoor aanmeldde, ontvangt op 08/05/2017 een uitnodigingsmail. Dit is optioneel, alle belangwekkende documenten blijven we op dit dashboard en op <a href='http://extranet.oxfamwereldwinkels.be/webshop target='_blank'>Extranet</a> plaatsen. Voor dringende problemen bel je vanaf 1 juni gewoon naar de Klantendienst, die een opleiding krijgt om jullie bij te staan bij het beheer.</p>";
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

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date_i18n( 'Y-m-d', strtotime('-9 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );
		
		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			
			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a class="rsswidget" href="'.$campaign->long_archive_url.'" target="_blank">'.str_replace( '*|FNAME|*: ', '', $campaign->settings->subject_line ).'</a> ('.date_i18n( 'j F Y', strtotime($campaign->send_time) ).')</li>';
			}
		}		

		return $mailings;
	}

	// Voeg een bericht toe bovenaan alle adminpagina's
	add_action( 'admin_notices', 'sample_admin_notice' );

	function sample_admin_notice() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		// var_dump($screen);
		if ( $pagenow === 'index.php' and $screen->base === 'dashboard' ) {
			// echo '<div class="notice notice-info">';
			if ( get_option( 'mollie-payments-for-woocommerce_test_mode_enabled' ) === 'yes' ) {
				// echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust bestellingen te plaatsen en te beheren.</p>';
			} else {
				// echo '<p>Opgelet: de betalingen op deze site zijn momenteel live! Tip: betaal je bestelling achteraf volledig terug door een refund uit te voeren via het platform.</p>';
			}
			// echo '<p>De mailing geraakte vrijdag niet meer de deur uit maar komt er tegen vanavond aan! Bekijk alvast <a href="http://www.gemeentekaart.be/#6d86cbca-8d77-43c3-8cb5-3c3fa09a6ca6" target="_blank">de bijgewerkte postcodekaart</a>.</p>';
			// echo '</div>';
			echo '<div class="notice notice-info">';
			echo '<p>Download <a href="http://demo.oxfamwereldwinkels.be/wp-content/uploads/verzendtarieven-B2C-pakketten.pdf" target="_blank">de nota met tarieven en voorwaarden</a> bij externe verzending via Bpost. Aangezien geen enkele groep aangaf interesse te hebben in verzending via Bubble Post, stoppen we geen werk meer in de integratie met hun systemen.</p><p>Goed nieuws: van de BTW-lijn kregen we te horen dat we 6% BTW mogen rekenen op thuisleveringen (bijzaak volgt hoofdzaak). Enkel indien de bestelling <u>volledig</u> uit voedingsproducten aan standaard BTW-tarief bestaat (= alcoholische dranken) moeten we ook 21% BTW rekenen op de verzending. We passen de prijs voor de consument voorlopig niet aan, dus in de praktijk zal de winkel doorgaans 6,56 i.p.v. 5,74 euro netto overhouden. Dit geeft wat meer ruimte om te investeren in fietskoeriers en/of degelijk verpakkingsmateriaal.</p>';
			echo '</div>';
		}
		if ( $pagenow === 'edit.php' and $post_type === 'product' and current_user_can( 'edit_products' ) ) {
			// echo '<div class="notice notice-warning">';
			// echo '<p>Hou er rekening mee dat alle volumes in g / ml ingegeven worden, zonder eenheid!</p>';
			// echo '</div>';
		}
		if ( $pagenow === 'admin.php' and $screen->parent_base === 'oxfam-products-photos' ) {
			echo '<div class="notice notice-info">';
			echo '<p>Een compactere lijstweergave is in de maak! We zullen ook verhinderen dat je niet-voorradige producten nog langer in de kijker kunt zetten.</p>';
			echo '</div>';
		}
	}

	// Schakel onnuttige widgets uit voor iedereen
	// KAN OOK VIA USER ROLE EDITOR PRO
	add_action( 'admin_init', 'remove_dashboard_meta' );

	function remove_dashboard_meta() {
		remove_meta_box( 'dashboard_primary', 'dashboard-network', 'normal' );
		remove_meta_box( 'network_dashboard_right_now', 'dashboard-network', 'normal' );
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		// remove_meta_box( 'dashboard_pilot_news_widget', 'dashboard', 'normal' );
		// remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item-network', 'side' );
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	// Beheerd via WooCommerce Order Status Manager of is dit voor het dashboard?
	// add_filter( 'woocommerce_reports_get_order_report_data_args', 'wc_reports_get_order_custom_report_data_args', 100, 1 );

	function wc_reports_get_order_custom_report_data_args( $args ) {
		$args['order_status'] = array( 'on-hold', 'processing', 'claimed', 'completed' );
		return $args;
	};

	function get_latest_newsletters() {
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '5cce3040aa';
		$folder_id = 'bbc1d65c43';

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date_i18n( 'Y-m-d', strtotime('-6 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );

		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			$mailings .= "<p>Dit zijn de nieuwsbrieven van de afgelopen zes maanden:</p>";
			$mailings .= "<ul style='margin-left: 20px; margin-bottom: 1em;'>";

			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a href="'.$campaign->long_archive_url.'" target="_blank">'.$campaign->settings->subject_line.'</a> ('.date_i18n( 'j F Y', strtotime($campaign->send_time) ).')</li>';
			}

			$mailings .= "</ul>";
		}		

		return $mailings;
	}

	function get_mailchimp_status() {
		$cur_user = wp_get_current_user();
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '5cce3040aa';
		$email = $cur_user->user_email;
		$member = md5( mb_strtolower($email) );

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
				$msg .= "al geabonneerd op het Digizine. Aan het begin van elke maand ontvang je dus een (h)eerlijke mail boordevol fairtradenieuws. (Even checken of je dit al nagelezen hebt, Griet!)";
			} else {
				$msg .= "helaas niet langer geabonneerd op het Digizine. Vul <a href='http://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$cur_user->user_firstname."&LNAME=".$cur_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om op je stappen terug te keren!";
			}
		} else {
			$msg .= "nog nooit geabonneerd geweest op het Digzine. Vul <a href='http://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$cur_user->user_firstname."&LNAME=".$cur_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om daar verandering in te brengen!";
		}

		return "<p>Je bent met het e-mailadres <a href='mailto:".$email."' target='_blank'>".$email."</a> ".$msg."</p>";
	}


	##############
	# SHORTCODES #
	##############

	// Personaliseer de begroeting op de startpagina
	add_shortcode( 'topbar', 'print_welcome' );
	add_shortcode( 'bezoeker', 'print_customer' );
	add_shortcode( 'copyright', 'print_copyright' );
	add_shortcode( 'straat', 'print_place' );
	add_shortcode( 'postcode', 'print_zipcode' );
	add_shortcode( 'gemeente', 'print_city' );
	add_shortcode( 'telefoon', 'print_telephone' );
	add_shortcode( 'e-mail', 'print_mail' );
	add_shortcode( 'openingsuren', 'print_office_hours' );
	add_shortcode( 'toon_inleiding', 'print_summary' );
	add_shortcode( 'toon_shops', 'print_shop_selection' );
	add_shortcode( 'toon_kaart', 'print_map' );
	add_shortcode( 'widget_usp', 'print_widget_usp' );
	add_shortcode( 'widget_delivery', 'print_widget_delivery' );
	add_shortcode( 'widget_contact', 'print_widget_contact' );
	add_shortcode( 'company_name', 'get_company_name' );
	add_shortcode( 'contact_address', 'get_company_contact' );
	add_shortcode( 'map_address', 'get_company_address' );
	add_shortcode( 'email_footer', 'get_company_and_year' );

	function print_widget_usp() {
		$msg = "Omdat de internationale handel eerlijker moet, en kàn. Dat bewijzen we elke dag opnieuw. Met je aankoop bieden we partners in het Zuiden een waardig bestaan. Vanaf nu zelfs vanuit je luie zetel!";
		return do_shortcode('[nm_feature icon="pe-7s-star" layout="centered" title="Fair trade. Altijd. Overal." icon_color="#282828"]'.$msg.'[/nm_feature]');
	}

	function print_widget_delivery() {
		if ( does_home_delivery() ) {
			$msg = "Alles wat je vóór 12 uur 's ochtends bestelt, wordt ten laatste drie werkdagen later door onze vrijwiligers bij je thuis geleverd. Afhalen in de winkel kan natuurlijk ook. Exotische continenten leken nog nooit zo dichtbij!";
		} else {
			$msg = "Alles wat je vóór 12 uur 's ochtends bestelt, kan je de volgende werkdag al afhalen in de winkel. (Kijk naar de schatting naast je winkelmandje.) Exotische continenten leken nog nooit zo dichtbij!";
		}
		return do_shortcode('[nm_feature icon="pe-7s-cart" layout="centered" title="Wereldproducten. Lokaal geleverd." icon_color="#282828"]'.$msg.'[/nm_feature]');
	}

	function print_widget_contact() {
		return do_shortcode('[nm_feature icon="pe-7s-mail" layout="centered" title="Eerlijk handelen. Ook met jou." icon_color="#282828"]Ook met onze klanten willen we fair zaken doen. We communiceren transparant en formuleren heldere beloftes. Heb je toch nog vragen? <a href="mailto:'.get_company_email().'">Stuur ons een mail</a> of bel naar '.get_oxfam_shop_data( 'telephone' ).'.[/nm_feature]');
	}

	function print_welcome() {
		if ( date_i18n('G') < 6 ) {
			$greet = "Goeienacht";
		} elseif ( date_i18n('G') < 12 ) {
			$greet = "Goeiemorgen";
		} elseif ( date_i18n('G') < 20 ) {
			$greet = "Goeiemiddag";
		} else {
			$greet = "Goeieavond";
		}
		return $greet." ".print_customer()."! Welkom op de webshop van ".get_company_name().".";
	}

	function print_customer() {
		global $current_user;
		return ( is_user_logged_in() and strlen($current_user->user_firstname) > 1 ) ? $current_user->user_firstname : "bezoeker";
	}

	function print_copyright() {
		if ( get_option('oxfam_shop_node') ) {
			$node = 'node/'.get_option('oxfam_shop_node');
		} else {
			$node = 'nl';
		}
		return "<a href='https://www.oxfamwereldwinkels.be/".$node."' target='_blank'>".get_company_name()." &copy; 2016-".date_i18n('Y')."</a>";
	}

	function print_office_hours( $atts = [] ) {
		// Overschrijf defaults met expliciete data van de gebruiker
		$atts = shortcode_atts( array( 'node' => get_option( 'oxfam_shop_node' ) ), $atts );
		
		$output = '';
		$days = get_office_hours( $atts['node'] );
		foreach ( $days as $day_index => $hours ) {
			// Check of er voor deze dag wel openingsuren bestaan
			if ( $hours ) {
				foreach ( $hours as $part => $part_hours ) {
					if ( ! isset( $$day_index ) ) {
						$output .= "<br>".ucwords( date_i18n( 'l', strtotime("Sunday +{$day_index} days") ) ).": " . $part_hours['start'] . " - " . $part_hours['end'];
						$$day_index = true;
					} else {
						$output .= " en " . $part_hours['start'] . " - " . $part_hours['end'];
					}
				}
			}
		}
		// Knip de eerste <br> er weer af
		$output = substr( $output, 4 );
		return $output;
	}

	function print_oxfam_shop_data( $key, $atts ) {
		// Overschrijf defaults door opgegeven attributen
		$atts = shortcode_atts( array( 'node' => get_option( 'oxfam_shop_node' ) ), $atts );
		return get_oxfam_shop_data( $key, $atts['node'] );
	}

	function print_mail() {
		return "<a href='mailto:".get_company_email()."'>".get_company_email()."</a>";
	}

	function print_place( $atts = [] ) {
		return print_oxfam_shop_data( 'place', $atts );
	}

	function print_zipcode( $atts = [] ) {
		return print_oxfam_shop_data( 'zipcode', $atts );
	}

	function print_city( $atts = [] ) {
		return print_oxfam_shop_data( 'city', $atts );
	}

	function print_telephone( $atts = [] ) {
		return print_oxfam_shop_data( 'telephone', $atts );
	}

	function print_summary() {
		$sites = get_sites( array( 'archived' => 0, 'count' => true ) );
		// Hoofdblog (en templates) ervan aftrekken
		$msg = "<h1>Shop online in één van onze ".($sites-1)." webshops en haal je bestelling na één werkdag af in de winkel (indien geopend).</h1>";
		return $msg;
	}

	function print_shop_selection() {
		$global_zips = get_shops();
 		$all_zips = get_site_option( 'oxfam_flemish_zip_codes' );
 		$msg = '<h1>Liever thuislevering? Vul één van de '.count($all_zips).' Vlaamse postcodes in en we sturen je door naar de winkel die jouw bestelling levert.</h1>';
		$msg .= '<p style="text-align: center;"><input type="tel" name="zip" maxlength="4"></p>';
		$set = 'Reeds ingevuld:<br><select>';
		foreach ( $all_zips as $zip ) {
			if ( isset( $global_zips[$zip] ) ) {
				$set .= '<option value="'.$global_zips[$zip].'">'.$zip.'</option>';
			}
		}
		$set .= '</select>';
		return $msg.'<br>'.$set;
	}

	function print_map() {
		// Open de file
		$myfile = fopen("newoutput.kml", "w");
		$str = "<?xml version='1.0' encoding='UTF-8'?><kml xmlns='http://www.opengis.net/kml/2.2'><Document>";
		
		// Definieer de styling (icon upscalen boven 32x32 pixels werkt helaas niet)
		$str .= "<Style id='1'><IconStyle><scale>1.21875</scale><w>39</w><h>51</h><Icon><href>https://demo.oxfamwereldwinkels.be/wp-content/uploads/google-maps.png</href></Icon></IconStyle></Style>";
		
		// Haal alle shopdata op (en sluit gearchiveerde webshops uit!)
		$sites = get_sites( array( 'archived' => 0 ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			// Sla de hoofdsite over
			if ( ! is_main_site() ) {
				$local_zips = get_option( 'oxfam_zip_codes' );
				if ( count($local_zips) >= 1 ) {
					$i = 0;
					$thuislevering = "Doet thuislevering in ";
					foreach ( $local_zips as $zip ) {
						$i++;
						$thuislevering .= $zip;
						if ( $i < count($local_zips) ) $thuislevering .= ", ";
					}
					$str .= "<Placemark><name><![CDATA[".get_company_name()."]]></name><styleUrl>#1</styleUrl><description><![CDATA[".get_company_address()."<br>".$thuislevering.".<br><a href=".get_site_url().">Naar deze webshop »</a>]]></description><Point><coordinates>".get_oxfam_shop_data( 'll' )."</coordinates></Point></Placemark>";
				}
			}
			restore_current_blog();
		}

		// Sluit document af
		$str .= "</Document></kml>";
		fwrite($myfile, $str);
		fclose($myfile);
		
		return do_shortcode("[flexiblemap src='".site_url()."/newoutput.kml?v=".rand()."' width='100%' height='500px' zoom='9' hidemaptype='true' maptype='light_monochrome']");
		// return do_shortcode('[osm_map_v3 zoom="9" width="100%" map_center="50.9667,4.2333" height="500" type="stamen_toner" map_border="3px solid black" file_list="'.site_url().'/newoutput.kml"]');
	}


	####################
	# HELPER FUNCTIONS #
	####################

	function set_flemish_zip_codes() {
		$zips = array( 1000, 1020, 1030, 1040, 1050, 1060, 1070, 1080, 1081, 1082, 1083, 1090, 1120, 1130, 1140, 1150, 1160, 1170, 1180, 1190, 1200, 1210, 1500, 1501, 1502, 1540, 1541, 1547, 1560, 1570, 1600, 1601, 1602, 1620, 1630, 1640, 1650, 1651, 1652, 1653, 1654, 1670, 1671, 1673, 1674, 1700, 1701, 1702, 1703, 1730, 1731, 1740, 1741, 1742, 1745, 1750, 1755, 1760, 1761, 1770, 1780, 1785, 1790, 1800, 1820, 1830, 1831, 1840, 1850, 1851, 1852, 1853, 1860, 1861, 1880, 1910, 1930, 1932, 1933, 1950, 1970, 1980, 1981, 1982, 2000, 2018, 2020, 2030, 2040, 2050, 2060, 2070, 2100, 2110, 2140, 2150, 2160, 2170, 2180, 2200, 2220, 2221, 2222, 2223, 2230, 2235, 2240, 2242, 2243, 2250, 2260, 2270, 2275, 2280, 2288, 2290, 2300, 2310, 2320, 2321, 2322, 2323, 2328, 2330, 2340, 2350, 2360, 2370, 2380, 2381, 2382, 2387, 2390, 2400, 2430, 2431, 2440, 2450, 2460, 2470, 2480, 2490, 2491, 2500, 2520, 2530, 2531, 2540, 2547, 2550, 2560, 2570, 2580, 2590, 2600, 2610, 2620, 2627, 2630, 2640, 2650, 2660, 2800, 2801, 2811, 2812, 2820, 2830, 2840, 2845, 2850, 2860, 2861, 2870, 2880, 2890, 2900, 2910, 2920, 2930, 2940, 2950, 2960, 2970, 2980, 2990, 3000, 3001, 3010, 3012, 3018, 3020, 3040, 3050, 3051, 3052, 3053, 3054, 3060, 3061, 3070, 3071, 3078, 3080, 3090, 3110, 3111, 3118, 3120, 3128, 3130, 3140, 3150, 3190, 3191, 3200, 3201, 3202, 3210, 3211, 3212, 3220, 3221, 3270, 3271, 3272, 3290, 3293, 3294, 3300, 3320, 3321, 3350, 3360, 3370, 3380, 3381, 3384, 3390, 3391, 3400, 3401, 3404, 3440, 3450, 3454, 3460, 3461, 3470, 3471, 3472, 3473, 3500, 3501, 3510, 3511, 3512, 3520, 3530, 3540, 3545, 3550, 3560, 3570, 3580, 3581, 3582, 3583, 3590, 3600, 3620, 3621, 3630, 3631, 3640, 3650, 3660, 3665, 3668, 3670, 3680, 3690, 3700, 3717, 3720, 3721, 3722, 3723, 3724, 3730, 3732, 3740, 3742, 3746, 3770, 3790, 3791, 3792, 3793, 3798, 3800, 3803, 3806, 3830, 3831, 3832, 3840, 3850, 3870, 3890, 3891, 3900, 3910, 3920, 3930, 3940, 3941, 3945, 3950, 3960, 3970, 3971, 3980, 3990, 8000, 8020, 8200, 8210, 8211, 8300, 8301, 8310, 8340, 8370, 8377, 8380, 8400, 8420, 8421, 8430, 8431, 8432, 8433, 8434, 8450, 8460, 8470, 8480, 8490, 8500, 8501, 8510, 8511, 8520, 8530, 8531, 8540, 8550, 8551, 8552, 8553, 8554, 8560, 8570, 8572, 8573, 8580, 8581, 8582, 8583, 8587, 8600, 8610, 8620, 8630, 8640, 8647, 8650, 8660, 8670, 8680, 8690, 8691, 8700, 8710, 8720, 8730, 8740, 8750, 8755, 8760, 8770, 8780, 8790, 8791, 8792, 8793, 8800, 8810, 8820, 8830, 8840, 8850, 8851, 8860, 8870, 8880, 8890, 8900, 8902, 8904, 8906, 8908, 8920, 8930, 8940, 8950, 8951, 8952, 8953, 8954, 8956, 8957, 8958, 8970, 8972, 8978, 8980, 9000, 9030, 9031, 9032, 9040, 9041, 9042, 9050, 9051, 9052, 9060, 9070, 9080, 9090, 9100, 9111, 9112, 9120, 9130, 9140, 9150, 9160, 9170, 9180, 9185, 9190, 9200, 9220, 9230, 9240, 9250, 9255, 9260, 9270, 9280, 9290, 9300, 9308, 9310, 9320, 9340, 9400, 9401, 9402, 9403, 9404, 9406, 9420, 9450, 9451, 9470, 9472, 9473, 9500, 9506, 9520, 9521, 9550, 9551, 9552, 9570, 9571, 9572, 9600, 9620, 9630, 9636, 9660, 9661, 9667, 9680, 9681, 9688, 9690, 9700, 9750, 9770, 9771, 9772, 9790, 9800, 9810, 9820, 9830, 9831, 9840, 9850, 9860, 9870, 9880, 9881, 9890, 9900, 9910, 9920, 9921, 9930, 9931, 9932, 9940, 9950, 9960, 9961, 9968, 9970, 9971, 9980, 9981, 9982, 9988, 9990, 9991, 9992 );
		update_site_option( 'oxfam_flemish_zip_codes', $zips );	
	}

	function does_home_delivery() {
		return get_option( 'oxfam_zip_codes' );
	}

	function is_regional_webshop() {
		return get_option( 'oxfam_member_shops' );
	}

	// Voorlopig nog identiek aan vorige functie, maar dat kan nog veranderen!
	function does_sendcloud_delivery() {
		return get_option( 'oxfam_zip_codes' );
	}

	function get_oxfam_shop_data( $key, $node = 0 ) {
		global $wpdb;
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		if ( ! is_main_site() ) {
			if ( $key === 'tax' or $key === 'account' ) {
				$row = $wpdb->get_row( 'SELECT * FROM field_data_field_shop_'.$key.' WHERE entity_id = '.get_oxfam_shop_data( 'shop', $node ) );
				if ( $row ) {
					return call_user_func( 'format_'.$key, $row->{'field_shop_'.$key.'_value'} );
				} else {
					return "UNKNOWN";
				}
			} else {
				$row = $wpdb->get_row( 'SELECT * FROM field_data_field_sellpoint_'.$key.' WHERE entity_id = '.intval($node) );
				if ( $row ) {
					switch ($key) {
						case 'shop':
							return $row->field_sellpoint_shop_nid;
						case 'll':
							// Voor KML-file moet longitude voor latitude komen!
							return $row->field_sellpoint_ll_lon.",".$row->field_sellpoint_ll_lat;
						case 'telephone':
							// Geef alternatieve delimiter mee
							return call_user_func( 'format_telephone', $row->field_sellpoint_telephone_value, '.' );
						default:
							return call_user_func( 'format_'.$key, $row->{'field_sellpoint_'.$key.'_value'} );
					}
				} else {
					return "(niet gevonden)";
				}
			}		
		} else {
			switch ($key) {
				case 'place':
					return call_user_func( 'format_place', 'Ververijstraat 15', '.' );
				case 'zipcode':
					return call_user_func( 'format_zipcode', '9000', '.' );
				case 'city':
					return call_user_func( 'format_city', 'Gent', '.' );
				case 'telephone':
					return call_user_func( 'format_telephone', '092188899', '.' );
				default:
					return "(gegevens cvba)";
			}
		}
	}

	function get_company_name() {
		return get_bloginfo( 'name' );
	}

	function get_main_shop_node() {
		$list = get_option( 'oxfam_shop_nodes' );
		return $list[0];
	}

	function get_company_email() {
		return get_option( 'admin_email' );
	}

	function get_company_contact() {
		return "<a href='mailto:".get_company_email()."'>".get_company_email()."</a><br>".get_oxfam_shop_data( 'telephone' );
	}

	function get_company_address() {
		return get_oxfam_shop_data( 'place' )."<br>".get_oxfam_shop_data( 'zipcode' )." ".get_oxfam_shop_data( 'city' );
	}

	function get_full_company() {
		return get_company_name()."<br>".get_company_address()."<br>".get_company_contact();
	}

	function get_shops() {
		$global_zips = array();
		// Negeer main site én gearchiveerde sites
		$sites = get_sites( array( 'site__not_in' => array( 1 ), 'archived' => 0, ) );
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

	function get_company_and_year() {
		return '<span style="color: #60646c">'.get_company_name().' &copy; 2016-'.date_i18n('Y').'</span>';
	}

	function get_oxfam_covered_zips() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'woocommerce_shipping_zone_locations WHERE location_type = postcode' );
		$zips = false;
		if ( count($rows) > 0 ) {
			foreach ( $rows as $row ) {
				$zips[] = $row->location_code;
			}
			$zips = array_unique( $zips );
			sort($zips, SORT_STRING);
		}
		return $zips;
	}

	
	##########
	# SEARCH #
	##########

	// Verander capability van 'manage_options' naar 'create_sites' zodat ook lokale beheerders de logs kunnen bekijken
	add_filter( 'relevanssi_options_capability', function($capability) { return 'create_sites'; } );
	
	// Verander capability van 'edit_pages' naar 'manage_woocommerce' zodat ook lokale beheerders de logs kunnen bekijken
	add_filter( 'relevanssi_user_searches_capability', function($capability) { return 'manage_woocommerce'; } );
		
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

	// Toon de bestsellers op zoekpagina's zonder resultaten IETS MEER NAAR BOVEN VERPLAATSEN?
	add_action( 'woocommerce_after_main_content', 'add_bestsellers' );

	function add_bestsellers() {
		global $wp_query;
		if ( is_search() and $wp_query->found_posts == 0 ) {
			echo do_shortcode('[vc_row css=".vc_custom_1487859300634{padding-top: 25px !important;padding-bottom: 25px !important;}"][vc_column][vc_text_separator title="<h2>Werp een blik op onze bestsellers ...</h2>" i_icon_fontawesome="fa fa-star" i_color="black" add_icon="true" css=".vc_custom_1487854440279{padding-bottom: 25px !important;}"][best_selling_products per_page="10" columns="5" orderby="rand"][/vc_column][/vc_row]');
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
	
?>