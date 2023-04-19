<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	################
	# COMMUNICATIE #
	################
	
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
			// Want lukt niet via User Role Editor Pro
			remove_meta_box( 'postcustom', 'shop_order', 'normal' );
		}
		
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}
	
	// Toon custom Oxfam-berichten met actuele info bovenaan bepaalde adminpagina's
	add_action( 'admin_head', 'show_oxfam_notices', 1000 );
	
	function show_oxfam_notices() {
		// Verberg alle berichten van plugins voor non-admins
		// Gelijkaardige 'Show plugins/themes notices to admin only'-optie van User Role Editor Pro niét inschakelen!
		if ( ! current_user_can('create_sites') ) {
			remove_all_actions('admin_notices');
		} else {
			// Extra info voor beheerders (bv. statistieken van een lopende promotie)
			// add_action( 'network_admin_notices', 'oxfam_network_admin_notices' );
		}
		
		// Toon meldingen bovenaan dashboard (zoals ingesteld via de 'Dashboard'-pagina)
		add_action( 'admin_notices', 'oxfam_admin_notices_dashboard' );
		// Toon meldingen bovenaan rapporten (bv. aantal digicheques die gecrediteerd zullen worden)
		add_action( 'admin_notices', 'oxfam_admin_notices_reports' );
		// Toon meldingen bovenaan bestellingen (indien bulkwijziging op geblokkeerd werd)
		add_action( 'admin_notices', 'oxfam_admin_notices_orders' );
	}
	
	function oxfam_admin_notices_dashboard() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		
		if ( 'index.php' === $pagenow and 'dashboard' === $screen->base ) {
			if ( in_array( get_current_blog_id(), get_site_option('oxfam_blocked_sites') ) ) {
				echo '<div class="notice notice-error">';
					echo '<p>Deze webshop is momenteel reeds bereikbaar (voor o.a. Mollie-controle) maar verschijnt nog niet in de winkelzoeker!</p>';
				echo '</div>';
			}
			if ( get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
				echo '<div class="notice notice-success">';
					echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust te experimenteren met bestellingen.</p>';
				echo '</div>';
			}
			
			if ( '' !== ( $success = get_site_option( 'oxfam_shop_dashboard_notice_success', '' ) ) ) {
				echo '<div class="notice notice-success">';
					echo '<p>' . stripslashes( $success ) . '</p>';
				echo '</div>';
			}
			
			// Er werden twee geschenkverpakkingen toegevoegd: een geschenkmand (servicekost: 3,95 euro, enkel afhaling) en een geschenkdoos (servicekost: 2,50 euro, ook thuislevering). Door minstens één product op voorraad te zetten activeer je de module. Onder het winkelmandje verschijnt dan een opvallende knop om een geschenkverpakking toe te voegen. <a href="https://github.com/OxfamFairTrade/ob2c/wiki/9.-Lokaal-assortiment#geschenkverpakkingen" target="_blank">Raadpleeg de handleiding voor info over de werking en hoe je zelf geschenkverpakkingen kunt aanmaken met andere prijzen/voorwaarden.</a> Opmerking: indien je thuislevering van breekbare goederen inschakelde onder <a href="admin.php?page=oxfam-options">Winkelgegevens</a> kan de geschenkmand ook thuisgeleverd worden.
			if ( '' !== ( $info = get_site_option( 'oxfam_shop_dashboard_notice_info', '' ) ) ) {
				echo '<div class="notice notice-info">';
					echo '<p>' . stripslashes( $info ) . '</p>';
				echo '</div>';
			}
			
			if ( '' !== ( $warning = get_site_option( 'oxfam_shop_dashboard_notice_warning', '' ) ) ) {
				echo '<div class="notice notice-warning">';
					echo '<p>' . stripslashes( $warning ) . '</p>';
				echo '</div>';
			}
			
			$new_skus = get_site_option( 'oxfam_shop_dashboard_notice_new_products', array() );
			if ( count( $new_skus ) > 0 ) {
				echo '<div class="notice notice-success">';
					echo '<p>Deze nieuwigheden werden onlangs toegevoegd aan de database:</p><ul style="margin-left: 2em; column-count: 2;">';
						foreach ( $new_skus as $sku ) {
							$product_id = wc_get_product_id_by_sku( $sku );
							if ( $product_id ) {
								$product = wc_get_product( $product_id );
								echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
							}
						}
					echo '</ul><p>';
					if ( current_user_can('manage_network_users') ) {
						echo 'Je herkent deze producten aan de blauwe achtergrond onder \'<a href="admin.php?page=oxfam-products-list">Voorraadbeheer</a>\'. ';
					}
					echo 'Pas wanneer een beheerder ze in voorraad plaatst, worden deze producten bestelbaar voor klanten.</p>';
				echo '</div>';
			}
			
			$replaced_skus = get_site_option( 'oxfam_shop_dashboard_notice_replaced_products', array() );
			if ( count( $replaced_skus ) > 0 ) {	
				echo '<div class="notice notice-success">';
					echo '<p>Volgende referenties vervangen een bestaand product (met ongewijzigde verpakking):</p><ul style="margin-left: 2em; column-count: 2;">';
						foreach ( $replaced_skus as $old_new ) {
							$parts = explode( '-', $old_new );
							if ( count( $parts ) !== 2 ) {
								continue;
							}
							$old_sku = $parts[0];
							$new_sku = $parts[1];
							$product_id = wc_get_product_id_by_sku( $new_sku );
							if ( $product_id ) {
								$product = wc_get_product( $product_id );
								echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_sku().' '.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')';
								
								$old_product_id = wc_get_product_id_by_sku( $old_sku );
								$old_product = wc_get_product( $old_product_id );
								if ( $old_product ) {
									echo ', vervangt '.$old_product->get_sku().' '.$old_product->get_title();
								}
								
								echo '</li>';
							}
						}
					echo '</ul><p>De voorraadstatus van het bestaande product werd overgenomen en het oude ompaknummer werd verborgen.</p>';
				echo '</div>';
			}
			
			// Het is momenteel niet werkbaar om de volledige productcatalogus van Magasins du Monde (+/- 2.500 voorradige producten) in het webshopnetwerk te pompen: dit stelt hogere eisen aan de productdata, de zoekfunctie, het voorraadbeheer, onze server, ... Bovendien is het voor de consument weinig zinvol om alle non-food te presenteren in onze nationale catalogus, gezien de beperkte lokale beschikbaarheid van de oudere craftsproducten.
			// echo '<p>Verder werden de prijzen van alle craftsproducten in de nationale database (eindelijk) gelijk getrokken met de adviesprijzen van MDM in ShopPlus (incl. de meest recente wijzigingen van 1 oktober). Daarnaast maakten we een resem extra referenties beschikbaar die de voorbije maanden verschenen:</p>';
			// echo '<ul>';
			// 	echo '<li>Kalenders en agenda\'s voor 2023:<ul style="margin-left: 2em; column-count: 2;">';
			// 		$skus = array( 87570, 87571, 87420, 87421, 87422, 87423, 87424, 87425, 87426, 87427, 87428, 87429, 87430, 87431 );
			// 		foreach ( $skus as $sku ) {
			// 			$product_id = wc_get_product_id_by_sku( $sku );
			// 			if ( $product_id ) {
			// 				$product = wc_get_product( $product_id );
			// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
			// 			}
			// 		}
			// 	echo '</ul></li>';
			// 	
			// 	echo '<li>Nieuwe Dopper-drinkflessen:<ul style="margin-left: 2em; column-count: 2;">';
			// 		$skus = array( 12374, 12375, 12376, 12377, 12378, 12379, 12380, 12381 );
			// 		foreach ( $skus as $sku ) {
			// 			$product_id = wc_get_product_id_by_sku( $sku );
			// 			if ( $product_id ) {
			// 				$product = wc_get_product( $product_id );
			// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
			// 			}
			// 		}
			// 	echo '</ul><p>Omdat sommige van deze producten al langer dan 3 maanden bestelbaar zijn, krijgen ze niet allemaal een blauwe achtergrond in de voorraadlijst!</p></li>';
			// echo '</ul>';
			
			if ( does_home_delivery() ) {
				// Boodschappen voor winkels die thuislevering doen
			}
			
			if ( does_sendcloud_delivery() ) {
				// Boodschappen voor winkels die verzenden met SendCloud
			}
		}
	}
	
	function oxfam_admin_notices_reports() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		
		if ( 'woocommerce_page_wc-reports' === $screen->base and ( empty( $_GET['tab'] ) or $_GET['tab'] === 'orders' ) ) {
			global $wpdb;
			$credit_date_timestamp = strtotime( '+1 weekday', strtotime('last day of this month') );
			$credit_month_timestamp = strtotime( '-1 month', strtotime('first day of this month') );
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE blog_id = ".get_current_blog_id()." AND DATE(credited) = '".date_i18n( 'Y-m-d', $credit_date_timestamp )."';";
			$results = $wpdb->get_results( $query );
			
			$sum = array_reduce( $results, function( $carry, $row ) {
				return $carry + $row->value;
			}, 0 );
			
			if ( $sum > 0 ) {
				echo '<div class="notice notice-success"><p>';
					if ( $credit_date_timestamp >= strtotime('today') ) {
						$tense = 'zal Oxfam Fair Trade '.wc_price( $sum ).' crediteren';
					} else {
						$tense = 'heeft Oxfam Fair Trade '.wc_price( $sum ).' gecrediteerd';
					}
					echo 'Op '.date_i18n( 'd/m/Y', $credit_date_timestamp ).' '.$tense.' voor de digitale cadeaubonnen die in de loop van de maand '.date_i18n( 'F Y', $credit_month_timestamp ).' gebruikt werden als betaalmiddel in jullie webshop.';
					if ( is_regional_webshop() ) {
						echo ' Elke cadeaubon wordt automatisch gecrediteerd aan het klantnummer van de winkel die de bestelling behandelde.';
					}
				echo '</p></div>';
			}
		}
	}
	
	function oxfam_admin_notices_orders() {
		global $pagenow, $post_type;
		
		if ( 'edit.php' === $pagenow and 'shop_order' === $post_type ) {
			if ( isset( $_REQUEST['bulk_action'] ) ) {
				if ( $_REQUEST['bulk_action'] === 'marked_completed' ) {
					$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
					$message = sprintf( _n( '%d bestelstatus proberen te wijzigen.', '%d bestelstatussen proberen te wijzigen.', $number, 'woocommerce' ), number_format_i18n( $number ) );
					echo '<div class="updated"><p>' . esc_html( $message ) . ' Ongeldige wijzigingen kunnen tegengehouden zijn door het systeem! Raadpleeg de logs in de rechterkolom van het orderdetail als je merkt dat de status onveranderd gebleven is.</p></div>';
				}
			}
		}
	}
	
	function oxfam_network_admin_notices() {
		global $pagenow;
		$screen = get_current_screen();
		
		if ( 'admin.php' === $pagenow and 'toplevel_page_woonet-woocommerce-network' === $screen->base ) {
			$orders = array();
			$total = 0;
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1 ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$orders_with_coupon = get_number_of_times_coupon_was_used( 'koffiechoc22', '2022-01-01', '2022-02-28', true );
				
				foreach ( $orders_with_coupon as $wc_order ) {
					// Om dit correct te tellen, zouden we alle gratis repen in het order moeten opsnorren ...
					// $total += count( $orders_with_coupon );
					$output = '<a href="'.$wc_order->get_edit_order_url().'" target="_blank">'.$wc_order->get_order_number().'</a>: '.wc_price( $wc_order->get_total() ).' &mdash; '.$wc_order->get_billing_email();
					$orders[ $wc_order->get_order_number() ] = $output;
				}
				
				restore_current_blog();
			}
			
			ksort( $orders );
			
			echo '<div class="notice notice-success">';
				echo '<p>Tot nu toe werd de kortingsbon KOFFIECHOC22 al een of meerdere keren toegekend in '.count( $orders ).' verschillende bestellingen!</p>';
				echo '<ul style="margin-left: 2em; column-count: 2;">';
				
				foreach( $orders as $string ) {
					echo '<li>'.$string.'</li>';
				}
				
				echo '</ul>';
			echo '</div>';
		}
	}
	
	// Voeg een custom dashboard widget toe met nieuws over het pilootproject
	add_action( 'wp_dashboard_setup', 'add_pilot_widget' );
	
	function add_pilot_widget() {
		global $wp_meta_boxes;
		
		wp_add_dashboard_widget(
			'dashboard_pilot_news_widget',
			'Info voor webshopmedewerkers',
			'dashboard_pilot_news_widget_callback'
		);
	}
	
	function dashboard_pilot_news_widget_callback() {
		echo '<div class="rss-widget">';
		echo '<p>De <a href="https://github.com/OxfamFairTrade/ob2c/wiki" target="_blank">online FAQ voor webshopbeheerders</a> staat online. Hierin verzamelen we alle mogelijke vragen die jullie als lokale webshopbeheerders kunnen hebben en beantwoorden we ze punt per punt met tekst en screenshots. Gebruik eventueel de zoekfunctie bovenaan rechts.</p>';
		echo '<p>Daarnaast kun je de nieuwe slides van de voorbije opleidingssessies raadplegen voor een overzicht van alle afspraken en praktische details: <a href="https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-opleiding-B2C-webshop-concept.pdf" download>Deel 1: Concept</a> (16/05/2020) en <a href="https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-opleiding-B2C-webshop-praktisch.pdf" download>Deel 2: Praktisch</a> (30/05/2020). Op <a href="https://copain.oww.be/webshop" target="_blank">de webshoppagina op Copain</a> vind je een overzicht van de belangrijkste documenten.</p>';
		echo '<p>Stuur een mailtje naar de <a href="mailto:webshop@oft.be">Helpdesk E-Commerce</a> als er toch nog iets onduidelijk is, of als je een suggestie hebt. Tineke, Ive en Sam helpen je zo snel mogelijk verder.</p>';
		echo '</div>';
	}