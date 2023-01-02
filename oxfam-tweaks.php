<?php

	if ( ! defined('ABSPATH') ) exit;

	// Toon custom Oxfam-berichten met actuele info bovenaan adminpagina's
	// Moet een latere prioriteit hebben dan hide_non_oxfam_notices!
	add_action( 'admin_head', 'show_oxfam_notices', 20000 );

	function show_oxfam_notices() {
		add_action( 'admin_notices', 'oxfam_admin_notices_dashboard' );
		add_action( 'admin_notices', 'oxfam_admin_notices_reports' );
		// Uitgeschakeld
		// add_action( 'network_admin_notices', 'oxfam_network_admin_notices' );
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
			
			if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
				// echo '<div class="notice notice-warning">';
				// 	echo '<p>Sinds de migratie van alle @oww.be mailboxen naar de Microsoft-account van Oxfam International op 23 mei lijken dubbel geforwarde mails niet langer goed te arriveren. Laat je de webshopmailbox forwarden naar het winkeladres <i>gemeente@oww.be</i>, dat de mail op zijn beurt doorstuurt naar je eigen Gmail / Hotmail / ... adres? Log dan in op de webshopmailbox en stel bij de instellingen onder \'<a href="https://outlook.office.com/mail/options/mail/forwarding" target="_blank">Doorsturen</a>\' een rechtstreekse forward in naar de uiteindelijke bestemmeling. Of beter nog: <a href="https://github.com/OxfamFairTrade/ob2c/wiki/3.-Verwerking#kan-ik-de-webshopmailbox-aan-mijn-bestaande-mailprogramma-toevoegen" target="_blank">voeg de webshopmailbox toe aan je mailprogramma</a> en verstuur professionele antwoorden vanuit @oxfamwereldwinkels.be.</p>';
				// echo '</div>';
				
				echo '<div class="notice notice-success">';
					echo '<p>De <a href="https://copain.oww.be/nieuwsbericht/2022/12/12/Promos-online--winkel-januari-2023" target="_blank">promoties voor januari</a> werden geactiveerd in alle webshops. Zoals aangekondigd in de webshopnieuwsbrief werden de <a href="https://copain.oww.be/nieuwsbericht/2022/11/21/Belangrijk-prijswijzigingen-en-nieuwe-prijsstructuur-food-vanaf-010123" target="_blank">prijswijzigingen van 01/01/2023</a> pas in de ochtend van 2 januari doorgevoerd in alle webshops. Bedankt voor jullie begrip!</p>';
				echo '</div>';
				
				echo '<div class="notice notice-success">';
					// echo '<p>De kleine repen witte chocolade zijn terug van weg geweest:</p><ul style="margin-left: 2em; column-count: 2;">';
					// 	$skus = array( 24149 );
					// 	foreach ( $skus as $sku ) {
					// 		$product_id = wc_get_product_id_by_sku( $sku );
					// 		if ( $product_id ) {
					// 			$product = wc_get_product( $product_id );
					// 			echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
					// 		}
					// 	}
					// echo '</ul><p>';
					// if ( current_user_can('manage_network_users') ) {
					// 	echo 'Je herkent deze producten aan de blauwe achtergrond onder \'<a href="admin.php?page=oxfam-products-list">Voorraadbeheer</a>\'. ';
					// }
					// echo 'Pas wanneer een beheerder ze in voorraad plaatst, worden deze producten bestelbaar voor klanten.</p>';
					
					echo '<p>Er is opnieuw een nieuwe referentie die een bestaand product vervangt, omwille van een gewijzigde ompakhoeveelheid:</p><ul style="margin-left: 2em; column-count: 2;">';
						$skus = array( 25010 => 25016 );
						foreach ( $skus as $old_sku => $sku ) {
							$product_id = wc_get_product_id_by_sku( $sku );
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
					// 	echo '<li>Nieuwe verzorgingsproducten:<ul style="margin-left: 2em; column-count: 2;">';
					// 		$skus = array( 45247, 45258, 45262, 45267, 45390, 65200, 65202, 65204, 65205, 65207, 65208, 65209, 65215, 65228, 65229, 65269, 65270, 65273, 65274, 87359, 87360, 87361 );
					// 		foreach ( $skus as $sku ) {
					// 			$product_id = wc_get_product_id_by_sku( $sku );
					// 			if ( $product_id ) {
					// 				$product = wc_get_product( $product_id );
					// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
					// 			}
					// 		}
					// 	echo '</ul></li>';
					// 	
					// 	echo '<li>Nieuwe onderhoudsproducten:<ul style="margin-left: 2em; column-count: 2;">';
					// 		$skus = array( 87309, 87312, 87351, 80282, 80283, 80306, 80313, 80314, 80315 );
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
				echo '</div>';
				
				// echo '<div class="notice notice-info">';
				// 	echo '<p>Er werden twee geschenkverpakkingen toegevoegd: een geschenkmand (servicekost: 3,95 euro, enkel afhaling) en een geschenkdoos (servicekost: 2,50 euro, ook thuislevering). Door minstens één product op voorraad te zetten activeer je de module. Onder het winkelmandje verschijnt dan een opvallende knop om een geschenkverpakking toe te voegen. <a href="https://github.com/OxfamFairTrade/ob2c/wiki/9.-Lokaal-assortiment#geschenkverpakkingen" target="_blank">Raadpleeg de handleiding voor info over de werking en hoe je zelf geschenkverpakkingen kunt aanmaken met andere prijzen/voorwaarden.</a> Opmerking: indien je thuislevering van breekbare goederen inschakelde onder \'<a href="admin.php?page=oxfam-options">Winkelgegevens</a>\' kan de geschenkmand ook thuisgeleverd worden.</p>';
				// echo '</div>';
				
				// Sommige FTO-producten worden tegenwoordig rechtstreeks aangekocht door Brugge / Mariakerke / Dilbeek / Roeselare => toch wissen (onmogelijk te beheren)
				// 27205 Noedels witte rijst, 27512 Ananasschijven, 27807 Woksaus zoet-zuur, 28318 BIO Currypoeder, 28319 BIO Kaneel, 28324 Pepermolen citroen/sinaas/knoflook, 28329 BIO Kurkuma
				// echo '<div class="notice notice-warning">';
				// 	echo '<p>Volgende uitgefaseerde producten werden uit de database verwijderd omdat hun uiterste houdbaarheid inmiddels gepasseerd is: 17115 BIO Volle jasmijnrijst 5 kg (THT: 31/07/2022), 20248 Chenin Blanc BOX 3 l, 21000 Sinaasappelsap 1 l (THT: 31/12/2022), 21002 Worldshakesap 1 l (THT: 31/12/2022), 21003 Tropicalsap 1 l (THT: 31/10/2022), 21008 BIO Sinaas-mangosap 1 l (THT: 31/12/2022), 21011 Vers geperst Belgisch appelsap 1 l (in omschakeling naar BIO) (THT: 31/12/2022), 21103 Tropicalsap 20 cl (THT: 31/10/2022), 22013 African Blendkoffie (THT: 29/01/2023), 23403 Groene thee citroengras 1 g x 20 (THT: 21/11/2022), 23501 BIO Losse groene thee (THT: 21/11/2022), 26493 Maya Speculoospasta (THT: 23/11/2022), 27517 BIO Zwarte linzen (THT: 31/07/2022). Ook enkele producten van Fairtrade Original die niet langer door Oxfam Fair Trade verdeeld worden (witte noedels, ananasschijven, kruiden, ...) werden gewist, bij gebrek aan masterdata. Je kunt deze producten uiteraard wel als lokaal assortiment toevoegen.</p>';
				// echo '</div>';
				
				// echo '<div class="notice notice-info">';
				// 	echo '<p>Op 21 februari ging een nieuwe actie van start met digitale vouchers, uitgereikt aan personeelsleden van Christelijke Mutualiteit. De verwerking verloopt volledig analoog als bij <a href="https://copain.oww.be/l/library/download/urn:uuid:cabf3637-35e9-4d21-920a-6c2d37f2b11f/handleiding+digitale+cadeaubonnen.pdf?format=save_to_disk" target="_blank">de Gezinsbond- en Cera-cheques</a>. Enige verschil is het kleinere bedrag: 10 i.p.v. 25, 30 of 50 euro. Een nieuwe artikelcode WGCD102022 vind je in <a href="https://copain.oww.be/shopplus" target="_blank">de ShopPlus-update voor maart</a> en werd toegevoegd aan <a href="https://copain.oww.be/l/nl/library/download/urn:uuid:027125de-b104-4946-a55d-b67f0ac47d67/028-nuttige_barcodes-02-2022.pdf?format=save_to_disk">het scanblad met nuttige barcodes</a>. Meer info in <a href="https://copain.oww.be/l/mailing2/archiveview/973/urn:uuid:49ec55a0-282e-42dd-838a-96c8f1a21b29" target="_blank">de webshopnieuwsbrief van 22 februari</a>.</p>';
				// echo '</div>';
				
				if ( does_home_delivery() ) {
					// Boodschappen voor winkels die thuislevering doen
				}
				
				if ( does_sendcloud_delivery() ) {
					// Boodschappen voor winkels die verzenden met SendCloud
				}
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