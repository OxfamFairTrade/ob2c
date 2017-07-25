<?php

	if ( ! defined('ABSPATH') ) exit;

	$activated_shops = array( 8, 9, 12, 15, 16, 19, 20, 21, 22, 24, 28, 30, 31, 32, 34, 35, 36, 37, 38 );

	// Verhinder bekijken door niet-ingelogde bezoekers
	add_action( 'init', 'v_forcelogin' );
	
	function v_forcelogin() {
		if ( ! is_user_logged_in() ) {
			global $activated_shops;
			$url = v_get_url();
			$redirect_url = apply_filters( 'v_forcelogin_redirect', $url );
			// Enkel redirecten op LIVE-site én indien webshop nog niet gelanceerd
			if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' and ! in_array( get_current_blog_id(), $activated_shops ) ) {
				// Nooit redirecten: inlogpagina, activatiepagina en WC API-calls
				if ( preg_replace( '/\?.*/', '', $url ) != preg_replace( '/\?.*/', '', wp_login_url() ) and ! strpos( $url, '.php' ) and ! strpos( $url, 'wc-api' ) ) {
					wp_safe_redirect( wp_login_url( $redirect_url ), 302 );
					exit();
				}
			}
		}
	}

	function v_get_url() {
		$url = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
		$url .= '://' . $_SERVER['SERVER_NAME'];
		$url .= in_array( $_SERVER['SERVER_PORT'], array('80', '443') ) ? '' : ':' . $_SERVER['SERVER_PORT'];
		$url .= $_SERVER['REQUEST_URI'];
		return $url;
	}
	
	// Vuile truc om te verhinderen dat WordPress de afmeting van 'large'-afbeeldingen verkeerd weergeeft
	$content_width = 1500;

	// Sta HTML-attribuut 'target' toe in beschrijvingen van taxonomieën
	add_action( 'init', 'allow_target_tag', 20 );

	function allow_target_tag() { 
	    global $allowedtags;
	    $allowedtags['a']['target'] = 1;
	}

	// Voeg klasse toe indien hoofdsite
	add_filter( 'body_class', 'add_main_site_class' );

	function add_main_site_class( $classes ) {
		if ( is_main_site() ) {
			$classes[] = 'portal';
		}
		return $classes;
	}

	// Voeg klasse toe indien recent product
	add_filter( 'post_class', 'add_recent_product_class' );

	function add_recent_product_class( $classes ) {
		global $post;
		if ( get_the_date( 'Y-m-d', $post->ID ) > date_i18n( 'Y-m-d', strtotime('-2 months') ) ) {
			$classes[] = 'newbee';
		}
		return $classes;
	}

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'load_child_theme' );

	function load_child_theme() {
		wp_enqueue_style( 'oxfam-webshop', get_stylesheet_uri(), array( 'nm-core' ) );
		// In de languages map van het child theme zal dit niet werken (checkt enkel nl_NL.mo) maar fallback is de algemene languages map (inclusief textdomain)
		load_child_theme_textdomain( 'oxfam-webshop', get_stylesheet_directory().'/languages' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_register_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' );  
	}

	// Stop vervelende nag van Visual Composer
	add_action( 'admin_init', function() {
		setcookie('vchideactivationmsg', '1', strtotime('+3 years'), '/');
		setcookie('vchideactivationmsg_vc11', ( defined('WPB_VC_VERSION') ? WPB_VC_VERSION : '1' ), strtotime('+3 years'), '/');
	});

	// Voeg custom styling toe aan de adminomgeving (voor Relevanssi en Voorraadbeheer)
	add_action( 'admin_enqueue_scripts', 'load_admin_css' );

	function load_admin_css() {
		wp_enqueue_style( 'oxfam-admin', get_stylesheet_directory_uri().'/admin.css' );
	}

	// Fixes i.v.m. cURL
	add_action( 'http_api_curl', 'custom_curl_timeout', 10, 3 );
	
	function custom_curl_timeout( $handle, $r, $url ) {
		// Fix error 28 - Operation timed out after 10000 milliseconds with 0 bytes received (bij het connecteren van Jetpack met Wordpress.com)
		curl_setopt( $handle, CURLOPT_TIMEOUT, 30 );
		// Fix error 60 - SSL certificate problem: unable to get local issuer certificate (bij het downloaden van een CSV in WP All Import)
		// curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
	}

	// Jetpack-tags uitschakelen op homepages om dubbel werk te vermijden
	if ( is_front_page() ) {
		add_filter( 'jetpack_enable_open_graph', '__return_false' );
	}
	
	// Beheer alle wettelijke feestdagen uit de testperiode centraal
	$default_holidays = array( '2017-07-21', '2017-08-15', '2017-11-01', '2017-11-11', '2017-12-25', '2018-01-01', '2018-04-01', '2018-04-02' );
	
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
		add_action( 'pre_get_posts', 'filter_orders_by_owner', 15 );

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
		$contactmethods['blog_'.get_current_blog_id().'_member_of_shop'] = 'Ik bevestig orders voor ...';
		return $contactmethods;
	}
	
	function save_extra_user_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) return false;
		// Usermeta is sitewide, dus ID van blog toevoegen aan de key!
		$key = 'blog_'.get_current_blog_id().'_member_of_shop';
		update_usermeta( $user_id, $key, $_POST[$key] );
	}

	function add_extra_user_field( $user ) {
		if ( user_can( $user, 'manage_woocommerce' ) ) {
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
		// Een gewone klant heeft deze eigenschap niet en retourneert dus sowieso 'false'
		$owner = get_the_author_meta( 'blog_'.$blog_id.'_member_of_shop', get_current_user_id() );
		
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			// Koppel automatisch aan de winkel waar de afhaling zal gebeuren
			$methods = $order->get_shipping_methods();
			$method = reset($methods);
			$meta_data = $method->get_meta_data();
			$pickup_data = reset($meta_data);
			$city = mb_strtolower( trim( str_replace( 'Oxfam-Wereldwinkel', '', $pickup_data->value['shipping_company'] ) ) );
			if ( in_array( $city, get_option( 'oxfam_member_shops' ) ) ) {
				// Dubbelcheck of deze stad wel tussen de deelnemende winkels zit
				$owner = $city;
			}
		}

		if ( ! $owner ) {
			// Koppel als laatste redmiddel aan de hoofdwinkel (op basis van het nodenummer) 
			$owner = mb_strtolower( get_oxfam_shop_data( 'city' ) );
		}

		update_post_meta( $order_id, 'claimed_by', $owner );
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
		return $columns;
	}

	function get_claimed_by_value( $column ) {
		global $the_order;
		if ( $column === 'claimed_by' ) { 
			if ( $the_order->get_status() === 'pending' ) {
				echo '<i>nog niet betaald</i>';
			} elseif ( $the_order->get_status() === 'processing' ) {
				echo '<i>nog niet bevestigd</i>';
			} elseif ( $the_order->get_status() === 'cancelled' ) {
				echo '<i>geannuleerd</i>';
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

	// Voeg ook een kolom toe aan het besteloverzicht in de back-end
	add_filter( 'manage_edit-shop_order_columns', 'add_estimated_delivery_column', 12 );

	// Maak sorteren op deze nieuwe kolom mogelijk
	add_filter( 'manage_edit-shop_order_sortable_columns', 'make_estimated_delivery_column_sortable' );

	// Toon de data van elk order in de kolom
	add_action( 'manage_shop_order_posts_custom_column' , 'get_estimated_delivery_value', 10, 2 );

	// Voer de sortering uit tijdens het bekijken van orders in de admin (voor alle zekerheid NA filteren uitvoeren)
	add_action( 'pre_get_posts', 'sort_orders_on_custom_column', 20 );
	
	function sort_orders_on_custom_column( $query ) {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' and $query->query['post_type'] === 'shop_order' ) {
			// Check of we moeten sorteren op één van onze custom kolommen
			if ( $query->get( 'orderby' ) === 'estimated_delivery' ) {
				$query->set( 'meta_key', 'estimated_delivery' );
				$query->set( 'orderby', 'meta_value_num' );
			}
			if ( $query->get( 'orderby' ) === 'claimed_by' ) {
				$query->set( 'meta_key', 'claimed_by' );
				$query->set( 'orderby', 'meta_value' );
			}
		}
	}

	function add_estimated_delivery_column( $columns ) {
		$columns['estimated_delivery'] = 'Uiterste leverdag';
		return $columns;
	}

	function make_estimated_delivery_column_sortable( $columns ) {
		$columns['estimated_delivery'] = 'estimated_delivery';
		return $columns;
	}

	function get_estimated_delivery_value( $column ) {
		global $the_order;
		if ( $column === 'estimated_delivery' ) {
			$processing_statusses = array( 'processing', 'claimed' );
			$completed_statusses = array( 'completed' );
			if ( get_post_meta( $the_order->get_id(), 'estimated_delivery', true ) ) {
				$delivery = date( 'Y-m-d H:i:s', get_post_meta( $the_order->get_id(), 'estimated_delivery', true ) );
				if ( in_array( $the_order->get_status(), $processing_statusses ) ) {
					$delivery = date( 'Y-m-d H:i:s', get_post_meta( $the_order->get_id(), 'estimated_delivery', true ) );
					if ( get_date_from_gmt( $delivery, 'Y-m-d' ) < date_i18n( 'Y-m-d' ) ) {
						$color = 'red';
					} elseif ( get_date_from_gmt( $delivery, 'Y-m-d' ) === date_i18n( 'Y-m-d' ) ) {
						$color = 'orange';
					} else {
						$color = 'green';
					}
					echo '<span style="color: '.$color.';">'.get_date_from_gmt( $delivery, 'd-m-Y' ).'</span>';
				} elseif ( in_array( $the_order->get_status(), $completed_statusses ) ) {
					if ( $the_order->get_date_completed()->date_i18n( 'Y-m-d H:i:s' ) < $delivery ) {
						echo '<i>op tijd geleverd</i>';
					} else {
						echo '<i>te laat geleverd</i>';
					}
				}
			} else {
				if ( $the_order->get_status() === 'cancelled' ) {
					echo '<i>geannuleerd</i>';
				} else {
					echo '<i>niet beschikbaar</i>';
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
					// Zorg ervoor dat de loop stopt!
					break;
				} elseif ( $key === 'wc-completed' ) {
					$array = array_slice( $array, 0, $cnt-1 ) + array( 'wc-claimed' => $stored_value ) + array_slice( $array, $cnt-1, count($array) - ($cnt-1) );
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
	
	// Pas het onderwerp van de mails aan naargelang de gekozen levermethode
	add_filter( 'woocommerce_email_subject_customer_processing_order', 'change_processing_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_completed_order', 'change_completed_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_refunded_order', 'change_refunded_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_note', 'change_note_subject', 10, 2 );

	function change_processing_order_subject( $subject, $order ) {
		$subject = sprintf( __( 'Onderwerp van de 1ste bevestigingsmail inclusief besteldatum (%1$s) en naam webshop (%2$s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y'), get_company_name() );
		return $subject;
	}

	function change_completed_order_subject( $subject, $order ) {
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			$subject = sprintf( __( 'Onderwerp van de 2de bevestigingsmail (indien afhaling) inclusief besteldatum (%1$s) en naam webshop (%2$s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y'), get_company_name() );
		} else {
			$subject = sprintf( __( 'Onderwerp van de 2de bevestigingsmail (indien thuislevering) inclusief besteldatum (%1$s) en naam webshop (%2$s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y'), get_company_name() );
		}
		return $subject;
	}

	function change_refunded_order_subject( $subject, $order ) {
		$subject = sprintf( __( 'Onderwerp van de terugbetalingsmail inclusief besteldatum (%1$s) en naam webshop (%2$s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y'), get_company_name() );
		return $subject;
	}

	function change_note_subject( $subject, $order ) {
		$subject = sprintf( __( 'Onderwerp van de opmerkingenmail inclusief besteldatum (%1$s) en naam webshop (%2$s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y'), get_company_name() );
		return $subject;
	}

	// Pas de header van de mails aan naargelang de gekozen levermethode
	add_filter( 'woocommerce_email_heading_customer_processing_order', 'change_processing_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_completed_order', 'change_completed_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_refunded_order', 'change_refunded_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_note', 'change_note_email_heading', 10, 2 );

	function change_processing_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de 1ste bevestigingsmail', 'oxfam-webshop' );
		return $email_heading;
	}

	function change_completed_email_heading( $email_heading, $order ) {
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			$email_heading = __( 'Heading van de 2de bevestigingsmail (indien afhaling)', 'oxfam-webshop' );
		} else {
			$email_heading = __( 'Heading van de 2de bevestigingsmail (indien thuislevering)', 'oxfam-webshop' );
		}
		return $email_heading;
	}

	function change_refunded_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de terugbetalingsmail', 'oxfam-webshop' );
		return $email_heading;
	}

	function change_note_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de opmerkingenmail', 'oxfam-webshop' );
		return $email_heading;
	}

	// Schakel autosaves uit
	add_action( 'wp_print_scripts', function() { wp_deregister_script('autosave'); } );

	if ( is_main_site() ) {
		// Zorg ervoor dat productrevisies bijgehouden worden op de hoofdsite
		add_filter( 'woocommerce_register_post_type_product', 'add_product_revisions' );
		// Log wijzigingen aan metadata na het succesvol bijwerken
		add_action( 'updated_post_metadata', 'log_product_changes', 100, 4 );
	}
	
	function add_product_revisions( $vars ) {
		$vars['supports'][] = 'revisions';
		return $vars;
	}

	function log_product_changes( $meta_id, $post_id, $meta_key, $new_meta_value ) {
		// Alle overige interessante data zitten in het algemene veld '_product_attributes' dus daarvoor best een ander filtertje zoeken
		$watched_metas = array( '_price', '_stock_status', '_tax_class', '_weight', '_length', '_width', '_height', '_thumbnail_id', '_force_sell_synced_ids', '_barcode', 'title_fr', 'description_fr', 'title_en', 'description_en', '_product_attributes' );
		// Deze actie vuurt bij 'single value meta keys' enkel indien er een wezenlijke wijziging was, dus check hoeft niet meer
		if ( get_post_type( $post_id ) === 'product' and in_array( $meta_key, $watched_metas ) ) {
			// Schrijf weg in log per weeknummer (zonder leading zero's)
			$user = wp_get_current_user();
			$str = date_i18n('d/m/Y H:i:s') . "\t" . get_post_meta( $post_id, '_sku', true ) . "\t" . $user->user_firstname . "\t" . $meta_key . " gewijzigd in " . serialize($new_meta_value) . "\t" . get_the_title( $post_id ) . "\n";
			file_put_contents(WP_CONTENT_DIR."/changelog-week-".intval( date_i18n('W') ).".csv", $str, FILE_APPEND);
		}
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
		$args['admin.php']['pmxe-admin-export'] = array(
			'id',
			'action',
			'pmxe_nt',
			'warnings',
		);
		$args['admin.php']['pmxe-admin-manage'] = array(
			'id',
			'action',
			'pmxe_nt',
			'warnings',
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
		}

		if ( isset( $_GET['referralCity'] ) ) {
			WC()->customer->set_billing_city( $_GET['referralCity'] );
			WC()->customer->set_shipping_city( $_GET['referralCity'] );
		}
		
		// var_dump(WC()->customer);
		// if ( isset( $_GET['downloadSheet'] ) ) create_product_pdf( wc_get_product( 4621 ) );
		
		if ( isset( $_GET['emptyCart'] ) ) WC()->cart->empty_cart();
		
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
		// $sortby['popularity'] = 'Best verkocht';
		// $sortby['date'] = 'Laatst toegevoegd';
		$sortby['alpha'] = 'Van A tot Z';
		$sortby['alpha-desc'] = 'Van Z tot A';
		// $sortby['price'] = 'Stijgende prijs';
		// $sortby['price-desc'] = 'Dalende prijs';
		// $sortby['sku'] = 'Stijgend artikelnummer';
		// $sortby['reverse_sku'] = 'Dalend artikelnummer';
		return $sortby;
	}

	// Herlaad winkelmandje automatisch na aanpassing en zorg dat postcode altijd gecheckt wordt (en activeer live search indien plugin geactiveerd)
	add_action( 'wp_footer', 'cart_update_qty_script' );
	
	function cart_update_qty_script() {
		$current_user = wp_get_current_user();
		$user_meta = get_userdata($current_user->ID);
		$user_roles = $user_meta->roles;
		if ( is_cart() ) {
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
		} elseif ( is_main_site() and is_front_page() ) {
			?>
				<script>
					var wto;
					jQuery( '#oxfam-zip-user' ).on( 'input change', function() {
						clearTimeout(wto);
						if ( jQuery( '#oxfam-zip-user' ).val().length == 4 ) {
							jQuery( '#do_oxfam_redirect' ).prop( 'disabled', false );
							// jQuery( '#do_oxfam_redirect' ).val('Doorsturen ...');
							// wto = setTimeout( function() {
							// 	jQuery( '#do_oxfam_redirect' ).trigger('click');
							// }, 500);
						} else {
							jQuery( '#do_oxfam_redirect' ).prop( 'disabled', true );
						}
					});
					
					jQuery( '#oxfam-zip-user' ).keyup( function(event) {
						if ( event.which == 13 ) {
							jQuery( '#do_oxfam_redirect' ).trigger('click');
						}
					});
					
					jQuery( '#do_oxfam_redirect' ).on( 'click', function() {
						jQuery(this).prop( 'disabled', true );
						var zip = jQuery( '#oxfam-zip-user' ).val();
						var url = jQuery( '#'+zip+'.oxfam-zip-value' ).val();
						if ( typeof url !== 'undefined' ) {
							if ( url.length > 10 ) {
								// TOE TE VOEGEN: +'&referralCity='+city (maar city nog niet bepaald)
								window.location.href = url+'?referralZip='+zip;
							} else {
								alert("<?php _e( 'Foutmelding na het ingeven van een Vlaamse postcode waar Oxfam-Wereldwinkels nog geen thuislevering voorziet.', 'oxfam_webshop' ); ?>");
								jQuery(this).val('Stuur mij door');
								jQuery( '#oxfam-zip-user' ).val('');
							}
						} else {
							alert("<?php _e( 'Foutmelding na het ingeven van een onbestaande Vlaamse postcode.', 'oxfam_webshop' ); ?>");
							jQuery(this).val('Stuur mij door');
							jQuery( '#oxfam-zip-user' ).val('');
						}
					});

					jQuery( function() {
						var zips = <?php echo json_encode( get_flemish_zips_and_cities() ); ?>;
						jQuery( "#oxfam-zip-user, #billing_postcode, #shipping_postcode" ).autocomplete({
							source: zips
						});
					} );
				</script>
			<?php
		} elseif ( is_account_page() and in_array( 'local_manager', $user_roles ) ) {
			?>
				<script>
					jQuery("form.woocommerce-EditAccountForm").find('input[name=account_email]').prop('readonly', true);
					jQuery("form.woocommerce-EditAccountForm").find('input[name=account_email]').after('<span class="description"> De lokale beheerder dient altijd gekoppeld te blijven aan de webshopmailbox, dus dit veld kun je niet bewerken.</span>');
				</script>
			<?php
		}

		if ( ! is_user_logged_in() or in_array( 'customer', $user_roles ) ) {
			?>
				<script type="text/javascript">
				    window.smartlook||(function(d) {
				    var o=smartlook=function(){ o.api.push(arguments)},h=d.getElementsByTagName('head')[0];
				    var c=d.createElement('script');o.api=new Array();c.async=true;c.type='text/javascript';
				    c.charset='utf-8';c.src='https://rec.smartlook.com/recorder.js';h.appendChild(c);
				    })(document);
				    smartlook('init', 'e6996862fe1127c697c24f1887605b3b9160a885');
				</script>
			<?php
		}

		?>
			<script type="text/javascript">
				jQuery(document).ready( function() {
					function hidePlaceholder( dateText, inst ) {
						// Placeholder onmiddellijk verwijderen
						jQuery(this).attr('placeholder', '');
						// Validatie voor alle zekerheid weer activeren
						jQuery('#datepicker_field').addClass( 'validate-required' );
					}

					jQuery("#datepicker").datepicker({
						dayNamesMin: [ "Zo", "Ma", "Di", "Wo", "Do", "Vr", "Za" ],
						monthNamesShort: [ "Jan", "Feb", "Maart", "April", "Mei", "Juni", "Juli", "Aug", "Sep", "Okt", "Nov", "Dec" ],
						changeMonth: true,
						changeYear: true,
						yearRange: "c-50:c+32",
						defaultDate: "-50y",
						maxDate: "-18y",
						onSelect: hidePlaceholder,
					});

					// Overijverige validatie uitschakelen
					jQuery('#datepicker_field').removeClass( 'validate-required' );
				});
			</script>
		<?php
	}

	// Verhinder bepaalde selecties in de back-end
	add_action( 'admin_footer', 'disable_custom_checkboxes' );

	function disable_custom_checkboxes() {
		?>
		<script>
			/* Disable hoofdcategorieën */
			jQuery( '#in-product_cat-200' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-204' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-210' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-213' ).prop( 'disabled', true );
			jQuery( '#in-product_cat-224' ).prop( 'disabled', true );
			
			/* Disable continenten */
			jQuery( '#in-product_partner-162' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-163' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-164' ).prop( 'disabled', true );
			jQuery( '#in-product_partner-165' ).prop( 'disabled', true );
			
			/* Disable bovenliggende landen/continenten van alle aangevinkte partners/landen */
			jQuery( '#taxonomy-product_partner' ).find( 'input[type=checkbox]:checked' ).closest( 'ul.children' ).siblings( 'label.selectit' ).find( 'input[type=checkbox]' ).prop( 'disabled', true );

			/* Disable/enable het bovenliggende land bij aan/afvinken van een partner */
			jQuery( '#taxonomy-product_partner' ).find( 'input[type=checkbox]' ).on( 'change', function() {
				jQuery(this).closest( 'ul.children' ).siblings( 'label.selectit' ).find( 'input[type=checkbox]' ).prop( 'disabled', jQuery(this).is(":checked") );
			});

			/* Disable allergeenklasses */
			jQuery( '#in-product_allergen-170' ).prop( 'disabled', true );
			jQuery( '#in-product_allergen-171' ).prop( 'disabled', true );

			/* Disable rode en witte druiven */
			jQuery( '#in-product_grape-1724' ).prop( 'disabled', true );
			jQuery( '#in-product_grape-1725' ).prop( 'disabled', true );

			/* Orderstatus vastzetten */
			jQuery( '#order_data' ).find( '#order_status' ).prop( 'disabled', true );

			/* Disbable prijswijzigingen bij terugbetalingen */
			jQuery( '#order_line_items' ).find( '.refund_line_total.wc_input_price' ).prop( 'disabled', true );
			jQuery( '#order_line_items' ).find( '.refund_line_tax.wc_input_price' ).prop( 'disabled', true );
			jQuery( '.wc-order-totals' ).find ( '#refund_amount' ).prop( 'disabled', true );
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
		$address_fields['billing_address_1']['class'] = array('form-row-first');
		$address_fields['billing_address_1']['clear'] = false;
		$address_fields['billing_email']['label'] = "E-mailadres";
		$address_fields['billing_email']['placeholder'] = "george@foreman.com";
		$address_fields['billing_phone']['label'] = "Telefoonnummer";
		$address_fields['billing_phone']['placeholder'] = get_oxfam_shop_data( 'telephone' );
		// $address_fields['billing_company']['label'] = "Bedrijf";
		// $address_fields['billing_company']['placeholder'] = "Oxfam Fair Trade cvba";
		// $address_fields['billing_vat']['label'] = "BTW-nummer";
		// $address_fields['billing_vat']['placeholder'] = "BE 0453.066.016";
		
		$address_fields['billing_first_name']['class'] = array('form-row-first');
		$address_fields['billing_last_name']['class'] = array('form-row-last');
		$address_fields['billing_email']['class'] = array('form-row-first');
		$address_fields['billing_email']['clear'] = true;
		$address_fields['billing_email']['required'] = true;
		$address_fields['billing_phone']['class'] = array('form-row-last');
		$address_fields['billing_phone']['clear'] = false;

		$address_fields['billing_birthday'] = array(
			'type' => 'text',
	        'label' => 'Geboortedatum',
			'id' => 'datepicker',
	        'placeholder' => '16/03/1988',
			'required' => true,
			'class' => array('form-row-last'),
			'clear' => true,
		);
		
		$order = array(
        	"billing_first_name",
        	"billing_last_name",
        	"billing_address_1",
        	"billing_birthday",
        	"billing_postcode",
        	"billing_city",
        	// NODIG VOOR SERVICE POINT!
        	"billing_country",
        	"billing_email",
        	"billing_phone",
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
				$placeholder = __( 'Voorbeeldnotitie op afrekenpagina (indien afhaling).', 'oxfam-webshop' );
				break;
			default:
				$placeholder = __( 'Voorbeeldnotitie op afrekenpagina (indien thuislevering).', 'oxfam-webshop' );
				break;
		}
		$fields['order']['order_comments']['placeholder'] = $placeholder;
		$fields['order']['order_comments']['description'] = sprintf( __( 'Boodschap onder de notities op de afrekenpagina, inclusief telefoonnummer van de hoofdwinkel (%s).', 'oxfam-webshop' ), get_oxfam_shop_data( 'telephone' ) );
		return $fields;
	}

	// add_action( 'woocommerce_checkout_update_order_meta', 'save_estimated_delivery' );
	// Wanneer het order BETAALD wordt, slaan we de geschatte leverdatum op
	add_action( 'woocommerce_order_status_pending_to_processing', 'save_estimated_delivery' );

	function save_estimated_delivery( $order_id ) {
		$order = wc_get_order($order_id);
		$shipping = $order->get_shipping_methods();
		$shipping = reset($shipping);
		$timestamp = estimate_delivery_date( $shipping['method_id'], $order_id );
		update_post_meta( $order_id, 'estimated_delivery', $timestamp );
	}

	// Valideer en formatteer het geboortedatumveld
	add_action( 'woocommerce_checkout_process', 'verify_age' );

	function verify_age() {
		$birthday = format_date( $_POST['billing_birthday'] );
		if ( $birthday ) {
			// Opletten met de Amerikaanse interpretatie DD/MM/YYYY!
			if ( strtotime( str_replace( '/', '-', $birthday ) ) > strtotime( '-18 years' ) ) {
		        wc_add_notice( __( 'Om een bestelling te kunnen plaatsen dien je minstens 18 jaar oud te zijn.' ), 'error' );
		    } else {
		    	$_POST['billing_birthday'] = $birthday;
		    }
		} else {
			wc_add_notice( __( 'Geef een geldige geboortedatum in.' ), 'error' );	
		}
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
		return implode( '-', array_map( 'ucwords', explode( '-', mb_strtolower( trim($value) ) ) ) );
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

	function format_headquarter( $value ) {
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
		if ( strlen($value) === 4 ) {
			return substr($value, 0, 2) . ':' . substr($value, 2, 2);
		} else {
			// Rekening houden met ochtenduren!
			return substr($value, 0, 1) . ':' . substr($value, 1, 2);
		}
	}

	function format_date( $value ) {
		$new_value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( strlen($new_value) === 8 ) {
			return substr($new_value, 0, 2) . '/' . substr($new_value, 2, 2) . '/' . substr($new_value, 4, 4);
		} else {
			return false;
		}
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
		if ( in_array( 'local_manager', $user_roles ) ) {
			?>
			<script type="text/javascript">
				/* Verhinder dat lokale webbeheerders het e-mailadres aanpassen van hun hoofdaccount */
				jQuery("tr.user-email-wrap").find('input[type=email]').prop('readonly', true);
				jQuery("tr.user-email-wrap").find('input[type=email]').after('<span class="description">De lokale beheerder dient altijd gekoppeld te blijven aan de webshopmailbox, dus dit veld kun je niet bewerken.</span>');
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
				$descr .= sprintf( __( 'Dag (%1$s) en uur (%2$s) vanaf wanneer de bestelling klaarstaat voor afhaling', 'oxfam-webshop' ), date_i18n( 'l d/m/Y', $timestamp ), date_i18n( 'G\ui', $timestamp ) );
				$label .= ':'.wc_price(0);
				break;
			// Alle instances van postpuntlevering
			case stristr( $method->id, 'service_point_shipping_method' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop het pakje beschikbaar zal zijn in postpunt / automaat', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				if ( floatval( $method->cost ) === 0.0 ) {
					$label = str_replace( 'Afhaling', 'Gratis afhaling', $label );
					$label .= ':'.wc_price(0);
				}
				break;
			// Alle instances van thuislevering
			case stristr( $method->id, 'flat_rate' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop de levering zal plaatsvinden', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				break;
			// Alle instances van gratis thuislevering
			case stristr( $method->id, 'free_shipping' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop de levering zal plaatsvinden', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				$label .= ':'.wc_price(0);
				break;
			default:
				$descr .= __( 'Boodschap indien schatting leverdatum niet beschikbaar', 'oxfam-webshop' );
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

	// Stop de openingsuren in een logische array (met dagindices van 1 tot 7!)
	function get_office_hours( $node = 0 ) {
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		
		if ( $node === 'griffel' ) {
			$hours = array(
				1 => array(
					array(
						'start' => '7:00',
						'end' => '12:30',
					),
				),
				2 => array(
					array(
						'start' => '7:00',
						'end' => '12:30',
					),
					array(
						'start' => '13:30',
						'end' => '18:00',
					),
				),
				3 => array(
					array(
						'start' => '7:00',
						'end' => '12:30',
					),
					array(
						'start' => '13:30',
						'end' => '18:00',
					),
				),
				4 => array(
					array(
						'start' => '7:00',
						'end' => '12:30'
					),
					array(
						'start' => '13:30',
						'end' => '18:00'
					),
				),
				5 => array(
					array(
						'start' => '7:00',
						'end' => '12:30'
					),
					array(
						'start' => '13:30',
						'end' => '18:00'
					),
				),
				6 => array(
					array(
						'start' => '7:00',
						'end' => '12:30'
					),
					array(
						'start' => '13:30',
						'end' => '18:00'
					),
				),
				7 => false,
			);
		} elseif( $node === 'martinique' ) {
			$hours = array(
				1 => array(
					array(
						'start' => '9:00',
						'end' => '22:00',
					),
				),
				2 => array(
					array(
						'start' => '9:00',
						'end' => '22:00',
					),
				),
				3 => array(
					array(
						'start' => '9:00',
						'end' => '22:00',
					),
				),
				4 => array(
					array(
						'start' => '9:00',
						'end' => '22:00',
					),
				),
				5 => array(
					array(
						'start' => '9:00',
						'end' => '21:00',
					),
				),
				6 => array(
					array(
						'start' => '9:00',
						'end' => '16:00',
					),
				),
				7 => array(
					array(
						'start' => '9:00',
						'end' => '12:30',
					),
				),
			);
		} else {
			for ( $day = 0; $day <= 6; $day++ ) {
				$hours[$day] = get_office_hours_for_day( $day, $node );
			}
			// Forceer 'natuurlijke' nummering
			$hours[7] = $hours[0];
			unset( $hours[0] );
		}

		return $hours;
	}

	// Bereken de eerst mogelijke leverdatum voor de opgegeven verzendmethode (retourneert een timestamp) 
	function estimate_delivery_date( $shipping_id, $order_id = false ) {
		$deadline = get_office_hours();
		
		// We gebruiken het geregistreerde besteltijdstip OF het live tijdstip voor schattingen van de leverdatum
		if ( $order_id === false ) {
			$from = current_time( 'timestamp' );
		} else {
			$order = wc_get_order($order_id);
			// We hebben de timestamp van de besteldatum nodig in de huidige tijdzone, dus pas get_date_from_gmt() toe die het formaat 'Y-m-d H:i:s' vereist!
			$from = strtotime( get_date_from_gmt( date_i18n( 'Y-m-d H:i:s', strtotime( $order->get_date_created() ) ) ) );
		}
		
		$timestamp = $from;
		write_log($shipping_id);
		write_log( date_i18n( 'd/m/Y H:i', $timestamp ) );
		
		switch ( $shipping_id ) {
			// Alle instances van winkelafhalingen
			case stristr( $shipping_id, 'local_pickup' ):
				// Standaard: bereken a.d.h.v. de hoofdwinkel
				$node = get_option( 'oxfam_shop_node' );
				
				if ( $locations = get_option( 'woocommerce_pickup_locations' ) ) {
					if ( $order_id === false ) {
						$pickup_locations = WC()->session->get('chosen_pickup_locations');
						$pickup_id = reset($pickup_locations);
					} else {
						$methods = $order->get_shipping_methods();
						$method = reset($methods);
						$pickup_location = $method->get_meta('pickup_location');
						$pickup_id = $pickup_location['id'];
					}
					foreach ( $locations as $location ) {
						if ( $location['id'] == $pickup_id ) {
							// var_dump($location);
							$parts = explode( 'node=', $location['note'] );
							if ( isset($parts[1]) ) {
								// Afwijkend punt geselecteerd: bereken a.d.h.v. het nodenummer in de openingsuren
								$node = str_replace( ']', '', $parts[1] );
							}
							break;
						}
					}
				}

				// Zoek de eerste werkdag na de volgende middagdeadline
				$timestamp = get_first_working_day( $from );

				// Tel feestdagen die in de verwerkingsperiode vallen erbij
				$timestamp = move_date_on_holidays( $from, $timestamp );
				
				write_log( date_i18n( 'd/m/Y H:i', $timestamp ) );
		
				// Check of de winkel op deze dag effectief nog geopend is na 12u
				$timestamp = find_first_opening_hour( get_office_hours( $node ), $timestamp );

				write_log( date_i18n( 'd/m/Y H:i', $timestamp ) );

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
			// We zitten al na de deadline van een werkdag, begin pas vanaf volgende werkdag te tellen
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
						$timestamp = find_first_opening_hour( $hours, strtotime( 'tomorrow', $from ), false );
					}
				}
			} else {
				// Neem sowieso het openingsuur van het eerste dagdeel
				$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
			}
		} else {
			// Vandaag zijn we gesloten, probeer het morgen opnieuw
			// Het mag ook een dag in het weekend zijn, de wachttijd is vervuld!
			$timestamp = find_first_opening_hour( $hours, strtotime( 'tomorrow', $from ), false );
		}
		return $timestamp;
	}

	// Bewaar het verzendadres niet tijdens het afrekenen indien het om een afhaling gaat WEL BIJ SERVICE POINT, WANT NODIG VOOR IMPORT
	add_filter( 'woocommerce_cart_needs_shipping_address', 'skip_shipping_address_on_pickups' ); 
	
	function skip_shipping_address_on_pickups( $needs_shipping_address ) {
		$chosen_methods = WC()->session->get('chosen_shipping_methods');
		// Deze vergelijking zoekt naar methodes die beginnen met deze string
		if ( strpos( reset($chosen_methods), 'local_pickup' ) !== false ) {
			$needs_shipping_address = false;
		}
		return $needs_shipping_address;
	}

	// Verberg het verzendadres na het bestellen ook bij een postpuntlevering in de front-end
	add_filter( 'woocommerce_order_hide_shipping_address', 'hide_shipping_address_on_pickups' ); 
	
	function hide_shipping_address_on_pickups( $hide_on_methods ) {
		// Bevat 'local_pickup' reeds via core en 'local_pickup_plus' via filter in plugin
		// Instances worden er afgeknipt bij de check dus achterwege laten
		$hide_on_methods[] = 'service_point_shipping_method';
		return $hide_on_methods;
	}

	function validate_zip_code( $zip ) {
		if ( does_home_delivery() and $zip !== 0 ) {
			if ( ! array_key_exists( $zip, get_site_option( 'oxfam_flemish_zip_codes' ) ) ) {
				wc_add_notice( __( 'Foutmelding na het ingeven van een onbestaande Vlaamse postcode.', 'oxfam-webshop' ), 'error' );
			} else {
				if ( ! in_array( $zip, get_option( 'oxfam_zip_codes' ) ) and is_cart() ) {
					// Enkel tonen op de winkelmandpagina, tijdens de checkout gaan we ervan uit dat de klant niet meer radicaal wijzigt (niet afschrikken met error!)
					$str = date_i18n('d/m/Y H:i:s')."\t\t".get_home_url()."\t\tPostcode ingevuld waarvoor deze winkel geen verzending organiseert\n";
					file_put_contents("shipping_errors.csv", $str, FILE_APPEND);
					$msg = WC()->session->get( 'no_zip_delivery' );
					// Toon de foutmelding slechts één keer
					if ( $msg !== 'SHOWN' ) {
						// Check eventueel of de boodschap al niet in de pijplijn zit door alle values van de array die wc_get_notices( 'error' ) retourneert te checken
						wc_add_notice( __( 'Foutmelding na het ingeven van een postcode waar deze webshop geen thuislevering voor organiseert.', 'oxfam-webshop' ), 'error' );
						WC()->session->set( 'no_zip_delivery', 'SHOWN' );
					}
				} else {
					WC()->session->set( 'no_zip_delivery', 'FIRST' );
				}
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
			if ( $item_value['data']->get_shipping_class() === 'breekbaar' ) {
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
			// Boodschap heeft enkel zin als thuislevering aangeboden wordt!
			if ( does_home_delivery() ) {
				$msg = WC()->session->get( 'no_home_delivery' );
				// Toon de foutmelding slechts één keer
				if ( $msg !== 'SHOWN' ) {
					wc_add_notice( sprintf( __( 'Foutmelding bij aanwezigheid van producten die niet thuisgeleverd worden, inclusief het aantal flessen (%d).', 'oxfam-webshop' ), $forbidden_cnt - floor( $forbidden_cnt / 6 ) ), 'error' );
					WC()->session->set( 'no_home_delivery', 'SHOWN' );
				}
			}
		} else {
			WC()->session->set( 'no_home_delivery', 'FIRST' );
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
			wc_add_notice( sprintf( __( 'Foutmelding bij bestellingen boven de 30 kg, inclusief het huidige gewicht in kilogram (%s).', 'oxfam-webshop' ), number_format( $cart_weight, 1, ',', '.' ) ), 'error' );
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
						// Unset i.p.v. op nul te zetten
						unset($rate->taxes[$tax_id_free]);
						$rate->taxes[$tax_id_cost] = $taxes;
						break;
					default:
						// Dit zijn de gratis pick-ups (+ eventueel thuisleveringen), niets mee doen
						break;
				}
			}
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

	// Voeg instructietekst toe boven de locaties
	// add_action( 'woocommerce_review_order_before_local_pickup_location', 'add_local_pickup_instructions' ) {
	
	function add_local_pickup_instructions() {
		echo '<p>Je kunt kiezen uit deze winkels ...</p>';
	}

	// Check of de persoon moet worden ingeschreven op het digizine 
	add_action( 'woocommerce_checkout_process', 'check_subscription_preference', 10, 1 );

	function check_subscription_preference( $posted ) {
		global $user_ID, $woocommerce;
		if ( ! empty( $posted['subscribe_digizine'] ) ) {
			if ( $posted['subscribe_digizine'] !== 1 ) {
				// wc_add_notice( __( 'Oei, je hebt ervoor gekozen om je niet te abonneren op het Digizine. Ben je zeker van je stuk?', 'oxfam-webshop' ), 'error' );
			}
		}

		// Eventueel bestelminimum om te kunnen afrekenen
		$min = 10;
		$max = 500;
		if ( round( $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total, 2 ) < $min ) {
	  		wc_add_notice( sprintf( __( 'Foutmelding bij te kleine bestellingen, inclusief minimumbedrag in euro (%d).', 'oxfam-webshop' ), $min ), 'error' );
	  	} elseif ( round( $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total, 2 ) > $max ) {
	  		wc_add_notice( sprintf( __( 'Foutmelding bij te grote bestellingen, inclusief maximumbedrag in euro (%d).', 'oxfam-webshop' ), $max ), 'error' );
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
					$forbidden_qty = 0;
					$glass_id = wc_get_product_id_by_sku('WLFSG');
					$plastic_id = wc_get_product_id_by_sku('WLBS6M');
					// write_log($empties);
					// write_log($product_item);
					foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
						if ( intval($values['product_id']) === $glass_id ) {
							$forbidden_qty += intval($values['quantity']);
							// write_log($values['quantity']." GROTE FLESSEN LEEGGOED ERBIJ GETELD");
						}
						if ( intval($values['product_id']) === $plastic_id ) {
							$plastic_qty = intval($values['quantity']);
						}
					}
					write_log("AANTAL GROTE FLESSEN LEEGGOED: ".$forbidden_qty);
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
				$forbidden_qty = 0;
				$plastic_qty = 0;
				$glass_id = wc_get_product_id_by_sku('WLFSG');
				$plastic_id = wc_get_product_id_by_sku('WLBS6M');
				// write_log($empties_item);
				foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
					if ( intval($values['product_id']) === $glass_id ) {
						$forbidden_qty += intval($values['quantity']);
						// write_log($values['quantity']." GROTE FLESSEN LEEGGOED ERBIJ GETELD");
					}
					if ( intval($values['product_id']) === $plastic_id ) {
						$plastic_qty += intval($values['quantity']);
						$plastic_item_key = $cart_item_key;
					}
				}
				write_log("AANTAL GROTE FLESSEN LEEGGOED: ".$forbidden_qty);
				if ( $forbidden_qty === 6 and $plastic_qty === 0 ) {
					// Zorg dat deze cart_item ook gelinkt is aan het product waaraan de fles al gelinkt was
					$args['forced_by'] = $empties_item['forced_by'];
					$result = WC()->cart->add_to_cart( wc_get_product_id_by_sku('WLBS6M'), 1, $empties_item['variation_id'], $empties_item['variation'], $args );
				} elseif ( $forbidden_qty % 6 === 0 and $plastic_qty !== 0 ) {
					$result = WC()->cart->set_quantity( $plastic_item_key, floor( $forbidden_qty / 6 ), 1 );
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
	add_filter( 'woocommerce_cart_item_quantity', 'add_bottles_to_quantity', 10, 3 );
	
	function add_bottles_to_quantity( $product_quantity, $cart_item_key, $cart_item ) {
		$productje = wc_get_product( $cart_item['product_id'] );
		if ( $productje->is_visible() ) {
			return $product_quantity;
		} else {
			return $product_quantity.' flessen';
		}
	}

	// Tel leeggoed niet mee bij aantal items in winkelmandje
	add_filter( 'woocommerce_cart_contents_count', 'exclude_empties_from_cart_count' );
	
	function exclude_empties_from_cart_count( $count ) {
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
		add_menu_page( 'Stel de voorraad van je lokale webshop in', 'Voorraadbeheer', 'manage_network_users', 'oxfam-products-list', 'oxfam_products_list_callback', 'dashicons-admin-settings', '56' );
		add_submenu_page( 'oxfam-products-list', 'Stel de voorraad van je lokale webshop in', 'Lijstweergave', 'manage_network_users', 'oxfam-products-list', 'oxfam_products_list_callback' );
		add_submenu_page( 'oxfam-products-list', 'Stel de voorraad van je lokale webshop in', 'Fotoweergave', 'manage_network_users', 'oxfam-products-photos', 'oxfam_products_photos_callback' );
		add_menu_page( 'Handige gegevens voor je lokale webshop', 'Winkelgegevens', 'manage_network_users', 'oxfam-options', 'oxfam_options_callback', 'dashicons-megaphone', '58' );
		if ( is_main_site() ) {
			add_media_page( 'Productfoto\'s', 'Productfoto\'s', 'create_sites', 'oxfam-photos', 'oxfam_photos_callback' );
		}
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
	
	// Vervang onnutige links in netwerkmenu door Oxfam-pagina's
	add_action( 'wp_before_admin_bar_render', 'oxfam_admin_bar_render' );

	function oxfam_admin_bar_render() {
		global $wp_admin_bar;
		if ( current_user_can('create_sites') ) {
			$toolbar_nodes = $wp_admin_bar->get_nodes();
			$sites = get_sites( array( 'archived' => 0 ) );
			foreach ( $sites as $site ) {
				$node_n = $wp_admin_bar->get_node('blog-'.$site->blog_id.'-n');
				if ( $node_n ) {
					$new_node = $node_n;
					$wp_admin_bar->remove_node('blog-'.$site->blog_id.'-n');
					$new_node->title = 'Winkelgegevens';
					$new_node->href = network_site_url( $site->path.'wp-admin/admin.php?page=oxfam-options' );
					$wp_admin_bar->add_node( $new_node );
				}
				$node_c = $wp_admin_bar->get_node('blog-'.$site->blog_id.'-c');
				if ( $node_c ) {
					$new_node = $node_c;
					$wp_admin_bar->remove_node('blog-'.$site->blog_id.'-c');
					$new_node->title = 'Voorraadbeheer';
					$new_node->href = network_site_url( $site->path.'wp-admin/admin.php?page=oxfam-products-list' );
					$wp_admin_bar->add_node( $new_node );
				}
			}
		}
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
		// Wordt standaard op ID geordend, dus creatie op hoofdsite gebeurt als eerste (= noodzakelijk!)
		$sites = get_sites( array( 'archived' => 0 ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			echo register_photo( $_POST['name'], $_POST['timestamp'], $_POST['path'] );
			restore_current_blog();
		}
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

    function register_photo( $filename, $filestamp, $main_filepath ) {			
    	// Parse de fototitel
    	$filetitle = explode( '.jpg', $filename );
	    $filetitle = $filetitle[0];
	    $product_id = 0;

		if ( ! is_main_site() ) {
		    // Bepaal het bestemmingspad in de subsite (zonder dimensies!)
			$child_filepath = str_replace( '/uploads/', '/uploads/sites/'.get_current_blog_id().'/', $main_filepath );
			switch_to_blog(1);
			// Zoek het pad van de 'large' thumbnail op in de hoofdsite
			$main_attachment_id = wp_get_attachment_id_by_post_name( $filetitle );
			if ( $main_attachment_id ) {
				// Kopieer een middelgrote thumbnail van de hoofdsite als 'full' naar de huidige subsite
				copy( get_scaled_image_path( $main_attachment_id, 'medium' ), $child_filepath );
				$current_filepath = $child_filepath;
			}
			restore_current_blog();
		} else {
			$current_filepath = $main_filepath;
		}
    	
    	// Check of er al een vorige versie bestaat
    	$updated = false;
    	$deleted = false;
    	// GEBEURT IN DE LOKALE MEDIABIB
    	$old_id = wp_get_attachment_id_by_post_name( $filetitle );
		if ( $old_id ) {
			// Bewaar de post_parent van het originele attachment
			$product_id = wp_get_post_parent_id( $old_id );
			// Check of de uploadlocatie op dit punt al ingegeven is!
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
			}

			// Stel het originele high-res bestand veilig
			rename( $current_filepath, WP_CONTENT_DIR.'/uploads/temporary.jpg' );
			// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!)
			// GEBEURT IN DE LOKALE MEDIABIB
    		if ( wp_delete_attachment( $old_id, true ) ) {
				// Extra check op het succesvol verwijderen
				$deleted = true;
			}
			$updated = true;
			// Hernoem opnieuw zodat de links weer naar de juiste file wijzen 
			rename( WP_CONTENT_DIR.'/uploads/temporary.jpg', $current_filepath );
		}
		
		// Creëer de parameters voor de foto
		$wp_filetype = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => $filetitle,
			'post_content' => '',
			'post_author' => get_current_user_id(),
			'post_status' => 'inherit',
		);

		// Probeer de foto in de mediabibliotheek te stoppen
		// Laatste argument: stel de uploadlocatie van de nieuwe afbeelding in op het product van het origineel (of 0 = geen)
		$attachment_id = wp_insert_attachment( $attachment, $current_filepath, $product_id );
		
		if ( ! is_wp_error( $attachment_id ) ) {
			if ( isset($product) ) {
				// Voeg de nieuwe attachment-ID weer toe aan het oorspronkelijke product
				$product->set_image_id( $attachment_id );
				$product->save();
			}

			// Registreer ook de metadata
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $current_filepath );
			wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			// Toon een succesboodschap
			if ( $updated ) {
				$deleted = $deleted ? "verwijderd en opnieuw aangemaakt" : "bijgewerkt";
				$msg = "<i>".$filename."</i> ".$deleted." in mediabibliotheek van site-ID ".get_current_blog_id()." om ".date_i18n('H:i:s')." ...<br>";
			} else {
				$msg = "<i>".$filename."</i> aangemaakt in mediabibliotheek van site-ID ".get_current_blog_id()." om ".date_i18n('H:i:s')." ...<br>";
			}
			// Sla het uploadtijdstip van de laatste succesvolle registratie op (indien recenter dan huidige optiewaarde)
			if ( $filestamp > get_option( 'laatste_registratie_timestamp' ) ) {
				update_option( 'laatste_registratie_timestamp', $filestamp );
			}
			$registered = true;
		} else {
			// Geef een waarschuwing als de aanmaak mislukte
			$msg = "Opgelet, er liep iets mis met <i>".$filename."</i>!<br>";
		}

		return $msg;
	}

	function get_scaled_image_path( $attachment_id, $size = 'full' ) {
		$file = get_attached_file( $attachment_id, true );
		if ( $size === 'full' ) return realpath($file);
		
		$info = image_get_intermediate_size( $attachment_id, $size );
		if ( ! is_array($info) or ! isset($info['file']) ) return false;
		
		return realpath( str_replace( wp_basename($file), $info['file'], $file ) );
	}

	// Toon een boodschap op de detailpagina indien het product niet thuisgeleverd wordt
	// Icoontje wordt toegevoegd op basis van CSS-klasse .product_shipping_class-breekbaar
	add_action( 'woocommerce_single_product_summary', 'show_delivery_warning', 45 );

	function show_delivery_warning() {
		global $product;
		if ( $product->get_shipping_class() === 'breekbaar' ) {
			echo "<p>Opgelet, dit product kan enkel afgehaald worden in de winkel! Tip: kleine glazen flesjes en tetrabrikken zijn wel beschikbaar voor thuislevering.</p>";
		}

		if ( $product->get_sku() === '20211' and date_i18n('Y-m-d') < '2017-09-01' ) {
			echo "<p style='margin: 1em 0; color: red;'>ZOMERPROMO: 5 + 1 FLES GRATIS</p>";
		}

		$cat_ids = $product->get_category_ids();
		$parent_id = get_term( $cat_ids[0], 'product_cat' )->parent;
		if ( get_term( $cat_ids[0], 'product_cat' )->slug === 'spirits' ) {
			echo "<p style='margin: 1em 0;'>Je dient minstens 18 jaar oud te zijn om dit alcoholische product te bestellen.</p>";
		} elseif ( get_term( $parent_id, 'product_cat' )->slug === 'wijn' ) {
			echo "<p style='margin: 1em 0;'>Je dient minstens 16 jaar oud te zijn om dit alcoholische product te bestellen.</p>";
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

	// Maak onze custom taxonomiën beschikbaar in menu editor
	add_filter('woocommerce_attribute_show_in_nav_menus', 'register_custom_taxonomies_for_menus', 1, 2 );

	function register_custom_taxonomies_for_menus( $register, $name = '' ) {
		$register = true;
		return $register;
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
	add_filter( 'woocommerce_product_tabs', 'add_extra_product_tabs' );
	
	function add_extra_product_tabs( $tabs ) {
		global $product;
		// Voeg tabje met herkomstinfo toe
		$tabs['partner_info'] = array(
			'title' 	=> 'Partnerinfo',
			'priority' 	=> 12,
			'callback' 	=> function() { output_tab_content('partner'); },
		);

		// Voeg tabje met voedingswaarde toe (indien niet leeg)
		if ( get_tab_content('food') !== false ) {
			$eh = $product->get_attribute( 'pa_eenheid' );
			if ( $eh === 'L' ) {
				$suffix = 'ml';
			} elseif ( $eh === 'KG' ) {
				$suffix = 'g';
			}
			$tabs['food_info'] = array(
				'title' 	=> 'Voedingswaarde per 100 '.$suffix,
				'priority' 	=> 14,
				'callback' 	=> function() { output_tab_content('food'); },
			);
		}

		// Voeg tabje met allergenen toe
		$tabs['allergen_info'] = array(
			'title' 	=> 'Allergenen',
			'priority' 	=> 16,
			'callback' 	=> function() { output_tab_content('allergen'); },
		);

		// Titel wijzigen van standaardtabs kan maar prioriteit niet! (description = 10, additional_information = 20)
		$tabs['additional_information']['title'] = 'Technische fiche';
		
		return $tabs;
	}

	// Retourneer de gegevens voor een custom tab (antwoordt met FALSE indien geen gegevens beschikbaar)
	function get_tab_content( $type ) {
		global $product;
		$has_row = false;
		$alt = 1;
		ob_start();
		echo '<table class="shop_attributes">';

		if ( $type === 'partner' ) {
			// Partnertab altijd tonen!
			$has_row = true;
			$str = 'partners';

			$partners = get_partner_terms_by_product( $product );
			if ( count($partners) > 0 ) {
				if ( count($partners) === 1 ) $str = 'een partner';
				?>
					<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
						<th>Partners</th>
						<td>
						<?php
							$i = 1;
							foreach ( $partners as $term_id => $partner_name ) {
								$partner_info = get_info_by_partner( get_term_by( 'id', $term_id, 'product_partner' ) );
								
								if ( isset( $partner_info['archive'] ) and strlen( $partner_info['archive'] ) > 10 ) {
									$text = '<a href="'.$partner_info['archive'].'" title="Bekijk alle producten van deze partner">'.$partner_info['name'].'</a>';
								} else {
									$text = $partner_info['name'];
								}
								
								if ( $i !== 1 ) $msg .= '<br>';
								$msg .= $text." &mdash; ".$partner_info['country'];
								$i++;
							}
							echo $msg;
						?>
						</td>
					</tr>
				<?php
			}
			
			// Enkel tonen indien percentage bekend 
			if ( intval( $product->get_attribute( 'pa_fairtrade' ) ) > 40 ) {
			?>
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
					<th><?php echo 'Fairtradepercentage'; ?></th>
					<td><?php echo 'Dit product is voor '.number_format( $product->get_attribute( 'pa_fairtrade' ), 0 ).' % afkomstig van '.$str.' waarmee Oxfam-Wereldwinkels een eerlijke handelsrelatie onderhoudt. <a href="https://www.oxfamwereldwinkels.be/nl/certificering" target="_blank">Lees meer over deze certificering op onze website.</a>'; ?></td>
				</tr>
			<?php	
			}
			
		} elseif ( $type === 'food' ) {
			$attributes = $product->get_attributes();

			foreach ( $attributes as $attribute ) {
				$forbidden = array( 'pa_ompak', 'pa_eenheid', 'pa_fairtrade', 'pa_shopplus' );
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
				<?php
			}

		} elseif ( $type === 'allergen' ) {
			// Allergenentab altijd tonen!
			$has_row = true;
			$allergens = get_the_terms( $product->get_id(), 'product_allergen' );

			foreach ( $allergens as $allergen ) {
				if ( get_term_by( 'id', $allergen->parent, 'product_allergen' )->slug === 'contains' ) {
					$contains[] = $allergen;
				} elseif ( get_term_by( 'id', $allergen->parent, 'product_allergen' )->slug === 'may-contain' ) {
					$traces[] = $allergen;
				}
			}
			?>
			<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
				<th><?php echo 'Dit product bevat'; ?></th>
				<td>
				<?php
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
				?>
				</td>
			</tr>

			<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
				<th><?php echo 'Kan sporen bevatten van'; ?></th>
				<td>
				<?php
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
				?>
				</td>
			</tr>
			<?php
		}
		
		echo '</table>';
		
		if ( $has_row ) {
			return ob_get_clean();
		} else {
			ob_end_clean();
			return false;
		}
	}

	// Print de inhoud van een tab
	function output_tab_content( $type ) {
		if ( get_tab_content( $type ) !== false ) {
			echo '<div class="nm-additional-information-inner">'.get_tab_content( $type ).'</div>';
		} else {
			echo '<div class="nm-additional-information-inner"><i>Geen info beschikbaar.</i></div>';
		}
	}

	// Retourneert een array met strings van landen waaruit dit product afkomstig is (en anders false)
	function get_countries_by_product( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_partner' );
		$args = array( 'taxonomy' => 'product_partner', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' );
		$continents = get_terms( $args );
		
		if ( count($terms) > 0 ) {
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
		} else {
			// Fallback indien nog geen herkomstinfo bekend
			$countries = false;
		}
		
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
		$partner_info['archive'] = get_term_link( $partner->term_id );
		
		if ( strlen( $partner->description ) > 20 ) {
			// Knip bij het woord 'node/'
			$url = explode('node/', $partner->description);
			$parts = explode('"', $url[1]);
			$partner_info['node'] = $parts[0];
			$partner_info['url'] = 'https://www.oxfamwereldwinkels.be/node/'.$partner_info['node'];
			
			$quote = $wpdb->get_row( 'SELECT * FROM field_data_field_manufacturer_quote WHERE entity_id = '.$partner_info['node'] );
			if ( strlen( $quote->field_manufacturer_quote_value ) > 20 ) {
				$partner_info['quote'] = trim($quote->field_manufacturer_quote_value);
				$quote_by = $wpdb->get_row( 'SELECT * FROM field_data_field_manufacturer_hero_name WHERE entity_id = '.$partner_info['node'] );
				if ( strlen( $quote_by->field_manufacturer_hero_name_value ) > 5 ) {
					$partner_info['quote_by'] = trim($quote_by->field_manufacturer_hero_name_value);
				}
			}
		}

		return $partner_info;
	}

	// Vervroeg actie zodat ze ook in de linkerkolom belandt op tablet (blijkbaar alles t.e.m. prioriteit 15)
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 12 );
	
	// Herkomstlanden tonen, net boven de winkelmandknop
	add_action( 'woocommerce_single_product_summary', 'show_herkomst', 14 );

	function show_herkomst() {
		global $product;
		echo '<p class="herkomst">';
		echo 'Herkomst: '.$product->get_meta( '_herkomst_nl', true );
		echo '</p>';
	}

	// Partnerquote tonen, net onder de winkelmandknop
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
				if ( strlen( $partners_with_quote[$i]['quote_by'] ) > 2 ) {
					$signature = $partners_with_quote[$i]['quote_by'];
				} else {
					$signature = $partners_with_quote[$i]['name'].', '.$partners_with_quote[$i]['country'];
				}
				echo nm_shortcode_nm_testimonial( array( 'signature' => $signature ), $partners_with_quote[$i]['quote'] );
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
	add_filter( 'woocommerce_attribute', 'add_suffixes', 10, 3 );

	function add_suffixes( $wpautop, $attribute, $values ) {
		$weighty_attributes = array( 'pa_choavl', 'pa_famscis', 'pa_fapucis', 'pa_fasat', 'pa_fat', 'pa_fibtg', 'pa_polyl', 'pa_pro', 'pa_salteq', 'pa_starch', 'pa_sugar' );
		$percenty_attributes = array( 'pa_alcohol', 'pa_fairtrade' );
		$energy_attributes = array( 'pa_ener' );

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
		} elseif ( $attribute['name'] === 'pa_eprijs' ) {
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

	// Verhinder dat de lokale voorraad- en uitlichtingsinstellingen overschreven worden bij elke update
	add_filter( 'woo_mstore/save_meta_to_post/ignore_meta_fields', 'ignore_featured_and_stock', 10, 2);

	function ignore_featured_and_stock( $ignored_fields, $post_id ) {
		$ignored_fields[] = 'total_sales';
		$ignored_fields[] = '_stock';
		$ignored_fields[] = '_stock_status';
		$ignored_fields[] = '_wc_review_count';
		$ignored_fields[] = '_wc_rating_count';
		$ignored_fields[] = '_wc_average_rating';
		$ignored_fields[] = '_barcode';
		$ignored_fields[] = 'title_fr';
		$ignored_fields[] = 'description_fr';
		$ignored_fields[] = '_herkomst_fr';
		$ignored_fields[] = 'title_en';
		$ignored_fields[] = 'description_en';
		$ignored_fields[] = '_herkomst_en';
		$ignored_fields[] = '_barcode';
		$ignored_fields[] = '_in_bestelweb';
		$ignored_fields[] = 'pal_aantallagen';
		$ignored_fields[] = 'pal_aantalperlaag';
		$ignored_fields[] = 'steh_ean';
		$ignored_fields[] = 'intrastat';
		return $ignored_fields;
	}

	// Zorg dat productupdates ook gesynchroniseerd worden via WP All Import (hoge prioriteit = helemaal op het einde)
	add_action( 'pmxi_saved_post', 'run_product_sync', 50, 1 );
	
	function run_product_sync( $post_id ) {
		// Enkel uitvoeren indien het een product was dat bijgewerkt werd
		if ( get_post_type( $post_id ) === 'product' ) {
			global $WOO_MSTORE;
			$WOO_MSTORE->quick_edit_save( $post_id, get_post( $post_id ), true );
		}
	}

	// Stel de attributen in die berekend moeten worden uit andere waarden
	add_action( 'set_object_terms', 'update_origin_on_update', 50, 6 );
	
	function update_origin_on_update( $post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// Enkel uitvoeren indien de partnerinfo van een product bijgewerkt werd
		if ( get_post_type( $post_id ) === 'product' and $taxonomy === 'product_partner' ) {
			write_log("HERKOMST BIJWERKEN ...");
			$productje = wc_get_product( $post_id );
			$countries_nl = get_countries_by_product( $productje );
			
			// Check of er wel herkomstinfo beschikbaar is
			if ( $countries_nl !== false ) {
				update_post_meta( $post_id, '_herkomst_nl', implode( ', ', $countries_nl ) );
			
				if ( is_main_site() ) {
					foreach ( $countries_nl as $country ) {
						$nl = get_site_option( 'countries_nl' );
						$code = array_search( $country, $nl, true );
						// We hebben een geldige landencode gevonden
						if ( strlen($code) === 3 ) {
							$countries_fr[] = translate_to_fr( $code );
							$countries_en[] = translate_to_en( $code );
						}
					}

					sort($countries_fr, SORT_STRING);
					update_post_meta( $post_id, '_herkomst_fr', implode( ', ', $countries_fr ) );
					sort($countries_en, SORT_STRING);
					update_post_meta( $post_id, '_herkomst_en', implode( ', ', $countries_en ) );
				}
			}
		}
	}	

	function translate_to_fr( $code ) {
		$fr = get_site_option( 'countries_fr' );
		return $fr[$code];
	}

	function translate_to_en( $code ) {
		$en = get_site_option( 'countries_en' );
		return $en[$code];
	}

	// Reset alle '_in_bestelweb' velden voor we aan de ERP-import beginnen
	add_action( 'pmxi_before_xml_import', 'before_xml_import', 10, 1 );
	
	function before_xml_import( $import_id ) {
		if ( $import_id == 7 ) {
			// Zet de key '_in_bestelweb' van alle producten op nee
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array( 'publish', 'draft', 'trash' ),
				'posts_per_page'	=> -1,
			);

			$to_remove = new WP_Query( $args );

			if ( $to_remove->have_posts() ) {
				while ( $to_remove->have_posts() ) {
					$to_remove->the_post();
					update_post_meta( get_the_ID(), '_in_bestelweb', 'nee' );
				}
				wp_reset_postdata();
			}
		}
	}

	// Zet producten die onaangeroerd bleven door de ERP-import uit voorraad
	add_action( 'pmxi_after_xml_import', 'after_xml_import', 10, 1 );
	
	function after_xml_import( $import_id ) {
		if ( $import_id == 7 ) {
			// Vind alle producten waarvan de key '_in_bestelweb' onaangeroerd is (= zat niet in Odisy-import)
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array( 'publish', 'draft', 'trash' ),
				'posts_per_page'	=> -1,
				'meta_key'			=> '_in_bestelweb', 
				'meta_value'		=> 'nee',
				'meta_compare'		=> '=',
			);

			$to_outofstock = new WP_Query( $args );

			if ( $to_outofstock->have_posts() ) {
				while ( $to_outofstock->have_posts() ) {
					$to_outofstock->the_post();
					$productje = wc_get_product( get_the_ID() );
					$productje->set_stock_status('outofstock');
					$productje->save();
				}
				wp_reset_postdata();
			}

			// Hernoem het importbestand zodat we een snapshot krijgen dat niet overschreven wordt
			$old = WP_CONTENT_DIR."/erp-import.csv";
			$new = WP_CONTENT_DIR."/erp-import-".date_i18n('Y-m-d').".csv";
			rename( $old, $new );
		}
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
            // Niet serialiseren voor coupons
            if ( $metakey === 'product_ids' ) {
            	$local_product_ids = implode( ',', $local_product_ids );
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
		echo "<p>De <a href='https://github.com/OxfamFairTrade/ob2c/wiki' target='_blank'>online FAQ voor webshopbeheerders</a> staat online. Hierin verzamelen we alle mogelijke vragen die jullie als lokale webshopbeheerders kunnen hebben en beantwoorden we ze punt per punt met tekst en screenshots. Daarnaast kun je nog altijd <a href='https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-1ste-opleiding-B2C-webshop.pdf' target='_blank'>de slides van de 1ste opleidingssessie</a> raadplegen voor een overzicht van alle afspraken, en de <a href='https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-2de-opleiding-B2C-webshop.pdf' target='_blank'>de slides van de 2de opleidingssessie</a> voor meer praktische details.</p>";
		echo "<p>Voor onderling overleg (en met ons) kun je vanaf nu ook terecht op <a href='https://oxfamfairtrade.slack.com' target='_blank'>Slack</a>. Iedere webshopvrijwilliger kreeg hiervoor op 22/05/2017 een uitnodigingsmail. Dit is optioneel, alle belangwekkende mededelingen blijven we op dit dashboard plaatsen. In de handleiding vind je steeds de meest uitgebreide informatie, gebruik eventueel de zoekfunctie bovenaan rechts. Op <a href='http://extranet.oxfamwereldwinkels.be/webshop target='_blank'>Extranet</a> vind je ook een overzicht van de belangrijkste documenten.</p>";
		echo "<p>Voor dringende problemen bel je vanaf 1 juli gewoon naar de Klantendienst, die een opleiding kreeg om jullie bij te staan bij het beheer.</p>";
		echo "</div>";
		echo '<div class="rss-widget"><ul>'.get_latest_mailings().'</ul></div>';
	}

	function get_latest_mailings() {
		$server = substr( MAILCHIMP_APIKEY, strpos( MAILCHIMP_APIKEY, '-' ) + 1 );
		$list_id = '53ee397c8b';
		$folder_id = '2a64174067';

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode( 'user:'.MAILCHIMP_APIKEY ),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date_i18n( 'Y-m-d', strtotime('-18 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );
		
		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			
			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a class="rsswidget" href="'.$campaign->long_archive_url.'" target="_blank">'.str_replace( '*|FNAME|*: ', '', $campaign->settings->subject_line ).'</a> ('.date_i18n( 'j F Y', strtotime($campaign->send_time) ).')</li>';
			}
		}		

		return $mailings;
	}

	function get_tracking_number( $order_id ) {
		// Check of we een 24-cijferig tracking number dat door SendCloud geannoteerd werd kunnen terugvinden
		$args = array( 'post_id' => $post_id, 'search' => 'SendCloud' );
		// Want anders zien we de private opmerkingen niet!
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		$comments = get_comments( $args );
		if ( count($comments) > 0 ) {
			$sendcloud_note = $comments[0];
			preg_match( '/[0-9]{24}/', $sendcloud_note->comment_content, $numbers );
			$tracking_number = $numbers[0];
		} else {
			$tracking_number = false;
		}
		// Reactiveer filter
		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		return $tracking_number;
	}

	function get_tracking_link( $order_id ) {
		return 'http://track.bpost.be/btr/web/#/search?itemCode='.get_tracking_number( $order_id ).'&lang=nl';
	}
	// Voeg een bericht toe bovenaan alle adminpagina's
	add_action( 'admin_notices', 'sample_admin_notice' );

	function sample_admin_notice() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		// var_dump($screen);
		if ( $pagenow === 'index.php' and $screen->base === 'dashboard' ) {
			global $activated_shops;
			if ( ! in_array( get_current_blog_id(), $activated_shops ) ) {
				echo '<div class="notice notice-success">';
				echo '<p>Alle logins zijn verstuurd naar de lokale beheerders. Op dit moment kun je enkel als ingelogde winkelbeheerder je webshop bekijken. Pas wanneer jullie zelf aangeven er klaar voor te zijn, publiceren we de site voor gewone bezoekers. We streven ernaar om alle webshops tegen begin augustus publiek te zetten, want in het FAIR-magazine zal een eerste aankondiging verschijnen.</p>';
				echo '</div>';
				echo '<div class="notice notice-info">';
				echo '<p>Volg <a href="https://github.com/OxfamFairTrade/ob2c/wiki/Betaling#hoe-activeer-ik-mijn-account-bij-mollie" target="_blank">de handleiding</a> om de activering van je Mollie-account te voltooien. Het duurt enkele dagen vooraleer je overschrijving met de gestructureerde mededeling verwerkt is. Let er ook goed op dat je de overschrijving uitvoert vanaf de winkelrekening (en niet je persoonlijke rekening!) want anders zal het IBAN-nummer niet herkend worden. De activatie van kredietkaarten als betaalmethode wordt pas afgerond nadat de webshop gepubliceerd is. Een onderdeel van dat activatieproces is immers een controle van de aangeboden producten.</p>';
				echo '</div>';
				echo '<div class="notice notice-error">';
				echo '<p>Sommige winkels kregen een bericht dat hun legitimatiebewijs afgewezen werd. Dit gebeurt indien de rechtsgeldige vertegenwoordiger die we opgaven (= de persoon waarvan jullie ons de identiteitskaart bezorgden) nog niet in de <u>digitale</u> versie van het KBO geregistreerd staat. We adviseren in dat geval om de rechtsgeldige vertegenwoordiger onder de Mollie-instellingen voor \'<a href="https://www.mollie.com/dashboard/settings/organization" target="_blank">Bedrijf</a>\' aan te passen naar iemand die wel reeds vermeld staat in het KBO. (Check de link naast het BTW-nummer op de \'<a href="admin.php?page=oxfam-options">Winkelgegevens</a>\'-pagina.) <a href="mailto:e-commerce@oft.be" target="_blank">Contacteer ons</a> indien je hierbij assistentie nodig hebt.</p>';
				echo '</div>';
			}
			if ( does_home_delivery() ) {
				echo '<div class="notice notice-info">';
				echo '<p>In de ShopPlus-update van juni zijn twee webleveringscodes aangemaakt waarmee je de thuislevering boekhoudkundig kunt verwerken. Op <a href="http://apps.oxfamwereldwinkels.be/shopplus/Nuttige-Barcodes-2017.pdf" target="_blank">het blad met nuttige barcodes</a> kun je doorgaans de bovenste code scannen (6% BTW). Indien je verplicht bent om 21% BTW toe te passen (omdat de bestellingen enkel producten aan 21% BTW bevat) verschijnt er een grote rode boodschap bovenaan de bevestigingsmail in de webshopmailbox.</p>';
				echo '</div>';
			}
			echo '<div class="notice notice-success">';
			if ( get_option( 'mollie-payments-for-woocommerce_test_mode_enabled' ) === 'yes' ) {
				echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust bestellingen te plaatsen en te beheren.</p>';
			} else {
				echo '<p>Opgelet: de betalingen op deze site zijn momenteel live! Tip: betaal je bestelling achteraf volledig terug door een refund uit te voeren via het platform.</p>';
			}
			echo '</div>';
		}
		if ( $pagenow === 'edit.php' and $post_type === 'product' and current_user_can( 'edit_products' ) ) {
			// echo '<div class="notice notice-warning">';
			// echo '<p>Hou er rekening mee dat alle volumes in g / ml ingegeven worden, zonder eenheid!</p>';
			// echo '</div>';
		}
		if ( $pagenow === 'admin.php' and stristr( $screen->base, 'oxfam-products-photos' ) ) {
			echo '<div class="notice notice-success">';
			echo '<p>Bovenaan de compacte lijstweergave vind je vanaf nu een knop om alle producten in of uit voorraad te zetten.</p>';
			echo '</div>';
		}
	}

	// Schakel onnuttige widgets uit voor iedereen
	add_action( 'admin_init', 'remove_dashboard_meta' );

	function remove_dashboard_meta() {
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item-network', 'side' );
		
		if ( ! current_user_can('create_sites') ) {
			remove_meta_box( 'dashboard_primary', 'dashboard-network', 'normal' );
			remove_meta_box( 'network_dashboard_right_now', 'dashboard-network', 'normal' );
			// Want lukt niet via URE Pro
			remove_meta_box( 'postcustom', 'shop_order', 'normal' );
		}

		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	// Beheerd via WooCommerce Order Status Manager of is dit voor het dashboard?
	// add_filter( 'woocommerce_reports_get_order_report_data_args', 'wc_reports_get_order_custom_report_data_args', 100, 1 );

	function wc_reports_get_order_custom_report_data_args( $args ) {
		$args['order_status'] = array( 'on-hold', 'processing', 'claimed', 'completed' );
		return $args;
	};

	function get_latest_newsletters() {
		$server = substr( MAILCHIMP_APIKEY, strpos( MAILCHIMP_APIKEY, '-' ) + 1 );
		$list_id = '5cce3040aa';
		$folder_id = 'bbc1d65c43';

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode( 'user:'.MAILCHIMP_APIKEY ),
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
		$server = substr( MAILCHIMP_APIKEY, strpos( MAILCHIMP_APIKEY, '-' ) + 1 );
		$list_id = '5cce3040aa';
		$email = $cur_user->user_email;
		$member = md5( mb_strtolower($email) );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode( 'user:'.MAILCHIMP_APIKEY ),
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
	add_shortcode( 'topbar', 'print_greeting' );
	add_shortcode( 'copyright', 'print_copyright' );
	add_shortcode( 'straat', 'print_place' );
	add_shortcode( 'postcode', 'print_zipcode' );
	add_shortcode( 'gemeente', 'print_city' );
	add_shortcode( 'telefoon', 'print_telephone' );
	add_shortcode( 'e-mail', 'print_mail' );
	add_shortcode( 'openingsuren', 'print_office_hours' );
	add_shortcode( 'toon_inleiding', 'print_welcome' );
	add_shortcode( 'toon_titel', 'print_portal_title' );
	add_shortcode( 'toon_shops', 'print_store_selector' );
	add_shortcode( 'toon_kaart', 'print_store_locator_map' );
	add_shortcode( 'toon_thuislevering', 'print_delivery_snippet' );
	add_shortcode( 'toon_winkel_kaart', 'print_store_map' );
	add_shortcode( 'scrolltext', 'print_scroll_text' );
	add_shortcode( 'widget_usp', 'print_widget_usp' );
	add_shortcode( 'widget_delivery', 'print_widget_delivery' );
	add_shortcode( 'widget_contact', 'print_widget_contact' );
	add_shortcode( 'company_name', 'get_company_name' );
	add_shortcode( 'contact_address', 'get_company_contact' );
	add_shortcode( 'map_address', 'get_company_address' );
	add_shortcode( 'email_footer', 'get_company_and_year' );
	add_shortcode( 'email_header', 'get_local_logo_url' );

	function print_widget_usp() {
		return do_shortcode('[nm_feature icon="pe-7s-timer" layout="centered" title="'.__( 'Titel van unique selling point in footer', 'oxfam-webshop' ).'"]'.__( 'Inhoud van unique selling point in footer.', 'oxfam-webshop' ).'[/nm_feature]');
	}

	function print_widget_delivery() {
		if ( does_home_delivery() ) {
			$text = __( 'Inhoud van praktisch blokje in footer (indien ook thuislevering).', 'oxfam-webshop' );
		} else {
			$text = __( 'Inhoud van praktisch blokje in footer (inden enkel afhaling).', 'oxfam-webshop' );
		}
		return do_shortcode('[nm_feature icon="pe-7s-global" layout="centered" title="'.__( 'Titel van praktisch blokje in footer', 'oxfam-webshop' ).'"]'.$text.'[/nm_feature]');
	}

	function print_widget_contact() {
		return do_shortcode('[nm_feature icon="pe-7s-comment" layout="centered" title="'.__( 'Titel van contactblokje in footer', 'oxfam-webshop' ).'"]'.sprintf( __( 'Inhoud van het contactblokje in de footer. Bevat <a href="mailto:%1$s">een e-mailadres</a> en een telefoonnummer (%2$s).', 'oxfam-webshop' ), get_company_email(), get_oxfam_shop_data('telephone') ).'[/nm_feature]');
	}

	function print_greeting() {
		if ( date_i18n('G') < 6 ) {
			$greeting = "Goeienacht";
		} elseif ( date_i18n('G') < 12 ) {
			$greeting = "Goeiemorgen";
		} elseif ( date_i18n('G') < 20 ) {
			$greeting = "Goeiemiddag";
		} else {
			$greeting = "Goeieavond";
		}
		return sprintf( __( 'Verwelkoming (%1$s) van de bezoeker (%2$s) op de webshop (%3$s).', 'oxfam-webshop' ), $greeting, get_customer(), get_company_name() );
	}

	function get_customer() {
		global $current_user;
		return ( is_user_logged_in() and strlen($current_user->user_firstname) > 1 ) ? $current_user->user_firstname : "bezoeker";
	}

	function print_copyright() {
		return "<a href='".get_site_url( get_current_blog_id(), '/contact/' )."'>".get_company_name()." &copy; ".date_i18n('Y')."</a>";
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

	function print_welcome() {
		$sites = get_sites( array( 'site__not_in' => array(1), 'archived' => 0, 'count' => true ) );
		// Hoofdblog (en templates) ervan aftrekken
		return '<img src="'.get_stylesheet_directory_uri().'/pointer-afhaling.png"><h3 class="afhaling">'.sprintf( __( 'Begroetingstekst met het aantal webshops (%d) en promotie voor de afhaalkaart.', 'oxfam-webshop' ), $sites ).'</h3>';
	}

	function print_portal_title() {
		return __( 'Titel in de header van de portaalpagina', 'oxfam-webshop' );
	}

	function print_store_selector() {
		$global_zips = get_shops();
 		$all_zips = get_site_option( 'oxfam_flemish_zip_codes' );
 		$msg = '<img src="'.get_stylesheet_directory_uri().'/pointer-levering.png">';
 		$msg .= '<h3 class="thuislevering">'.__( 'Blokje uitleg bij store selector op basis van postcode.', 'oxfam-webshop' ).'</h3><br>';
		$msg .= '<p style="text-align: center;">';
		// $msg .= '<form id="referrer" action="javascript:void(0);">';
		$msg .= '<input type="text" class="" id="oxfam-zip-user" style="width: 160px; height: 40px; text-align: center;" autocomplete="on"> ';
		$msg .= '<input type="submit" class="button" id="do_oxfam_redirect" value="Stuur mij door" style="width: 160px; height: 40px; position: relative; top: -1px;" disabled>';
		// $msg .= '</form>';
		foreach ( $all_zips as $zip => $city ) {
			if ( isset( $global_zips[$zip] ) ) {
				$url = $global_zips[$zip];
			} else {
				$url = '';
			}
			$msg .= '<input type="hidden" class="oxfam-zip-value" id="'.$zip.'" value="'.$url.'">';
		}
		$msg .= '</p>';
		return $msg;
	}

	function print_store_locator_map() {
		?>
			<script>
				// getLocation();
				function getLocation() {
					if ( navigator.geolocation ) {
						navigator.geolocation.getCurrentPosition(showPosition);
					} else {
						alert("Geolocatie wordt niet ondersteund door deze browser.");
					}
				}

				function showPosition(position) {
					alert("Lengtegraad: " + position.coords.latitude + " -- Breedtegraad: " + position.coords.longitude);
				}
			</script>
		<?php
		// Verhinderen cachen van KML-bestand
		// Eventuele styling: maptype='light_monochrome'
		return do_shortcode("[flexiblemap src='".content_url('/maps/global.kml')."' width='100%' height='600px' zoom='9' hidemaptype='true' hidescale='false' kmlcache='1 hours' locale='nl-BE' id='map-oxfam']");
	}

	function print_delivery_snippet() {
		$msg = "";
		if ( does_home_delivery() ) {
			$cities = get_site_option( 'oxfam_flemish_zip_codes' );
			$zips = get_oxfam_covered_zips();
			// Knip de '9999' die altijd aanwezig is (en achteraan staat) eraf
			unset($zips[count($zips)-1]);
			$i = 1;
			$list = "";
			foreach ( $zips as $zip ) {
				if ( $i < count($zips) ) {
					if ( $i === count($zips) - 1 ) {
						$list .= $zip." ".$cities[$zip]." en ";
					} else {
						$list .= $zip." ".$cities[$zip].", ";
					}
				} else {
					$list .= $zip." ".$cities[$zip];
				}
				$i++;
			}
			$msg = "Heb je gekozen voor levering? Dan staan we maximaal 3 werkdagen later met je pakje op je stoep. Wij leveren in ".$list.".";
		}
		return $msg;
	}

	function print_store_map() {
		// Zoom kaart wat minder ver in indien regiowebshop (of beter nog: naar gelang het aantal afhaalpunten?)
		if ( is_regional_webshop() ) {
			$zoom = 13;
		} else {
			$zoom = 15;
		}
		return do_shortcode("[flexiblemap src='".content_url( '/maps/site-'.get_current_blog_id().'.kml?v='.rand() )."' width='100%' height='600px' zoom='".$zoom."' hidemaptype='true' hidescale='false' kmlcache='8 hours' locale='nl-BE' id='map-oxfam']");
	}

	function print_scroll_text() {
		return __( 'Tekst die verschijnt bovenaan de hoofdpagina met producten.', 'oxfam-webshop' );
	}


	###########
	# HELPERS #
	###########

	function set_flemish_zip_codes() {
		$zips = array( 1000 => "Brussel", 1020 => "Laken", 1030 => "Schaarbeek", 1040 => "Etterbeek", 1050 => "Elsene", 1060 => "Sint-Gillis", 1070 => "Anderlecht", 1080 => "Sint-Jans-Molenbeek", 1081 => "Koekelberg", 1082 => "Sint-Agatha-Berchem", 1083 => "Ganshoren", 1090 => "Jette", 1120 => "Neder-over-Heembeek", 1130 => "Haren", 1140 => "Evere", 1150 => "Sint-Pieters-Woluwe", 1160 => "Oudergem", 1170 => "Watermaal-Bosvoorde", 1180 => "Ukkel", 1190 => "Vorst", 1200 => "Sint-Lambrechts-Woluwe", 1210 => "Sint-Joost-ten-Node", 1500 => "Halle", 1501 => "Buizingen", 1502 => "Lembeek", 1540 => "Herne", 1541 => "Sint-Pieters-Kapelle", 1547 => "Bever", 1560 => "Hoeilaart", 1570 => "Galmaarden", 1600 => "Sint-Pieters-Leeuw", 1601 => "Ruisbroek", 1602 => "Vlezenbeek", 1620 => "Drogenbos", 1630 => "Linkebeek", 1640 => "Sint-Genesius-Rode", 1650 => "Beersel", 1651 => "Lot", 1652 => "Alsemberg", 1653 => "Dworp", 1654 => "Huizingen", 1670 => "Pepingen", 1671 => "Elingen", 1673 => "Beert", 1674 => "Bellingen", 1700 => "Dilbeek", 1701 => "Itterbeek", 1702 => "Groot-Bijgaarden", 1703 => "Schepdaal", 1730 => "Asse", 1731 => "Relegem", 1740 => "Ternat", 1741 => "Wambeek", 1742 => "Sint-Katherina-Lombeek", 1745 => "Opwijk", 1750 => "Lennik", 1755 => "Gooik", 1760 => "Roosdaal", 1761 => "Borchtlombeek", 1770 => "Liedekerke", 1780 => "Wemmel", 1785 => "Merchtem", 1790 => "Affligem", 1800 => "Vilvoorde", 1820 => "Steenokkerzeel", 1830 => "Machelen", 1831 => "Diegem", 1840 => "Londerzeel", 1850 => "Grimbergen", 1851 => "Humbeek", 1852 => "Beigem", 1853 => "Strombeek-Bever", 1860 => "Meise", 1861 => "Wolvertem", 1880 => "Kapelle-op-den-Bos", 1910 => "Kampenhout", 1930 => "Zaventem", 1932 => "Sint-Stevens-Woluwe", 1933 => "Sterrebeek", 1950 => "Kraainem", 1970 => "Wezembeek-Oppem", 1980 => "Zemst", 1981 => "Hofstade", 1982 => "Elewijt", 2000 => "Antwerpen", 2018 => "Antwerpen", 2020 => "Antwerpen", 2030 => "Antwerpen", 2040 => "Antwerpen", 2050 => "Antwerpen", 2060 => "Antwerpen", 2070 => "Zwijndrecht", 2100 => "Deurne", 2110 => "Wijnegem", 2140 => "Borgerhout", 2150 => "Borsbeek", 2160 => "Wommelgem", 2170 => "Merksem", 2180 => "Ekeren", 2200 => "Herentals", 2220 => "Heist-op-den-Berg", 2221 => "Booischot", 2222 => "Itegem", 2223 => "Schriek", 2230 => "Herselt", 2235 => "Hulshout", 2240 => "Zandhoven", 2242 => "Pulderbos", 2243 => "Pulle", 2250 => "Olen", 2260 => "Westerlo", 2270 => "Herenthout", 2275 => "Lille", 2280 => "Grobbendonk", 2288 => "Bouwel", 2290 => "Vorselaar", 2300 => "Turnhout", 2310 => "Rijkevorsel", 2320 => "Hoogstraten", 2321 => "Meer", 2322 => "Minderhout", 2323 => "Wortel", 2328 => "Meerle", 2330 => "Merksplas", 2340 => "Beerse", 2350 => "Vosselaar", 2360 => "Oud-Turnhout", 2370 => "Arendonk", 2380 => "Ravels", 2381 => "Weelde", 2382 => "Poppel", 2387 => "Baarle-Hertog", 2390 => "Malle", 2400 => "Mol", 2430 => "Laakdal", 2431 => "Varendonk", 2440 => "Geel", 2450 => "Meerhout", 2460 => "Kasterlee", 2470 => "Retie", 2480 => "Dessel", 2490 => "Balen", 2491 => "Olmen", 2500 => "Lier", 2520 => "Ranst", 2530 => "Boechout", 2531 => "Vremde", 2540 => "Hove", 2547 => "Lint", 2550 => "Kontich", 2560 => "Nijlen", 2570 => "Duffel", 2580 => "Putte", 2590 => "Berlaar", 2600 => "Berchem", 2610 => "Wilrijk", 2620 => "Hemiksem", 2627 => "Schelle", 2630 => "Aartselaar", 2640 => "Mortsel", 2650 => "Edegem", 2660 => "Hoboken", 2800 => "Mechelen", 2801 => "Heffen", 2811 => "Hombeek", 2812 => "Muizen", 2820 => "Bonheiden", 2830 => "Willebroek", 2840 => "Rumst", 2845 => "Niel", 2850 => "Boom", 2860 => "Sint-Katelijne-Waver", 2861 => "Onze-Lieve-Vrouw-Waver", 2870 => "Puurs", 2880 => "Bornem", 2890 => "Sint-Amands", 2900 => "Schoten", 2910 => "Essen", 2920 => "Kalmthout", 2930 => "Brasschaat", 2940 => "Stabroek", 2950 => "Kapellen", 2960 => "Brecht", 2970 => "Schilde", 2980 => "Zoersel", 2990 => "Wuustwezel", 3000 => "Leuven", 3001 => "Heverlee", 3010 => "Kessel-Lo", 3012 => "Wilsele", 3018 => "Wijgmaal", 3020 => "Herent", 3040 => "Huldenberg", 3050 => "Oud-Heverlee", 3051 => "Sint-Joris-Weert", 3052 => "Blanden", 3053 => "Haasrode", 3054 => "Vaalbeek", 3060 => "Bertem", 3061 => "Leefdaal", 3070 => "Kortenberg", 3071 => "Erps-Kwerps", 3078 => "Everberg", 3080 => "Tervuren", 3090 => "Overijse", 3110 => "Rotselaar", 3111 => "Wezemaal", 3118 => "Werchter", 3120 => "Tremelo", 3128 => "Baal", 3130 => "Begijnendijk", 3140 => "Keerbergen", 3150 => "Haacht", 3190 => "Boortmeerbeek", 3191 => "Hever", 3200 => "Aarschot", 3201 => "Langdorp", 3202 => "Rillaar", 3210 => "Lubbeek", 3211 => "Binkom", 3212 => "Pellenberg", 3220 => "Holsbeek", 3221 => "Nieuwrode", 3270 => "Scherpenheuvel-Zichem", 3271 => "Averbode", 3272 => "Messelbroek", 3290 => "Diest", 3293 => "Kaggevinne", 3294 => "Molenstede", 3300 => "Tienen", 3320 => "Hoegaarden", 3321 => "Outgaarden", 3350 => "Linter", 3360 => "Bierbeek", 3370 => "Boutersem", 3380 => "Glabbeek-Zuurbemde", 3381 => "Kapellen", 3384 => "Attenrode", 3390 => "Tielt-Winge", 3391 => "Meensel-Kiezegem", 3400 => "Landen", 3401 => "Waasmont", 3404 => "Attenhoven", 3440 => "Zoutleeuw", 3450 => "Geetbets", 3454 => "Rummen", 3460 => "Bekkevoort", 3461 => "Molenbeek-Wersbeek", 3470 => "Kortenaken", 3471 => "Hoeleden", 3472 => "Kersbeek-Miskom", 3473 => "Waanrode", 3500 => "Hasselt", 3501 => "Wimmertingen", 3510 => "Kermt", 3511 => "Kuringen", 3512 => "Stevoort", 3520 => "Zonhoven", 3530 => "Houthalen-Helchteren", 3540 => "Herk-de-Stad", 3545 => "Halen", 3550 => "Heusden-Zolder", 3560 => "Lummen", 3570 => "Alken", 3580 => "Beringen", 3581 => "Beverlo", 3582 => "Koersel", 3583 => "Paal", 3590 => "Diepenbeek", 3600 => "Genk", 3620 => "Lanaken", 3621 => "Rekem", 3630 => "Maasmechelen", 3631 => "Boorsem", 3640 => "Kinrooi", 3650 => "Dilsen-Stokkem", 3660 => "Opglabbeek", 3665 => "As", 3668 => "Niel-bij-As", 3670 => "Meeuwen-Gruitrode", 3680 => "Maaseik", 3690 => "Zutendaal", 3700 => "Tongeren", 3717 => "Herstappe", 3720 => "Kortessem", 3721 => "Vliermaalroot", 3722 => "Wintershoven", 3723 => "Guigoven", 3724 => "Vliermaal", 3730 => "Hoeselt", 3732 => "Schalkhoven", 3740 => "Bilzen", 3742 => "Martenslinde", 3746 => "Hoelbeek", 3770 => "Riemst", 3790 => "Voeren", 3791 => "Remersdaal", 3792 => "Sint-Pieters-Voeren", 3793 => "Teuven", 3798 => "'s Gravenvoeren", 3800 => "Sint-Truiden", 3803 => "Duras", 3806 => "Velm", 3830 => "Wellen", 3831 => "Herten", 3832 => "Ulbeek", 3840 => "Borgloon", 3850 => "Nieuwerkerken", 3870 => "Heers", 3890 => "Gingelom", 3891 => "Borlo", 3900 => "Overpelt", 3910 => "Neerpelt", 3920 => "Lommel", 3930 => "Hamont-Achel", 3940 => "Hechtel-Eksel", 3941 => "Eksel", 3945 => "Ham", 3950 => "Bocholt", 3960 => "Bree", 3970 => "Leopoldsburg", 3971 => "Heppen", 3980 => "Tessenderlo", 3990 => "Peer", 8000 => "Brugge", 8020 => "Oostkamp", 8200 => "Sint-Andries", 8210 => "Zedelgem", 8211 => "Aartrijke", 8300 => "Knokke-Heist", 8301 => "Heist-aan-Zee", 8310 => "Assebroek", 8340 => "Damme", 8370 => "Blankenberge", 8377 => "Zuienkerke", 8380 => "Dudzele", 8400 => "Oostende", 8420 => "De Haan", 8421 => "Vlissegem", 8430 => "Middelkerke", 8431 => "Wilskerke", 8432 => "Leffinge", 8433 => "Mannekensvere", 8434 => "Lombardsijde", 8450 => "Bredene", 8460 => "Oudenburg", 8470 => "Gistel", 8480 => "Ichtegem", 8490 => "Jabbeke", 8500 => "Kortrijk", 8501 => "Bissegem", 8510 => "Bellegem", 8511 => "Aalbeke", 8520 => "Kuurne", 8530 => "Harelbeke", 8531 => "Bavikhove", 8540 => "Deerlijk", 8550 => "Zwevegem", 8551 => "Heestert", 8552 => "Moen", 8553 => "Otegem", 8554 => "Sint-Denijs", 8560 => "Wevelgem", 8570 => "Anzegem", 8572 => "Kaster", 8573 => "Tiegem", 8580 => "Avelgem", 8581 => "Kerkhove", 8582 => "Outrijve", 8583 => "Bossuit", 8587 => "Spiere-Helkijn", 8600 => "Diksmuide", 8610 => "Kortemark", 8620 => "Nieuwpoort", 8630 => "Veurne", 8640 => "Vleteren", 8647 => "Lo-Reninge", 8650 => "Houthulst", 8660 => "De Panne", 8670 => "Koksijde", 8680 => "Koekelare", 8690 => "Alveringem", 8691 => "Beveren-aan-den-IJzer", 8700 => "Tielt", 8710 => "Wielsbeke", 8720 => "Dentergem", 8730 => "Beernem", 8740 => "Pittem", 8750 => "Wingene", 8755 => "Ruiselede", 8760 => "Meulebeke", 8770 => "Ingelmunster", 8780 => "Oostrozebeke", 8790 => "Waregem", 8791 => "Beveren-Leie", 8792 => "Desselgem", 8793 => "Sint-Eloois-Vijve", 8800 => "Roeselare", 8810 => "Lichtervelde", 8820 => "Torhout", 8830 => "Hooglede", 8840 => "Staden", 8850 => "Ardooie", 8851 => "Koolskamp", 8860 => "Lendelede", 8870 => "Izegem", 8880 => "Ledegem", 8890 => "Moorslede", 8900 => "Ieper", 8902 => "Hollebeke", 8904 => "Boezinge", 8906 => "Elverdinge", 8908 => "Vlamertinge", 8920 => "Langemark-Poelkapelle", 8930 => "Menen", 8940 => "Wervik", 8950 => "Heuvelland", 8951 => "Dranouter", 8952 => "Wulvergem", 8953 => "Wijtschate", 8954 => "Westouter", 8956 => "Kemmel", 8957 => "Mesen", 8958 => "Loker", 8970 => "Poperinge", 8972 => "Krombeke", 8978 => "Watou", 8980 => "Zonnebeke", 9000 => "Gent", 9030 => "Mariakerke", 9031 => "Drongen", 9032 => "Wondelgem", 9040 => "Sint-Amandsberg", 9041 => "Oostakker", 9042 => "Desteldonk", 9050 => "Gentbrugge", 9051 => "Afsnee", 9052 => "Zwijnaarde", 9060 => "Zelzate", 9070 => "Destelbergen", 9080 => "Lochristi", 9090 => "Melle", 9100 => "Sint-Niklaas", 9111 => "Belsele", 9112 => "Sinaai-Waas", 9120 => "Beveren-Waas", 9130 => "Doel", 9140 => "Temse", 9150 => "Kruibeke", 9160 => "Lokeren", 9170 => "Sint-Gillis-Waas", 9180 => "Moerbeke-Waas", 9185 => "Wachtebeke", 9190 => "Stekene", 9200 => "Dendermonde", 9220 => "Hamme", 9230 => "Wetteren", 9240 => "Zele", 9250 => "Waasmunster", 9255 => "Buggenhout", 9260 => "Wichelen", 9270 => "Laarne", 9280 => "Lebbeke", 9290 => "Berlare", 9300 => "Aalst", 9308 => "Gijzegem", 9310 => "Baardegem", 9320 => "Erembodegem", 9340 => "Lede", 9400 => "Ninove", 9401 => "Pollare", 9402 => "Meerbeke", 9403 => "Neigem", 9404 => "Aspelare", 9406 => "Outer", 9420 => "Erpe-Mere", 9450 => "Haaltert", 9451 => "Kerksken", 9470 => "Denderleeuw", 9472 => "Iddergem", 9473 => "Welle", 9500 => "Geraardsbergen", 9506 => "Grimminge", 9520 => "Sint-Lievens-Houtem", 9521 => "Letterhoutem", 9550 => "Herzele", 9551 => "Ressegem", 9552 => "Borsbeke", 9570 => "Lierde", 9571 => "Hemelveerdegem", 9572 => "Sint-Martens-Lierde", 9600 => "Ronse", 9620 => "Zottegem", 9630 => "Zwalm", 9636 => "Nederzwalm-Hermelgem", 9660 => "Brakel", 9661 => "Parike", 9667 => "Horebeke", 9680 => "Maarkedal", 9681 => "Nukerke", 9688 => "Schorisse", 9690 => "Kluisbergen", 9700 => "Oudenaarde", 9750 => "Zingem", 9770 => "Kruishoutem", 9771 => "Nokere", 9772 => "Wannegem-Lede", 9790 => "Wortegem-Petegem", 9800 => "Deinze", 9810 => "Nazareth", 9820 => "Merelbeke", 9830 => "Sint-Martens-Latem", 9831 => "Deurle", 9840 => "De Pinte", 9850 => "Nevele", 9860 => "Oosterzele", 9870 => "Zulte", 9880 => "Aalter", 9881 => "Bellem", 9890 => "Gavere", 9900 => "Eeklo", 9910 => "Knesselare", 9920 => "Lovendegem", 9921 => "Vinderhoute", 9930 => "Zomergem", 9931 => "Oostwinkel", 9932 => "Ronsele", 9940 => "Evergem", 9950 => "Waarschoot", 9960 => "Assenede", 9961 => "Boekhoute", 9968 => "Bassevelde", 9970 => "Kaprijke", 9971 => "Lembeke", 9980 => "Sint-Laureins", 9981 => "Sint-Margriete", 9982 => "Sint-Jan-in-Eremo", 9988 => "Waterland-Oudeman", 9990 => "Maldegem", 9991 => "Adegem", 9992 => "Middelburg" );
		update_site_option( 'oxfam_flemish_zip_codes', $zips );
	}

	function get_flemish_zips_and_cities() {
		$zips = get_site_option( 'oxfam_flemish_zip_codes' );
		foreach ( $zips as $zip => $city ) {
				$content[] = array( 'label' => $zip.' '.$city, 'value' => $zip );
		}
		return $content;
	}

	function does_home_delivery() {
		return get_option( 'oxfam_zip_codes' );
	}

	function does_sendcloud_delivery() {
		if ( does_home_delivery() ) {
			$sendcloud_zone_id = 3;
			$zone = WC_Shipping_Zones::get_zone_by( 'zone_id', $sendcloud_zone_id );
			if ( $zone ) {
				// Enkel actieve methodes meetellen
				$methods = $zone->get_shipping_methods( true );
				if ( count( $methods ) > 0 ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function is_regional_webshop() {
		// Antwerpen en Leuven
		$regions = array( 24, 28 );
		return in_array( get_current_blog_id(), $regions );
	}

	function get_oxfam_shop_data( $key, $node = 0 ) {
		global $wpdb;
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		if ( ! is_main_site() ) {
			if ( $key === 'tax' or $key === 'account' or $key === 'headquarter' ) {
				if ( $node === '857' ) {
					// Uitzonderingen voor Regio Leuven vzw
					switch ($key) {
						case 'tax':
							return call_user_func( 'format_'.$key, 'BE 0479.961.641' );
						case 'account':
							return call_user_func( 'format_'.$key, 'BE86 0014 0233 4050' );
						case 'headquarter':
							return call_user_func( 'format_'.$key, 'Parijsstraat 56, 3000 Leuven' );
					};
				} elseif ( $node === '795' and $key === 'account' ) {
					// Uitzondering voor Regio Antwerpen vzw
					return call_user_func( 'format_'.$key, 'BE56 0018 1366 6388' );
				} else {
					$row = $wpdb->get_row( 'SELECT * FROM field_data_field_shop_'.$key.' WHERE entity_id = '.get_oxfam_shop_data( 'shop', $node ) );
					if ( $row ) {
						return call_user_func( 'format_'.$key, $row->{'field_shop_'.$key.'_value'} );
					} else {
						return "UNKNOWN";
					}
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
							if ( $node === '857' ) {
								return call_user_func( 'format_telephone', '0486762195', '.' );
							} else {	
								// Geef alternatieve delimiter mee
								return call_user_func( 'format_telephone', $row->field_sellpoint_telephone_value, '.' );
							}
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
		return get_company_address()."<br><a href='mailto:".get_company_email()."'>".get_company_email()."</a><br>".get_oxfam_shop_data( 'telephone' )."<br>".get_oxfam_shop_data( 'tax' );
	}

	function get_company_address( $node = 0 ) {
		if ( $node === 0 ) $node = get_option( 'oxfam_shop_node' );
		return get_oxfam_shop_data( 'place', $node )."<br>".get_oxfam_shop_data( 'zipcode', $node )." ".get_oxfam_shop_data( 'city', $node );
	}

	function get_full_company() {
		return get_company_name()."<br>".get_company_address()."<br>".get_company_contact();
	}

	function get_shops() {
		$global_zips = array();
		// Negeer main site én gearchiveerde sites
		$sites = get_sites( array( 'site__not_in' => array( 1, 11, 25 ), 'archived' => 0, ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			$local_zips = get_option( 'oxfam_zip_codes' );
			if ( $local_zips !== false ) {
				foreach ( $local_zips as $zip ) {
					if ( isset($global_zips[$zip]) ) {
						write_log("CONSISTENTIEFOUT: Postcode ".$zip." is reeds gelinkt aan ".$global_zips[$zip].'!');
					}
					$global_zips[$zip] = 'https://' . $site->domain . $site->path;
				}
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

	function get_local_logo_url() {
		return get_stylesheet_directory_uri() . '/logo/' . get_option( 'oxfam_shop_node' ) . '.png';
	}

	function get_oxfam_covered_zips() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_shipping_zone_locations WHERE location_type = 'postcode'" );
		$zips = false;
		if ( count($rows) > 0 ) {
			foreach ( $rows as $row ) {
				$zips[] = $row->location_code;
			}
			$zips = array_unique( $zips );
			sort($zips, SORT_NUMERIC);
		}
		return $zips;
	}

	
	##########
	# SEARCH #
	##########

	// Verander capability van 'manage_options' naar 'create_sites' zodat enkel superbeheerders de instellingen kunnen wijzigen
	add_filter( 'relevanssi_options_capability', function( $capability ) { return 'create_sites'; } );
	
	// Verander capability van 'edit_pages' naar 'manage_woocommerce' zodat ook lokale beheerders de logs kunnen bekijken
	add_filter( 'relevanssi_user_searches_capability', function( $capability ) { return 'manage_woocommerce'; } );
		
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

	// Toon de bestsellers op zoekpagina's zonder resultaten MOET MEER NAAR BOVEN + VERSCHIJNT OOK ALS ER WEL RESULTATEN ZIJN
	// add_action( 'woocommerce_after_main_content', 'add_bestsellers' );

	function add_bestsellers() {
		global $wp_query;
		if ( is_search() and $wp_query->found_posts == 0 ) {
			echo do_shortcode('[vc_row css=".vc_custom_1487859300634{padding-top: 25px !important;padding-bottom: 25px !important;}"][vc_column][vc_text_separator title="<h2>Werp een blik op onze bestsellers ...</h2>" css=".vc_custom_1487854440279{padding-bottom: 25px !important;}"][best_selling_products per_page="10" columns="5" orderby="rand"][/vc_column][/vc_row]');
		}
	}

	// Zorg ervoor dat verborgen producten niet geïndexeerd worden (en dus niet opduiken in de zoekresultaten, ook als we de filter 'post_type=product' weglaten)
	add_filter( 'relevanssi_do_not_index', 'exclude_hidden_products', 10, 2 );
	
	function exclude_hidden_products( $block, $post_id ) {
		if ( has_term( 'exclude-from-search', 'product_visibility', $post_id ) ) $block = true;
		return $block;
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
	add_filter( 'relevanssi_30days', function() { return 90; } );


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
		$newArray = array();
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