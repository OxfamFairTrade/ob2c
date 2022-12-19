<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	##############
	# RELEVANSSI #
	##############
	
	// Verleng de logs tot 90 dagen
	add_filter( 'relevanssi_30days', function() { return 90; } );
	
	// Verander capability van 'manage_options' naar 'create_sites' zodat enkel superbeheerders de instellingen kunnen wijzigen
	add_filter( 'relevanssi_options_capability', function( $capability ) { return 'create_sites'; } );
	
	// Verander capability van 'edit_pages' naar 'manage_woocommerce' zodat ook lokale beheerders de logs kunnen bekijken
	add_filter( 'relevanssi_user_searches_capability', function( $capability ) { return 'manage_woocommerce'; } );
	
	// Laat logs rechtstreeks doorlinken naar specifieke product search in een nieuw venster
	add_filter( 'relevanssi_user_searches_query_url',  function( $query_url ) { return $query_url.'&post_type=product'; } );
	
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
	// @toFix: deze actie wordt niet uitgevoerd door Savoy bovenaan zoekresultaten!
	// add_action( 'woocommerce_archive_description', 'ob2c_add_didyoumean' );
	
	function ob2c_add_didyoumean() {
		if ( is_search() ) {
			relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );
		}
	}
	
	// Zorg ervoor dat de zoeksuggestie opnieuw linkt naar de productenzoeker
	add_filter( 'relevanssi_didyoumean_url', 'ob2c_modify_didyoumean_url', 10, 1 );
	
	function ob2c_modify_didyoumean_url( $url ) {
		return add_query_arg( 'post_type', 'product', $url );
	}
	
	// Verhinder dat termen die slechts 1x in de index voorkomen de automatische suggesties verstoren
	// add_filter( 'relevanssi_get_words_having', function() { return 2; } );
	
	// Toon de bestsellers op zoekpagina's zonder resultaten
	// @toDo: Moet meer naar boven geschoven worden (en herschreven worden zonder VC-shortcodes)
	// @toFix: Verschijnt ook als er wél resultaten zijn?
	// add_action( 'woocommerce_after_main_content', 'add_bestsellers' );
	
	function add_bestsellers() {
		global $wp_query;
		if ( is_search() and $wp_query->found_posts == 0 ) {
			echo do_shortcode('[vc_row css=".vc_custom_1487859300634{padding-top: 25px !important;padding-bottom: 25px !important;}"][vc_column][vc_text_separator title="<h2>Werp een blik op onze bestsellers ...</h2>" css=".vc_custom_1487854440279{padding-bottom: 25px !important;}"][best_selling_products per_page="10" columns="5" orderby="rand"][/vc_column][/vc_row]');
		}
	}
	
	// Zorg ervoor dat verborgen producten niet geïndexeerd worden (en dus niet opduiken in de zoekresultaten) SOWIESO AL ONZICHTBAAR, ZIE OPTIE
	add_filter( 'relevanssi_woocommerce_indexing', 'ob2c_exclude_hidden_products', 10, 1 );
	
	function ob2c_exclude_hidden_products( $blocks ) {
		$blocks['outofstock'] = false;
		// $blocks['exclude-from-catalog'] = false;
		$blocks['exclude-from-search'] = true;
		return $blocks;
	}
	
	// Voeg de bovenliggende categorie toe aan de te indexeren content van een product (inclusief synoniemen)
	// @toDo: Gebruik 'relevanssi_index_custom_fields'-filter om verborgen metavelden toe te voegen (kan ook via instellingen)
	add_filter( 'relevanssi_content_to_index', 'ob2c_index_parent_category_and_origin', 10, 2 );
	
	function ob2c_index_parent_category_and_origin( $content, $post ) {
		global $relevanssi_variables;
		$categories = get_the_terms( $post->ID, 'product_cat' );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				// Check de bovenliggende cateogrie
				if ( ! empty( $category->parent ) ) {
					$parent = get_term( $category->parent, 'product_cat' );
					if ( array_key_exists( 'synonyms', $relevanssi_variables ) ) {
						// Laat de synoniemenlijst eerst nog even inwerken
						$search = array_keys( $relevanssi_variables['synonyms'] );
						$replace = array_values( $relevanssi_variables['synonyms'] );
						$content .= str_ireplace( $search, $replace, $parent->name ).' ';
					} else {
						// Voeg direct toe
						$content .= $parent->name.' ';
					}
				}
			}
		}
		return $content;
	}