<?php

	// Startpagina instellen
	$homepage = get_page_by_title( 'Startpagina' );
	if ( $homepage ) {
	    update_option( 'show_on_front', 'page' );
	    update_option( 'page_on_front', $homepage->ID );
	}

	// Voorwaardenpagina opsnorren
	$terms = get_page_by_title( 'Algemene voorwaarden' );
	if ( $terms ) {
	    update_option( 'woocommerce_terms_page_id', $terms->ID );
	}

	// Relevanssi-index opbouwen
	relevanssi_build_index();

	// Leeggoed verbergen en op voorraad zetten
	$args = array(
		'post_type'			=> 'product',
		'post_status'		=> array( 'publish' ),
		'posts_per_page'	=> -1,
	);

	$all_products = new WP_Query( $args );

	if ( $all_products->have_posts() ) {
		while ( $all_products->have_posts() ) {
			$all_products->the_post();
			$productje = wc_get_product( get_the_ID() );
			if ( ! is_numeric( $productje->get_sku() ) ) {
				$productje->set_stock_status( 'instock' );
				$productje->set_catalog_visibility( 'hidden' );
				$productje->save();
			}
		}
		wp_reset_postdata();
	}

	// Product-ID's in kortingsbon lokaal maken
	$args = array(
		'post_type'		=> 'shop_coupon',
		'post_status'	=> array( 'publish' ),
		'title'			=> 'chileens-duo',
	);

	$all_coupons = new WP_Query( $args );
	
	if ( $all_coupons->have_posts() ) {
		while ( $all_coupons->have_posts() ) {
			$all_coupons->the_post();
			$ids = get_post_meta( get_the_ID(), 'product_ids', true );
			if ( $ids !== false ) {
				$global_ids = explode( ',', $ids );
				translate_main_to_local_ids( get_the_ID(), 'product_ids', $global_ids );
			}
		}
		wp_reset_postdata();
	}

	// Wijziging aan een orderstatus doorvoeren
	$args = array(
		'post_type'		=> 'wc_order_status',
		'post_status'	=> array( 'publish' ),
		'name'			=> 'on-hold',
	);

	$order_statuses = new WP_Query( $args );

	if ( $order_statuses->have_posts() ) {
		while ( $order_statuses->have_posts() ) {
			$order_statuses->the_post();
			update_post_meta( get_the_ID(), '_bulk_action', 'no' );
		}
		wp_reset_postdata();
	}

	// Een welbepaalde foto verwijderen
	$photo_id = wp_get_attachment_id_by_post_name( '21515' );
	if ( $photo_id ) {
		// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!)
		wp_delete_attachment( $photo_id, true );
	}

	// Product weer linken aan juiste (geüpdatete) foto
	$sku = '21515';
	$product_id = wc_get_product_id_by_sku( $sku );
	$new_photo_id = wp_get_attachment_id_by_post_name( $sku );
	if ( $product_id and $new_photo_id ) {
		$product = wc_get_product( $product_id );
		
		// Update de mapping tussen globale en lokale foto
		switch_to_blog( 1 );
		// OPGELET: NA IMPORT BEVAT DE TITEL OP HET HOOFDNIVEAU DE OMSCHRIJVING VAN HET PRODUCT DUS DIT WERKT NIET
		// $new_global_photo_id = wp_get_attachment_id_by_post_name( $sku );
		$new_global_photo_id = 886;
		restore_current_blog();
		$new_value = array( $new_global_photo_id => $new_photo_id );
		update_post_meta( $product_id, '_woonet_images_mapping', $new_value );
		
		// Koppel nieuw packshot aan product
		$product->set_image_id( $new_photo_id );
		$product->save();
		
		// Stel de uploadlocatie van de nieuwe afbeelding in
		wp_update_post(
			array(
				'ID' => $new_photo_id, 
				'post_parent' => $product_id,
			)
		);
	}

	// Zet een specifiek artikel uit voorraad
	$product_id = wc_get_product_id_by_sku( '21515' );
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$product->set_stock_status( 'outofstock' );
		$product->save();
	}

	// Werk de datum van een product bij
	$product_id = wc_get_product_id_by_sku( '24532' );
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$product->set_date_created( '2017-09-06T00:00:00Z' );
		$product->save();
	}

	// Stel de openingsuren in van een niet-OWW-afhaalpunt
	$node = 'griffel';
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
	update_site_option( 'oxfam_opening_hours_'.$node , $hours );

	// Fix de BestelWeb-parameter indien de ERP-import herstart moest worden
	$erp_skus = array( '20031', '20032', '20050', '20052', '20054', '20055', '20057', '20058', '20059', '20060', '20061', '20062', '20063', '20067', '20068', '20152', '20154', '20211', '20212', '20225', '20249', '20250', '20252', '20253', '20254', '20256', '20257', '20258', '20259', '20260', '20409', '20410', '20413', '20414', '20415', '20600', '20602', '20607', '20608', '20609', '20995', '20996', '20997', '20998', '20999', '21000', '21002', '21003', '21008', '21009', '21010', '21050', '21052', '21060', '21061', '21100', '21102', '21103', '21104', '21107', '21500', '21502', '21504', '21509', '21511', '21515', '22005', '22013', '22019', '22023', '22024', '22025', '22026', '22029', '22030', '22200', '22206', '22208', '22400', '22401', '22600', '22601', '22602', '22604', '22704', '22706', '22710', '22719', '22720', '22721', '22750', '22800', '22805', '23002', '23006', '23201', '23400', '23401', '23402', '23501', '23503', '23504', '23505', '23506', '23507', '23600', '23601', '23602', '23603', '23604', '23702', '23705', '24006', '24016', '24100', '24101', '24102', '24103', '24117', '24199', '24218', '24219', '24220', '24230', '24231', '24232', '24233', '24240', '24283', '24284', '24286', '24287', '24288', '24290', '24291', '24293', '24295', '24300', '24303', '24500', '24501', '24502', '24525', '24531', '24532', '24533', '24534', '24535', '24536', '24537', '24538', '24539', '24540', '24541', '24542', '24614', '24626', '24635', '24637', '24638', '24639', '24640', '25002', '25004', '25006', '25009', '25010', '25011', '25193', '25194', '25195', '25196', '25197', '25199', '25201', '25208', '25210', '25211', '25216', '25217', '25218', '25219', '25296', '25298', '25300', '25301', '25302', '25310', '25314', '25315', '25316', '25404', '25405', '25406', '25407', '25450', '25451', '25480', '25481', '25482', '25483', '25600', '25612', '25613', '25630', '25633', '25712', '25713', '25714', '25715', '25717', '25720', '25721', '25722', '26008', '26090', '26093', '26094', '26095', '26098', '26099', '26311', '26312', '26313', '26314', '26320', '26400', '26401', '26402', '26419', '26495', '26499', '26700', '26701', '26703', '26712', '27003', '27008', '27009', '27011', '27012', '27051', '27100', '27101', '27103', '27108', '27109', '27111', '27150', '27201', '27202', '27203', '27502', '27503', '27506', '27510', '27512', '27513', '27807', '27808', '27810', '27811', '27812', '27813', '27814', '27815', '27818', '27819', '27820', '28018', '28020', '28103', '28310', '28311', '28312', '28318', '28319', '28321', '28324', '28327', '28328', '28329', '28330', '28331', '28332', '28600', '28601', '28602', '28603', '28604', '28605', '28606', '28607', '28608', '28609', '28610', '28611', '28612', '28613', '28614', '28615', '28616', '28617' );
	foreach ( $erp_skus as $sku ) {
		$product_id = wc_get_product_id_by_sku( $sku );
		if ( $product_id ) {
			update_post_meta( $product_id, '_in_bestelweb', 'ja' );
		}
	}

	// Vlaamse gemeentelijst bijwerken
	$zips = array( 1000 => "Brussel", 1020 => "Laken", 1030 => "Schaarbeek", 1040 => "Etterbeek", 1050 => "Elsene", 1060 => "Sint-Gillis", 1070 => "Anderlecht", 1080 => "Sint-Jans-Molenbeek", 1081 => "Koekelberg", 1082 => "Sint-Agatha-Berchem", 1083 => "Ganshoren", 1090 => "Jette", 1120 => "Neder-over-Heembeek", 1130 => "Haren", 1140 => "Evere", 1150 => "Sint-Pieters-Woluwe", 1160 => "Oudergem", 1170 => "Watermaal-Bosvoorde", 1180 => "Ukkel", 1190 => "Vorst", 1200 => "Sint-Lambrechts-Woluwe", 1210 => "Sint-Joost-ten-Node", 1500 => "Halle", 1501 => "Buizingen", 1502 => "Lembeek", 1540 => "Herne / Herfelingen", 1541 => "Sint-Pieters-Kapelle", 1547 => "Bever", 1560 => "Hoeilaart", 1570 => "Galmaarden / Tollembeek / Vollezele", 1600 => "Sint-Pieters-Leeuw / Oudenaken / Sint-Laureins-Berchem", 1601 => "Ruisbroek", 1602 => "Vlezenbeek", 1620 => "Drogenbos", 1630 => "Linkebeek", 1640 => "Sint-Genesius-Rode", 1650 => "Beersel", 1651 => "Lot", 1652 => "Alsemberg", 1653 => "Dworp", 1654 => "Huizingen", 1670 => "Pepingen / Bogaarden / Heikruis", 1671 => "Elingen", 1673 => "Beert", 1674 => "Bellingen", 1700 => "Dilbeek / Sint-Martens-Bodegem / Sint-Ulriks-Kapelle", 1701 => "Itterbeek", 1702 => "Groot-Bijgaarden", 1703 => "Schepdaal", 1730 => "Asse / Bekkerzeel / Kobbegem / Mollem", 1731 => "Zellik / Relegem", 1740 => "Ternat", 1741 => "Wambeek", 1742 => "Sint-Katherina-Lombeek", 1745 => "Opwijk / Mazenzele", 1750 => "Lennik / Gaasbeek / Sint-Kwintens-Lennik / Sint-Martens-Lennik", 1755 => "Gooik / Kester / Leerbeek / Oetingen", 1760 => "Roosdaal / Onze-Lieve-Vrouw-Lombeek / Pamel / Strijtem", 1761 => "Borchtlombeek", 1770 => "Liedekerke", 1780 => "Wemmel", 1785 => "Merchtem / Brussegem / Hamme", 1790 => "Affligem / Essene / Hekelgem / Teralfene", 1800 => "Vilvoorde / Peutie", 1804 => "Cargovil", 1818 => "VTM", 1820 => "Steenokkerzeel / Melsbroek / Perk", 1830 => "Machelen", 1831 => "Diegem", 1840 => "Londerzeel / Malderen / Steenhuffel", 1850 => "Grimbergen", 1851 => "Humbeek", 1852 => "Beigem", 1853 => "Strombeek-Bever", 1860 => "Meise", 1861 => "Wolvertem", 1880 => "Kapelle-op-den-Bos / Nieuwenrode / Ramsdonk", 1910 => "Kampenhout / Berg / Buken / Nederokkerzeel", 1930 => "Zaventem / Nossegem", 1931 => "Brucargo", 1932 => "Sint-Stevens-Woluwe", 1933 => "Sterrebeek", 1934 => "Brussel X", 1950 => "Kraainem", 1970 => "Wezembeek-Oppem", 1980 => "Zemst / Eppegem", 1981 => "Hofstade", 1982 => "Elewijt / Weerde", 2000 => "Antwerpen", 2018 => "Antwerpen", 2020 => "Antwerpen", 2030 => "Antwerpen", 2040 => "Antwerpen / Berendrecht / Lillo / Zandvliet", 2050 => "Antwerpen", 2060 => "Antwerpen", 2070 => "Burcht / Zwijndrecht", 2100 => "Deurne", 2110 => "Wijnegem", 2140 => "Borgerhout", 2150 => "Borsbeek", 2160 => "Wommelgem", 2170 => "Merksem", 2180 => "Ekeren", 2200 => "Herentals / Morkhoven / Noorderwijk", 2220 => "Heist-op-den-Berg / Hallaar", 2221 => "Booischot", 2222 => "Itegem / Wiekevorst", 2223 => "Schriek", 2230 => "Herselt / Ramsel", 2235 => "Hulshout / Houtvenne / Westmeerbeek", 2240 => "Zandhoven / Massenhoven / Viersel", 2242 => "Pulderbos", 2243 => "Pulle", 2250 => "Olen", 2260 => "Westerlo / Oevel / Tongerlo / Zoerle-Parwijs", 2270 => "Herenthout", 2275 => "Lille / Gierle / Poederlee / Wechelderzande", 2280 => "Grobbendonk", 2288 => "Bouwel", 2290 => "Vorselaar", 2300 => "Turnhout", 2310 => "Rijkevorsel", 2320 => "Hoogstraten", 2321 => "Meer", 2322 => "Minderhout", 2323 => "Wortel", 2328 => "Meerle", 2330 => "Merksplas", 2340 => "Beerse / Vlimmeren", 2350 => "Vosselaar", 2360 => "Oud-Turnhout", 2370 => "Arendonk", 2380 => "Ravels", 2381 => "Weelde", 2382 => "Poppel", 2387 => "Baarle-Hertog", 2390 => "Malle / Oostmalle / Westmalle", 2400 => "Mol", 2430 => "Laakdal / Eindhout / Vorst", 2431 => "Varendonk / Veerle", 2440 => "Geel", 2450 => "Meerhout", 2460 => "Kasterlee / Lichtaart / Tielen", 2470 => "Retie", 2480 => "Dessel", 2490 => "Balen", 2491 => "Olmen", 2500 => "Lier / Koningshooikt", 2520 => "Ranst / Broechem / Emblem / Oelegem", 2530 => "Boechout", 2531 => "Vremde", 2540 => "Hove", 2547 => "Lint", 2550 => "Kontich / Waarloos", 2560 => "Nijlen / Bevel / Kessel", 2570 => "Duffel", 2580 => "Putte / Beerzel", 2590 => "Berlaar / Gestel", 2600 => "Berchem", 2610 => "Wilrijk", 2620 => "Hemiksem", 2627 => "Schelle", 2630 => "Aartselaar", 2640 => "Mortsel", 2650 => "Edegem", 2660 => "Hoboken", 2800 => "Mechelen / Walem", 2801 => "Heffen", 2811 => "Hombeek / Leest", 2812 => "Muizen", 2820 => "Bonheiden / Rijmenam", 2830 => "Willebroek / Blaasveld / Heindonk / Tisselt", 2840 => "Rumst / Reet / Terhagen", 2845 => "Niel", 2850 => "Boom", 2860 => "Sint-Katelijne-Waver", 2861 => "Onze-Lieve-Vrouw-Waver", 2870 => "Puurs / Breendonk / Liezele / Ruisbroek", 2880 => "Bornem / Hingene / Mariekerke / Weert", 2890 => "Sint-Amands / Lippelo / Oppuurs", 2900 => "Schoten", 2910 => "Essen", 2920 => "Kalmthout", 2930 => "Brasschaat", 2940 => "Stabroek / Hoevenen", 2950 => "Kapellen", 2960 => "Brecht / Sint-Job-in-'t-Goor / Sint-Lenaarts", 2970 => "Schilde / 's Gravenwezel", 2980 => "Zoersel / Halle", 2990 => "Wuustwezel / Loenhout", 3000 => "Leuven", 3001 => "Heverlee", 3010 => "Kessel-Lo", 3012 => "Wilsele", 3018 => "Wijgmaal", 3020 => "Herent / Veltem-Beisem / Winksele", 3040 => "Huldenberg / Loonbeek / Neerijse / Ottenburg / Sint-Agatha-Rode", 3050 => "Oud-Heverlee", 3051 => "Sint-Joris-Weert", 3052 => "Blanden", 3053 => "Haasrode", 3054 => "Vaalbeek", 3060 => "Bertem / Korbeek-Dijle", 3061 => "Leefdaal", 3070 => "Kortenberg", 3071 => "Erps-Kwerps", 3078 => "Everberg / Meerbeek", 3080 => "Tervuren / Duisburg / Vossem", 3090 => "Overijse", 3110 => "Rotselaar", 3111 => "Wezemaal", 3118 => "Werchter", 3120 => "Tremelo", 3128 => "Baal", 3130 => "Begijnendijk / Betekom", 3140 => "Keerbergen", 3150 => "Haacht / Tildonk / Wespelaar", 3190 => "Boortmeerbeek", 3191 => "Hever", 3200 => "Aarschot / Gelrode", 3201 => "Langdorp", 3202 => "Rillaar", 3210 => "Lubbeek / Linden", 3211 => "Binkom", 3212 => "Pellenberg", 3220 => "Holsbeek / Kortrijk-Dutsel / Sint-Pieters-Rode", 3221 => "Nieuwrode", 3270 => "Scherpenheuvel-Zichem", 3271 => "Averbode / Zichem", 3272 => "Messelbroek / Testelt", 3290 => "Diest / Deurne / Schaffen / Webbekom", 3293 => "Kaggevinne", 3294 => "Molenstede", 3300 => "Tienen / Bost / Goetsenhoven / Hakendover / Kumtich / Oorbeek / Oplinter / Sint-Margriete-Houtem / Vissenaken", 3320 => "Hoegaarden / Meldert", 3321 => "Outgaarden", 3350 => "Linter / Drieslinter / Melkwezer / Neerhespen / Neerlinter / Orsmaal-Gussenhoven / Overhespen / Wommersom", 3360 => "Bierbeek / Korbeek-Lo / Lovenjoel / Opvelp", 3370 => "Boutersem / Kerkom / Neervelp / Roosbeek / Vertrijk / Willebringen", 3380 => "Glabbeek-Zuurbemde / Bunsbeek", 3381 => "Kapellen", 3384 => "Attenrode", 3390 => "Tielt-Winge / Houwaart / Sint-Joris-Winge / Tielt", 3391 => "Meensel-Kiezegem", 3400 => "Landen / Eliksem / Ezemaal / Laar / Landen / Neerwinden / Overwinden / Rumsdorp / Wange", 3401 => "Waasmont / Walsbets / Walshoutem / Wezeren", 3404 => "Attenhoven / Neerlanden", 3440 => "Zoutleeuw / Budingen / Dormaal / Halle-Booienhoven / Helen-Bos", 3450 => "Geetbets / Grazen", 3454 => "Rummen", 3460 => "Bekkevoort / Assent", 3461 => "Molenbeek-Wersbeek", 3470 => "Kortenaken / Ransberg", 3471 => "Hoeleden", 3472 => "Kersbeek-Miskom", 3473 => "Waanrode", 3500 => "Hasselt / Sint-Lambrechts-Herk", 3501 => "Wimmertingen", 3510 => "Kermt / Spalbeek", 3511 => "Kuringen / Stokrooie", 3512 => "Stevoort", 3520 => "Zonhoven", 3530 => "Houthalen-Helchteren", 3540 => "Herk-de-Stad / Berbroek / Donk / Schulen", 3545 => "Halen / Loksbergen / Zelem", 3550 => "Heusden-Zolder", 3560 => "Lummen / Linkhout / Meldert", 3570 => "Alken", 3580 => "Beringen", 3581 => "Beverlo", 3582 => "Koersel", 3583 => "Paal", 3590 => "Diepenbeek", 3600 => "Genk", 3620 => "Lanaken / Gellik / Neerharen / Veldwezelt", 3621 => "Rekem", 3630 => "Maasmechelen / Eisden / Leut / Mechelen-aan-de-Maas / Meeswijk /Opgrimbie / Vucht", 3631 => "Boorsem / Uikhoven", 3640 => "Kinrooi / Kessenich / Molenbeersel / Ophoven", 3650 => "Dilsen-Stokkem / Elen / Lanklaar / Rotem / Stokkem", 3660 => "Opglabbeek", 3665 => "As", 3668 => "Niel-bij-As", 3670 => "Meeuwen-Gruitrode / Ellikom / Neerglabbeek / Wijshagen", 3680 => "Maaseik / Neeroeteren / Opoeteren", 3690 => "Zutendaal", 3700 => "Tongeren / 's Herenelderen / Berg / Diets-Heur / Haren / Henis / Kolmont / Koninksem / Lauw / Mal / Neerrepen / Nerem / Overrepen / Piringen / Riksingen / Rutten / Sluizen / Vreren / Widooie", 3717 => "Herstappe", 3720 => "Kortessem", 3721 => "Vliermaalroot", 3722 => "Wintershoven", 3723 => "Guigoven", 3724 => "Vliermaal", 3730 => "Hoeselt / Romershoven / Sint-Huibrechts-Hern / Werm", 3732 => "Schalkhoven", 3740 => "Bilzen / Beverst / Eigenbilzen / Grote-Spouwen / Hees / Kleine-Spouwen / Mopertingen / Munsterbilzen / Rijkhoven / Rosmeer / Spouwen / Waltwilder", 3742 => "Martenslinde", 3746 => "Hoelbeek", 3770 => "Riemst / Genoelselderen / Herderen / Kanne / Membruggen / Millen / Val-Meer / Vlijtingen / Vroenhoven / Zichen-Zussen-Bolder", 3790 => "Voeren / Moelingen / Sint-Martens-Voeren", 3791 => "Remersdaal", 3792 => "Sint-Pieters-Voeren", 3793 => "Teuven", 3798 => "'s Gravenvoeren", 3800 => "Sint-Truiden / Aalst / Brustem / Engelmanshoven / Gelinden / Groot-Gelmen / Halmaal / Kerkom-bij-Sint-Truiden / Ordingen / Zepperen", 3803 => "Duras / Gorsem / Runkelen / Wilderen", 3806 => "Velm", 3830 => "Wellen / Berlingen", 3831 => "Herten", 3832 => "Ulbeek", 3840 => "Borgloon / Bommershoven / Broekom / Gors-Opleeuw / Gotem / Groot-Loon / Haren / Hendrieken / Hoepertingen / Jesseren / Kerniel / Kolmont / Kuttekoven / Rijkel / Voort", 3850 => "Nieuwerkerken / Binderveld / Kozen / Wijer", 3870 => "Heers / Batsheers / Bovelingen / Gutschoven / Heks / Horpmaal / Klein-Gelmen / Mechelen-Bovelingen / Mettekoven / Opheers / Rukkelingen-Loon / Vechmaal / Veulen", 3890 => "Gingelom / Boekhout / Jeuk / Kortijs / Montenaken / Niel-bij-Sint-Truiden / Vorsen", 3891 => "Borlo / Buvingen / Mielen-Boven-Aalst / Muizen", 3900 => "Overpelt", 3910 => "Neerpelt / Sint-Huibrechts-Lille", 3920 => "Lommel", 3930 => "Hamont-Achel", 3940 => "Hechtel-Eksel", 3941 => "Eksel", 3945 => "Ham / Kwaadmechelen / Oostham", 3950 => "Bocholt / Kaulille / Reppel", 3960 => "Bree / Beek / Gerdingen / Opitter / Tongerlo", 3970 => "Leopoldsburg", 3971 => "Heppen", 3980 => "Tessenderlo", 3990 => "Peer / Grote-Brogel / Kleine-Brogel / Peer / Wijchmaal", 8000 => "Brugge / Koolkerke", 8020 => "Oostkamp / Hertsberge / Ruddervoorde / Waardamme", 8200 => "Sint-Andries / Sint-Michiels", 8210 => "Zedelgem / Loppem / Veldegem", 8211 => "Aartrijke", 8300 => "Knokke-Heist / Westkapelle", 8301 => "Heist-aan-Zee / Ramskapelle", 8310 => "Assebroek / Sint-Kruis", 8340 => "Damme / Hoeke / Lapscheure / Moerkerke / Oostkerke / Sijsele", 8370 => "Blankenberge / Uitkerke", 8377 => "Zuienkerke / Houtave / Meetkerke / Nieuwmunster", 8380 => "Dudzele / Lissewege / Zeebrugge", 8400 => "Oostende / Stene / Zandvoorde", 8420 => "De Haan / Klemskerke / Wenduine", 8421 => "Vlissegem", 8430 => "Middelkerke", 8431 => "Wilskerke", 8432 => "Leffinge", 8433 => "Mannekensvere / Schore / Sint-Pieters-Kapelle / Slijpe / Spermalie", 8434 => "Lombardsijde / Westende", 8450 => "Bredene", 8460 => "Oudenburg / Ettelgem / Roksem / Westkerke", 8470 => "Gistel / Moere / Snaaskerke / Zevekote", 8480 => "Ichtegem / Bekegem / Eernegem", 8490 => "Jabbeke / Snellegem / Stalhille / Varsenare / Zerkegem", 8500 => "Kortrijk", 8501 => "Bissegem / Heule", 8510 => "Bellegem / Kooigem / Marke / Rollegem", 8511 => "Aalbeke", 8520 => "Kuurne", 8530 => "Harelbeke", 8531 => "Bavikhove / Hulste", 8540 => "Deerlijk", 8550 => "Zwevegem", 8551 => "Heestert", 8552 => "Moen", 8553 => "Otegem", 8554 => "Sint-Denijs", 8560 => "Wevelgem / Gullegem / Moorsele", 8570 => "Anzegem / Gijzelbrechtegem / Ingooigem / Vichte", 8572 => "Kaster", 8573 => "Tiegem", 8580 => "Avelgem", 8581 => "Kerkhove / Waarmaarde", 8582 => "Outrijve", 8583 => "Bossuit", 8587 => "Spiere-Helkijn", 8600 => "Diksmuide / Beerst / Driekapellen / Esen / Kaaskerke / Keiem / Lampernisse / Leke / Nieuwkapelle / Oostkerke / Oudekapelle / Pervijze / Sint-Jacobskapelle / Stuivekenskerke / Vladslo / Woumen", 8610 => "Kortemark / Handzame / Werken / Zarren", 8620 => "Nieuwpoort / Ramskapelle / Sint-Joris", 8630 => "Veurne / Avekapelle / Beauvoorde / Booitshoeke / Bulskamp / De Moeren / Eggewaartskapelle / Houtem / Steenkerke / Vinkem / Wulveringem / Zoutenaaie", 8640 => "Vleteren / Oostvleteren / Westvleteren / Woesten", 8647 => "Lo-Reninge / Noordschote / Pollinkhove", 8650 => "Houthulst / Klerken / Merkem", 8660 => "De Panne / Adinkerke", 8670 => "Koksijde / Oostduinkerke / Wulpen", 8680 => "Koekelare / Bovekerke / Zande", 8690 => "Alveringem / Hoogstade / Oeren / Sint-Rijkers", 8691 => "Beveren-aan-den-IJzer / Gijverinkhove / Izenberge / Leisele / Stavele", 8700 => "Tielt / Aarsele / Kanegem / Schuiferskapelle", 8710 => "Wielsbeke / Ooigem / Sint-Baafs-Vijve", 8720 => "Dentergem / Markegem / Oeselgem / Wakken", 8730 => "Beernem / Oedelem / Sint-Joris", 8740 => "Pittem / Egem", 8750 => "Wingene / Zwevezele", 8755 => "Ruiselede", 8760 => "Meulebeke", 8770 => "Ingelmunster", 8780 => "Oostrozebeke", 8790 => "Waregem", 8791 => "Beveren-Leie", 8792 => "Desselgem", 8793 => "Sint-Eloois-Vijve", 8800 => "Roeselare / Beveren / Oekene / Rumbeke", 8810 => "Lichtervelde", 8820 => "Torhout", 8830 => "Hooglede / Gits", 8840 => "Staden / Oostnieuwkerke / Westrozebeke", 8850 => "Ardooie", 8851 => "Koolskamp", 8860 => "Lendelede", 8870 => "Izegem / Emelgem / Kachtem", 8880 => "Ledegem / Rollegem-Kapelle / Sint-Eloois-Winkel", 8890 => "Moorslede / Dadizele", 8900 => "Ieper / Brielen / Dikkebus / Sint-Jan", 8902 => "Hollebeke / Voormezele / Zillebeke", 8904 => "Boezinge / Zuidschote", 8906 => "Elverdinge", 8908 => "Vlamertinge", 8920 => "Langemark-Poelkapelle / Bikschote", 8930 => "Menen / Lauwe / Rekkem", 8940 => "Wervik / Geluwe", 8950 => "Heuvelland / Nieuwkerke", 8951 => "Dranouter", 8952 => "Wulvergem", 8953 => "Wijtschate", 8954 => "Westouter", 8956 => "Kemmel", 8957 => "Mesen", 8958 => "Loker", 8970 => "Poperinge / Reningelst", 8972 => "Krombeke / Proven / Roesbrugge-Haringe", 8978 => "Watou", 8980 => "Zonnebeke / Beselare / Geluveld / Passendale / Zandvoorde", 9000 => "Gent", 9030 => "Mariakerke", 9031 => "Drongen", 9032 => "Wondelgem", 9040 => "Sint-Amandsberg", 9041 => "Oostakker", 9042 => "Desteldonk / Mendonk / Sint-Kruis-Winkel", 9050 => "Gentbrugge / Ledeberg", 9051 => "Afsnee / Sint-Denijs-Westrem", 9052 => "Zwijnaarde", 9060 => "Zelzate", 9070 => "Destelbergen / Heusden", 9080 => "Lochristi / Beervelde / Zaffelare / Zeveneken", 9090 => "Melle / Gontrode", 9100 => "Sint-Niklaas / Nieuwkerken-Waas", 9111 => "Belsele", 9112 => "Sinaai-Waas", 9120 => "Beveren-Waas / Haasdonk / Kallo / Melsele / Vrasene", 9130 => "Doel / Kallo / Kieldrecht / Verrebroek", 9140 => "Temse / Elversele / Steendorp / Tielrode", 9150 => "Kruibeke / Bazel / Rupelmonde", 9160 => "Lokeren / Daknam / Eksaarde", 9170 => "Sint-Gillis-Waas / De Klinge / Meerdonk / Sint-Pauwels", 9180 => "Moerbeke-Waas", 9185 => "Wachtebeke", 9190 => "Stekene", 9200 => "Dendermonde / Appels / Baasrode / Grembergen / Mespelare / Oudegem / Schoonaarde / Sint-Gillis-bij-Dendermonde", 9220 => "Hamme / Moerzeke", 9230 => "Wetteren / Massemen / Westrem", 9240 => "Zele", 9250 => "Waasmunster / ", 9255 => "Buggenhout / Opdorp", 9260 => "Wichelen / Schellebelle / Serskamp", 9270 => "Laarne / Kalken", 9280 => "Lebbeke / Denderbelle / Wieze", 9290 => "Berlare / Overmere / Uitbergen", 9300 => "Aalst", 9308 => "Gijzegem / Hofstade", 9310 => "Baardegem / Herdersem / Meldert / Moorsel", 9320 => "Erembodegem / Nieuwerkerken", 9340 => "Lede / Impe / Oordegem / Smetlede / Wanzele", 9400 => "Ninove / Appelterre-Eichem / Denderwindeke / Lieferinge / Nederhasselt / Okegem / Voorde", 9401 => "Pollare", 9402 => "Meerbeke", 9403 => "Neigem", 9404 => "Aspelare", 9406 => "Outer", 9420 => "Erpe-Mere / Aaigem / Bambrugge / Burst / Erondegem / Ottergem / Vlekkem", 9450 => "Haaltert / Denderhoutem / Heldergem", 9451 => "Kerksken", 9470 => "Denderleeuw", 9472 => "Iddergem", 9473 => "Welle", 9500 => "Geraardsbergen / Goeferdinge / Moerbeke / Nederboelare / Onkerzele / Ophasselt / Overboelare / Viane / Zarlardinge", 9506 => "Grimminge / Idegem / Nieuwenhove / Schendelbeke / Smeerebbe-Vloerzegem / Waarbeke / Zandbergen", 9520 => "Sint-Lievens-Houtem / Bavegem / Vlierzele / Zonnegem", 9521 => "Letterhoutem", 9550 => "Herzele / Hillegem / Sint-Antelinks / Sint-Lievens-Esse / Steenhuize-Wijnhuize / Woubrechtegem", 9551 => "Ressegem", 9552 => "Borsbeke", 9570 => "Lierde / Deftinge / Sint-Maria-Lierde", 9571 => "Hemelveerdegem", 9572 => "Sint-Martens-Lierde", 9600 => "Ronse", 9620 => "Zottegem / Elene / Erwetegem / Godveerdegem / Grotenberge / Leeuwergem / Oombergen / Sint-Goriks-Oudenhove / Sint-Maria-Oudenhove / Strijpen / Velzeke-Ruddershove", 9630 => "Zwalm / Beerlegem / Dikkele / Hundelgem / Meilegem / Munkzwalm / Paulatem / Roborst / Rozebeke / Sint-Blasius-Boekel / Sint-Denijs-Boekel / Sint-Maria-Latem", 9636 => "Nederzwalm-Hermelgem", 9660 => "Brakel / Elst / Everbeek / Michelbeke / Nederbrakel / Opbrakel / Zegelsem", 9661 => "Parike", 9667 => "Horebeke / Sint-Kornelis-Horebeke / Sint-Maria-Horebeke", 9680 => "Maarkedal / Etikhove / Maarke-Kerkem", 9681 => "Nukerke", 9688 => "Schorisse", 9690 => "Kluisbergen / Berchem / Kwaremont / Ruien / Zulzeke", 9700 => "Oudenaarde / Bevere / Edelare / Eine / Ename / Heurne / Leupegem / Mater / Melden / Mullem / Nederename / Volkegem / Welden", 9750 => "Zingem / Huise / Ouwegem", 9770 => "Kruishoutem", 9771 => "Nokere", 9772 => "Wannegem-Lede", 9790 => "Wortegem-Petegem / Elsegem / Moregem / Ooike / Petegem-aan-de-Schelde", 9800 => "Deinze / Astene / Bachte-Maria-Leerne / Gottem / Grammene / Meigem / Petegem-aan-de-Leie / Sint-Martens-Leerne / Vinkt / Wontergem / Zeveren", 9810 => "Nazareth / Eke", 9820 => "Merelbeke / Bottelare / Lemberge / Melsen / Munte / Schelderode", 9830 => "Sint-Martens-Latem", 9831 => "Deurle", 9840 => "De Pinte / Zevergem", 9850 => "Nevele / Hansbeke / Landegem / Merendree / Poesele / Vosselare", 9860 => "Oosterzele / Balegem / Gijzenzele / Landskouter / Moortsele / Scheldewindeke", 9870 => "Zulte / Machelen / Olsene", 9880 => "Aalter / Lotenhulle / Poeke", 9881 => "Bellem", 9890 => "Gavere / Asper / Baaigem / Dikkelvenne / Semmerzake / Vurste", 9900 => "Eeklo", 9910 => "Knesselare / Ursel", 9920 => "Lovendegem", 9921 => "Vinderhoute", 9930 => "Zomergem", 9931 => "Oostwinkel", 9932 => "Ronsele", 9940 => "Evergem / Ertvelde / Kluizen / Sleidinge", 9950 => "Waarschoot", 9960 => "Assenede", 9961 => "Boekhoute", 9968 => "Bassevelde / Oosteeklo", 9970 => "Kaprijke", 9971 => "Lembeke", 9980 => "Sint-Laureins", 9981 => "Sint-Margriete", 9982 => "Sint-Jan-in-Eremo", 9988 => "Waterland-Oudeman / Watervliet", 9990 => "Maldegem", 9991 => "Adegem", 9992 => "Middelburg" );
	update_site_option( 'oxfam_flemish_zip_codes', $zips );

	// Tabel met stopwoorden kopiëren

	// Sjabloon van WP All Export kopiëren

?>